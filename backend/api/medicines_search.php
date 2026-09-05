<?php
/**
 * ============================================================
 * GET /backend/api/medicines_search.php?q=searchTerm
 * Used by the Doctor's prescription form to find medicines.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireLogin();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$pdo = getDBConnection();

if ($q === '') {
    // Return first 15 medicines by default (e.g. to show proprietary set quickly)
    $stmt = $pdo->query(
        'SELECT medicine_id, name, unit_price, stock_qty, is_proprietary
         FROM medicines ORDER BY name ASC LIMIT 15'
    );
} else {
    $stmt = $pdo->prepare(
        'SELECT medicine_id, name, unit_price, stock_qty, is_proprietary
         FROM medicines WHERE name LIKE ? ORDER BY name ASC LIMIT 15'
    );
    $stmt->execute(["%$q%"]);
}

echo json_encode(['medicines' => $stmt->fetchAll()]);
