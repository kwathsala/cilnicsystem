<?php
/**
 * ============================================================
 * GET /backend/api/queue_list.php
 * Today's waiting queue for the Doctor's sidebar - token number
 * order, so the doctor always knows who's next.
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Doctor', 'Admin']);

header('Content-Type: application/json');

$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT qt.token_id, qt.token_number, qt.status,
            p.patient_id, p.name, p.contact_number, p.patient_type, p.allergies
     FROM queue_tokens qt
     JOIN patients p ON p.patient_id = qt.patient_id
     WHERE qt.queue_date = CURDATE() AND qt.status = 'Waiting'
     ORDER BY qt.token_number ASC"
);
$stmt->execute();
$rows = $stmt->fetchAll();

echo json_encode(['queue' => $rows]);
