<?php
/**
 * ============================================================
 * POST /backend/api/visits_create.php
 * Doctor submits a completed consultation: diagnosis + prescriptions.
 * The default consultation fee is auto-applied, and the visit is
 * immediately marked 'Sent to Pharmacy' so the Cashier/Pharmacy
 * station can pick it up right away (queue integration).
 *
 * Body (JSON): {
 *   patient_id: 1,
 *   diagnosis: "...",
 *   prescriptions: [
 *     { medicine_id: 3, dosage: "1 tablet", duration: "5 days", quantity: 10 },
 *     ...
 *   ]
 * }
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

$doctorId = $_SESSION['linked_doctor_id'] ?? null;
if (!$doctorId) {
    http_response_code(400);
    echo json_encode(['error' => 'This account is not linked to a doctor profile.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$patientId = (int)($input['patient_id'] ?? 0);
$diagnosis = trim($input['diagnosis'] ?? '');
$prescriptions = $input['prescriptions'] ?? [];
$feeOverride = isset($input['consultation_fee']) ? (float)$input['consultation_fee'] : null;

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'A patient must be selected.']);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Get doctor's default fee
    $feeStmt = $pdo->prepare('SELECT default_fee FROM doctors WHERE doctor_id = ?');
    $feeStmt->execute([$doctorId]);
    $doctor = $feeStmt->fetch();
    $defaultFee = $doctor ? $doctor['default_fee'] : 0;

    // Doctor can edit the fee per-visit (e.g. a follow-up or discounted visit);
    // fall back to the default fee if no valid override was sent.
    $consultationFee = ($feeOverride !== null && $feeOverride >= 0) ? $feeOverride : $defaultFee;

    // Create the visit, immediately sent to pharmacy/cashier queue
    $visitStmt = $pdo->prepare(
        'INSERT INTO visits (patient_id, doctor_id, diagnosis, consultation_fee, status)
         VALUES (?, ?, ?, ?, ?)'
    );
    $visitStmt->execute([$patientId, $doctorId, $diagnosis, $consultationFee, 'Sent to Pharmacy']);
    $visitId = $pdo->lastInsertId();

    // Insert prescriptions, snapshotting the current unit price of each medicine
    if (is_array($prescriptions)) {
        $priceStmt = $pdo->prepare('SELECT unit_price FROM medicines WHERE medicine_id = ?');
        $rxStmt = $pdo->prepare(
            'INSERT INTO prescriptions (visit_id, medicine_id, dosage, duration, quantity, unit_price)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($prescriptions as $rx) {
            $medicineId = (int)($rx['medicine_id'] ?? 0);
            if ($medicineId <= 0) continue;

            $priceStmt->execute([$medicineId]);
            $priceRow = $priceStmt->fetch();
            if (!$priceRow) continue; // skip invalid medicine ids

            $dosage = trim($rx['dosage'] ?? '');
            $duration = trim($rx['duration'] ?? '');
            $quantity = max(1, (int)($rx['quantity'] ?? 1));

            $rxStmt->execute([$visitId, $medicineId, $dosage, $duration, $quantity, $priceRow['unit_price']]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'visit_id' => $visitId]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save consultation: ' . $e->getMessage()]);
}
