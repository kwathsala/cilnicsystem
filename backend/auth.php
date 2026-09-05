<?php
/**
 * ============================================================
 * AUTH HELPERS
 * Session-based authentication with role-based access control.
 * ============================================================
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require the user to be logged in. If not, redirect to login page
 * (for page requests) or return a 401 JSON error (for API requests).
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        if (isApiRequest()) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }
        header('Location: ' . BASE_URL . '/frontend/login.html');
        exit;
    }
}

/**
 * Require the logged-in user to have one of the allowed roles.
 * Example: requireRole(['Doctor', 'Admin']);
 */
function requireRole(array $allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to access this resource.']);
        exit;
    }
}

function isApiRequest() {
    return strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
}

function currentUser() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'doctor_id' => $_SESSION['linked_doctor_id'] ?? null,
    ];
}
