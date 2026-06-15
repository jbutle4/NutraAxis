<?php

/**
 * One-off script: fetch a single product by SKU from ACCS and dump all attributes.
 * Usage: php scripts/fetch-product-sku.php NA-GW-002
 */

require_once __DIR__ . '/../includes/adobe-commerce.php';

$sku = isset($argv[1]) ? trim($argv[1]) : 'NA-GW-002';

if ($sku === '') {
    echo "Usage: php fetch-product-sku.php <SKU>\n";
    exit(1);
}

echo "Environment : " . adobe_commerce_environment() . "\n";
echo "Tenant      : " . adobe_commerce_tenant_id() . "\n";
echo "Base URL    : " . adobe_commerce_base_url() . "\n";
echo str_repeat('-', 60) . "\n";
echo "Fetching SKU: $sku\n\n";

// GET /products/{sku} — direct single-product endpoint
$result = adobe_commerce_api_request('GET', '/products/' . urlencode($sku));

if (!$result['ok']) {
    echo "ERROR: " . $result['error'] . "\n";
    exit(1);
}

$product = $result['data'];

// Core fields
$coreFields = ['id', 'sku', 'name', 'attribute_set_id', 'price', 'status',
                'visibility', 'type_id', 'created_at', 'updated_at', 'weight'];

echo "=== Core Fields ===\n";
foreach ($coreFields as $field) {
    if (array_key_exists($field, $product)) {
        $val = is_bool($product[$field]) ? ($product[$field] ? 'true' : 'false') : $product[$field];
        printf("  %-20s %s\n", $field . ':', $val ?? '(null)');
    }
}

// Custom attributes
$customAttrs = $product['custom_attributes'] ?? [];
if (!empty($customAttrs)) {
    echo "\n=== Custom Attributes ===\n";
    usort($customAttrs, fn($a, $b) => strcmp($a['attribute_code'], $b['attribute_code']));
    foreach ($customAttrs as $attr) {
        $code = $attr['attribute_code'] ?? '?';
        $value = $attr['value'] ?? '(null)';
        if (is_array($value)) {
            $value = json_encode($value);
        }
        // Truncate long HTML/text values for readability
        if (is_string($value) && strlen($value) > 200) {
            $value = substr(strip_tags($value), 0, 200) . '…';
        }
        printf("  %-35s %s\n", $code . ':', $value);
    }
} else {
    echo "\n(No custom_attributes returned)\n";
}

// Extension attributes
$extAttrs = $product['extension_attributes'] ?? [];
if (!empty($extAttrs)) {
    echo "\n=== Extension Attributes ===\n";
    echo json_encode($extAttrs, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Raw JSON (full) ===\n";
echo json_encode($product, JSON_PRETTY_PRINT) . "\n";
