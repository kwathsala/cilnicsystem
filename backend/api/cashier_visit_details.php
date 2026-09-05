<?php
/**
 * ============================================================
 * GET /backend/api/cashier_visit_details.php?visit_id=123
 * Returns full visit + patient + prescription details for billing.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Cashier', 'Admin']);

header('Content-Type: application/json');

$visitId = (int)($_GET['visit_id'] ?? 0);
if ($visitId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'visit_id is required']);
    exit;
}

$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT v.visit_id, v.visit_date, v.diagnosis, v.consultation_fee, v.status,
            p.patient_id, p.name AS patient_name, p.contact_number, p.patient_type,
            p.allergies, p.monthly_fee,
            d.name AS doctor_name
     FROM visits v
     JOIN patients p ON p.patient_id = v.patient_id
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE v.visit_id = ?"
);
$stmt->execute([$visitId]);
$visit = $stmt->fetch();

if (!$visit) {
    http_response_code(404);
    echo json_encode(['error' => 'Visit not found']);
    exit;
}

$rxStmt = $pdo->prepare(
    "SELECT p.prescription_id, p.medicine_id, m.name AS medicine_name, m.is_proprietary,
            p.dosage, p.duration, p.quantity, p.unit_price, p.removed_by_cashier
     FROM prescriptions p
     JOIN medicines m ON m.medicine_id = p.medicine_id
     WHERE p.visit_id = ?"
);
$rxStmt->execute([$visitId]);
$visit['prescriptions'] = $rxStmt->fetchAll();

// If Regular/Monthly patient, also fetch the 6 proprietary products so the
// cashier can quickly add any that weren't already prescribed.
$proprietary = [];
if (in_array($visit['patient_type'], ['Regular', 'Monthly'], true)) {
    $propStmt = $pdo->query(
        "SELECT medicine_id, name, unit_price, stock_qty FROM medicines WHERE is_proprietary = 1 ORDER BY name ASC"
    );
    $proprietary = $propStmt->fetchAll();
}

echo json_encode(['visit' => $visit, 'proprietary_products' => $proprietary]);
