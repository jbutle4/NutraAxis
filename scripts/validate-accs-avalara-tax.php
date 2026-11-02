<?php
/**
 * Quote-only Avalara tax validation on ACCS Stage: one cart quote per US jurisdiction,
 * compared against the taxability matrix (±tolerance relative to the expected rate).
 *
 * Usage:
 *   php scripts/validate-accs-avalara-tax.php --matrix=/tmp/taxability-matrix.json \
 *       [--customer=288] [--sku=NA-MT-001,NA-GW-002] [--tolerance=5] [--csv=/tmp/out.csv]
 *       [--lines-csv=/tmp/lines.csv] [--only=CA,TX]
 *
 * Never places an order. Removes the test lines from the cart when finished.
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/data-profile.php';
data_profile_set('uat');
require dirname(__DIR__) . '/includes/adobe-commerce.php';

$opts = getopt('', ['matrix:', 'customer::', 'sku::', 'tolerance::', 'csv::', 'lines-csv::', 'only::']);
$matrixPath = (string) ($opts['matrix'] ?? '');
$customerId = (int) ($opts['customer'] ?? 288);
$skus = array_values(array_filter(array_map('trim', explode(',', (string) ($opts['sku'] ?? 'NA-MT-001')))));
$tolerancePct = (float) ($opts['tolerance'] ?? 5);
$csvPath = (string) ($opts['csv'] ?? '');
$linesCsvPath = (string) ($opts['lines-csv'] ?? '');
$only = array_filter(array_map('trim', explode(',', strtoupper((string) ($opts['only'] ?? '')))));

if ($matrixPath === '' || !is_file($matrixPath)) {
    fwrite(STDERR, "--matrix=<json> is required\n");
    exit(1);
}

if (adobe_commerce_environment() !== 'stage' || !str_contains(adobe_commerce_api_host(), 'sandbox')) {
    fwrite(STDERR, "ABORT: not ACCS Stage\n");
    exit(1);
}

$addresses = require __DIR__ . '/avatax-test-addresses.php';

$matrix = json_decode((string) file_get_contents($matrixPath), true, 512, JSON_THROW_ON_ERROR);

$country = adobe_commerce_api_request('GET', '/directory/countries/US');
if (!$country['ok']) {
    fwrite(STDERR, 'Unable to load US regions: ' . $country['error'] . "\n");
    exit(1);
}
$regionsByCode = [];
foreach ($country['data']['available_regions'] ?? [] as $region) {
    $regionsByCode[strtoupper((string) $region['code'])] ??= $region;
}

$cart = adobe_commerce_api_request('POST', '/customers/' . $customerId . '/carts', null, []);
if (!$cart['ok']) {
    fwrite(STDERR, 'Unable to open cart: ' . $cart['error'] . "\n");
    exit(1);
}
$cartId = (string) $cart['data'];

/**
 * Ensure the cart holds exactly one of each requested SKU; returns item_id => sku and the re-added SKUs.
 *
 * @return array{0: array<int, string>, 1: list<string>}
 */
function sync_cart_lines(string $cartId, array $skus): array
{
    $current = adobe_commerce_api_request('GET', '/carts/' . $cartId);
    $itemSkus = [];
    foreach ($current['data']['items'] ?? [] as $item) {
        $itemSku = (string) ($item['sku'] ?? '');
        if (in_array($itemSku, $skus, true) && !in_array($itemSku, $itemSkus, true) && (float) $item['qty'] == 1.0) {
            $itemSkus[(int) $item['item_id']] = $itemSku;
        } else {
            adobe_commerce_api_request('DELETE', '/carts/' . $cartId . '/items/' . (int) $item['item_id']);
        }
    }
    $added = [];
    foreach (array_diff($skus, $itemSkus) as $missing) {
        $add = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/items', null, [
            'cartItem' => ['sku' => $missing, 'qty' => 1, 'quote_id' => $cartId],
        ]);
        if (!$add['ok']) {
            fwrite(STDERR, 'Unable to add ' . $missing . ': ' . $add['error'] . "\n");
            exit(1);
        }
        $itemSkus[(int) $add['data']['item_id']] = $missing;
        $added[] = $missing;
    }

    return [$itemSkus, $added];
}

[$itemSkus] = sync_cart_lines($cartId, $skus);

echo "Stage cart {$cartId} (customer {$customerId}), " . count($skus) . " SKU(s), tolerance ±{$tolerancePct}%\n\n";
printf("%-4s %-15s %9s %8s %8s %8s %8s  %s\n", 'ST', 'City', 'Subtotal', 'Tax', 'Actual%', 'Matrix%', 'Diff%', 'Result');

$results = [];
$lines = [];
foreach ($matrix as $row) {
    $name = $row['name'];
    if (!isset($addresses[$name])) {
        echo "SKIP {$name}: no address configured\n";
        continue;
    }
    [$code, $street, $city, $zip] = $addresses[$name];
    if ($only !== [] && !in_array($code, $only, true)) {
        continue;
    }
    $region = $regionsByCode[$code] ?? null;
    $address = [
        'firstname'   => 'Avatax',
        'lastname'    => 'Validation',
        'street'      => [$street],
        'city'        => $city,
        'region'      => (string) ($region['name'] ?? $code),
        'region_id'   => (int) ($region['id'] ?? 0),
        'region_code' => $code,
        'postcode'    => $zip,
        'country_id'  => 'US',
        'telephone'   => '5555550100',
        'email'       => 'avatax-validation@example.com',
    ];

    $result = [
        'state' => $code, 'jurisdiction' => $name, 'city' => $city, 'zip' => $zip,
        'matrix_taxable' => $row['taxable'], 'matrix_rate_pct' => round($row['rate'] * 100, 4),
        'subtotal' => null, 'item_tax' => null, 'shipping_tax' => null, 'actual_rate_pct' => null,
        'diff_pct' => null, 'result' => 'ERROR', 'shipping_methods' => 0, 'note' => '',
    ];

    [$itemSkus, $readded] = sync_cart_lines($cartId, $skus);
    $present = $readded === [];
    if (!$present) {
        $result['note'] = 'cart lines re-added: ' . implode(',', $readded) . '. ';
    }

    $methods = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/estimate-shipping-methods', null, [
        'address' => $address,
    ]);
    $method = null;
    foreach (($methods['ok'] ? $methods['data'] : []) as $candidate) {
        if (!empty($candidate['available'])) {
            $result['shipping_methods']++;
            $method ??= $candidate;
        }
    }

    $addressInformation = ['address' => $address];
    if ($method !== null) {
        $addressInformation['shipping_carrier_code'] = $method['carrier_code'];
        $addressInformation['shipping_method_code'] = $method['method_code'];
    } else {
        $result['note'] .= 'no shipping methods offered for this address. ';
    }

    $info = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/totals-information', null, [
        'addressInformation' => $addressInformation,
    ]);
    if (!$info['ok']) {
        $result['note'] .= 'totals-information failed: ' . $info['error'];
        $results[] = $result;
        printf("%-4s %-15s %s\n", $code, $city, $result['note']);
        continue;
    }

    $totals = $info['data'] ?? [];
    $base = 0.0;
    $itemTax = 0.0;
    $stateLines = [];
    foreach ($totals['items'] ?? [] as $line) {
        $lineBase = (float) ($line['row_total'] ?? 0) - (float) ($line['discount_amount'] ?? 0);
        $lineTax = (float) ($line['tax_amount'] ?? 0);
        $base += $lineBase;
        $itemTax += $lineTax;
        $stateLines[] = [
            'state' => $code,
            'sku' => $itemSkus[(int) ($line['item_id'] ?? 0)] ?? '',
            'name' => (string) ($line['name'] ?? ''),
            'taxable_base' => round($lineBase, 2),
            'tax' => round($lineTax, 2),
            'rate_pct' => $lineBase > 0 ? round($lineTax / $lineBase * 100, 4) : 0.0,
        ];
    }
    $expected = $result['matrix_rate_pct'];
    $actual = $base > 0 ? round($itemTax / $base * 100, 4) : 0.0;
    $outliers = [];
    foreach ($stateLines as $line) {
        $isOutlier = $expected == 0.0 ? $line['tax'] > 0 : abs($line['rate_pct'] - $actual) > 0.05;
        $lines[] = $line + ['outlier' => $isOutlier ? 'Y' : ''];
        if ($isOutlier) {
            $outliers[] = $line['sku'];
        }
    }
    if ($outliers !== []) {
        $result['note'] .= 'SKUs taxed differently: ' . implode(',', $outliers) . '. ';
    }

    $result['subtotal'] = round($base, 2);
    $result['item_tax'] = round($itemTax, 2);
    $result['shipping_tax'] = round((float) ($totals['shipping_tax_amount'] ?? 0), 2);
    $result['actual_rate_pct'] = $actual;

    if ($expected == 0.0) {
        $result['result'] = $itemTax == 0.0 ? 'PASS' : 'FAIL';
        $result['note'] .= $itemTax == 0.0 ? 'expected exempt / no tax' : 'expected $0 tax';
    } else {
        $diff = round(($actual - $expected) / $expected * 100, 2);
        $result['diff_pct'] = $diff;
        $result['result'] = abs($diff) <= $tolerancePct ? 'PASS' : 'FAIL';
    }

    $results[] = $result;
    printf(
        "%-4s %-15s %9.2f %8.2f %7.3f%% %7.3f%% %8s  %s%s%s%s\n",
        $code,
        $city,
        $result['subtotal'],
        $result['item_tax'],
        $actual,
        $expected,
        $result['diff_pct'] === null ? '-' : sprintf('%+.2f%%', $result['diff_pct']),
        $result['result'],
        $result['shipping_methods'] === 0 ? ' [NO SHIPPING]' : '',
        $present ? '' : ' [line re-added]',
        $outliers === [] ? '' : ' [OUTLIERS: ' . implode(',', $outliers) . ']'
    );
}

foreach (array_keys($itemSkus) as $itemId) {
    adobe_commerce_api_request('DELETE', '/carts/' . $cartId . '/items/' . $itemId);
}

$counts = array_count_values(array_column($results, 'result'));
echo "\nPASS=" . ($counts['PASS'] ?? 0) . ' FAIL=' . ($counts['FAIL'] ?? 0) . ' ERROR=' . ($counts['ERROR'] ?? 0) . "\n";

if ($csvPath !== '') {
    $fh = fopen($csvPath, 'w');
    fputcsv($fh, array_keys($results[0] ?? ['state' => '']), ',', '"', '');
    foreach ($results as $r) {
        fputcsv($fh, $r, ',', '"', '');
    }
    fclose($fh);
    echo "CSV: {$csvPath}\n";
}

if ($linesCsvPath !== '' && $lines !== []) {
    $fh = fopen($linesCsvPath, 'w');
    fputcsv($fh, array_keys($lines[0]), ',', '"', '');
    foreach ($lines as $line) {
        fputcsv($fh, $line, ',', '"', '');
    }
    fclose($fh);
    echo "Lines CSV: {$linesCsvPath}\n";
}
