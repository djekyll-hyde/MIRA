<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/GeminiAPI.php';
require_once __DIR__ . '/helpers/PdfParser.php';
require_once __DIR__ . '/helpers/Chunker.php';
require_once __DIR__ . '/helpers/Similarity.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ── Auth ──────────────────────────────────────────────────────────────────
function getAuthUser(): object|null {
    $headers = apache_request_headers();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/', $auth, $m)) return null;
    try {
        return JWT::decode($m[1], new Key(JWT_SECRET, 'HS256'));
    } catch (Exception $e) { return null; }
}

$user = getAuthUser();
if (!$user) respond(['error' => 'Unauthorised'], 401);
if (!in_array($user->role, ['lecturer', 'admin'])) {
    respond(['error' => 'Only lecturers and admins can upload papers'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

// ── Validate uploaded file ────────────────────────────────────────────────
if (empty($_FILES['pdf'])) {
    respond(['error' => 'No PDF file uploaded'], 400);
}

$file = $_FILES['pdf'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    respond(['error' => 'File upload error code: ' . $file['error']], 400);
}

if ($file['size'] > MAX_FILE_SIZE) {
    respond(['error' => 'File too large. Maximum size is 10MB'], 400);
}

$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, ['application/pdf', 'application/x-pdf'])) {
    respond(['error' => 'Only PDF files are allowed'], 400);
}

// ── Validate form fields ──────────────────────────────────────────────────
$title   = trim($_POST['title']   ?? '');
$authors = trim($_POST['authors'] ?? '');
$abstract = trim($_POST['abstract'] ?? '');
$tags    = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

if (!$title) respond(['error' => 'Paper title is required'], 400);

// ── Save PDF to uploads folder ────────────────────────────────────────────
$fileName = uniqid('paper_', true) . '.pdf';
$filePath = UPLOAD_DIR . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    respond(['error' => 'Failed to save uploaded file'], 500);
}

try {
    $db = getDB();

    // ── Step 1: Save paper metadata ───────────────────────────────────────
    $stmt = $db->prepare(
        "INSERT INTO papers (title, authors, abstract, file_path, uploaded_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$title, $authors, $abstract, $fileName, $user->id]);
    $paperId = (int) $db->lastInsertId();

    // ── Step 2: Extract text from PDF ────────────────────────────────────
    $text = PdfParser::extract($filePath);
    if (empty($text)) {
        throw new Exception('Could not extract text from PDF. The file may be scanned or image-only.');
    }

    // ── Step 3: Split into chunks ─────────────────────────────────────────
    $chunks = Chunker::chunk($text);
    if (empty($chunks)) {
        throw new Exception('Could not split paper into chunks.');
    }

    // ── Step 4: Embed each chunk and save to database ─────────────────────
    $stmtChunk = $db->prepare(
        "INSERT INTO paper_chunks (paper_id, chunk_index, content, embedding_json)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($chunks as $index => $chunkText) {
        $embedding = GeminiAPI::embed($chunkText);
        $stmtChunk->execute([
            $paperId,
            $index,
            $chunkText,
            json_encode($embedding)
        ]);
    }

    // ── Step 5: Generate AI summary ───────────────────────────────────────
    $cleanText     = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    $cleanText     = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $cleanText);
    $summaryPrompt = "You are an academic assistant. Write a concise summary of 150-200 words covering the main objective, methodology, key findings, and conclusion of this research paper.\n\nPaper text:\n" . substr($cleanText, 0, 6000);

    $summary = GeminiAPI::generate($summaryPrompt);

    $stmtSummary = $db->prepare(
        "INSERT INTO summaries (paper_id, content) VALUES (?, ?)"
    );
    $stmtSummary->execute([$paperId, $summary]);

    // ── Step 6: Save tags ─────────────────────────────────────────────────
    foreach ($tags as $tagName) {
        if (empty($tagName)) continue;

        // Insert tag if it doesn't exist
        $stmtTag = $db->prepare(
            "INSERT IGNORE INTO tags (name) VALUES (?)"
        );
        $stmtTag->execute([$tagName]);

        // Get tag id
        $stmtGetTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
        $stmtGetTag->execute([$tagName]);
        $tagId = $stmtGetTag->fetchColumn();

        // Link tag to paper
        $stmtPaperTag = $db->prepare(
            "INSERT IGNORE INTO paper_tags (paper_id, tag_id) VALUES (?, ?)"
        );
        $stmtPaperTag->execute([$paperId, $tagId]);
    }

    respond([
        'message'  => 'Paper uploaded and processed successfully',
        'paper_id' => $paperId,
        'chunks'   => count($chunks),
        'tags'     => count($tags),
    ], 201);

} catch (Exception $e) {
    // Clean up uploaded file on error
    if (file_exists($filePath)) unlink($filePath);

    // Remove partial database entries
    if (isset($paperId)) {
        $db->prepare("DELETE FROM papers WHERE id = ?")->execute([$paperId]);
    }

    respond(['error' => $e->getMessage()], 500);
}