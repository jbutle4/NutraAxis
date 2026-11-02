<?php
/**
 * Place AvaTax test orders on ACCS Stage: one order per US jurisdiction, all requested SKUs (qty 1),
 * shipped to the state capitol, paid by Check / Money Order. Optional coupon for the discount pass.
 *
 * Usage:
 *   php scripts/run-accs-avalara-order-test.php --matrix=/tmp/taxability-matrix.json \
 *       --sku=NA-MT-001,NA-GW-002 [--coupon=Avalara] [--customers=10] [--customer-first=1] [--group=1] [--only=TX,UT] \
 *       [--limit=1] [--invoice] [--quote-only] [--csv=/tmp/orders.csv] [--lines-csv=/tmp/lines.csv]
 *
 * Test customers are avatax-order-test-NN@example.com (created on first run).
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/data-profile.php';
data_profile_set('uat');
require dirname(__DIR__) . '/includes/adobe-commerce.php';

const PAYMENT_METHOD = 'checkmo';
const PREFERRED_CARRIER = 'flatrate';

$opts = getopt('', [
    'matrix:', 'sku:', 'coupon::', 'customers::', 'customer-first::', 'group::', 'only::', 'limit::',
    'invoice', 'quote-only', 'csv::', 'lines-csv::',
]);
$matrixPath = (string) ($opts['matrix'] ?? '');
$skus = array_values(array_filter(array_map('trim', explode(',', (string) ($opts['sku'] ?? '')))));
$coupon = trim((string) ($opts['coupon'] ?? ''));
$customerCount = max(1, (int) ($opts['customers'] ?? 10));
$customerFirst = max(1, (int) ($opts['customer-first'] ?? 1));
$groupId = (int) ($opts['group'] ?? 1);
$only = array_filter(array_map('trim', explode(',', strtoupper((string) ($opts['only'] ?? '')))));
$limit = (int) ($opts['limit'] ?? 0);
$invoice = isset($opts['invoice']);
$quoteOnly = isset($opts['quote-only']);
$csvPath = (string) ($opts['csv'] ?? '');
$linesCsvPath = (string) ($opts['lines-csv'] ?? '');

if ($matrixPath === '' || !is_file($matrixPath) || $skus === []) {
    fwrite(STDERR, "--matrix=<json> and --sku=<list> are required\n");
    exit(1);
}
if (adobe_commerce_environment() !== 'stage' || !str_contains(adobe_commerce_api_host(), 'sandbox')) {
    fwrite(STDERR, "ABORT: not ACCS Stage\n");
    exit(1);
}

$addresses = require __DIR__ . '/avatax-test-addresses.php';
$matrix = json_decode((string) file_get_contents($matrixPath), true, 512, JSON_THROW_ON_ERROR);

$country = adobe_commerce_api_request('GET', '/directory/countries/US');
$regionsByCode = [];
foreach ($country['data']['available_regions'] ?? [] as $region) {
    $regionsByCode[strtoupper((string) $region['code'])] ??= $region;
}

/** @return list<array{id: int, email: string, firstname: string, lastname: string}> */
function ensure_test_customers(int $first, int $count, int $groupId): array
{
    $customers = [];
    for ($i = $first; $i < $first + $count; $i++) {
        $email = sprintf('avatax-order-test-%02d@example.com', $i);
        $found = adobe_commerce_api_request('GET', '/customers/search', [
            'searchCriteria[filter_groups][0][filters][0][field]' => 'email',
            'searchCriteria[filter_groups][0][filters][0][value]' => $email,
        ]);
        $existing = $found['data']['items'][0] ?? null;
        if ($existing === null) {
            $created = adobe_commerce_api_request('POST', '/customers', null, ['customer' => [
                'email' => $email, 'firstname' => 'AvaTax', 'lastname' => sprintf('Test %02d', $i),
                'group_id' => $groupId, 'website_id' => 1, 'store_id' => 1,
            ]]);
            if (!$created['ok']) {
                fwrite(STDERR, "Unable to create {$email}: {$created['error']}\n");
                exit(1);
            }
            $existing = $created['data'];
        }
        $customers[] = [
            'id' => (int) $existing['id'], 'email' => $email,
            'firstname' => (string) $existing['firstname'], 'lastname' => (string) $existing['lastname'],
        ];
    }

    return $customers;
}

/** Empty the customer's active cart and load one of each SKU; returns [cartId, item_id => sku]. */
function prepare_cart(int $customerId, array $skus): array
{
    $cart = adobe_commerce_api_request('POST', '/customers/' . $customerId . '/carts', null, []);
    if (!$cart['ok']) {
        throw new RuntimeException('open cart: ' . $cart['error']);
    }
    $cartId = (string) $cart['data'];
    $current = adobe_commerce_api_request('GET', '/carts/' . $cartId);
    foreach ($current['data']['items'] ?? [] as $item) {
        adobe_commerce_api_request('DELETE', '/carts/' . $cartId . '/items/' . (int) $item['item_id']);
    }
    adobe_commerce_api_request('DELETE', '/carts/' . $cartId . '/coupons');
    $itemSkus = [];
    foreach ($skus as $sku) {
        $add = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/items', null, [
            'cartItem' => ['sku' => $sku, 'qty' => 1, 'quote_id' => $cartId],
        ]);
        if (!$add['ok']) {
            throw new RuntimeException("add {$sku}: " . $add['error']);
        }
        $itemSkus[(int) $add['data']['item_id']] = $sku;
    }

    return [$cartId, $itemSkus];
}

$customers = ensure_test_customers($customerFirst, $customerCount, $groupId);
$mode = $quoteOnly ? 'QUOTE ONLY' : ('PLACE ORDERS' . ($invoice ? ' + INVOICE' : ''));
echo "Stage AvaTax order test — {$mode}; " . count($skus) . ' SKUs; coupon=' . ($coupon ?: 'none')
    . '; customers=' . implode(',', array_column($customers, 'id')) . "\n\n";
printf("%-4s %-10s %9s %9s %9s %8s %8s  %-12s %s\n", 'ST', 'Customer', 'Subtotal', 'Discount', 'TaxBase', 'Tax', 'Rate%', 'Order', 'Note');

$results = [];
$lines = [];
$n = 0;
foreach ($matrix as $row) {
    $name = $row['name'];
    if (!isset($addresses[$name])) {
        continue;
    }
    [$code, $street, $city, $zip] = $addresses[$name];
    if ($only !== [] && !in_array($code, $only, true)) {
        continue;
    }
    if ($limit > 0 && $n >= $limit) {
        break;
    }
    $customer = $customers[$n % count($customers)];
    $n++;

    $region = $regionsByCode[$code] ?? null;
    $address = [
        'firstname' => $customer['firstname'], 'lastname' => $customer['lastname'],
        'street' => [$street], 'city' => $city,
        'region' => (string) ($region['name'] ?? $code), 'region_id' => (int) ($region['id'] ?? 0), 'region_code' => $code,
        'postcode' => $zip, 'country_id' => 'US', 'telephone' => '5555550100', 'email' => $customer['email'],
    ];
    $result = [
        'state' => $code, 'jurisdiction' => $name, 'city' => $city, 'zip' => $zip,
        'matrix_rate_pct' => round($row['rate'] * 100, 4), 'customer_id' => $customer['id'], 'customer_email' => $customer['email'],
        'coupon' => $coupon, 'shipping_method' => '', 'quote_subtotal' => null, 'quote_discount' => null, 'quote_tax' => null,
        'order_entity_id' => null, 'order_increment_id' => null, 'invoice_id' => null,
        'subtotal' => null, 'discount' => null, 'tax_base' => null, 'tax' => null, 'shipping' => null, 'shipping_tax' => null,
        'grand_total' => null, 'rate_on_base_pct' => null, 'rate_on_subtotal_pct' => null, 'status' => 'ERROR', 'note' => '',
    ];

    try {
        [$cartId, $itemSkus] = prepare_cart($customer['id'], $skus);

        if ($coupon !== '') {
            $applied = adobe_commerce_api_request('PUT', '/carts/' . $cartId . '/coupons/' . rawurlencode($coupon));
            if (!$applied['ok']) {
                throw new RuntimeException("coupon {$coupon}: " . $applied['error']);
            }
        }

        $methods = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/estimate-shipping-methods', null, ['address' => $address]);
        $method = null;
        foreach (($methods['ok'] ? $methods['data'] : []) as $candidate) {
            if (empty($candidate['available'])) {
                continue;
            }
            if ($method === null || ($candidate['carrier_code'] === PREFERRED_CARRIER && $method['carrier_code'] !== PREFERRED_CARRIER)) {
                $method = $candidate;
            }
        }
        if ($method === null) {
            throw new RuntimeException('no shipping methods offered for this address');
        }
        $result['shipping_method'] = $method['carrier_code'] . '_' . $method['method_code'];

        $ship = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/shipping-information', null, ['addressInformation' => [
            'shipping_address' => $address + ['same_as_billing' => 1],
            'billing_address' => $address,
            'shipping_carrier_code' => $method['carrier_code'],
            'shipping_method_code' => $method['method_code'],
        ]]);
        if (!$ship['ok']) {
            throw new RuntimeException('shipping-information: ' . $ship['error']);
        }
        $quoteTotals = $ship['data']['totals'] ?? [];
        $result['quote_subtotal'] = (float) ($quoteTotals['subtotal'] ?? 0);
        $result['quote_discount'] = (float) ($quoteTotals['discount_amount'] ?? 0);
        $result['quote_tax'] = (float) ($quoteTotals['tax_amount'] ?? 0);
        $offered = array_column($ship['data']['payment_methods'] ?? [], 'code');

        if ($quoteOnly) {
            $result['status'] = 'QUOTED';
            $result['note'] = 'payment methods: ' . implode(',', $offered);
            foreach ($itemSkus as $itemId => $sku) {
                adobe_commerce_api_request('DELETE', '/carts/' . $cartId . '/items/' . $itemId);
            }
        } else {
            if (!in_array(PAYMENT_METHOD, $offered, true)) {
                fwrite(STDERR, "ABORT: '" . PAYMENT_METHOD . "' is not offered on Stage (offered: " . implode(',', $offered) . ")\n");
                exit(1);
            }
            $billing = adobe_commerce_api_request('POST', '/carts/' . $cartId . '/billing-address', null, ['address' => $address]);
            if (!$billing['ok']) {
                throw new RuntimeException('billing-address: ' . $billing['error']);
            }
            $pay = adobe_commerce_api_request('PUT', '/carts/' . $cartId . '/selected-payment-method', null, ['method' => ['method' => PAYMENT_METHOD]]);
            if (!$pay['ok']) {
                throw new RuntimeException('payment method: ' . $pay['error']);
            }
            $placed = adobe_commerce_api_request('PUT', '/carts/' . $cartId . '/order', null, []);
            if (!$placed['ok']) {
                throw new RuntimeException('place order: ' . $placed['error']);
            }
            $orderId = (int) $placed['data'];
            $order = adobe_commerce_api_request('GET', '/orders/' . $orderId);
            if (!$order['ok']) {
                throw new RuntimeException("order {$orderId} placed but fetch failed: " . $order['error']);
            }
            $o = $order['data'];
            $result['order_entity_id'] = $orderId;
            $result['order_increment_id'] = (string) $o['increment_id'];
            $result['subtotal'] = (float) $o['subtotal'];
            $result['discount'] = (float) ($o['discount_amount'] ?? 0);
            $result['tax'] = (float) ($o['tax_amount'] ?? 0);
            $result['shipping'] = (float) ($o['shipping_amount'] ?? 0);
            $result['shipping_tax'] = (float) ($o['shipping_tax_amount'] ?? 0);
            $result['grand_total'] = (float) $o['grand_total'];

            $base = 0.0;
            $itemTax = 0.0;
            foreach ($o['items'] ?? [] as $item) {
                if (!empty($item['parent_item_id'])) {
                    continue;
                }
                $lineBase = (float) $item['price'] * (float) $item['qty_ordered'] - (float) ($item['discount_amount'] ?? 0);
                $base += $lineBase;
                $itemTax += (float) ($item['tax_amount'] ?? 0);
                $lines[] = [
                    'state' => $code, 'order_increment_id' => $result['order_increment_id'], 'coupon' => $coupon,
                    'sku' => (string) $item['sku'], 'name' => (string) $item['name'],
                    'price' => (float) $item['price'], 'row_total' => (float) $item['row_total'], 'discount' => (float) ($item['discount_amount'] ?? 0),
                    'tax_base' => round($lineBase, 2), 'tax' => (float) ($item['tax_amount'] ?? 0),
                    'tax_percent' => (float) ($item['tax_percent'] ?? 0),
                ];
            }
            $result['tax_base'] = round($base, 2);
            $result['rate_on_base_pct'] = $base > 0 ? round($itemTax / $base * 100, 4) : 0.0;
            $result['rate_on_subtotal_pct'] = $result['subtotal'] > 0 ? round($itemTax / $result['subtotal'] * 100, 4) : 0.0;
            $result['status'] = 'PLACED';

            if ($invoice) {
                $inv = adobe_commerce_api_request('POST', '/order/' . $orderId . '/invoice', null, ['capture' => false, 'notify' => false]);
                if ($inv['ok']) {
                    $result['invoice_id'] = (int) $inv['data'];
                    $result['status'] = 'INVOICED';
                } else {
                    $result['note'] .= 'invoice failed: ' . $inv['error'] . '. ';
                }
            }
        }
    } catch (RuntimeException $e) {
        $result['note'] .= $e->getMessage();
    }

    $results[] = $result;
    printf(
        "%-4s %-10s %9s %9s %9s %8s %8s  %-12s %s\n",
        $code,
        '#' . $customer['id'],
        number_format((float) ($result['subtotal'] ?? $result['quote_subtotal']), 2),
        number_format((float) ($result['discount'] ?? $result['quote_discount']), 2),
        $result['tax_base'] === null ? '-' : number_format($result['tax_base'], 2),
        number_format((float) ($result['tax'] ?? $result['quote_tax']), 2),
        $result['rate_on_base_pct'] === null ? '-' : sprintf('%.3f', $result['rate_on_base_pct']),
        $result['order_increment_id'] ?? $result['status'],
        trim($result['note'])
    );
}

$counts = array_count_values(array_column($results, 'status'));
echo "\n" . implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($counts), $counts)) . "\n";

foreach ([[$csvPath, $results], [$linesCsvPath, $lines]] as [$path, $rows]) {
    if ($path === '' || $rows === []) {
        continue;
    }
    $fh = fopen($path, 'w');
    fputcsv($fh, array_keys($rows[0]), ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($fh, $r, ',', '"', '');
    }
    fclose($fh);
    echo "CSV: {$path}\n";
}
