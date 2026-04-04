<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ── Authenticate request ──────────────────────────────────────────────────
function getAuthUser(): object|null {
    $headers = apache_request_headers();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/', $auth, $m)) return null;
    try {
        return JWT::decode($m[1], new Key(JWT_SECRET, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}

$user = getAuthUser();
if (!$user) respond(['error' => 'Unauthorised'], 401);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db     = getDB();

// ── LIST papers ───────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $tag    = $_GET['tag']    ?? '';
    $search = $_GET['search'] ?? '';

    $sql = "SELECT p.id, p.title, p.authors, p.abstract,
                   p.uploaded_at, u.name AS uploader,
                   s.content AS summary
            FROM papers p
            LEFT JOIN users u ON u.id = p.uploaded_by
            LEFT JOIN summaries s ON s.paper_id = p.id";

    $params = [];

    if ($tag) {
        $sql .= " JOIN paper_tags pt ON pt.paper_id = p.id
                  JOIN tags t ON t.id = pt.tag_id
                  WHERE t.name = ?";
        $params[] = $tag;
    }

    $sql .= " ORDER BY p.uploaded_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $papers = $stmt->fetchAll();

    // Attach tags to each paper
    foreach ($papers as &$paper) {
        $ts = $db->prepare(
            "SELECT t.name FROM tags t
             JOIN paper_tags pt ON pt.tag_id = t.id
             WHERE pt.paper_id = ?"
        );
        $ts->execute([$paper['id']]);
        $paper['tags'] = array_column($ts->fetchAll(), 'name');
    }

    // Get stats
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalTags  = $db->query("SELECT COUNT(DISTINCT tag_id) FROM paper_tags")->fetchColumn();
    respond([
        'papers'      => $papers,
        'total_users' => (int) $totalUsers,
        'total_tags'  => (int) $totalTags,
    ]);
}

// ── GET single paper ──────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'get') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) respond(['error' => 'Paper ID required'], 400);

    $stmt = $db->prepare(
        "SELECT p.*, u.name AS uploader, s.content AS summary
         FROM papers p
         LEFT JOIN users u ON u.id = p.uploaded_by
         LEFT JOIN summaries s ON s.paper_id = p.id
         WHERE p.id = ?"
    );
    $stmt->execute([$id]);
    $paper = $stmt->fetch();

    if (!$paper) respond(['error' => 'Paper not found'], 404);

    // Get tags
    $ts = $db->prepare(
        "SELECT t.name FROM tags t
         JOIN paper_tags pt ON pt.tag_id = t.id
         WHERE pt.paper_id = ?"
    );
    $ts->execute([$id]);
    $paper['tags'] = array_column($ts->fetchAll(), 'name');

    respond(['paper' => $paper]);
}

// ── DELETE paper ──────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($user->role !== 'admin') {
        respond(['error' => 'Only admins can delete papers'], 403);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) respond(['error' => 'Paper ID required'], 400);

    // Get file path before deleting
    $stmt = $db->prepare("SELECT file_path FROM papers WHERE id = ?");
    $stmt->execute([$id]);
    $paper = $stmt->fetch();

    if (!$paper) respond(['error' => 'Paper not found'], 404);

    // Delete file from disk
    $filePath = UPLOAD_DIR . basename($paper['file_path']);
    if (file_exists($filePath)) unlink($filePath);

    // Delete from database (cascades to chunks, tags, summaries, conversations)
    $stmt = $db->prepare("DELETE FROM papers WHERE id = ?");
    $stmt->execute([$id]);

    respond(['message' => 'Paper deleted successfully']);
}