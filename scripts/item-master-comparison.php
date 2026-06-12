#!/usr/bin/env php
<?php
/**
 * Fetch item master data from MSSQL, Jazz OMS, and ACCS for comparison export.
 *
 * Usage:
 *   php scripts/item-master-comparison.php [--output=path/to/data.json]
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/jazz-oms.php';
require dirname(__DIR__) . '/includes/adobe-commerce.php';

function imc_jazz_fetch_all(string $path): array
{
    $url = jazz_oms_base_url() . $path;
    $params = ['limit' => 100, 'offset' => 0];
    $rows = [];
    $guard = 0;

    while ($url !== '' && $guard < 200) {
        $guard++;
        $result = jazz_oms_api_get($url, $guard === 1 ? $params : null);
        if (!$result['ok']) {
            break;
        }

        $data = $result['data'] ?? [];
        foreach ($data['results'] ?? [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        $next = $data['next'] ?? null;
        $url = is_string($next) && $next !== '' ? $next : '';
        $params = [];
    }

    return $rows;
}

function imc_accs_attr(array $product, string $code): string
{
    foreach ($product['custom_attributes'] ?? [] as $attribute) {
        if (($attribute['attribute_code'] ?? '') !== $code) {
            continue;
        }

        $value = $attribute['value'] ?? '';
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return trim((string) $value);
    }

    return '';
}

function imc_normalize_text($value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return $text;
}

function imc_strip_html(?string $value): string
{
    $text = imc_normalize_text($value);
    if ($text === '') {
        return '';
    }

    return imc_normalize_text(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function imc_normalize_product_name(?string $value): string
{
    $text = imc_normalize_text($value);
    if (str_starts_with(strtolower($text), 'nutraaxis ')) {
        $text = imc_normalize_text(substr($text, 10));
    }

    return $text;
}

function imc_normalize_manufacturer(?string $value): string
{
    $text = imc_normalize_text($value);
    if ($text === '') {
        return '';
    }

    static $map = [
        'VQ' => 'VitaQuest',
        'HW' => 'IFF-HealthWright',
        'NS' => 'NutraSeal',
        'IFF-HealthWright' => 'IFF-HealthWright',
        'VitaQuest' => 'VitaQuest',
        'NutraSeal' => 'NutraSeal',
    ];

    $upper = strtoupper($text);
    if (isset($map[$upper])) {
        return $map[$upper];
    }
    if (isset($map[$text])) {
        return $map[$text];
    }

    return $text;
}

function imc_normalize_status_mssql(?string $value): string
{
    $text = imc_normalize_text($value);
    if ($text === '') {
        return '';
    }

    return strtolower($text);
}

function imc_normalize_status_accs($value): string
{
    $status = (int) $value;
    if ($status === 1) {
        return 'active';
    }
    if ($status === 2) {
        return 'inactive';
    }

    return (string) $status;
}

function imc_normalize_status_jazz(?string $value): string
{
    return strtolower(imc_normalize_text($value));
}

function imc_normalize_money($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if (!is_numeric($value)) {
        return imc_normalize_text($value);
    }

    return number_format((float) $value, 4, '.', '');
}

function imc_normalize_upc($value): string
{
    $text = imc_normalize_text($value);
    $text = preg_replace('/\D+/', '', $text) ?? $text;

    return $text;
}

function imc_jazz_primary_barcode(array $row): string
{
    $barcode = imc_normalize_text($row['barcode'] ?? '');
    if ($barcode !== '') {
        return $barcode;
    }

    foreach ($row['barcodes'] ?? [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $candidate = imc_normalize_text($entry['barcode'] ?? '');
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function imc_mssql_row(array $row): array
{
    return [
        'sku'                 => imc_normalize_text($row['SKUCode'] ?? ''),
        'product_name'        => imc_normalize_product_name($row['ProductName'] ?? ''),
        'brand'               => imc_normalize_text($row['Brand'] ?? ''),
        'manufacturer'        => imc_normalize_manufacturer($row['Manufacturer'] ?? ''),
        'primary_category'    => imc_normalize_text($row['PrimaryTherapeuticCategory'] ?? ''),
        'secondary_category'  => imc_normalize_text($row['SecondaryCategory'] ?? ''),
        'status'              => imc_normalize_status_mssql($row['SKUStatus'] ?? ''),
        'upc'                 => imc_normalize_upc($row['UPC'] ?? ''),
        'gtin14'              => imc_normalize_upc($row['GTIN14'] ?? ''),
        'case_barcode'        => imc_normalize_upc($row['SKUCaseBarcode'] ?? ''),
        'msrp'                => imc_normalize_money($row['MSRP'] ?? null),
        'wholesale_price'     => imc_normalize_money($row['WholesalePrice'] ?? null),
        'cogs'                => imc_normalize_money($row['COGS'] ?? null),
        'serving_count'       => imc_normalize_text($row['ServingCount'] ?? ''),
        'bottle_size'         => imc_normalize_text($row['BottleSize'] ?? ''),
        'label_selection'     => imc_normalize_text($row['LabelSelection'] ?? ''),
        'formulation'         => imc_normalize_text($row['Formulation'] ?? ''),
        'directions'          => imc_normalize_text($row['Directions'] ?? ''),
        'capsule_count'       => imc_normalize_text($row['CapsuleCount'] ?? ''),
        'product'             => imc_normalize_text($row['Product'] ?? ''),
        'launch_date'         => imc_normalize_text($row['LaunchDate'] ?? ''),
        'notes'               => imc_normalize_text($row['Notes'] ?? ''),
    ];
}

function imc_accs_row(array $row): array
{
    return [
        'sku'                 => imc_normalize_text($row['sku'] ?? ''),
        'product_name'        => imc_normalize_product_name($row['name'] ?? ''),
        'brand'               => '',
        'manufacturer'        => imc_normalize_manufacturer(imc_accs_attr($row, 'cmo')),
        'primary_category'    => '',
        'secondary_category'  => '',
        'status'              => imc_normalize_status_accs($row['status'] ?? 0),
        'upc'                 => imc_normalize_upc(imc_accs_attr($row, 'upc')),
        'gtin14'              => '',
        'case_barcode'        => '',
        'msrp'                => imc_normalize_money($row['price'] ?? null),
        'wholesale_price'     => '',
        'cogs'                => imc_normalize_money(imc_accs_attr($row, 'cost')),
        'serving_count'       => imc_normalize_text(imc_accs_attr($row, 'servings')),
        'bottle_size'         => imc_normalize_text(imc_accs_attr($row, 'bottle_size')),
        'label_selection'     => imc_normalize_text(imc_accs_attr($row, 'label_selection')),
        'formulation'         => imc_strip_html(imc_accs_attr($row, 'description')),
        'directions'          => imc_normalize_text(imc_accs_attr($row, 'directions')),
        'capsule_count'       => imc_normalize_text(imc_accs_attr($row, 'capsule_count')),
        'product'             => '',
        'launch_date'         => '',
        'notes'               => imc_normalize_text(imc_accs_attr($row, 'short_description')),
        'url_key'             => imc_normalize_text(imc_accs_attr($row, 'url_key')),
        'type_id'             => imc_normalize_text($row['type_id'] ?? ''),
        'visibility'          => imc_normalize_text($row['visibility'] ?? ''),
    ];
}

function imc_jazz_sku_row(array $row): array
{
    return [
        'sku'                 => imc_normalize_text($row['sku_code'] ?? ''),
        'product_name'        => imc_normalize_product_name($row['description'] ?? ''),
        'brand'               => '',
        'manufacturer'        => '',
        'primary_category'    => '',
        'secondary_category'  => '',
        'status'              => imc_normalize_status_jazz($row['status'] ?? ''),
        'upc'                 => imc_normalize_upc(imc_jazz_primary_barcode($row)),
        'gtin14'              => '',
        'case_barcode'        => '',
        'msrp'                => imc_normalize_money($row['original_price'] ?? null),
        'wholesale_price'     => '',
        'cogs'                => imc_normalize_money($row['cost'] ?? null),
        'serving_count'       => imc_normalize_text($row['size_code'] ?? ''),
        'bottle_size'         => imc_normalize_text($row['size_description'] ?? ''),
        'label_selection'     => '',
        'formulation'         => '',
        'directions'          => '',
        'capsule_count'       => '',
        'product'             => imc_normalize_text($row['item_code'] ?? ''),
        'launch_date'         => '',
        'notes'               => '',
        'item_code'           => imc_normalize_text($row['item_code'] ?? ''),
        'is_kit'              => imc_normalize_text($row['is_kit'] ?? ''),
        'uses_inventory'      => imc_normalize_text($row['uses_inventory'] ?? ''),
        'lot_number_required' => imc_normalize_text($row['lot_number_required'] ?? ''),
        'tenant_code'         => imc_normalize_text($row['tenant_code'] ?? ''),
        'updated_at'          => imc_normalize_text($row['updated_at'] ?? ''),
    ];
}

function imc_jazz_item_row(array $row): array
{
    return [
        'item_code'           => imc_normalize_text($row['item_code'] ?? ''),
        'product_name'        => imc_normalize_text($row['description'] ?? ''),
        'vendor_code'         => imc_normalize_text($row['vendor_code'] ?? ''),
        'status'              => imc_normalize_status_jazz($row['status'] ?? ''),
        'msrp'                => imc_normalize_money($row['original_price'] ?? null),
        'cogs'                => imc_normalize_money($row['cost'] ?? null),
        'current_price'       => imc_normalize_money($row['current_price'] ?? null),
        'inventory_minimum'   => imc_normalize_text($row['inventory_minimum'] ?? ''),
        'backorderable'       => imc_normalize_text($row['backorderable'] ?? ''),
        'updated_at'          => imc_normalize_text($row['updated_at'] ?? ''),
    ];
}

function imc_index_by(array $rows, string $key): array
{
    $indexed = [];
    foreach ($rows as $row) {
        $value = imc_normalize_text($row[$key] ?? '');
        if ($value === '') {
            continue;
        }
        $indexed[$value] = $row;
    }

    return $indexed;
}

$outputArg = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputArg = substr($arg, 9);
    }
}

$pdo = db();
$mssqlRaw = $pdo->query('SELECT * FROM dbo.SKUMaster ORDER BY SKUCode')->fetchAll(PDO::FETCH_ASSOC);
$jazzSkuRaw = imc_jazz_fetch_all('/api/v1/product/sku');
$jazzItemRaw = imc_jazz_fetch_all('/api/v1/product/item');
$accsResult = adobe_commerce_fetch_paginated_items('/products');
$accsRaw = $accsResult['rows'] ?? [];

$mssql = array_map('imc_mssql_row', $mssqlRaw);
$accs = array_map('imc_accs_row', $accsRaw);
$jazzSku = array_map('imc_jazz_sku_row', $jazzSkuRaw);
$jazzItem = array_map('imc_jazz_item_row', $jazzItemRaw);

$mssqlBySku = imc_index_by($mssql, 'sku');
$accsBySku = imc_index_by($accs, 'sku');
$jazzBySku = imc_index_by($jazzSku, 'sku');

$allSkus = array_values(array_unique(array_merge(
    array_keys($mssqlBySku),
    array_keys($accsBySku),
    array_keys($jazzBySku)
)));
sort($allSkus, SORT_STRING);

$compareFields = [
    'product_name',
    'brand',
    'manufacturer',
    'primary_category',
    'secondary_category',
    'status',
    'upc',
    'gtin14',
    'case_barcode',
    'msrp',
    'wholesale_price',
    'cogs',
    'serving_count',
    'bottle_size',
    'label_selection',
    'formulation',
    'directions',
    'capsule_count',
];

$mssqlAccsRows = [];
$jazzRows = [];

foreach ($allSkus as $sku) {
    $m = $mssqlBySku[$sku] ?? null;
    $a = $accsBySku[$sku] ?? null;
    $j = $jazzBySku[$sku] ?? null;

    $mssqlAccsMismatchFields = [];
    $jazzMismatchFields = [];

    $alignedRow = [
        'sku' => $sku,
        'in_mssql' => $m !== null,
        'in_accs' => $a !== null,
        'in_jazz' => $j !== null,
    ];

    foreach ($compareFields as $field) {
        $mValue = $m[$field] ?? '';
        $aValue = $a[$field] ?? '';
        $jValue = $j[$field] ?? '';

        $alignedRow['mssql_' . $field] = $mValue;
        $alignedRow['accs_' . $field] = $aValue;

        if ($m !== null && $a !== null && $mValue !== $aValue) {
            $mssqlAccsMismatchFields[] = $field;
            $alignedRow['match_' . $field] = 'MISMATCH';
        } elseif ($m !== null && $a !== null) {
            $alignedRow['match_' . $field] = 'MATCH';
        } else {
            $alignedRow['match_' . $field] = 'N/A';
        }

        $expected = $mValue !== '' ? $mValue : $aValue;
        $alignedRow['jazz_' . $field] = $jValue;
        $alignedRow['expected_' . $field] = $expected;

        if ($expected !== '' && $j === null) {
            $alignedRow['jazz_match_' . $field] = 'MISSING IN JAZZ';
            $jazzMismatchFields[] = $field;
        } elseif ($expected !== '' && $jValue !== $expected) {
            $alignedRow['jazz_match_' . $field] = 'MISMATCH';
            $jazzMismatchFields[] = $field;
        } elseif ($expected !== '' && $j !== null) {
            $alignedRow['jazz_match_' . $field] = 'MATCH';
        } else {
            $alignedRow['jazz_match_' . $field] = 'N/A';
        }
    }

    $alignedRow['mssql_accs_overall'] = ($m !== null && $a !== null && $mssqlAccsMismatchFields === [])
        ? 'ALIGNED'
        : (($m === null || $a === null) ? 'MISSING SKU' : 'MISMATCH');
    $alignedRow['mssql_accs_mismatch_fields'] = implode(', ', $mssqlAccsMismatchFields);

    if ($j === null && ($m !== null || $a !== null)) {
        $jazzAction = 'ADD TO JAZZ';
    } elseif ($jazzMismatchFields !== []) {
        $jazzAction = 'CORRECT IN JAZZ';
    } elseif ($j === null) {
        $jazzAction = 'JAZZ ONLY / NO SOURCE';
    } else {
        $jazzAction = 'OK';
    }

    $alignedRow['jazz_overall'] = $jazzAction;
    $alignedRow['jazz_mismatch_fields'] = implode(', ', $jazzMismatchFields);

    $mssqlAccsRows[] = $alignedRow;

    $jazzRows[] = [
        'sku' => $sku,
        'in_mssql' => $m !== null,
        'in_accs' => $a !== null,
        'in_jazz_sku' => $j !== null,
        'in_jazz_item' => isset(imc_index_by($jazzItem, 'item_code')[$sku]),
        'jazz_action' => $jazzAction,
        'jazz_mismatch_fields' => implode(', ', $jazzMismatchFields),
        'expected' => $m ?? $a ?? [],
        'jazz' => $j ?? [],
    ];
}

$payload = [
    'generated_at' => gmdate('c'),
    'sources' => [
        'mssql' => [
            'table' => 'dbo.SKUMaster',
            'count' => count($mssqlRaw),
        ],
        'accs' => [
            'endpoint' => adobe_commerce_base_url() . '/products',
            'environment' => adobe_commerce_environment(),
            'count' => count($accsRaw),
        ],
        'jazz' => [
            'base_url' => jazz_oms_base_url(),
            'sku_endpoint' => '/api/v1/product/sku',
            'item_endpoint' => '/api/v1/product/item',
            'sku_count' => count($jazzSkuRaw),
            'item_count' => count($jazzItemRaw),
        ],
    ],
    'summary' => [
        'total_unique_skus' => count($allSkus),
        'mssql_only' => array_values(array_diff(array_keys($mssqlBySku), array_keys($accsBySku))),
        'accs_only' => array_values(array_diff(array_keys($accsBySku), array_keys($mssqlBySku))),
        'missing_in_jazz' => array_values(array_diff(array_keys($mssqlBySku), array_keys($jazzBySku))),
        'jazz_only' => array_values(array_diff(array_keys($jazzBySku), array_keys($mssqlBySku))),
        'mssql_accs_aligned_count' => count(array_filter($mssqlAccsRows, static fn(array $row): bool => $row['mssql_accs_overall'] === 'ALIGNED')),
        'jazz_needs_correction_count' => count(array_filter($jazzRows, static fn(array $row): bool => in_array($row['jazz_action'], ['ADD TO JAZZ', 'CORRECT IN JAZZ'], true))),
    ],
    'compare_fields' => $compareFields,
    'aligned_rows' => $mssqlAccsRows,
    'jazz_rows' => $jazzRows,
    'raw' => [
        'mssql' => $mssqlRaw,
        'accs' => $accsRaw,
        'jazz_sku' => $jazzSkuRaw,
        'jazz_item' => $jazzItemRaw,
    ],
    'normalized' => [
        'mssql' => $mssql,
        'accs' => $accs,
        'jazz_sku' => $jazzSku,
        'jazz_item' => $jazzItem,
    ],
];

$defaultOutput = dirname(__DIR__) . '/docs/exports/item-master-comparison-data.json';
$outputPath = $outputArg ?: $defaultOutput;
$outputDir = dirname($outputPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, json_encode([
    'ok' => true,
    'output' => $outputPath,
    'summary' => $payload['summary'],
    'sources' => $payload['sources'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
