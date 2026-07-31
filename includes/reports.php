<?php
// Report queries shared by app/reports.php (HTML) and api/export.php (CSV).
// Owner-only callers must enforce the role check before including this file.
require_once __DIR__ . '/db.php';

/**
 * Profit per batch (§6.7.1).
 * Batch cost = direct expenses + direct labour + share of general overheads,
 * allocated in proportion to seedlings produced (initial_qty) (§5).
 */
function report_profit_per_batch(): array {
    $pdo = db();
    $rows = $pdo->query(
        "SELECT b.id, b.batch_code, b.stage, b.initial_qty, b.current_qty,
                s.name AS species_name,
                COALESCE(ex.total, 0) AS direct_expenses,
                COALESCE(lb.total, 0) AS direct_labour,
                COALESCE(si.revenue, 0) AS revenue,
                COALESCE(si.sold_qty, 0) AS sold_qty
         FROM batches b
         JOIN species s ON s.id = b.species_id
         LEFT JOIN (SELECT batch_id, SUM(amount) AS total FROM expenses
                    WHERE batch_id IS NOT NULL GROUP BY batch_id) ex ON ex.batch_id = b.id
         LEFT JOIN (SELECT batch_id, SUM(amount) AS total FROM labour
                    WHERE batch_id IS NOT NULL GROUP BY batch_id) lb ON lb.batch_id = b.id
         LEFT JOIN (SELECT batch_id, SUM(line_total) AS revenue, SUM(qty) AS sold_qty
                    FROM sale_items GROUP BY batch_id) si ON si.batch_id = b.id
         WHERE b.deleted_at IS NULL
         ORDER BY b.sown_date DESC"
    )->fetchAll();

    $overhead = (float)$pdo->query(
        "SELECT COALESCE((SELECT SUM(amount) FROM expenses WHERE batch_id IS NULL), 0)
              + COALESCE((SELECT SUM(amount) FROM labour WHERE batch_id IS NULL), 0)"
    )->fetchColumn();
    $totalSeedlings = array_sum(array_column($rows, 'initial_qty'));

    foreach ($rows as &$r) {
        $share = $totalSeedlings > 0
            ? round($overhead * $r['initial_qty'] / $totalSeedlings, 2) : 0.0;
        $r['overhead_share'] = $share;
        $r['cost'] = round($r['direct_expenses'] + $r['direct_labour'] + $share, 2);
        $r['profit'] = round($r['revenue'] - $r['cost'], 2);
        $r['margin'] = $r['revenue'] > 0 ? round($r['profit'] / $r['revenue'] * 100, 1) : null;
    }
    return $rows;
}

/** Stock ready for sale by species, with estimated value (§6.7.2). */
function report_stock_ready(): array {
    return db()->query(
        "SELECT s.name AS species_name,
                SUM(b.current_qty) AS qty,
                SUM(b.current_qty * COALESCE(b.sale_price_override, s.default_sale_price)) AS estimated_value
         FROM batches b JOIN species s ON s.id = b.species_id
         WHERE b.stage = 'ready' AND b.deleted_at IS NULL AND b.current_qty > 0
         GROUP BY s.id, s.name
         ORDER BY estimated_value DESC"
    )->fetchAll();
}

/** Debtors with aging buckets 0–7 / 8–30 / 30+ days (§6.7.3). */
function report_debtors(): array {
    return db()->query(
        "SELECT sa.id AS sale_id, sa.sale_date,
                c.name AS customer_name, c.phone,
                (sa.total_amount - sa.amount_paid) AS balance,
                DATEDIFF(CURDATE(), sa.sale_date) AS days_out,
                CASE WHEN DATEDIFF(CURDATE(), sa.sale_date) <= 7 THEN '0-7 days'
                     WHEN DATEDIFF(CURDATE(), sa.sale_date) <= 30 THEN '8-30 days'
                     ELSE 'Over 30 days' END AS bucket
         FROM sales sa LEFT JOIN customers c ON c.id = sa.customer_id
         WHERE sa.status IN ('partial','credit')
         ORDER BY days_out DESC"
    )->fetchAll();
}

/** Income vs expenses per month, last 12 months (§6.7.4). */
function report_monthly(): array {
    $pdo = db();
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("first day of -$i months"));
        $months[$key] = ['month' => $key, 'income' => 0.0, 'expenses' => 0.0];
    }
    $from = array_key_first($months) . '-01';

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(sale_date, '%Y-%m') AS m, SUM(total_amount) AS t
         FROM sales WHERE sale_date >= ? GROUP BY m"
    );
    $stmt->execute([$from]);
    foreach ($stmt->fetchAll() as $r) {
        if (isset($months[$r['m']])) $months[$r['m']]['income'] = (float)$r['t'];
    }

    $stmt = $pdo->prepare(
        "SELECT m, SUM(t) AS t FROM (
            SELECT DATE_FORMAT(expense_date, '%Y-%m') AS m, SUM(amount) AS t
            FROM expenses WHERE expense_date >= ? GROUP BY m
            UNION ALL
            SELECT DATE_FORMAT(labour_date, '%Y-%m') AS m, SUM(amount) AS t
            FROM labour WHERE labour_date >= ? GROUP BY m
         ) x GROUP BY m"
    );
    $stmt->execute([$from, $from]);
    foreach ($stmt->fetchAll() as $r) {
        if (isset($months[$r['m']])) $months[$r['m']]['expenses'] = (float)$r['t'];
    }
    return array_values($months);
}

/** Losses by species and reason (§6.7.5). */
function report_losses(): array {
    return db()->query(
        "SELECT s.name AS species_name, ev.loss_reason, SUM(ev.qty) AS qty,
                COUNT(*) AS times
         FROM batch_events ev
         JOIN batches b ON b.id = ev.batch_id
         JOIN species s ON s.id = b.species_id
         WHERE ev.event_type = 'loss'
         GROUP BY s.id, s.name, ev.loss_reason
         ORDER BY qty DESC"
    )->fetchAll();
}
