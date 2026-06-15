<?php

/**
 * Bulk update: set fulfillment = "Cart" on all products that don't already have it.
 */

require_once __DIR__ . '/../includes/adobe-commerce.php';

echo "Environment : " . adobe_commerce_environment() . "\n";
echo "Tenant      : " . adobe_commerce_tenant_id() . "\n";
echo str_repeat('-', 70) . "\n\n";

// Fetch all products
echo "Fetching all products...\n";
$result = adobe_commerce_fetch_paginated_items('/products', [
    'searchCriteria[sortOrders][0][field]'     => 'sku',
    'searchCriteria[sortOrders][0][direction]' => 'ASC',
]);

if (!$result['ok']) {
    echo "ERROR fetching products: " . $result['error'] . "\n";
    exit(1);
}

$products    = $result['rows'];
$toUpdate    = [];
$alreadySet  = [];

foreach ($products as $product) {
    $sku = $product['sku'] ?? '';
    $fulfillment = null;
    foreach ($product['custom_attributes'] ?? [] as $attr) {
        if (($attr['attribute_code'] ?? '') === 'fulfillment') {
            $fulfillment = $attr['value'];
            break;
        }
    }
    if ($fulfillment === 'Cart') {
        $alreadySet[] = $sku;
    } else {
        $toUpdate[] = $sku;
    }
}

echo "Total products : " . count($products) . "\n";
echo "Already 'Cart' : " . count($alreadySet) . " — skipping\n";
echo "To update      : " . count($toUpdate) . "\n\n";

if ($toUpdate === []) {
    echo "Nothing to do.\n";
    exit(0);
}

// Get token once
$tokenResult = adobe_commerce_get_token();
if (!$tokenResult['ok']) {
    echo "ERROR getting token: " . $tokenResult['error'] . "\n";
    exit(1);
}
$token    = $tokenResult['token'];
$clientId = adobe_commerce_client_id();
$baseUrl  = adobe_commerce_base_url();

$updated = 0;
$failed  = 0;

foreach ($toUpdate as $sku) {
    $url  = $baseUrl . '/products/' . urlencode($sku);
    $body = json_encode([
        'product' => [
            'custom_attributes' => [
                ['attribute_code' => 'fulfillment', 'value' => 'Cart'],
            ],
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'x-api-key: ' . $clientId,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $responseBody = curl_exec($ch);
    $status       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        printf("  [OK]   %-20s HTTP %d\n", $sku, $status);
        $updated++;
    } else {
        $err = '';
        if ($responseBody) {
            $decoded = json_decode($responseBody, true);
            $err = $decoded['message'] ?? $responseBody;
        }
        printf("  [FAIL] %-20s HTTP %d — %s\n", $sku, $status, $err);
        $failed++;
    }
}

echo "\n";
echo "Done. Updated: $updated  Failed: $failed\n";
