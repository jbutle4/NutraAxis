<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__) . '/includes/product-enrichment.php';

function pdp_enrichment_load_decode_sku(string $encoded): string
{
    $encoded = trim($encoded);
    if ($encoded === '') {
        return '';
    }

    if (preg_match('/^[0-9a-f]+$/i', $encoded) === 1 && strlen($encoded) % 2 === 0) {
        $hexDecoded = hex2bin($encoded);
        if (is_string($hexDecoded) && $hexDecoded !== '') {
            return product_enrichment_normalize_sku($hexDecoded);
        }
    }

    $normalized = strtr($encoded, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding > 0) {
        $normalized .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($normalized, true);
    if (!is_string($decoded) || $decoded === '') {
        return '';
    }

    return product_enrichment_normalize_sku($decoded);
}

function pdp_enrichment_load_read_sku(): string
{
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'POST') === 0) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            try {
                $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($body)) {
                    foreach (['h', 's', 'ref', 'code'] as $key) {
                        $encodedSku = pdp_enrichment_load_decode_sku((string) ($body[$key] ?? ''));
                        if ($encodedSku !== '') {
                            return $encodedSku;
                        }
                    }

                    $sku = product_enrichment_normalize_sku((string) ($body['sku'] ?? ''));
                    if ($sku !== '') {
                        return $sku;
                    }
                }
            } catch (Throwable) {
                /* fall through */
            }
        }
    }

    foreach (['h', 's', 'ref', 'code'] as $key) {
        $encodedSku = pdp_enrichment_load_decode_sku((string) ($_GET[$key] ?? ''));
        if ($encodedSku !== '') {
            return $encodedSku;
        }
    }

    return product_enrichment_normalize_sku((string) ($_GET['sku'] ?? ''));
}

$sku = pdp_enrichment_load_read_sku();
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
