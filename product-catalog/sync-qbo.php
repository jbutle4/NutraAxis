<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

catalog_require_update();

$skuId = (int) ($_POST['sku_id'] ?? 0);
if ($skuId <= 0) {
    header('Location: /product-catalog/', true, 302);
    exit;
}

try {
    if (catalog_get_sku($skuId) === null) {
        header('Location: /product-catalog/', true, 302);
        exit;
    }

    $result = qbo_sync_sku($skuId);
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => qbo_sync_format_exception($e, 'sync this SKU to QuickBooks')];
}

$notice = $result['ok'] ? (!empty($result['reconciled']) ? 'qbo_reconciled' : 'qbo_synced') : null;
$error = $result['ok'] ? null : ($result['error'] ?? 'QuickBooks sync failed.');
$warning = $result['ok'] ? ($result['warning'] ?? null) : null;
$returnToList = ($_POST['return'] ?? '') === 'list';

$query = http_build_query(array_filter(
    $returnToList
        ? ['notice' => $notice, 'error' => $error, 'warning' => $warning]
        : ['id' => $skuId, 'notice' => $notice, 'error' => $error, 'warning' => $warning]
));

header(
    'Location: ' . ($returnToList ? '/product-catalog/?' : '/product-catalog/view.php?') . $query,
    true,
    302
);
exit;
