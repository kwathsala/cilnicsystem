<?php
/**
 * ============================================================
 * POST /backend/api/login.php
 * Body (JSON): { "username": "...", "password": "..." }
 * ============================================================
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT user_id, username, password_hash, role, linked_doctor_id FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

// Success - store session
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['linked_doctor_id'] = $user['linked_doctor_id'];

echo json_encode([
    'success' => true,
    'user' => [
        'username' => $user['username'],
        'role' => $user['role'],
    ]
]);
