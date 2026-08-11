<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';
require dirname(__DIR__, 2) . '/includes/sales-rep-reporting.php';

sales_reporting_require_read();

$sourceEnvironment = sales_rep_reporting_source_environment();
$filters = [
    'q'          => trim($_GET['q'] ?? ''),
    'rep'        => trim($_GET['rep'] ?? ''),
    'status'     => trim($_GET['status'] ?? ''),
    'date_from'  => trim($_GET['date_from'] ?? ''),
    'date_to'    => trim($_GET['date_to'] ?? ''),
    'limit'      => 5000,
    'for_export' => true,
] + table_sort_state(SALES_REP_REPORTING_SORT_COLUMNS, 'date', 'desc', $_GET);

$rows = sales_rep_reporting_list($filters);
$rows = table_sort_rows(
    $rows,
    $filters,
    [
        'order_id'      => fn(array $r): string => (string) ($r['order_id'] ?? ''),
        'date'          => fn(array $r): string => (string) ($r['date'] ?? ''),
        'amount'        => fn(array $r) => $r['amount'] ?? 0,
        'item_subtotal' => fn(array $r) => $r['item_subtotal'] ?? 0,
        'status'        => fn(array $r): string => (string) ($r['status'] ?? ''),
        'sales_rep'     => fn(array $r): string => (string) ($r['sales_rep'] ?? ''),
        'company_name'  => fn(array $r): string => (string) ($r['company_name'] ?? ''),
        'company_id'    => fn(array $r) => $r['company_id'] ?? 0,
        'company_state' => fn(array $r): string => (string) ($r['company_state'] ?? ''),
        'company_zip'   => fn(array $r): string => (string) ($r['company_zip'] ?? ''),
        'purchaser'     => fn(array $r): string => (string) ($r['purchaser'] ?? ''),
        'ship_state'    => fn(array $r): string => (string) ($r['ship_state'] ?? ''),
        'ship_zip'      => fn(array $r): string => (string) ($r['ship_zip'] ?? ''),
    ],
    SALES_REP_REPORTING_SORT_NUMERIC,
    'date',
    'desc'
);

$filename = sales_rep_reporting_export_filename($sourceEnvironment);
$csv = sales_rep_reporting_export_csv($rows);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $csv;
exit;
