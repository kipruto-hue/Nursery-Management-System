<?php
// CSV export of any report (§6.7). GET because it downloads a file; read-only.
require_once __DIR__ . '/../includes/auth.php';
require_owner_api();
require_once __DIR__ . '/../includes/reports.php';

$report = $_GET['report'] ?? '';

function send_csv(string $filename, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
    fputcsv($out, $header);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

$date = date('Y-m-d');

switch ($report) {

case 'profit':
    $rows = array_map(fn($r) => [
        $r['batch_code'], $r['species_name'], $r['stage'], $r['initial_qty'], $r['sold_qty'],
        $r['direct_expenses'], $r['direct_labour'], $r['overhead_share'],
        $r['cost'], $r['revenue'], $r['profit'], $r['margin'],
    ], report_profit_per_batch());
    send_csv("profit-per-batch-$date.csv",
        ['Batch', 'Plant', 'Stage', 'Seedlings', 'Sold', 'Direct expenses (KES)',
         'Direct labour (KES)', 'Overhead share (KES)', 'Total cost (KES)',
         'Revenue (KES)', 'Profit (KES)', 'Margin %'], $rows);

case 'stock':
    $rows = array_map(fn($r) => [$r['species_name'], $r['qty'], $r['estimated_value']],
        report_stock_ready());
    send_csv("stock-ready-$date.csv", ['Plant', 'Ready seedlings', 'Estimated value (KES)'], $rows);

case 'debts':
    $rows = array_map(fn($r) => [
        $r['customer_name'] ?? 'Walk-in', $r['phone'], $r['sale_id'], $r['sale_date'],
        $r['balance'], $r['days_out'], $r['bucket'],
    ], report_debtors());
    send_csv("money-owed-$date.csv",
        ['Customer', 'Phone', 'Sale no.', 'Sale date', 'Owes (KES)', 'Days outstanding', 'Age group'], $rows);

case 'monthly':
    $rows = array_map(fn($m) => [
        $m['month'], $m['income'], $m['expenses'], $m['income'] - $m['expenses'],
    ], report_monthly());
    send_csv("income-vs-expenses-$date.csv",
        ['Month', 'Income (KES)', 'Expenses (KES)', 'Difference (KES)'], $rows);

case 'losses':
    $rows = array_map(fn($r) => [
        $r['species_name'], $r['loss_reason'], $r['qty'], $r['times'],
    ], report_losses());
    send_csv("losses-$date.csv", ['Plant', 'Reason', 'Seedlings lost', 'Number of events'], $rows);

default:
    json_fail('Unknown report', 404);
}
