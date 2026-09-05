<?php
/**
 * ============================================================
 * GET /backend/api/visits_by_date.php?date=YYYY-MM-DD
 * All visits (with prescriptions) that happened on a given day -
 * "who did we see, who saw them, and what did they get" lookup.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Doctor', 'Cashier', 'Admin']);

header('Content-Type: application/json');

$date = trim($_GET['date'] ?? '');
if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid date (YYYY-MM-DD) is required']);
    exit;
}

$pdo = getDBConnection();

$stmt = $pdo->prepare(
    'SELECT v.visit_id, v.visit_date, v.diagnosis, v.consultation_fee, v.status,
            p.name AS patient_name, p.contact_number,
            d.name AS doctor_name
     FROM visits v
     JOIN patients p ON p.patient_id = v.patient_id
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE DATE(v.visit_date) = ?
     ORDER BY v.visit_date ASC'
);
$stmt->execute([$date]);
$visits = $stmt->fetchAll();

foreach ($visits as &$visit) {
    $pStmt = $pdo->prepare(
        'SELECT m.name AS medicine_name, pr.dosage, pr.duration, pr.quantity, pr.unit_price, pr.removed_by_cashier
         FROM prescriptions pr
         JOIN medicines m ON m.medicine_id = pr.medicine_id
         WHERE pr.visit_id = ?'
    );
    $pStmt->execute([$visit['visit_id']]);
    $visit['prescriptions'] = $pStmt->fetchAll();
}
unset($visit);

echo json_encode(['date' => $date, 'count' => count($visits), 'visits' => $visits]);
