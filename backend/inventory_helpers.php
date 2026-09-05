<?php
/**
 * ============================================================
 * INVENTORY HELPERS
 * Shared by all inventory-related endpoints and by
 * cashier_finalize.php (stock deducted on sale).
 * ============================================================
 */

/**
 * Records a stock_history snapshot for a medicine. Called every
 * time stock_qty changes (restock, sale, manual correction) so
 * that "week comparison" always has data points to look back on.
 */
function recordStockSnapshot(PDO $pdo, int $medicineId, int $newQty, float $priceAtTime) {
    $stmt = $pdo->prepare(
        'INSERT INTO stock_history (medicine_id, stock_qty, price_at_time, recorded_date)
         VALUES (?, ?, ?, CURDATE())'
    );
    $stmt->execute([$medicineId, $newQty, $priceAtTime]);
}

/**
 * Returns the closest stock_history snapshot taken 7+ days ago
 * for a medicine, so current stock/price can be compared against
 * "this time last week". Returns null if no history exists yet.
 */
function getWeekAgoSnapshot(PDO $pdo, int $medicineId): ?array {
    $stmt = $pdo->prepare(
        'SELECT stock_qty, price_at_time, recorded_date
         FROM stock_history
         WHERE medicine_id = ? AND recorded_date <= (CURDATE() - INTERVAL 7 DAY)
         ORDER BY recorded_date DESC, history_id DESC
         LIMIT 1'
    );
    $stmt->execute([$medicineId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
