<?php
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '120');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers/GeminiAPI.php';
require_once __DIR__ . '/helpers/Similarity.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ── Read input once ───────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$data     = json_decode($rawInput, true);

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['error' => 'Method not allowed'], 405);

$query = trim($data['query'] ?? '');
if (!$query) respond(['error' => 'Search query is required'], 400);

try {

    $db = getDB();

    // ── Embed the search query ────────────────────────────────────────────
    $queryEmbedding = GeminiAPI::embed($query);

    if (empty($queryEmbedding)) {
        respond(['error' => 'Failed to process search query'], 500);
    }

    // ── Fetch all chunks from all papers ──────────────────────────────────
    $stmt = $db->query(
        "SELECT pc.id, pc.paper_id, pc.content, pc.embedding_json,
                p.title, p.authors, p.abstract, p.uploaded_at,
                u.name AS uploader,
                s.content AS summary
         FROM paper_chunks pc
         JOIN papers p ON p.id = pc.paper_id
         LEFT JOIN users u ON u.id = p.uploaded_by
         LEFT JOIN summaries s ON s.paper_id = p.id"
    );
    $chunks = $stmt->fetchAll();

    if (empty($chunks)) {
        respond(['results' => [], 'query' => $query, 'total' => 0]);
    }

    // ── Score each chunk against the query ────────────────────────────────
    $paperScores = [];

    foreach ($chunks as $chunk) {
        $embedding = json_decode($chunk['embedding_json'], true);
        if (!$embedding) continue;

        $score = Similarity::cosine($queryEmbedding, $embedding);
        $pid   = $chunk['paper_id'];

        if (!isset($paperScores[$pid]) || $score > $paperScores[$pid]['score']) {
            $paperScores[$pid] = [
                'score'       => $score,
                'paper_id'    => $pid,
                'title'       => $chunk['title'],
                'authors'     => $chunk['authors'],
                'abstract'    => $chunk['abstract'],
                'uploaded_at' => $chunk['uploaded_at'],
                'uploader'    => $chunk['uploader'],
                'summary'     => $chunk['summary'],
                'excerpt'     => substr($chunk['content'], 0, 200) . '...',
            ];
        }
    }

    // ── Sort by score descending ──────────────────────────────────────────
    usort($paperScores, fn($a, $b) => $b['score'] <=> $a['score']);

    // ── Only return results above threshold ───────────────────────────────
    $results = array_filter($paperScores, fn($p) => $p['score'] > 0.3);
    $results = array_values($results);

    // ── Attach tags to each result ────────────────────────────────────────
    foreach ($results as &$result) {
        $ts = $db->prepare(
            "SELECT t.name FROM tags t
             JOIN paper_tags pt ON pt.tag_id = t.id
             WHERE pt.paper_id = ?"
        );
        $ts->execute([$result['paper_id']]);
        $result['tags'] = array_column($ts->fetchAll(), 'name');
    }

    respond([
        'results' => $results,
        'query'   => $query,
        'total'   => count($results),
    ]);

} catch (Exception $e) {
    respond(['error' => $e->getMessage()], 500);
}