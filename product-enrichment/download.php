<?php

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_read();

$id = (int) ($_GET['id'] ?? 0);
$row = product_enrichment_get($id);

if ($row === null) {
    http_response_code(404);
    exit('Product enrichment record not found.');
}

product_enrichment_stream_or_backfill($row, true);
