<?php
/**
 * ============================================================
 * POST /backend/api/patients_create.php
 * Registers a new patient.
 * Body (JSON): { name, address, age, contact_number, patient_type,
 *                allergies, monthly_fee, report_due_date }
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Reception', 'Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$name           = trim($input['name'] ?? '');
$address        = trim($input['address'] ?? '');
$ageRaw         = $input['age'] ?? '';
$age            = ($ageRaw !== '' && $ageRaw !== null) ? (int)$ageRaw : null;
$contact        = trim($input['contact_number'] ?? '');
$patientType    = $input['patient_type'] ?? 'Normal';
$allergies      = trim($input['allergies'] ?? '');
$monthlyFeeRaw  = $input['monthly_fee'] ?? '';
$monthlyFee     = ($monthlyFeeRaw !== '' && $monthlyFeeRaw !== null) ? $monthlyFeeRaw : null;
$reportDueDateRaw = $input['report_due_date'] ?? '';
$reportDueDate  = ($reportDueDateRaw !== '' && $reportDueDateRaw !== null) ? $reportDueDateRaw : null;

if ($name === '' || $contact === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name and contact number are required']);
    exit;
}

if (!in_array($patientType, ['Regular', 'Monthly', 'Normal'], true)) {
    $patientType = 'Normal';
}

$pdo = getDBConnection();
$stmt = $pdo->prepare(
    'INSERT INTO patients (name, address, age, contact_number, patient_type, allergies, monthly_fee, report_due_date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$name, $address, $age, $contact, $patientType, $allergies ?: null, $monthlyFee, $reportDueDate]);

$patientId = $pdo->lastInsertId();

// If a report_due_date was set, schedule an SMS reminder for 7 days before it
if ($reportDueDate) {
    $reminderDate = date('Y-m-d', strtotime($reportDueDate . ' -7 days'));
    $message = "Dear $name, this is a reminder that your report/appointment is due on $reportDueDate. Please visit us soon.";
    $smsStmt = $pdo->prepare(
        'INSERT INTO sms_log (patient_id, message, scheduled_for, status) VALUES (?, ?, ?, ?)'
    );
    $smsStmt->execute([$patientId, $message, $reminderDate, 'Pending']);
}

echo json_encode(['success' => true, 'patient_id' => $patientId]);
