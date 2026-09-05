<?php
/**
 * ============================================================
 * GET /backend/api/patients_search.php?q=searchTerm&scope=today
 * Searches patients by name or contact number.
 * scope=today restricts results to patients who have a queue
 * token for today (i.e. patients actually at the clinic today) -
 * used by the Doctor's search bar so old/unrelated patients
 * don't clutter today's consultations.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireLogin(); // any logged-in role can search

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? '';

if ($q === '') {
    echo json_encode(['patients' => []]);
    exit;
}

$pdo = getDBConnection();
$like = "%$q%";

if ($scope === 'today') {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.patient_id, p.address, p.age, p.name, p.contact_number, p.patient_type, p.allergies, p.monthly_fee, p.report_due_date
         FROM patients p
         JOIN queue_tokens qt ON qt.patient_id = p.patient_id AND qt.queue_date = CURDATE()
         WHERE p.name LIKE ? OR p.contact_number LIKE ?
         ORDER BY p.name ASC
         LIMIT 20'
    );
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->prepare(
        'SELECT patient_id, address, age, name, contact_number, patient_type, allergies, monthly_fee, report_due_date
         FROM patients
         WHERE name LIKE ? OR contact_number LIKE ?
         ORDER BY name ASC
         LIMIT 20'
    );
    $stmt->execute([$like, $like]);
}

$patients = $stmt->fetchAll();

echo json_encode(['patients' => $patients]);
