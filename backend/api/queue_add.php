<?php
/**
 * ============================================================
 * POST /backend/api/queue_add.php
 * Reception sends a patient (new registration or existing) into
 * today's waiting queue for the doctor. Token numbers restart
 * at 1 each day.
 * Body (JSON): { patient_id }
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
$patientId = (int)($input['patient_id'] ?? 0);

if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'patient_id is required']);
    exit;
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT patient_id, name FROM patients WHERE patient_id = ?');
    $check->execute([$patientId]);
    $patient = $check->fetch();
    if (!$patient) {
        throw new Exception('Patient not found');
    }

    // Next token number for today (starts at 1 each day)
    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(token_number), 0) + 1 AS next_token
         FROM queue_tokens WHERE queue_date = CURDATE() FOR UPDATE'
    );
    $stmt->execute();
    $nextToken = (int)$stmt->fetch()['next_token'];

    $insert = $pdo->prepare(
        'INSERT INTO queue_tokens (patient_id, token_number, queue_date, status)
         VALUES (?, ?, CURDATE(), \'Waiting\')'
    );
    $insert->execute([$patientId, $nextToken]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'token_id' => (int)$pdo->lastInsertId(),
        'token_number' => $nextToken,
        'patient_name' => $patient['name'],
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
