<?php
/**
 * ============================================================
 * POST /backend/api/cashier_add_item.php
 * Adds an extra medicine (typically a proprietary product) to a
 * visit's prescription list directly from the cashier screen.
 * Body (JSON): { visit_id, medicine_id, quantity }
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Cashier', 'Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$visitId = (int)($input['visit_id'] ?? 0);
$medicineId = (int)($input['medicine_id'] ?? 0);
$quantity = max(1, (int)($input['quantity'] ?? 1));

if ($visitId <= 0 || $medicineId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'visit_id and medicine_id are required']);
    exit;
}

$pdo = getDBConnection();

$priceStmt = $pdo->prepare('SELECT unit_price FROM medicines WHERE medicine_id = ?');
$priceStmt->execute([$medicineId]);
$med = $priceStmt->fetch();

if (!$med) {
    http_response_code(404);
    echo json_encode(['error' => 'Medicine not found']);
    exit;
}

$stmt = $pdo->prepare(
    'INSERT INTO prescriptions (visit_id, medicine_id, dosage, duration, quantity, unit_price)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$visitId, $medicineId, '-', '-', $quantity, $med['unit_price']]);

echo json_encode(['success' => true, 'prescription_id' => $pdo->lastInsertId()]);
