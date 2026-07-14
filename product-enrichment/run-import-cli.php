<?php

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$result = product_enrichment_import_defaults_from_site(true);

foreach ($result['results'] as $sku => $item) {
    $line = $sku . ': ';
    $line .= $item['ok']
        ? 'OK #' . (int) ($item['id'] ?? 0)
        : ($item['error'] ?? 'Import failed.');
    fwrite(STDOUT, $line . PHP_EOL);
}

exit($result['ok'] ? 0 : 1);
