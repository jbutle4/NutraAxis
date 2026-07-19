<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

catalog_require_update();

set_time_limit(300);

try {
    $result = catalog_sync_all_skus_to_qbo();
} catch (Throwable $e) {
    $result = [
        'ok'         => false,
        'error'      => qbo_sync_format_exception($e, 'sync all SKUs to QuickBooks'),
        'total'      => 0,
        'synced'     => 0,
        'reconciled' => 0,
        'failed'     => 0,
        'failures'   => [],
    ];
}

catalog_store_bulk_sync_result($result);

$queryParams = ['notice' => 'qbo_bulk_sync'];
if ((int) ($result['warnings'] ?? 0) > 0) {
    $queryParams['warning'] = (int) $result['warnings'] . ' SKU(s) synced as Non-inventory product items because QuickBooks Essentials does not support inventory quantity tracking.';
}

$query = http_build_query(array_filter($queryParams));

header('Location: /product-catalog/?' . $query, true, 302);
exit;
