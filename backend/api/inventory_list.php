<?php
/**
 * ============================================================
 * GET /backend/api/inventory_list.php?q=searchTerm
 * Full inventory list for the Inventory Management page:
 *  - current stock_qty, unit_price
 *  - week-ago snapshot (from stock_history) + computed diffs
 *  - low_stock / expiring_soon flags
 * ============================================================
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inventory_helpers.php';
requireRole(['Cashier', 'Admin']);

header('Content-Type: application/json');

$LOW_STOCK_THRESHOLD = 10;
$EXPIRY_WARNING_DAYS = 30;

$q = trim($_GET['q'] ?? '');
$pdo = getDBConnection();

if ($q === '') {
    $stmt = $pdo->query(
        'SELECT medicine_id, name, category, is_proprietary, unit_price, cost_price, stock_qty, expiry_date
         FROM medicines ORDER BY name ASC'
    );
} else {
    $stmt = $pdo->prepare(
        'SELECT medicine_id, name, category, is_proprietary, unit_price, cost_price, stock_qty, expiry_date
         FROM medicines WHERE name LIKE ? OR category LIKE ? ORDER BY name ASC'
    );
    $stmt->execute(["%$q%", "%$q%"]);
}

$medicines = $stmt->fetchAll();

$today = new DateTime('today');
$result = [];

foreach ($medicines as $med) {
    $weekAgo = getWeekAgoSnapshot($pdo, (int)$med['medicine_id']);

    $stockDiff = null;
    $priceDiff = null;
    if ($weekAgo) {
        $stockDiff = (int)$med['stock_qty'] - (int)$weekAgo['stock_qty'];
        $priceDiff = round((float)$med['unit_price'] - (float)$weekAgo['price_at_time'], 2);
    }

    $expiringSoon = false;
    $daysToExpiry = null;
    if (!empty($med['expiry_date'])) {
        $expiry = new DateTime($med['expiry_date']);
        $daysToExpiry = (int)$today->diff($expiry)->format('%r%a');
        $expiringSoon = $daysToExpiry !== null && $daysToExpiry <= $EXPIRY_WARNING_DAYS;
    }

    $result[] = [
        'medicine_id'    => (int)$med['medicine_id'],
        'name'           => $med['name'],
        'category'       => $med['category'],
        'is_proprietary' => (bool)$med['is_proprietary'],
        'unit_price'     => (float)$med['unit_price'],
        'cost_price'     => $med['cost_price'] !== null ? (float)$med['cost_price'] : null,
        'stock_qty'      => (int)$med['stock_qty'],
        'expiry_date'    => $med['expiry_date'],
        'low_stock'      => (int)$med['stock_qty'] < $LOW_STOCK_THRESHOLD,
        'expiring_soon'  => $expiringSoon,
        'days_to_expiry' => $daysToExpiry,
        'week_ago'       => $weekAgo ? [
            'stock_qty' => (int)$weekAgo['stock_qty'],
            'unit_price' => (float)$weekAgo['price_at_time'],
            'recorded_date' => $weekAgo['recorded_date'],
        ] : null,
        'stock_diff' => $stockDiff,
        'price_diff' => $priceDiff,
    ];
}

echo json_encode(['medicines' => $result, 'low_stock_threshold' => $LOW_STOCK_THRESHOLD]);
