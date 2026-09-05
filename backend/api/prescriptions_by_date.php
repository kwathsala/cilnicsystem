<?php
/**
 * ============================================================
 * GET /backend/api/prescriptions_by_date.php?date=YYYY-MM-DD
 * For a selected date: every visit, which doctor saw them, and
 * exactly what medicines (dosage/duration/qty) were prescribed.
 * Used by the "Past Prescriptions by Date" lookup.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Doctor', 'Admin']);

header('Content-Type: application/json');

$date = trim($_GET['date'] ?? '');
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid date (YYYY-MM-DD) is required']);
    exit;
}

$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT v.visit_id, v.visit_date, v.diagnosis, v.consultation_fee, v.status,
            p.name AS patient_name, p.contact_number,
            d.name AS doctor_name
     FROM visits v
     JOIN patients p ON p.patient_id = v.patient_id
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE DATE(v.visit_date) = ?
     ORDER BY v.visit_date ASC"
);
$stmt->execute([$date]);
$visits = $stmt->fetchAll();

$rxStmt = $pdo->prepare(
    "SELECT pr.medicine_id, pr.dosage, pr.duration, pr.quantity, pr.unit_price, pr.removed_by_cashier, m.name AS medicine_name
     FROM prescriptions pr
     JOIN medicines m ON m.medicine_id = pr.medicine_id
     WHERE pr.visit_id = ?"
);

$result = [];
foreach ($visits as $v) {
    $rxStmt->execute([$v['visit_id']]);
    $result[] = [
        'visit_id' => (int)$v['visit_id'],
        'visit_date' => $v['visit_date'],
        'patient_name' => $v['patient_name'],
        'contact_number' => $v['contact_number'],
        'doctor_name' => $v['doctor_name'],
        'diagnosis' => $v['diagnosis'],
        'consultation_fee' => (float)$v['consultation_fee'],
        'status' => $v['status'],
        'prescriptions' => array_map(fn($r) => [
            'medicine_name' => $r['medicine_name'],
            'dosage' => $r['dosage'],
            'duration' => $r['duration'],
            'quantity' => (int)$r['quantity'],
            'unit_price' => (float)$r['unit_price'],
            'removed_by_cashier' => (bool)$r['removed_by_cashier'],
        ], $rxStmt->fetchAll()),
    ];
}

echo json_encode(['date' => $date, 'visit_count' => count($result), 'visits' => $result]);
