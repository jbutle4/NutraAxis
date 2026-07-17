<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /product-enrichment/', true, 302);
    exit;
}

$publish = !empty($_POST['publish']);
$result = product_enrichment_import_defaults_from_site($publish);

if ($result['ok']) {
    header('Location: /product-enrichment/?notice=imported', true, 302);
    exit;
}

$errors = [];
foreach ($result['results'] as $sku => $item) {
    if (!$item['ok']) {
        $errors[] = $sku . ': ' . ($item['error'] ?? 'Import failed.');
    }
}

http_response_code(400);
echo implode("\n", $errors);
