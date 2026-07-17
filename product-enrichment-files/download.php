<?php

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

$productEnrichmentId = (int) ($_GET['id'] ?? 0);
$row = null;

if ($productEnrichmentId > 0) {
    $candidate = product_enrichment_get($productEnrichmentId);
    if ($candidate !== null && !empty($candidate['Publish'])) {
        $row = $candidate;
    }
}

if ($row === null) {
    $sku = product_enrichment_normalize_sku((string) ($_GET['sku'] ?? ''));
    $row = $sku !== '' ? product_enrichment_get_published_by_sku($sku) : null;
}

if ($row === null) {
    http_response_code(404);
    $sku = product_enrichment_normalize_sku((string) ($_GET['sku'] ?? ''));
    if ($sku !== '' && product_enrichment_get_by_sku($sku) !== null) {
        exit('Product information sheet is not published yet.');
    }
    exit('Product information sheet not found.');
}

product_enrichment_stream_or_backfill($row, true);
