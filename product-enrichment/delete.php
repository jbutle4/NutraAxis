<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /product-enrichment/', true, 302);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$result = product_enrichment_delete($id);

if ($result['ok']) {
    header('Location: /product-enrichment/?notice=deleted', true, 302);
    exit;
}

http_response_code(400);
exit($result['error'] ?? 'Unable to delete product enrichment record.');
