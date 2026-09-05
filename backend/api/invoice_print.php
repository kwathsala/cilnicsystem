<?php
/**
 * ============================================================
 * GET /backend/api/invoice_print.php?invoice_id=1
 * Renders a printable HTML invoice (opens in a new tab; user
 * can Ctrl+P / browser print to paper or PDF).
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Cashier', 'Admin']);

$invoiceId = (int)($_GET['invoice_id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT i.*, p.name AS patient_name, p.contact_number, p.address, v.visit_date, d.name AS doctor_name
     FROM invoices i
     JOIN patients p ON p.patient_id = i.patient_id
     JOIN visits v ON v.visit_id = i.visit_id
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE i.invoice_id = ?"
);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    echo "Invoice not found.";
    exit;
}

$rxStmt = $pdo->prepare(
    "SELECT m.name, p.dosage, p.duration, p.quantity, p.unit_price
     FROM prescriptions p
     JOIN medicines m ON m.medicine_id = p.medicine_id
     WHERE p.visit_id = ? AND p.removed_by_cashier = 0"
);
$rxStmt->execute([$invoice['visit_id']]);
$items = $rxStmt->fetchAll();

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice #<?= h($invoice['invoice_id']) ?></title>
<style>
    body { font-family: Arial, sans-serif; padding: 30px; color: #1f2937; }
    h1 { color: #0f4c81; font-size: 20px; margin-bottom: 4px; }
    .meta { font-size: 13px; color: #4b5563; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
    th { background: #f3f4f6; }
    .totals { margin-top: 16px; text-align: right; font-size: 14px; }
    .totals div { margin-bottom: 4px; }
    .grand { font-size: 17px; font-weight: bold; color: #0f4c81; }
    @media print { button { display: none; } }
</style>
</head>
<body>
    <h1>Clinic Invoice</h1>
    <div class="meta">
        Invoice #<?= h($invoice['invoice_id']) ?> &nbsp;|&nbsp; Date: <?= h($invoice['created_at']) ?><br>
        Patient: <?= h($invoice['patient_name']) ?> (<?= h($invoice['contact_number']) ?>)<br>
        Address: <?= h($invoice['address']) ?><br>
        Doctor: <?= h($invoice['doctor_name']) ?> &nbsp;|&nbsp; Visit Date: <?= h($invoice['visit_date']) ?><br>
        Payment Method: <?= h($invoice['payment_method']) ?>
    </div>

    <table>
        <thead><tr><th>Medicine</th><th>Dosage</th><th>Duration</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h($item['name']) ?></td>
                <td><?= h($item['dosage']) ?></td>
                <td><?= h($item['duration']) ?></td>
                <td><?= h($item['quantity']) ?></td>
                <td>Rs. <?= number_format($item['unit_price'], 2) ?></td>
                <td>Rs. <?= number_format($item['unit_price'] * $item['quantity'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div>Consultation Fee: Rs. <?= number_format($invoice['consultation_fee'], 2) ?></div>
        <div>Medicine Total: Rs. <?= number_format($invoice['medicine_total'], 2) ?></div>
        <div class="grand">Grand Total: Rs. <?= number_format($invoice['grand_total'], 2) ?></div>
    </div>

    <button onclick="window.print()" style="margin-top:24px; padding:10px 20px;">Print</button>
</body>
</html>
