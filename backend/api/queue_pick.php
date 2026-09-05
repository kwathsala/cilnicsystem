<?php
/**
 * ============================================================
 * POST /backend/api/queue_pick.php
 * Doctor clicks a token in the sidebar to start that patient's
 * consultation. Marks the token 'With Doctor' so it drops off
 * the waiting list, and returns the patient record so the
 * frontend can prefill it exactly like a manual search-select.
 * Body (JSON): { token_id }
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Doctor', 'Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tokenId = (int)($input['token_id'] ?? 0);

if ($tokenId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'token_id is required']);
    exit;
}

$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT qt.token_id, qt.status, p.patient_id, p.name, p.contact_number, p.patient_type, p.allergies
     FROM queue_tokens qt
     JOIN patients p ON p.patient_id = qt.patient_id
     WHERE qt.token_id = ?"
);
$stmt->execute([$tokenId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Token not found']);
    exit;
}

if ($row['status'] !== 'Waiting') {
    http_response_code(409);
    echo json_encode(['error' => 'This patient has already been picked up by another doctor screen.']);
    exit;
}

$update = $pdo->prepare("UPDATE queue_tokens SET status = 'With Doctor' WHERE token_id = ?");
$update->execute([$tokenId]);

echo json_encode([
    'success' => true,
    'patient' => [
        'patient_id' => (int)$row['patient_id'],
        'name' => $row['name'],
        'contact_number' => $row['contact_number'],
        'patient_type' => $row['patient_type'],
        'allergies' => $row['allergies'],
    ],
]);
