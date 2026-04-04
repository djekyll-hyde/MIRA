<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$data   = json_decode(file_get_contents('php://input'), true);
$token  = $data['token']  ?? '';
$action = $data['action'] ?? '';

if (!$token) respond(['error' => 'Google token is required'], 400);

// ── Verify Google token ───────────────────────────────────────────────────
$googleUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
$ch = curl_init($googleUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    respond(['error' => 'Invalid Google token'], 401);
}

$googleUser = json_decode($response, true);

// Verify the token was issued for our app
if (($googleUser['aud'] ?? '') !== 'YOUR_CLIENT_ID_GOES_HERE') {
    respond(['error' => 'Token audience mismatch'], 401);
}

$email  = $googleUser['email']          ?? '';
$name   = $googleUser['name']           ?? 'Google User';
$google = $googleUser['sub']            ?? '';

if (!$email) respond(['error' => 'Could not get email from Google'], 400);

$db = getDB();

// ── Check if user already exists ──────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($action === 'login') {
    if (!$user) {
        respond(['error' => 'No account found with this Google email. Please register first.'], 404);
    }
} elseif ($action === 'register') {
    if ($user) {
        // Already registered — just log them in
    } else {
        // Create new account with student role by default
        $stmt = $db->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        // Use a random unusable password hash since they use Google
        $stmt->execute([$name, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), 'student']);
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }
} else {
    respond(['error' => 'Invalid action'], 400);
}

// ── Generate MIRA JWT ─────────────────────────────────────────────────────
$payload = [
    'iat'   => time(),
    'exp'   => time() + JWT_EXPIRY,
    'id'    => (int) $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
];
$jwtToken = JWT::encode($payload, JWT_SECRET, 'HS256');

respond([
    'token' => $jwtToken,
    'user'  => [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ]
]);