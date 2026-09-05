<?php
/**
 * ============================================================
 * POST /backend/api/inventory_update_stock.php
 * Adjusts stock for a medicine (restock delivery, or manual
 * correction after a stock-take).
 * Body (JSON): { medicine_id, adjustment, mode }
 *   mode = "add"  -> stock_qty += adjustment (e.g. new delivery)
 *   mode = "set"  -> stock_qty = adjustment  (e.g. stock-take)
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
$adjustment = (int)($input['adjustment'] ?? 0);
$mode = ($input['mode'] ?? 'add') === 'set' ? 'set' : 'add';

if ($medicineId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'medicine_id is required']);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT stock_qty, unit_price FROM medicines WHERE medicine_id = ? FOR UPDATE');
    $stmt->execute([$medicineId]);
    $med = $stmt->fetch();

    if (!$med) {
        throw new Exception('Medicine not found');
    }

    $newQty = $mode === 'set' ? $adjustment : (int)$med['stock_qty'] + $adjustment;
    $newQty = max(0, $newQty);

    $update = $pdo->prepare('UPDATE medicines SET stock_qty = ? WHERE medicine_id = ?');
    $update->execute([$newQty, $medicineId]);

    recordStockSnapshot($pdo, $medicineId, $newQty, (float)$med['unit_price']);

    $pdo->commit();

    echo json_encode(['success' => true, 'stock_qty' => $newQty]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
