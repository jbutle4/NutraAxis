#!/usr/bin/env php
<?php
/**
 * Append an asterisk to each product short_description in ACCS.
 *
 * Usage:
 *   php scripts/update-accs-short-description-asterisk.php [--production] [--dry-run]
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/data-profile.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
if (in_array('--production', $argv ?? [], true) || !in_array('--uat', $argv ?? [], true)) {
    data_profile_set('production');
}

require_once __DIR__ . '/../includes/adobe-commerce.php';

const UASA_SKIP_SKUS = ['NA-GW-002', 'NA-MT-001'];

function uasa_accs_attr(array $product, string $code): string
{
    foreach ($product['custom_attributes'] ?? [] as $attribute) {
        if (($attribute['attribute_code'] ?? '') !== $code) {
            continue;
        }

        $value = $attribute['value'] ?? '';
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    return '';
}

function uasa_append_asterisk(string $html): string
{
    $trimmed = rtrim($html);
    if ($trimmed === '') {
        return $html;
    }

    if (preg_match('/\*(\s*<\/p>\s*)$/i', $trimmed)) {
        return $html;
    }

    if (preg_match('/<\/p>\s*$/i', $trimmed)) {
        return preg_replace('/<\/p>\s*$/i', '*</p>', $trimmed, 1) ?? ($trimmed . '*');
    }

    return $trimmed . '*';
}

echo 'Environment : ' . adobe_commerce_environment() . "\n";
echo 'Tenant      : ' . adobe_commerce_tenant_id() . "\n";
echo 'Mode        : ' . ($dryRun ? 'DRY RUN' : 'LIVE UPDATE') . "\n";
echo str_repeat('-', 70) . "\n\n";

$result = adobe_commerce_fetch_paginated_items('/products', [
    'searchCriteria[sortOrders][0][field]'     => 'sku',
    'searchCriteria[sortOrders][0][direction]' => 'ASC',
]);

if (!$result['ok']) {
    fwrite(STDERR, 'ERROR fetching products: ' . $result['error'] . "\n");
    exit(1);
}

$toUpdate = [];
$skipped = [];

foreach ($result['rows'] as $product) {
    $sku = (string) ($product['sku'] ?? '');
    if ($sku === '') {
        continue;
    }

    if (in_array($sku, UASA_SKIP_SKUS, true)) {
        $skipped[] = ['sku' => $sku, 'reason' => 'excluded'];
        continue;
    }

    $shortDescription = uasa_accs_attr($product, 'short_description');
    if (trim($shortDescription) === '') {
        $skipped[] = ['sku' => $sku, 'reason' => 'empty short_description'];
        continue;
    }

    $updated = uasa_append_asterisk($shortDescription);
    if ($updated === $shortDescription) {
        $skipped[] = ['sku' => $sku, 'reason' => 'already ends with asterisk'];
        continue;
    }

    $toUpdate[] = [
        'sku' => $sku,
        'name' => (string) ($product['name'] ?? ''),
        'before' => $shortDescription,
        'after' => $updated,
    ];
}

echo 'Total products : ' . count($result['rows']) . "\n";
echo 'To update      : ' . count($toUpdate) . "\n";
echo 'Skipped        : ' . count($skipped) . "\n\n";

if ($toUpdate === []) {
    echo "Nothing to do.\n";
    exit(0);
}

foreach ($toUpdate as $row) {
    echo "  {$row['sku']} ({$row['name']})\n";
}

echo "\n";

if ($dryRun) {
    echo "Dry run complete. No changes sent to ACCS.\n";
    exit(0);
}

$tokenResult = adobe_commerce_get_token();
if (!$tokenResult['ok']) {
    fwrite(STDERR, 'ERROR getting token: ' . $tokenResult['error'] . "\n");
    exit(1);
}

$token = $tokenResult['token'];
$clientId = adobe_commerce_client_id();
$baseUrl = adobe_commerce_base_url();

$updated = 0;
$failed = 0;

foreach ($toUpdate as $row) {
    $url = $baseUrl . '/products/' . rawurlencode($row['sku']);
    $body = json_encode([
        'product' => [
            'custom_attributes' => [
                ['attribute_code' => 'short_description', 'value' => $row['after']],
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
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        printf("  [OK]   %-20s HTTP %d\n", $row['sku'], $status);
        $updated++;
        continue;
    }

    $err = '';
    if (is_string($responseBody) && $responseBody !== '') {
        $decoded = json_decode($responseBody, true);
        $err = is_array($decoded) ? (string) ($decoded['message'] ?? $responseBody) : $responseBody;
    }
    printf("  [FAIL] %-20s HTTP %d — %s\n", $row['sku'], $status, $err);
    $failed++;
}

echo "\nDone. Updated: {$updated}  Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
