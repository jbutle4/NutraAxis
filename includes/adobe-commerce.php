<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/adobe-commerce-settings.php';
require_once __DIR__ . '/data-profile.php';

const ADOBE_COMMERCE_IMS_SCOPE = 'openid,AdobeID,commerce.accs,additional_info.roles,org.read,additional_info.projectedProductContext,profile,email';

const ADOBE_COMMERCE_ENVIRONMENTS = [
    'stage' => [
        'tenant'   => 'UAEyTrirS4qBMAWYZa4uic',
        'api_host' => 'na1-sandbox.api.commerce.adobe.com',
    ],
    'dev' => [
        'tenant'   => 'JZTG7BaEUkyB9oWTNxEzEq',
        'api_host' => 'na1-sandbox.api.commerce.adobe.com',
    ],
    'production' => [
        'tenant'   => 'VLuKe3eeTwf1D5oxmLBfcr',
        'api_host' => 'na1.api.commerce.adobe.com',
    ],
];

function adobe_commerce_environment(): string
{
    if (data_profile_is_uat()) {
        $uat = strtolower(trim((string) env_first([
            'ADOBE_COMMERCE_UAT_ENVIRONMENT',
            'ACCS_UAT_ENVIRONMENT',
            'ADOBE_COMMERCE_ENVIRONMENT',
            'ADOBE_ACCS_ENVIRONMENT',
            'ACCS_ENVIRONMENT',
        ], 'stage')));

        return array_key_exists($uat, ADOBE_COMMERCE_ENVIRONMENTS) ? $uat : 'stage';
    }

    $env = strtolower(trim((string) env_first([
        'ADOBE_COMMERCE_PRODUCTION_ENVIRONMENT',
        'ACCS_PRODUCTION_ENVIRONMENT',
    ], 'production')));

    return array_key_exists($env, ADOBE_COMMERCE_ENVIRONMENTS) ? $env : 'production';
}

function adobe_commerce_tenant_for_environment(string $env): string
{
    return trim((string) env_first([
        'ADOBE_COMMERCE_' . strtoupper($env),
        'ADOBE_ACCS_' . strtoupper($env),
    ], ''));
}

function adobe_commerce_tenant_id(): string
{
    $override = trim((string) env_first([
        'ADOBE_COMMERCE_TENANT_ID',
        'ADOBE_ACCS_TENANT_ID',
        'ACCS_TENANT_ID',
    ], ''));
    if ($override !== '') {
        return $override;
    }

    $env = adobe_commerce_environment();
    $tenantByEnv = adobe_commerce_tenant_for_environment($env);
    if ($tenantByEnv !== '') {
        return $tenantByEnv;
    }

    $config = ADOBE_COMMERCE_ENVIRONMENTS[$env];

    return (string) $config['tenant'];
}

function adobe_commerce_api_host(): string
{
    $override = trim((string) env('ADOBE_COMMERCE_API_HOST', ''));
    if ($override !== '') {
        return $override;
    }

    $config = ADOBE_COMMERCE_ENVIRONMENTS[adobe_commerce_environment()];

    return (string) $config['api_host'];
}

function adobe_commerce_ims_token_url(): string
{
    $url = trim((string) env('ADOBE_COMMERCE_IMS_TOKEN_URL', ''));

    return $url !== '' ? $url : 'https://ims-na1.adobelogin.com/ims/token/v3';
}

function adobe_commerce_base_url(): string
{
    return 'https://' . adobe_commerce_api_host() . '/' . adobe_commerce_tenant_id() . '/V1';
}

function adobe_commerce_client_id(): string
{
    return trim((string) env_first([
        'ADOBE_COMMERCE_CLIENT_ID',
        'ADOBE_ACCS_CLIENT_ID',
        'ACCS_CLIENT_ID',
    ], ''));
}

function adobe_commerce_client_secret(): string
{
    return (string) env_first([
        'ADOBE_COMMERCE_CLIENT_SECRET',
        'ADOBE_ACCS_CLIENT_SECRET',
        'ACCS_CLIENT_SECRET',
    ], '');
}

function adobe_commerce_is_configured(): bool
{
    return adobe_commerce_client_id() !== ''
        && adobe_commerce_client_secret() !== ''
        && adobe_commerce_tenant_id() !== '';
}

function adobe_commerce_config_error(): ?string
{
    if (adobe_commerce_is_configured()) {
        return null;
    }

    $missing = [];
    if (adobe_commerce_client_id() === '') {
        $missing[] = 'ADOBE_COMMERCE_CLIENT_ID';
    }
    if (adobe_commerce_client_secret() === '') {
        $missing[] = 'ADOBE_COMMERCE_CLIENT_SECRET';
    }

    $env = adobe_commerce_environment();
    if (adobe_commerce_tenant_id() === '') {
        $missing[] = 'ADOBE_COMMERCE_' . strtoupper($env) . ' (or ADOBE_COMMERCE_TENANT_ID)';
    }

    $location = env_is_azure_hosted()
        ? 'Azure App Service application settings'
        : 'your local .env file (copy the Adobe values from Azure App Settings for CLI scripts)';

    $message = 'Adobe Commerce is not configured in ' . $location . '.';
    if ($missing !== []) {
        $message .= ' Missing or empty: ' . implode(', ', $missing) . '.';
    }
    $profile = data_profile_is_uat() ? 'UAT (stage)' : 'production';
    $message .= ' Active data profile: ' . $profile . '.';

    return $message;
}

function adobe_commerce_get_token(): array
{
    static $cachedToken = null;
    static $cachedExpiresAt = 0;

    if (is_string($cachedToken) && $cachedExpiresAt > time()) {
        return ['ok' => true, 'error' => null, 'token' => $cachedToken];
    }

    $configError = adobe_commerce_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'token' => null];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Adobe Commerce.', 'token' => null];
    }

    $ch = curl_init(adobe_commerce_ims_token_url());
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => adobe_commerce_client_id(),
            'client_secret' => adobe_commerce_client_secret(),
            'scope'         => ADOBE_COMMERCE_IMS_SCOPE,
        ]),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach Adobe IMS token endpoint.', 'token' => null];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Adobe IMS returned an unexpected response.', 'token' => null];
    }

    if ($status >= 400) {
        $message = $data['error_description'] ?? $data['error'] ?? ('Adobe IMS token request failed (HTTP ' . $status . ').');

        return ['ok' => false, 'error' => is_string($message) ? $message : 'Adobe IMS token request failed.', 'token' => null];
    }

    $token = (string) ($data['access_token'] ?? '');
    if ($token === '') {
        return ['ok' => false, 'error' => 'Adobe IMS did not return an access token.', 'token' => null];
    }

    $expiresIn = (int) ($data['expires_in'] ?? 3600);
    $cachedToken = $token;
    $cachedExpiresAt = time() + max(60, $expiresIn - 60);

    return ['ok' => true, 'error' => null, 'token' => $token];
}

function adobe_commerce_api_request(string $method, string $path, ?array $query = null): array
{
    $tokenResult = adobe_commerce_get_token();
    if (!$tokenResult['ok']) {
        return ['ok' => false, 'error' => $tokenResult['error'], 'data' => null, 'status' => 0];
    }

    $path = '/' . ltrim($path, '/');
    $url = adobe_commerce_base_url() . $path;
    if ($query !== null && $query !== []) {
        $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Adobe Commerce.', 'data' => null, 'status' => 0];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $tokenResult['token'],
            'x-api-key: ' . adobe_commerce_client_id(),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach Adobe Commerce.', 'data' => null, 'status' => $status];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Adobe Commerce returned an unexpected response.', 'data' => null, 'status' => $status];
    }

    if ($status >= 400) {
        $message = $data['message'] ?? $data['error'] ?? ('Adobe Commerce request failed (HTTP ' . $status . ').');

        return ['ok' => false, 'error' => is_string($message) ? $message : 'Adobe Commerce request failed.', 'data' => $data, 'status' => $status];
    }

    return ['ok' => true, 'error' => null, 'data' => $data, 'status' => $status];
}

function adobe_commerce_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}

function adobe_commerce_page_size(): int
{
    $size = (int) env('ADOBE_COMMERCE_PAGE_SIZE', '10');

    return max(1, min(100, $size > 0 ? $size : 10));
}

function adobe_commerce_order_item_qty(array $order): int
{
    $qty = 0;
    foreach ($order['items'] ?? [] as $item) {
        if (is_array($item)) {
            $qty += (int) ($item['qty_ordered'] ?? 0);
        }
    }

    return $qty;
}

function adobe_commerce_orders_page_size(): int
{
    $size = (int) env('ADOBE_COMMERCE_ORDERS_PAGE_SIZE', '100');

    return max(1, min(100, $size > 0 ? $size : 100));
}

function adobe_commerce_fetch_paginated_orders(array $baseQuery = [], int $maxPages = 200): array
{
    $pageSize = adobe_commerce_orders_page_size();
    $currentPage = 1;
    $rows = [];
    $total = 0;

    while ($currentPage <= $maxPages) {
        $query = array_merge($baseQuery, [
            'searchCriteria[pageSize]'    => $pageSize,
            'searchCriteria[currentPage]' => $currentPage,
        ]);

        $result = adobe_commerce_api_request('GET', '/orders', $query);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'rows' => $rows, 'total' => $total];
        }

        $data = $result['data'] ?? [];
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            break;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            }
        }

        $total = (int) ($data['total_count'] ?? count($rows));
        if ($items === [] || count($items) < $pageSize) {
            break;
        }

        $currentPage++;
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows, 'total' => $total];
}

function adobe_commerce_list_orders(?int $pageSize = null, int $currentPage = 1): array
{
    $pageSize = $pageSize ?? adobe_commerce_page_size();
    $result = adobe_commerce_api_request('GET', '/orders', [
        'searchCriteria[pageSize]'                 => max(1, min(100, $pageSize)),
        'searchCriteria[currentPage]'                => max(1, $currentPage),
        'searchCriteria[sortOrders][0][field]'       => 'created_at',
        'searchCriteria[sortOrders][0][direction]'   => 'DESC',
    ]);

    if (!$result['ok']) {
        return $result;
    }

    $data = $result['data'] ?? [];

    return [
        'ok'    => true,
        'error' => null,
        'rows'  => is_array($data['items'] ?? null) ? $data['items'] : [],
        'total' => (int) ($data['total_count'] ?? 0),
    ];
}

function adobe_commerce_list_orders_for_date_range(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $from = $start->format('Y-m-d H:i:s');
    $to = $end->format('Y-m-d H:i:s');

    return adobe_commerce_fetch_paginated_orders([
        'searchCriteria[filter_groups][0][filters][0][field]'          => 'created_at',
        'searchCriteria[filter_groups][0][filters][0][value]'          => $from,
        'searchCriteria[filter_groups][0][filters][0][condition_type]' => 'gte',
        'searchCriteria[filter_groups][1][filters][0][field]'          => 'created_at',
        'searchCriteria[filter_groups][1][filters][0][value]'          => $to,
        'searchCriteria[filter_groups][1][filters][0][condition_type]' => 'lte',
        'searchCriteria[filter_groups][2][filters][0][field]'          => 'status',
        'searchCriteria[filter_groups][2][filters][0][value]'          => 'canceled',
        'searchCriteria[filter_groups][2][filters][0][condition_type]' => 'neq',
        'searchCriteria[sortOrders][0][field]'                         => 'created_at',
        'searchCriteria[sortOrders][0][direction]'                   => 'ASC',
    ]);
}

function adobe_commerce_fetch_order_by_number(string $orderNumber): array
{
    $orderNumber = trim($orderNumber);
    if ($orderNumber === '') {
        return ['ok' => false, 'error' => 'Enter an order number.', 'order' => null];
    }

    $result = adobe_commerce_api_request('GET', '/orders', [
        'searchCriteria[filter_groups][0][filters][0][field]'          => 'increment_id',
        'searchCriteria[filter_groups][0][filters][0][value]'          => $orderNumber,
        'searchCriteria[filter_groups][0][filters][0][condition_type]' => 'eq',
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'order' => null];
    }

    $items = $result['data']['items'] ?? [];
    if (!is_array($items) || $items === []) {
        return ['ok' => false, 'error' => 'Order ' . $orderNumber . ' not found.', 'order' => null];
    }

    return ['ok' => true, 'error' => null, 'order' => $items[0]];
}

function adobe_commerce_order_shipping_address(array $order): ?array
{
    $assignments = $order['extension_attributes']['shipping_assignments'] ?? [];
    if (!is_array($assignments) || $assignments === []) {
        return null;
    }

    $address = $assignments[0]['shipping']['address'] ?? null;

    return is_array($address) ? $address : null;
}

function adobe_commerce_inventory_page_size(): int
{
    $size = (int) env('ADOBE_COMMERCE_INVENTORY_PAGE_SIZE', '100');

    return max(1, min(500, $size > 0 ? $size : 100));
}

function adobe_commerce_format_quantity($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float) $value, 0);
}

function adobe_commerce_source_item_status_label($status): string
{
    return (int) $status === 1 ? 'In stock' : 'Out of stock';
}

function adobe_commerce_source_item_status_class($status): string
{
    return (int) $status === 1 ? 'status-received' : 'status-cancelled';
}

function adobe_commerce_fetch_paginated_items(string $path, array $baseQuery = [], int $maxPages = 200): array
{
    $pageSize = adobe_commerce_inventory_page_size();
    $currentPage = 1;
    $rows = [];
    $total = 0;

    while ($currentPage <= $maxPages) {
        $query = array_merge($baseQuery, [
            'searchCriteria[pageSize]'               => $pageSize,
            'searchCriteria[currentPage]'            => $currentPage,
            'searchCriteria[sortOrders][0][field]'   => 'sku',
            'searchCriteria[sortOrders][0][direction]' => 'ASC',
        ]);

        $result = adobe_commerce_api_request('GET', $path, $query);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'rows' => $rows, 'total' => $total];
        }

        $data = $result['data'] ?? [];
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            break;
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            }
        }

        $total = (int) ($data['total_count'] ?? count($rows));
        if ($items === [] || count($items) < $pageSize) {
            break;
        }

        $currentPage++;
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows, 'total' => $total];
}

function adobe_commerce_list_source_items(): array
{
    return adobe_commerce_fetch_paginated_items('/inventory/source-items');
}

function adobe_commerce_list_stock_items(): array
{
    $result = adobe_commerce_fetch_paginated_items('/stockItems');
    if (!$result['ok']) {
        return $result;
    }

    $rows = [];
    foreach ($result['rows'] as $item) {
        $rows[] = [
            'sku'         => (string) ($item['sku'] ?? $item['name'] ?? ''),
            'source_code' => 'default',
            'quantity'    => $item['qty'] ?? $item['quantity'] ?? null,
            'status'      => !empty($item['is_in_stock']) ? 1 : 0,
        ];
    }

    return [
        'ok'    => true,
        'error' => null,
        'rows'  => $rows,
        'total' => $result['total'],
    ];
}

function adobe_commerce_list_inventory(): array
{
    $configError = adobe_commerce_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'rows' => [], 'total' => 0];
    }

    $result = adobe_commerce_list_source_items();
    if ($result['ok'] && ($result['rows'] ?? []) !== []) {
        return $result;
    }

    if ($result['ok'] && ($result['rows'] ?? []) === []) {
        return $result;
    }

    $fallback = adobe_commerce_list_stock_items();
    if ($fallback['ok']) {
        return $fallback;
    }

    return [
        'ok'    => false,
        'error' => $result['error'] ?? $fallback['error'] ?? 'Unable to load ACCS inventory.',
        'rows'  => [],
        'total' => 0,
    ];
}

function adobe_commerce_order_shipping_lines(array $order): string
{
    $address = adobe_commerce_order_shipping_address($order);
    if ($address === null) {
        return '—';
    }

    $street = $address['street'] ?? [];
    if (!is_array($street)) {
        $street = [$street];
    }

    $lines = array_filter([
        trim((string) ($address['firstname'] ?? '') . ' ' . (string) ($address['lastname'] ?? '')),
        trim(implode(' ', array_map('strval', $street))),
        trim(
            (string) ($address['city'] ?? '') . ', '
            . (string) ($address['region'] ?? '') . ' '
            . (string) ($address['postcode'] ?? '')
        ),
        (string) ($address['country_id'] ?? ''),
    ]);

    return $lines !== [] ? implode("\n", $lines) : '—';
}
