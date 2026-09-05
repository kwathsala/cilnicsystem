<?php
/**
 * ============================================================
 * DATABASE CONFIGURATION
 * ============================================================
 * Update these values to match your MySQL/hosting environment.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_system');
define('DB_USER', 'root');       // change for production
define('DB_PASS', '');           // change for production

// SMS Gateway settings (Notify.lk example - update with your account details)
define('SMS_API_URL', 'https://app.notify.lk/api/v1/send');
define('SMS_USER_ID', 'YOUR_USER_ID');
define('SMS_API_KEY', 'YOUR_API_KEY');
define('SMS_SENDER_ID', 'NotifyDEMO');

// Timezone
date_default_timezone_set('Asia/Colombo');

/**
 * Auto-detect the base URL of the project (e.g. "/clinic-system")
 * so redirects work no matter what subfolder the project sits in.
 * This file lives in /clinic-system/backend/config.php, so we go
 * one level up to get the project root.
 */
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname(dirname(__FILE__ ))); // project root on disk
    $docRoot   = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $base = str_replace($docRoot, '', $scriptDir);
    define('BASE_URL', rtrim($base, '/'));
}

/**
 * Get a PDO database connection (used across all backend files).
 * Using PDO + prepared statements everywhere to prevent SQL Injection.
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
