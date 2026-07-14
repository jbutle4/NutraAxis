<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__) . '/includes/product-enrichment.php';

$lookupKey = (int) ($_GET['ref'] ?? 0);
if ($lookupKey === 0) {
    coa_public_json_response([
        'ok'    => false,
        'error' => 'Lookup key is required.',
    ], 400);
}

$row = product_enrichment_get_published_by_lookup_key($lookupKey);
if ($row === null) {
    coa_public_json_response([
        'ok'    => false,
        'error' => 'No published enrichment found for this product.',
    ], 404);
}

coa_public_json_response([
    'ok'           => true,
    'generated_at' => gmdate('c'),
    'item'         => product_enrichment_to_api_item($row),
]);
