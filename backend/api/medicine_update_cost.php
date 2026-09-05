<?php
/**
 * ============================================================
 * POST /backend/api/medicine_update_cost.php
 * Sets/updates the wholesale cost_price for a medicine, used to
 * compute profit margin on the Financial Analytics dashboard.
 * Body (JSON): { medicine_id, cost_price }
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Admin', 'Doctor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$medicineId = (int)($input['medicine_id'] ?? 0);
$costPrice = (float)($input['cost_price'] ?? -1);

if ($medicineId <= 0 || $costPrice < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'medicine_id and a valid cost_price are required']);
    exit;
}

$pdo = getDBConnection();

$stmt = $pdo->prepare('UPDATE medicines SET cost_price = ? WHERE medicine_id = ?');
$stmt->execute([$costPrice, $medicineId]);

if ($stmt->rowCount() === 0) {
    // Could be "no row found" OR "value unchanged" - check existence separately.
    $check = $pdo->prepare('SELECT medicine_id FROM medicines WHERE medicine_id = ?');
    $check->execute([$medicineId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Medicine not found']);
        exit;
    }
}

echo json_encode(['success' => true]);
