<?php
/**
 * ============================================================
 * GET /backend/api/analytics_summary.php?range=today|week|month|custom&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Financial dashboard for Doctor/Admin:
 *  - total revenue (consultation + medicine), invoice count
 *  - cost of goods sold (COGS) using medicines.cost_price
 *    NOTE: cost_price is looked up at CURRENT value, not the value
 *    on the day of sale (schema doesn't snapshot cost at sale time).
 *    This is an approximation - good enough for a running P&L view,
 *    but if cost prices change often the historical margin is only
 *    as accurate as the current cost_price.
 *  - net profit = revenue - COGS
 *  - daily revenue trend for the selected range
 *  - top 5 medicines by revenue
 *  - revenue by doctor
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
requireRole(['Doctor', 'Admin']);

header('Content-Type: application/json');

$pdo = getDBConnection();

// ---------- Resolve date range ----------
$range = $_GET['range'] ?? 'today';
$today = new DateTime('today');

switch ($range) {
    case 'week':
        $from = (clone $today)->modify('-6 days')->format('Y-m-d');
        $to = $today->format('Y-m-d');
        break;
    case 'month':
        $from = (clone $today)->modify('first day of this month')->format('Y-m-d');
        $to = $today->format('Y-m-d');
        break;
    case 'custom':
        $from = trim($_GET['from'] ?? '');
        $to = trim($_GET['to'] ?? '');
        if ($from === '' || $to === '') {
            http_response_code(400);
            echo json_encode(['error' => 'from and to are required for a custom range']);
            exit;
        }
        break;
    case 'today':
    default:
        $range = 'today';
        $from = $today->format('Y-m-d');
        $to = $today->format('Y-m-d');
        break;
}

// ---------- Overall totals ----------
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS invoice_count,
        COALESCE(SUM(consultation_fee), 0) AS consultation_revenue,
        COALESCE(SUM(medicine_total), 0) AS medicine_revenue,
        COALESCE(SUM(grand_total), 0) AS total_revenue
     FROM invoices
     WHERE DATE(created_at) BETWEEN ? AND ?"
);
$stmt->execute([$from, $to]);
$totals = $stmt->fetch();

// ---------- Cost of goods sold (billed items, current cost_price) ----------
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(pr.quantity * COALESCE(m.cost_price, 0)), 0) AS cogs
     FROM invoices i
     JOIN prescriptions pr ON pr.visit_id = i.visit_id AND pr.removed_by_cashier = 0
     JOIN medicines m ON m.medicine_id = pr.medicine_id
     WHERE DATE(i.created_at) BETWEEN ? AND ?"
);
$stmt->execute([$from, $to]);
$cogsRow = $stmt->fetch();
$cogs = (float)$cogsRow['cogs'];

$totalRevenue = (float)$totals['total_revenue'];
$netProfit = $totalRevenue - $cogs;

// Flag whether any billed medicine is missing a cost_price, so the
// frontend can warn that COGS/profit is understated, not wrong data.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS missing_cost
     FROM invoices i
     JOIN prescriptions pr ON pr.visit_id = i.visit_id AND pr.removed_by_cashier = 0
     JOIN medicines m ON m.medicine_id = pr.medicine_id
     WHERE DATE(i.created_at) BETWEEN ? AND ? AND m.cost_price IS NULL"
);
$stmt->execute([$from, $to]);
$missingCost = (int)$stmt->fetch()['missing_cost'];

// ---------- Daily revenue trend ----------
$stmt = $pdo->prepare(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(grand_total), 0) AS revenue
     FROM invoices
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY d ASC"
);
$stmt->execute([$from, $to]);
$dailyTrend = $stmt->fetchAll();

// ---------- Top 5 medicines by revenue ----------
$stmt = $pdo->prepare(
    "SELECT m.name,
            SUM(pr.quantity) AS qty_sold,
            SUM(pr.quantity * pr.unit_price) AS revenue
     FROM invoices i
     JOIN prescriptions pr ON pr.visit_id = i.visit_id AND pr.removed_by_cashier = 0
     JOIN medicines m ON m.medicine_id = pr.medicine_id
     WHERE DATE(i.created_at) BETWEEN ? AND ?
     GROUP BY m.medicine_id, m.name
     ORDER BY revenue DESC
     LIMIT 5"
);
$stmt->execute([$from, $to]);
$topMedicines = $stmt->fetchAll();

// ---------- Revenue by doctor ----------
$stmt = $pdo->prepare(
    "SELECT d.name AS doctor_name,
            COUNT(*) AS visit_count,
            COALESCE(SUM(i.grand_total), 0) AS revenue
     FROM invoices i
     JOIN visits v ON v.visit_id = i.visit_id
     JOIN doctors d ON d.doctor_id = v.doctor_id
     WHERE DATE(i.created_at) BETWEEN ? AND ?
     GROUP BY d.doctor_id, d.name
     ORDER BY revenue DESC"
);
$stmt->execute([$from, $to]);
$byDoctor = $stmt->fetchAll();

echo json_encode([
    'range' => $range,
    'from' => $from,
    'to' => $to,
    'invoice_count' => (int)$totals['invoice_count'],
    'consultation_revenue' => (float)$totals['consultation_revenue'],
    'medicine_revenue' => (float)$totals['medicine_revenue'],
    'total_revenue' => $totalRevenue,
    'cogs' => $cogs,
    'net_profit' => $netProfit,
    'missing_cost_price_items' => $missingCost,
    'daily_trend' => array_map(fn($r) => ['date' => $r['d'], 'revenue' => (float)$r['revenue']], $dailyTrend),
    'top_medicines' => array_map(fn($r) => [
        'name' => $r['name'],
        'qty_sold' => (int)$r['qty_sold'],
        'revenue' => (float)$r['revenue'],
    ], $topMedicines),
    'by_doctor' => array_map(fn($r) => [
        'doctor_name' => $r['doctor_name'],
        'visit_count' => (int)$r['visit_count'],
        'revenue' => (float)$r['revenue'],
    ], $byDoctor),
]);
