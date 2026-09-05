<?php
/**
 * ============================================================
 * GET /backend/api/patient_history.php?patient_id=123
 * Returns patient profile + full visit history with prescriptions.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireLogin();

header('Content-Type: application/json');

$patientId = (int)($_GET['patient_id'] ?? 0);

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'patient_id is required']);
    exit;
}

$pdo = getDBConnection();

// Patient profile
$stmt = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
$stmt->execute([$patientId]);
$patient = $stmt->fetch();

if (!$patient) {
    http_response_code(404);
    echo json_encode(['error' => 'Patient not found']);
    exit;
}

// Visit history with doctor name
$stmt = $pdo->prepare(
    'SELECT v.visit_id, v.visit_date, v.diagnosis, v.consultation_fee, v.status, d.name AS doctor_name
     FROM visits v
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE v.patient_id = ?
     ORDER BY v.visit_date DESC'
);
$stmt->execute([$patientId]);
$visits = $stmt->fetchAll();

// Attach prescriptions for each visit
foreach ($visits as &$visit) {
    $pStmt = $pdo->prepare(
        'SELECT p.prescription_id, m.name AS medicine_name, p.dosage, p.duration, p.quantity, p.unit_price, p.removed_by_cashier
         FROM prescriptions p
         JOIN medicines m ON m.medicine_id = p.medicine_id
         WHERE p.visit_id = ?'
    );
    $pStmt->execute([$visit['visit_id']]);
    $visit['prescriptions'] = $pStmt->fetchAll();
}
unset($visit);

echo json_encode(['patient' => $patient, 'visits' => $visits]);
