<?php
/**
 * ============================================================
 * POST /backend/api/cashier_finalize.php
 * Finalizes the bill for a visit:
 *  - Uses the patient's special monthly_fee instead of the normal
 *    consultation fee, if the patient is Regular/Monthly and a
 *    monthly_fee is set.
 *  - Excludes any prescription items the cashier removed.
 *  - Deducts stock for billed items.
 *  - Creates the invoice record and marks the visit 'Completed'.
 *
 * Body (JSON): { visit_id: 123, payment_method: "Cash" }
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inventory_helpers.php';
requireRole(['Cashier', 'Admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$visitId = (int)($input['visit_id'] ?? 0);
$paymentMethod = $input['payment_method'] ?? 'Cash';

if ($visitId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'visit_id is required']);
    exit;
}
if (!in_array($paymentMethod, ['Cash', 'Card', 'Other'], true)) {
    $paymentMethod = 'Cash';
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();

    // Lock and fetch the visit + patient info
    $stmt = $pdo->prepare(
        "SELECT v.visit_id, v.patient_id, v.consultation_fee, v.status,
                p.patient_type, p.monthly_fee
         FROM visits v
         JOIN patients p ON p.patient_id = v.patient_id
         WHERE v.visit_id = ? FOR UPDATE"
    );
    $stmt->execute([$visitId]);
    $visit = $stmt->fetch();

    if (!$visit) {
        throw new Exception('Visit not found');
    }
    if ($visit['status'] === 'Completed') {
        throw new Exception('This visit has already been billed.');
    }

    // Special fee structure for Regular/Monthly patients
    $consultationFee = $visit['consultation_fee'];
    if (in_array($visit['patient_type'], ['Regular', 'Monthly'], true) && $visit['monthly_fee'] !== null) {
        $consultationFee = $visit['monthly_fee'];
    }

    // Get all NON-removed prescription items
    $rxStmt = $pdo->prepare(
        'SELECT prescription_id, medicine_id, quantity, unit_price
         FROM prescriptions WHERE visit_id = ? AND removed_by_cashier = 0'
    );
    $rxStmt->execute([$visitId]);
    $items = $rxStmt->fetchAll();

    $medicineTotal = 0;
    $deductStmt = $pdo->prepare('UPDATE medicines SET stock_qty = stock_qty - ? WHERE medicine_id = ?');
    $stockLookup = $pdo->prepare('SELECT stock_qty, unit_price FROM medicines WHERE medicine_id = ?');

    foreach ($items as $item) {
        $medicineTotal += $item['unit_price'] * $item['quantity'];
        $deductStmt->execute([$item['quantity'], $item['medicine_id']]);

        // Log the post-sale stock level so Inventory's week-comparison
        // reflects real consumption, not just manual restocks.
        $stockLookup->execute([$item['medicine_id']]);
        $updatedMed = $stockLookup->fetch();
        if ($updatedMed) {
            recordStockSnapshot($pdo, (int)$item['medicine_id'], (int)$updatedMed['stock_qty'], (float)$updatedMed['unit_price']);
        }
    }

    $grandTotal = $consultationFee + $medicineTotal;

    $invStmt = $pdo->prepare(
        'INSERT INTO invoices (visit_id, patient_id, consultation_fee, medicine_total, grand_total, payment_method)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $invStmt->execute([$visitId, $visit['patient_id'], $consultationFee, $medicineTotal, $grandTotal, $paymentMethod]);
    $invoiceId = $pdo->lastInsertId();

    $updateVisit = $pdo->prepare("UPDATE visits SET status = 'Completed' WHERE visit_id = ?");
    $updateVisit->execute([$visitId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice_id' => $invoiceId,
        'consultation_fee' => $consultationFee,
        'medicine_total' => $medicineTotal,
        'grand_total' => $grandTotal,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
