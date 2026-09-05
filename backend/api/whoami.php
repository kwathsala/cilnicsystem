<?php
/**
 * ============================================================
 * GET /backend/api/whoami.php
 * Returns the currently logged-in user's info, or 401 if not logged in.
 * Used by every frontend page to guard access and show the username.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

echo json_encode(['user' => currentUser()]);
