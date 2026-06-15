<?php

/**
 * Audit: list all products and their current fulfillment attribute value.
 * Run before bulk-updating to confirm scope.
 */

require_once __DIR__ . '/../includes/adobe-commerce.php';

echo "Environment : " . adobe_commerce_environment() . "\n";
echo "Tenant      : " . adobe_commerce_tenant_id() . "\n";
echo str_repeat('-', 70) . "\n\n";

echo "Fetching all products...\n\n";

$result = adobe_commerce_fetch_paginated_items('/products', [
    'searchCriteria[sortOrders][0][field]'     => 'sku',
    'searchCriteria[sortOrders][0][direction]' => 'ASC',
]);

if (!$result['ok']) {
    echo "ERROR: " . $result['error'] . "\n";
    exit(1);
}

$products = $result['rows'];
$total    = $result['total'];

echo "Total products: $total\n\n";

$needsUpdate = [];
$alreadySet  = [];

printf("%-20s %-30s %-15s\n", "SKU", "Name", "fulfillment");
printf("%-20s %-30s %-15s\n", str_repeat('-', 19), str_repeat('-', 29), str_repeat('-', 14));

foreach ($products as $product) {
    $sku  = $product['sku'] ?? '?';
    $name = $product['name'] ?? '?';

    $fulfillment = null;
    foreach ($product['custom_attributes'] ?? [] as $attr) {
        if (($attr['attribute_code'] ?? '') === 'fulfillment') {
            $fulfillment = $attr['value'];
            break;
        }
    }

    $display = $fulfillment ?? '(not set)';
    printf("%-20s %-30s %-15s\n", $sku, substr($name, 0, 29), $display);

    if ($fulfillment !== 'Cart') {
        $needsUpdate[] = $sku;
    } else {
        $alreadySet[] = $sku;
    }
}

echo "\n";
echo "Already 'Cart' : " . count($alreadySet)  . " product(s)\n";
echo "Needs update   : " . count($needsUpdate) . " product(s)\n";

if ($needsUpdate !== []) {
    echo "\nSKUs that will be updated:\n";
    foreach ($needsUpdate as $sku) {
        echo "  - $sku\n";
    }
}
