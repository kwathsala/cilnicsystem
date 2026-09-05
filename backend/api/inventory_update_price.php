<?php
/**
 * ============================================================
 * POST /backend/api/inventory_update_price.php
 * Updates a medicine's unit_price. Takes effect immediately for
 * every screen that reads it (Doctor's prescribing list, Cashier
 * billing, proprietary product dropdown) since they all query
 * medicines.unit_price live - no separate "publish" step needed.
 * Body (JSON): { medicine_id, new_price }
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inventory_helpers.php';
requireRole(['Cashier', 'Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$medicineId = (int)($input['medicine_id'] ?? 0);
$newPrice = (float)($input['new_price'] ?? -1);

if ($medicineId <= 0 || $newPrice < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'medicine_id and a valid new_price are required']);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT unit_price, stock_qty FROM medicines WHERE medicine_id = ? FOR UPDATE');
    $stmt->execute([$medicineId]);
    $med = $stmt->fetch();

    if (!$med) {
        throw new Exception('Medicine not found');
    }

    $oldPrice = (float)$med['unit_price'];

    $update = $pdo->prepare('UPDATE medicines SET unit_price = ? WHERE medicine_id = ?');
    $update->execute([$newPrice, $medicineId]);

    $log = $pdo->prepare(
        'INSERT INTO price_change_log (medicine_id, old_price, new_price) VALUES (?, ?, ?)'
    );
    $log->execute([$medicineId, $oldPrice, $newPrice]);

    recordStockSnapshot($pdo, $medicineId, (int)$med['stock_qty'], $newPrice);

    $pdo->commit();

    echo json_encode(['success' => true, 'old_price' => $oldPrice, 'new_price' => $newPrice]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
