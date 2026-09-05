<?php
/**
 * ============================================================
 * POST /backend/api/cashier_toggle_item.php
 * Toggles removed_by_cashier flag on a prescription item.
 * Body (JSON): { prescription_id: 5, removed: true|false }
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
$prescriptionId = (int)($input['prescription_id'] ?? 0);
$removed = !empty($input['removed']) ? 1 : 0;

if ($prescriptionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'prescription_id is required']);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('UPDATE prescriptions SET removed_by_cashier = ? WHERE prescription_id = ?');
$stmt->execute([$removed, $prescriptionId]);

echo json_encode(['success' => true]);
