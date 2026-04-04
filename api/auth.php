<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$method = $_SERVER['REQUEST_METHOD'];
$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── REGISTER ───────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'register') {
    $name     = trim($data['name']     ?? '');
    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');
    $role     = in_array($data['role'] ?? '', ['student','lecturer','admin'])
                ? $data['role'] : 'student';

    // Validate inputs
    if (!$name || !$email || !$password) {
        respond(['error' => 'Name, email and password are required'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Invalid email address'], 400);
    }
    if (strlen($password) < 6) {
        respond(['error' => 'Password must be at least 6 characters'], 400);
    }

    $db = getDB();

    // Check if email already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respond(['error' => 'Email already registered'], 409);
    }

    // Hash password and insert user
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $hash, $role]);
    $userId = $db->lastInsertId();

    // Generate JWT token
    $token = generateToken($userId, $name, $email, $role);
    respond(['token' => $token, 'user' => compact('userId','name','email','role')], 201);
}

// ── LOGIN ──────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'login') {
    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');

    if (!$email || !$password) {
        respond(['error' => 'Email and password are required'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify password
    if (!$user || !password_verify($password, $user['password_hash'])) {
        respond(['error' => 'Invalid email or password'], 401);
    }

    $token = generateToken($user['id'], $user['name'], $user['email'], $user['role']);
    respond([
        'token' => $token,
        'user'  => [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ]
    ]);
}

// ── VERIFY TOKEN (used by other pages to check if still logged in) ─────────
if ($method === 'GET') {
    $token = getBearerToken();
    if (!$token) respond(['error' => 'No token provided'], 401);

    $user = verifyToken($token);
    if (!$user) respond(['error' => 'Invalid or expired token'], 401);

    respond(['user' => (array) $user]);
}

// ── HELPERS ────────────────────────────────────────────────────────────────
function generateToken(int $id, string $name, string $email, string $role): string {
    $payload = [
        'iat'   => time(),
        'exp'   => time() + JWT_EXPIRY,
        'id'    => $id,
        'name'  => $name,
        'email' => $email,
        'role'  => $role,
    ];
    return JWT::encode($payload, JWT_SECRET, 'HS256');
}

function verifyToken(string $token): object|false {
    try {
        return JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    } catch (Exception $e) {
        return false;
    }
}

function getBearerToken(): string|null {
    $headers = apache_request_headers();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
        return $matches[1];
    }
    return null;
}