<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

catalog_require_update();

$skuId = (int) ($_POST['sku_id'] ?? 0);
$convertAll = ($_POST['convert_all'] ?? '') === '1';

try {
    if ($convertAll) {
        set_time_limit(300);
        $result = catalog_convert_all_skus_to_qbo_inventory();
        catalog_store_bulk_sync_result($result);
        $query = http_build_query([
            'notice' => 'qbo_inventory_convert_bulk',
            'error' => $result['ok'] ? null : ($result['error'] ?? 'Inventory conversion failed.'),
        ]);
        header('Location: /product-catalog/?' . $query, true, 302);
        exit;
    }

    if ($skuId <= 0 || catalog_get_sku($skuId) === null) {
        header('Location: /product-catalog/', true, 302);
        exit;
    }

    $result = catalog_convert_sku_to_qbo_inventory($skuId);
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => qbo_sync_format_exception($e, 'convert this SKU to a QuickBooks Inventory item')];
}

$notice = null;
if ($result['ok']) {
    $notice = (($result['action'] ?? '') === 'already_inventory')
        ? 'qbo_already_inventory'
        : 'qbo_inventory_converted';
}

$query = http_build_query(array_filter([
    'id' => $skuId,
    'notice' => $notice,
    'error' => $result['ok'] ? null : ($result['error'] ?? 'Inventory conversion failed.'),
]));

header('Location: /product-catalog/view.php?' . $query, true, 302);
exit;
