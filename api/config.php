<?php

error_reporting(0);
ini_set('display_errors', 0);

// ── Database configuration ─────────────────────────────────────────────────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mira');
define('DB_USER', 'root');
define('DB_PASS', 'root');

// ── Gemini API configuration ───────────────────────────────────────────────

define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_GOES_HERE');
define('GEMINI_BASE_URL',    'https://generativelanguage.googleapis.com/v1beta');
define('GEMINI_CHAT_MODEL', 'gemini-2.5-flash');
define('GEMINI_EMBED_MODEL', 'gemini-embedding-001');

// ── File upload configuration ──────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB maximum

// ── JWT configuration ──────────────────────────────────────────────────────
define('JWT_SECRET', 'change_this_to_a_long_random_string_in_production');
define('JWT_EXPIRY', 86400); // 24 hours in seconds

// ── Database connection function ───────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    // Only create the connection once
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . 
                   ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }

    return $pdo;
}

// ── JSON response helper ───────────────────────────────────────────────────
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── CORS headers ───────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}