<?php
/**
 * ============================================================
 * GET /backend/api/cashier_queue.php
 * Returns all visits currently waiting at the Cashier/Pharmacy
 * station (status = 'Sent to Pharmacy'), oldest first.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Cashier', 'Admin']);

header('Content-Type: application/json');

$pdo = getDBConnection();
$stmt = $pdo->query(
    "SELECT v.visit_id, v.visit_date, v.diagnosis, v.consultation_fee,
            p.patient_id, p.name AS patient_name, p.contact_number, p.patient_type, p.allergies, p.monthly_fee,
            d.name AS doctor_name
     FROM visits v
     JOIN patients p ON p.patient_id = v.patient_id
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE v.status = 'Sent to Pharmacy'
     ORDER BY v.visit_date ASC"
);

echo json_encode(['queue' => $stmt->fetchAll()]);
