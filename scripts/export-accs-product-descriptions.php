#!/usr/bin/env php
<?php
/**
 * Export ACCS product short descriptions.
 *
 * Usage:
 *   php scripts/export-accs-product-descriptions.php [--production] [--output=path] [--format=json|csv|both]
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/data-profile.php';
data_profile_set('production');
require_once __DIR__ . '/../includes/adobe-commerce.php';

function eapd_accs_attr(array $product, string $code): string
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

function eapd_normalize_text(?string $value): string
{
    $text = trim((string) ($value ?? ''));
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return $text;
}

function eapd_strip_html(?string $value): string
{
    $text = eapd_normalize_text($value);
    if ($text === '') {
        return '';
    }

    return eapd_normalize_text(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function eapd_truncate(string $value, int $maxLen = 120): string
{
    if (strlen($value) <= $maxLen) {
        return $value;
    }

    return substr($value, 0, $maxLen - 3) . '...';
}

$outputArg = null;
$format = 'both';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputArg = substr($arg, 9);
        continue;
    }
    if (str_starts_with($arg, '--format=')) {
        $format = strtolower(substr($arg, 9));
    }
}

if (!in_array($format, ['json', 'csv', 'both'], true)) {
    fwrite(STDERR, "Invalid --format value. Use json, csv, or both.\n");
    exit(1);
}

$configError = adobe_commerce_config_error();
if ($configError !== null) {
    fwrite(STDERR, "ERROR: {$configError}\n");
    exit(1);
}

$defaultBase = dirname(__DIR__) . '/docs/exports/accs-production-product-descriptions';
if ($outputArg !== null && $outputArg !== '') {
    $basePath = preg_replace('/\.(json|csv)$/i', '', $outputArg) ?? $outputArg;
} else {
    $basePath = $defaultBase;
}

$jsonPath = $basePath . '.json';
$csvPath = $basePath . '.csv';

$result = adobe_commerce_fetch_paginated_items('/products');
if (!$result['ok']) {
    fwrite(STDERR, 'ERROR fetching products: ' . ($result['error'] ?? 'unknown error') . "\n");
    exit(1);
}

$products = [];
$missingShortDescription = 0;

foreach ($result['rows'] as $product) {
    if (!is_array($product)) {
        continue;
    }

    $sku = trim((string) ($product['sku'] ?? ''));
    $shortDescription = eapd_accs_attr($product, 'short_description');

    if ($shortDescription === '') {
        $missingShortDescription++;
    }

    $products[] = [
        'sku' => $sku,
        'product_name' => trim((string) ($product['name'] ?? '')),
        'short_description' => $shortDescription,
        'short_description_plain' => eapd_strip_html($shortDescription),
    ];
}

usort($products, static fn(array $a, array $b): int => strcmp($a['sku'], $b['sku']));

$payload = [
    'exported_at' => gmdate('c'),
    'environment' => adobe_commerce_environment(),
    'tenant_id' => adobe_commerce_tenant_id(),
    'api_base' => adobe_commerce_base_url(),
    'total_count' => count($products),
    'missing_short_description_count' => $missingShortDescription,
    'products' => $products,
];

$outputDir = dirname($jsonPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$writtenPaths = [];

if ($format === 'json' || $format === 'both') {
    file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $writtenPaths[] = $jsonPath;
}

if ($format === 'csv' || $format === 'both') {
    $csvDir = dirname($csvPath);
    if (!is_dir($csvDir)) {
        mkdir($csvDir, 0755, true);
    }

    $handle = fopen($csvPath, 'wb');
    if ($handle === false) {
        fwrite(STDERR, "ERROR: unable to write CSV to {$csvPath}\n");
        exit(1);
    }

    fputcsv($handle, ['sku', 'product_name', 'short_description', 'short_description_plain'], ',', '"', '\\');
    foreach ($products as $row) {
        fputcsv($handle, [
            $row['sku'],
            $row['product_name'],
            $row['short_description'],
            $row['short_description_plain'],
        ], ',', '"', '\\');
    }
    fclose($handle);
    $writtenPaths[] = $csvPath;
}

$sample = array_slice($products, 0, 3);
$sampleOut = [];
foreach ($sample as $row) {
    $sampleOut[] = [
        'sku' => $row['sku'],
        'product_name' => $row['product_name'],
        'short_description_plain' => eapd_truncate($row['short_description_plain']),
    ];
}

fwrite(STDOUT, json_encode([
    'ok' => true,
    'environment' => $payload['environment'],
    'tenant_id' => $payload['tenant_id'],
    'total_count' => $payload['total_count'],
    'missing_short_description_count' => $missingShortDescription,
    'output_paths' => $writtenPaths,
    'sample' => $sampleOut,
    'api_error' => null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
