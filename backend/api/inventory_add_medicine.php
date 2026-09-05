<?php
/**
 * ============================================================
 * POST /backend/api/inventory_add_medicine.php
 * Body (JSON): { name, category, is_proprietary, unit_price,
 *                stock_qty, expiry_date }
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

$name         = trim($input['name'] ?? '');
$category     = trim($input['category'] ?? '');
$isProprietary = !empty($input['is_proprietary']) ? 1 : 0;
$unitPrice    = (float)($input['unit_price'] ?? 0);
$stockQty     = (int)($input['stock_qty'] ?? 0);
$expiryDate   = trim($input['expiry_date'] ?? '') ?: null;

if ($name === '' || $unitPrice <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Medicine name and a valid unit price are required']);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO medicines (name, category, is_proprietary, unit_price, stock_qty, expiry_date)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $category, $isProprietary, $unitPrice, $stockQty, $expiryDate]);
    $medicineId = (int)$pdo->lastInsertId();

    recordStockSnapshot($pdo, $medicineId, $stockQty, $unitPrice);

    $pdo->commit();

    echo json_encode(['success' => true, 'medicine_id' => $medicineId]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
