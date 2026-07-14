<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__) . '/includes/product-enrichment.php';

function pdp_enrichment_data_read_lookup_key(): int
{
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') === 0) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            try {
                $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($body)) {
                    return (int) ($body['id'] ?? 0);
                }
            } catch (Throwable) {
                /* fall through */
            }
        }
    }

    return (int) ($_GET['id'] ?? 0);
}

$lookupKey = pdp_enrichment_data_read_lookup_key();
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
