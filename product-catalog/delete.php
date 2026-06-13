<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';

catalog_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /product-catalog/', true, 302);
    exit;
}

$skuId = (int) ($_POST['sku_id'] ?? 0);
$result = catalog_delete_sku($skuId);

if ($result['ok']) {
    header('Location: /product-catalog/?notice=deleted', true, 302);
    exit;
}

header('Location: /product-catalog/view.php?id=' . $skuId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
