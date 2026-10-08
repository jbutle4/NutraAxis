<?php

require_once __DIR__ . '/provider-signup-accs.php';
require_once __DIR__ . '/provider-signup-accs-config.php';
require_once __DIR__ . '/accs-support-case.php';

const ACCS_SUPPORT_CART_PROD_ENV = 'production';
const ACCS_SUPPORT_CART_STAGE_ENV = 'stage';

/**
 * @return array{ok: bool, error: ?string, order: ?array<string, mixed>}
 */
function accs_support_cart_load_prod_order(string $orderRef): array
{
    $orderRef = trim($orderRef);
    if ($orderRef === '') {
        return ['ok' => false, 'error' => 'Enter a Production order number (increment ID) or entity ID.', 'order' => null];
    }

    return provider_signup_accs_with_environment(ACCS_SUPPORT_CART_PROD_ENV, static function () use ($orderRef): array {
        if (ctype_digit($orderRef)) {
            $byId = provider_signup_accs_api_request('GET', '/orders/' . (int) $orderRef);
            if ($byId['ok'] && is_array($byId['data'] ?? null) && !empty($byId['data']['entity_id'])) {
                return ['ok' => true, 'error' => null, 'order' => $byId['data']];
            }
        }

        $result = provider_signup_accs_api_request('GET', '/orders', [
            'searchCriteria[filter_groups][0][filters][0][field]'          => 'increment_id',
            'searchCriteria[filter_groups][0][filters][0][value]'          => $orderRef,
            'searchCriteria[filter_groups][0][filters][0][condition_type]' => 'eq',
            'searchCriteria[pageSize]'                                     => '1',
            'searchCriteria[currentPage]'                                  => '1',
        ]);
        if (!$result['ok']) {
            return [
                'ok'    => false,
                'error' => provider_signup_accs_format_api_error($result),
                'order' => null,
            ];
        }

        $items = $result['data']['items'] ?? [];
        if (!is_array($items) || $items === [] || !is_array($items[0] ?? null)) {
            return [
                'ok'    => false,
                'error' => 'Production order "' . $orderRef . '" was not found.',
                'order' => null,
            ];
        }

        return ['ok' => true, 'error' => null, 'order' => $items[0]];
    });
}

/**
 * Extract buyable line items from a Magento order (simple/children; skip configurable parents).
 *
 * @param array<string, mixed> $order
 * @return list<array{sku: string, name: string, qty: float, product_type: string, item_id: int}>
 */
function accs_support_cart_order_lines(array $order): array
{
    $rawItems = is_array($order['items'] ?? null) ? $order['items'] : [];
    $childrenByParent = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $parentId = (int) ($item['parent_item_id'] ?? 0);
        if ($parentId > 0) {
            $childrenByParent[$parentId][] = $item;
        }
    }

    $lines = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = (int) ($item['item_id'] ?? 0);
        $parentId = (int) ($item['parent_item_id'] ?? 0);
        if ($parentId > 0) {
            continue;
        }

        $type = strtolower(trim((string) ($item['product_type'] ?? '')));
        if (in_array($type, ['configurable', 'bundle', 'grouped'], true) && !empty($childrenByParent[$itemId])) {
            foreach ($childrenByParent[$itemId] as $child) {
                $sku = trim((string) ($child['sku'] ?? ''));
                $qty = (float) ($child['qty_ordered'] ?? 0);
                if ($sku === '' || $qty <= 0) {
                    continue;
                }
                $lines[] = [
                    'sku'          => $sku,
                    'name'         => trim((string) ($child['name'] ?? $item['name'] ?? $sku)),
                    'qty'          => $qty,
                    'product_type' => strtolower(trim((string) ($child['product_type'] ?? 'simple'))),
                    'item_id'      => (int) ($child['item_id'] ?? 0),
                ];
            }
            continue;
        }

        $sku = trim((string) ($item['sku'] ?? ''));
        $qty = (float) ($item['qty_ordered'] ?? 0);
        if ($sku === '' || $qty <= 0) {
            continue;
        }
        if (in_array($type, ['configurable', 'bundle', 'grouped'], true)) {
            continue;
        }

        $lines[] = [
            'sku'          => $sku,
            'name'         => trim((string) ($item['name'] ?? $sku)),
            'qty'          => $qty,
            'product_type' => $type !== '' ? $type : 'simple',
            'item_id'      => $itemId,
        ];
    }

    return $lines;
}

/**
 * @param array<string, mixed> $order
 * @return array<string, mixed>
 */
function accs_support_cart_normalize_address_from_order(array $order): array
{
    $shipping = null;
    $assignments = $order['extension_attributes']['shipping_assignments'] ?? [];
    if (is_array($assignments) && $assignments !== []) {
        $candidate = $assignments[0]['shipping']['address'] ?? null;
        if (is_array($candidate)) {
            $shipping = $candidate;
        }
    }
    $billing = is_array($order['billing_address'] ?? null) ? $order['billing_address'] : null;
    $base = $shipping ?? $billing ?? [];

    $street = $base['street'] ?? [''];
    if (!is_array($street)) {
        $street = [trim((string) $street)];
    }

    return [
        'firstname'   => trim((string) ($base['firstname'] ?? $order['customer_firstname'] ?? 'Support')),
        'lastname'    => trim((string) ($base['lastname'] ?? $order['customer_lastname'] ?? 'Clone')),
        'street'      => $street !== [] ? array_values($street) : [''],
        'city'        => trim((string) ($base['city'] ?? '')),
        'region'      => trim((string) ($base['region'] ?? '')),
        'region_code' => trim((string) ($base['region_code'] ?? '')),
        'region_id'   => (int) ($base['region_id'] ?? 0) ?: null,
        'postcode'    => trim((string) ($base['postcode'] ?? '')),
        'country_id'  => trim((string) ($base['country_id'] ?? 'US')) ?: 'US',
        'telephone'   => trim((string) ($base['telephone'] ?? '')),
        'email'       => strtolower(trim((string) ($base['email'] ?? $order['customer_email'] ?? ''))),
    ];
}

/**
 * Preview Prod order against Stage catalog for a cloned support case.
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   case: ?array<string, mixed>,
 *   order: ?array<string, mixed>,
 *   lines: list<array<string, mixed>>,
 *   available: list<array<string, mixed>>,
 *   missing: list<array<string, mixed>>,
 *   address: ?array<string, mixed>
 * }
 */
function accs_support_cart_preview(int $caseId, string $orderRef): array
{
    $case = accs_support_case_get($caseId);
    if ($case === null) {
        return [
            'ok'        => false,
            'error'     => 'Support case not found.',
            'case'      => null,
            'order'     => null,
            'lines'     => [],
            'available' => [],
            'missing'   => [],
            'address'   => null,
        ];
    }

    $stageCustomerId = (int) ($case['StageCustomerId'] ?? 0);
    $stageCompanyId = (int) ($case['StageCompanyId'] ?? 0);
    if ($stageCustomerId <= 0 || $stageCompanyId <= 0) {
        return [
            'ok'        => false,
            'error'     => 'This case has no Stage company/customer. Clone the Production account first.',
            'case'      => $case,
            'order'     => null,
            'lines'     => [],
            'available' => [],
            'missing'   => [],
            'address'   => null,
        ];
    }
    if (in_array((string) ($case['Status'] ?? ''), ['purged', 'purge_started'], true)) {
        return [
            'ok'        => false,
            'error'     => 'This case Stage account has been purged.',
            'case'      => $case,
            'order'     => null,
            'lines'     => [],
            'available' => [],
            'missing'   => [],
            'address'   => null,
        ];
    }

    $loaded = accs_support_cart_load_prod_order($orderRef);
    if (!$loaded['ok'] || !is_array($loaded['order'] ?? null)) {
        return [
            'ok'        => false,
            'error'     => $loaded['error'] ?? 'Unable to load Production order.',
            'case'      => $case,
            'order'     => null,
            'lines'     => [],
            'available' => [],
            'missing'   => [],
            'address'   => null,
        ];
    }

    $order = $loaded['order'];
    $lines = accs_support_cart_order_lines($order);
    if ($lines === []) {
        return [
            'ok'        => false,
            'error'     => 'Production order has no addable line items.',
            'case'      => $case,
            'order'     => $order,
            'lines'     => [],
            'available' => [],
            'missing'   => [],
            'address'   => accs_support_cart_normalize_address_from_order($order),
        ];
    }

    $skus = array_values(array_unique(array_map(static fn (array $line): string => $line['sku'], $lines)));

    return provider_signup_accs_with_environment(ACCS_SUPPORT_CART_STAGE_ENV, static function () use (
        $case,
        $order,
        $lines,
        $skus
    ): array {
        $loadedProducts = provider_signup_accs_config_load_products_by_sku($skus);
        $stageSkuSet = [];
        if ($loadedProducts['ok']) {
            foreach (array_keys($loadedProducts['products'] ?? []) as $sku) {
                $stageSkuSet[(string) $sku] = true;
            }
        }

        $available = [];
        $missing = [];
        foreach ($lines as $line) {
            if (isset($stageSkuSet[$line['sku']])) {
                $available[] = $line;
            } else {
                $missing[] = $line;
            }
        }

        return [
            'ok'        => true,
            'error'     => null,
            'case'      => $case,
            'order'     => [
                'entity_id'      => (int) ($order['entity_id'] ?? 0),
                'increment_id'   => trim((string) ($order['increment_id'] ?? '')),
                'status'         => trim((string) ($order['status'] ?? '')),
                'customer_email' => strtolower(trim((string) ($order['customer_email'] ?? ''))),
                'grand_total'    => (float) ($order['grand_total'] ?? 0),
                'created_at'     => trim((string) ($order['created_at'] ?? '')),
            ],
            'lines'     => $lines,
            'available' => $available,
            'missing'   => $missing,
            'address'   => accs_support_cart_normalize_address_from_order($order),
        ];
    });
}

/**
 * Build an open Stage cart from a Production order. Does not place the order.
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   cart_id: ?string,
 *   summary: array<string, mixed>
 * }
 */
function accs_support_cart_run(int $caseId, string $orderRef, bool $setAddresses = true): array
{
    $preview = accs_support_cart_preview($caseId, $orderRef);
    if (!($preview['ok'] ?? false)) {
        return [
            'ok'      => false,
            'error'   => $preview['error'] ?? 'Cart preview failed.',
            'cart_id' => null,
            'summary' => [],
        ];
    }

    $available = $preview['available'] ?? [];
    if ($available === []) {
        return [
            'ok'      => false,
            'error'   => 'None of the Production order SKUs exist on Stage. Mirror the catalog or pick another order.',
            'cart_id' => null,
            'summary' => [
                'missing' => $preview['missing'] ?? [],
            ],
        ];
    }

    $case = $preview['case'];
    $orderMeta = $preview['order'];
    $stageCustomerId = (int) ($case['StageCustomerId'] ?? 0);
    $prodOrderId = trim((string) ($orderMeta['increment_id'] ?? $orderRef));

    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }

    accs_support_case_update($caseId, [
        'prod_order_id' => $prodOrderId,
    ]);
    accs_support_case_add_event($caseId, 'cart_started', 'Stage open cart build started from Production order ' . $prodOrderId . '.', [
        'prod_order_id' => $prodOrderId,
        'entity_id'     => (int) ($orderMeta['entity_id'] ?? 0),
        'line_count'    => count($available),
    ]);

    return provider_signup_accs_with_environment(ACCS_SUPPORT_CART_STAGE_ENV, static function () use (
        $caseId,
        $stageCustomerId,
        $available,
        $preview,
        $prodOrderId,
        $orderMeta,
        $setAddresses
    ): array {
        $configError = adobe_commerce_config_error();
        if ($configError !== null) {
            accs_support_case_add_event($caseId, 'cart_failed', $configError);
            return ['ok' => false, 'error' => $configError, 'cart_id' => null, 'summary' => []];
        }

        // Magento accepts POST with no body or empty JSON object for cart create.
        $cartCreate = provider_signup_accs_api_request(
            'POST',
            '/customers/' . $stageCustomerId . '/carts'
        );
        if (!$cartCreate['ok']) {
            $cartCreate = provider_signup_accs_api_request(
                'POST',
                '/customers/' . $stageCustomerId . '/carts',
                null,
                []
            );
        }
        if (!$cartCreate['ok']) {
            $error = provider_signup_accs_format_api_error($cartCreate);
            accs_support_case_add_event($caseId, 'cart_failed', $error);
            return ['ok' => false, 'error' => $error, 'cart_id' => null, 'summary' => []];
        }

        $cartId = trim((string) ($cartCreate['data'] ?? ''));
        if ($cartId === '' && is_array($cartCreate['data'] ?? null)) {
            $cartId = trim((string) ($cartCreate['data']['id'] ?? $cartCreate['data']['quote_id'] ?? ''));
        }
        if ($cartId === '') {
            $error = 'ACCS Stage did not return a cart ID.';
            accs_support_case_add_event($caseId, 'cart_failed', $error);
            return ['ok' => false, 'error' => $error, 'cart_id' => null, 'summary' => []];
        }

        accs_support_case_add_resource(
            $caseId,
            'created',
            'cart',
            $cartId,
            'Stage cart from Prod order ' . $prodOrderId,
            (string) ($orderMeta['entity_id'] ?? $prodOrderId),
            ['increment_id' => $prodOrderId]
        );

        $added = [];
        $addErrors = [];
        foreach ($available as $line) {
            $sku = (string) $line['sku'];
            $qty = (float) $line['qty'];
            $add = provider_signup_accs_api_request('POST', '/carts/' . rawurlencode($cartId) . '/items', null, [
                'cartItem' => [
                    'sku'      => $sku,
                    'qty'      => $qty,
                    'quote_id' => $cartId,
                ],
            ]);
            if ($add['ok']) {
                $added[] = ['sku' => $sku, 'qty' => $qty];
            } else {
                $addErrors[] = [
                    'sku'   => $sku,
                    'qty'   => $qty,
                    'error' => provider_signup_accs_format_api_error($add),
                ];
            }
        }

        if ($added === []) {
            $error = 'Unable to add any lines to the Stage cart.'
                . ($addErrors !== [] ? ' ' . ($addErrors[0]['error'] ?? '') : '');
            accs_support_case_add_event($caseId, 'cart_failed', $error, [
                'cart_id'    => $cartId,
                'add_errors' => $addErrors,
            ]);
            return [
                'ok'      => false,
                'error'   => $error,
                'cart_id' => $cartId,
                'summary' => ['add_errors' => $addErrors],
            ];
        }

        $addressNote = null;
        if ($setAddresses && is_array($preview['address'] ?? null)) {
            $addr = $preview['address'];
            $bill = provider_signup_accs_api_request(
                'POST',
                '/carts/' . rawurlencode($cartId) . '/billing-address',
                null,
                ['address' => $addr]
            );
            if ($bill['ok']) {
                $addressNote = 'billing address set from Production order';
            } else {
                $addressNote = 'billing address not set: ' . provider_signup_accs_format_api_error($bill);
            }
        }

        $summary = [
            'prod_order_id'     => $prodOrderId,
            'prod_entity_id'    => (int) ($orderMeta['entity_id'] ?? 0),
            'stage_customer_id' => $stageCustomerId,
            'cart_id'           => $cartId,
            'lines_added'       => $added,
            'lines_add_errors'  => $addErrors,
            'lines_missing_sku' => $preview['missing'] ?? [],
            'address_note'      => $addressNote,
            'storefront_url'    => provider_signup_accs_storefront_url(ACCS_SUPPORT_CART_STAGE_ENV),
            'placed'            => false,
        ];

        accs_support_case_update($caseId, [
            'status'        => 'cart_ready',
            'stage_cart_id' => $cartId,
            'prod_order_id' => $prodOrderId,
        ]);

        // Persist cart summary alongside existing clone summary when present.
        $caseRow = accs_support_case_get($caseId);
        $existingSummary = [];
        if (!empty($caseRow['CloneSummaryJson'])) {
            $decoded = json_decode((string) $caseRow['CloneSummaryJson'], true);
            if (is_array($decoded)) {
                $existingSummary = $decoded;
            }
        }
        $existingSummary['cart'] = $summary;
        $merged = json_encode($existingSummary, JSON_UNESCAPED_SLASHES);
        if ($merged !== false) {
            accs_support_case_update($caseId, ['clone_summary_json' => $merged]);
        }

        accs_support_case_add_event($caseId, 'cart_built', 'Open Stage cart created. Order was not placed.', $summary);

        $missingCount = count($preview['missing'] ?? []);
        $errorCount = count($addErrors);
        $comment = "Open Stage cart #{$cartId} built from Production order {$prodOrderId}.\n"
            . 'Lines added: ' . count($added)
            . ($missingCount > 0 ? "; SKUs missing on Stage: {$missingCount}" : '')
            . ($errorCount > 0 ? "; add failures: {$errorCount}" : '')
            . "\nStorefront: " . ($summary['storefront_url'] ?? '')
            . "\nLog in as the Stage admin and complete checkout to observe the issue. Do not place the order from this portal.";
        accs_support_case_add_comment($caseId, $comment);

        return [
            'ok'      => true,
            'error'   => null,
            'cart_id' => $cartId,
            'summary' => $summary,
        ];
    });
}
