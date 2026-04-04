<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/GeminiAPI.php';
require_once __DIR__ . '/helpers/Similarity.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);

$data    = json_decode(file_get_contents('php://input'), true);
$paperId = (int) ($data['paper_id'] ?? 0);
$message = trim($data['message']  ?? '');

if (!$paperId) respond(['error' => 'Paper ID required'], 400);
if (!$message) respond(['error' => 'Message is required'], 400);

$db = getDB();

// ── Verify paper exists ───────────────────────────────────────────────────
$stmt = $db->prepare("SELECT id, title FROM papers WHERE id = ?");
$stmt->execute([$paperId]);
$paper = $stmt->fetch();
if (!$paper) respond(['error' => 'Paper not found'], 404);

// ── Get or create conversation ────────────────────────────────────────────
$convId = (int) ($data['conv_id'] ?? 0);

if (!$convId) {
    $stmt = $db->prepare(
        "INSERT INTO conversations (user_id, paper_id) VALUES (?, ?)"
    );
    $stmt->execute([$user->id, $paperId]);
    $convId = (int) $db->lastInsertId();
}

// ── Load conversation history (last 10 messages) ──────────────────────────
$stmt = $db->prepare(
    "SELECT role, content FROM messages
     WHERE conv_id = ?
     ORDER BY created_at ASC
     LIMIT 10"
);
$stmt->execute([$convId]);
$history = $stmt->fetchAll();

// ── Embed the user question ───────────────────────────────────────────────
$questionEmbedding = GeminiAPI::embed($message);

// ── Fetch all chunks for this paper ──────────────────────────────────────
$stmt = $db->prepare(
    "SELECT id, content, embedding_json FROM paper_chunks WHERE paper_id = ?"
);
$stmt->execute([$paperId]);
$chunks = $stmt->fetchAll();

// ── Find top 5 most relevant chunks ──────────────────────────────────────
$topChunks = Similarity::topK($questionEmbedding, $chunks, 5);

if (empty($topChunks)) respond(['error' => 'Could not retrieve paper content'], 500);

// ── Build context from top chunks ────────────────────────────────────────
$context = implode("\n\n---\n\n", array_column($topChunks, 'content'));

// ── Build prompt ──────────────────────────────────────────────────────────
$prompt = "You are an academic research assistant. A user is asking a question about the research paper titled \"{$paper['title']}\".

Use ONLY the following excerpts from the paper to answer the question. If the answer is not found in the excerpts, say so clearly — do not make up information.

PAPER EXCERPTS:
{$context}

USER QUESTION:
{$message}

Provide a clear, accurate, and helpful answer based on the paper excerpts above.";

// ── Call Gemini ───────────────────────────────────────────────────────────
$reply = GeminiAPI::generate($prompt, $history);

// ── Save user message and reply to database ───────────────────────────────
$stmt = $db->prepare(
    "INSERT INTO messages (conv_id, role, content) VALUES (?, ?, ?)"
);
$stmt->execute([$convId, 'user',      $message]);
$stmt->execute([$convId, 'assistant', $reply]);

respond([
    'reply'   => $reply,
    'conv_id' => $convId,
]);