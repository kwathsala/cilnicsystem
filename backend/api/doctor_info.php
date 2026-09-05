<?php
/**
 * ============================================================
 * GET /backend/api/doctor_info.php
 * Returns the logged-in doctor's profile + default fee.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Doctor', 'Admin']);

header('Content-Type: application/json');

$doctorId = $_SESSION['linked_doctor_id'] ?? null;

if (!$doctorId) {
    http_response_code(400);
    echo json_encode(['error' => 'This account is not linked to a doctor profile.']);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare('SELECT doctor_id, name, specialization, default_fee FROM doctors WHERE doctor_id = ?');
$stmt->execute([$doctorId]);
$doctor = $stmt->fetch();

if (!$doctor) {
    http_response_code(404);
    echo json_encode(['error' => 'Doctor not found']);
    exit;
}

echo json_encode(['doctor' => $doctor]);
