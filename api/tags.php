<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

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

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

// ── GET all tags ──────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->query("SELECT id, name FROM tags ORDER BY name ASC");
    respond(['tags' => $stmt->fetchAll()]);
}

// ── POST create tag and assign to paper ───────────────────────────────────
if ($method === 'POST') {
    $data     = json_decode(file_get_contents('php://input'), true);
    $name     = trim($data['name']     ?? '');
    $paperId  = (int) ($data['paper_id'] ?? 0);

    if (!$name) respond(['error' => 'Tag name is required'], 400);

    // Insert tag if it doesn't exist
    $db->prepare("INSERT IGNORE INTO tags (name) VALUES (?)")->execute([$name]);

    // Get tag id
    $stmt = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $stmt->execute([$name]);
    $tagId = $stmt->fetchColumn();

    // Link to paper if paper_id provided
    if ($paperId) {
        $db->prepare(
            "INSERT IGNORE INTO paper_tags (paper_id, tag_id) VALUES (?, ?)"
        )->execute([$paperId, $tagId]);
    }

    respond(['tag' => ['id' => $tagId, 'name' => $name]], 201);
}

// ── DELETE tag from paper ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($user->role !== 'admin' && $user->role !== 'lecturer') {
        respond(['error' => 'Not authorised'], 403);
    }
    $paperId = (int) ($_GET['paper_id'] ?? 0);
    $tagId   = (int) ($_GET['tag_id']   ?? 0);

    if (!$paperId || !$tagId) respond(['error' => 'paper_id and tag_id required'], 400);

    $db->prepare(
        "DELETE FROM paper_tags WHERE paper_id = ? AND tag_id = ?"
    )->execute([$paperId, $tagId]);

    respond(['message' => 'Tag removed']);
}