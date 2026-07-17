<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__) . '/includes/product-enrichment.php';

function pdp_enrichment_public_decode_hex_sku(string $encoded): string
{
    $encoded = strtolower(trim($encoded));
    if ($encoded === '' || preg_match('/^[0-9a-f]+$/', $encoded) !== 1 || strlen($encoded) % 2 !== 0) {
        return '';
    }

    $decoded = hex2bin($encoded);
    if (!is_string($decoded) || $decoded === '') {
        return '';
    }

    return product_enrichment_normalize_sku($decoded);
}

function pdp_enrichment_public_read_sku(): string
{
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') === 0) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            try {
                $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($body)) {
                    $partOne = pdp_enrichment_public_decode_hex_sku((string) ($body['p1'] ?? ''));
                    $partTwo = pdp_enrichment_public_decode_hex_sku((string) ($body['p2'] ?? ''));
                    if ($partOne !== '' && $partTwo !== '') {
                        return product_enrichment_normalize_sku($partOne . $partTwo);
                    }

                    $hexSku = pdp_enrichment_public_decode_hex_sku((string) ($body['h'] ?? ''));
                    if ($hexSku !== '') {
                        return $hexSku;
                    }
                }
            } catch (Throwable) {
                /* fall through */
            }
        }
    }

    $partOne = pdp_enrichment_public_decode_hex_sku((string) ($_GET['p1'] ?? ''));
    $partTwo = pdp_enrichment_public_decode_hex_sku((string) ($_GET['p2'] ?? ''));
    if ($partOne !== '' && $partTwo !== '') {
        return product_enrichment_normalize_sku($partOne . $partTwo);
    }

    return pdp_enrichment_public_decode_hex_sku((string) ($_GET['h'] ?? ''));
}

$sku = pdp_enrichment_public_read_sku();
if ($sku === '') {
    coa_public_json_response([
        'ok'    => false,
        'error' => 'SKU is required.',
    ], 400);
}

$row = product_enrichment_get_published_by_sku($sku);
if ($row === null) {
    coa_public_json_response([
        'ok'    => false,
        'error' => 'No published enrichment found for this SKU.',
        'sku'   => $sku,
    ], 404);
}

coa_public_json_response([
    'ok'           => true,
    'generated_at' => gmdate('c'),
    'item'         => product_enrichment_to_api_item($row),
]);
