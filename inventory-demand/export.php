<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-forecasting.php';

inventory_forecasting_require_read();

$skuFilter = trim($_GET['sku'] ?? '');
$shortageFilter = trim($_GET['shortage'] ?? '');
$rows = inventory_forecasting_list_plan_rows(
    $skuFilter !== '' ? $skuFilter : null,
    $shortageFilter !== '' ? $shortageFilter : null
);

$filename = inventory_forecasting_export_filename();
$csv = inventory_forecasting_export_csv($rows);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo $csv;
exit;
