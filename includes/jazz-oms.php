<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/data-profile.php';

function jazz_oms_normalize_domain(string $domain): string
{
    $domain = trim($domain);
    if ($domain === '') {
        return '';
    }

    $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
    $domain = rtrim($domain, '/');
    $domain = preg_replace('#\.jazz-oms\.com$#i', '', $domain) ?? $domain;
    $domain = explode('/', $domain, 2)[0];

    return trim($domain);
}

function jazz_oms_domain(): string
{
    if (data_profile_is_uat()) {
        $domain = trim((string) env_first(['JAZZ_UAT_DOMAIN', 'JAZZ_DOMAIN'], ''));
    } else {
        $domain = trim((string) env_first(['JAZZ_DOMAIN_PROD', 'JAZZ_PRODUCTION_DOMAIN'], ''));
    }

    return jazz_oms_normalize_domain($domain);
}

function jazz_oms_username(): string
{
    if (data_profile_is_uat()) {
        return trim((string) env_first(['JAZZ_UAT_USERNAME', 'JAZZ_USERNAME'], ''));
    }

    return trim((string) env_first(['JAZZ_USERNAME_PROD', 'JAZZ_PRODUCTION_USERNAME'], ''));
}

function jazz_oms_password(): string
{
    if (data_profile_is_uat()) {
        return (string) env_first(['JAZZ_UAT_PASSWORD', 'JAZZ_PASSWORD'], '');
    }

    return (string) env_first(['JAZZ_PASSWORD_PROD', 'JAZZ_PRODUCTION_PASSWORD'], '');
}

function jazz_oms_tenant_code(): string
{
    return trim((string) env('JAZZ_TENANT_CODE', ''));
}

function jazz_oms_page_size(): int
{
    $size = (int) env('JAZZ_PAGE_SIZE', '100');

    return max(1, min(500, $size > 0 ? $size : 100));
}

function jazz_oms_base_url(): string
{
    if (data_profile_is_uat()) {
        $override = rtrim(trim((string) env_first(['JAZZ_UAT_BASE_URL', 'JAZZ_BASE_URL'], '')), '/');
    } else {
        $override = rtrim(trim((string) env_first(['JAZZ_BASE_URL_PROD', 'JAZZ_PRODUCTION_BASE_URL'], '')), '/');
    }

    if ($override !== '') {
        return $override;
    }

    $domain = jazz_oms_domain();

    return $domain !== '' ? 'https://' . $domain . '.jazz-oms.com' : '';
}

function jazz_oms_is_configured(): bool
{
    return jazz_oms_base_url() !== ''
        && jazz_oms_username() !== ''
        && jazz_oms_password() !== ''
        && jazz_oms_tenant_code() !== '';
}

function jazz_oms_config_error(): ?string
{
    if (jazz_oms_is_configured()) {
        return null;
    }

    return data_profile_is_uat()
        ? 'Jazz OMS is not configured. Set JAZZ_UAT_DOMAIN (or JAZZ_DOMAIN), JAZZ_UAT_USERNAME (or JAZZ_USERNAME), JAZZ_UAT_PASSWORD (or JAZZ_PASSWORD), and JAZZ_TENANT_CODE in Azure application settings.'
        : 'Jazz OMS is not configured. Set JAZZ_DOMAIN_PROD, JAZZ_USERNAME_PROD, JAZZ_PASSWORD_PROD, and JAZZ_TENANT_CODE in Azure application settings.';
}

function jazz_oms_data_source_label(): string
{
    return data_profile_is_uat() ? 'UAT' : 'Production';
}

function jazz_oms_is_cloudflare_block(?string $responseBody): bool
{
    if ($responseBody === null || $responseBody === '') {
        return false;
    }

    return str_contains($responseBody, 'Just a moment')
        || str_contains($responseBody, 'cf-browser-verification')
        || str_contains($responseBody, 'challenge-platform')
        || str_contains($responseBody, '/cdn-cgi/');
}

function jazz_oms_cloudflare_error_message(): string
{
    return 'Jazz OMS is blocking this server with Cloudflare bot protection (HTTP 403). '
        . 'Your JAZZ_* settings are reaching the right host, but Azure App Service outbound IPs must be allowlisted in Cloudflare for API access. '
        . 'In Azure Portal open App Service → Properties and send Jazz IT the Outbound IP addresses and Additional outbound IP addresses for nutraaxisweb. '
        . 'Ask them to allow those IPs (or bypass bot checks on /api/*). Python works locally because Cloudflare treats your machine differently than datacenter traffic.';
}

function jazz_oms_token_error(string $message, string $url, int $status = 0, ?string $responseBody = null): array
{
    if (jazz_oms_is_cloudflare_block($responseBody)) {
        return ['ok' => false, 'error' => jazz_oms_cloudflare_error_message(), 'token' => null];
    }

    $detail = $message;
    if ($status > 0) {
        $detail .= ' (HTTP ' . $status . ')';
    }
    $detail .= ' at ' . $url . '.';

    if ($responseBody !== null && $responseBody !== '') {
        $preview = trim(preg_replace('/\s+/', ' ', $responseBody) ?? $responseBody);
        if (strlen($preview) > 160) {
            $preview = substr($preview, 0, 160) . '…';
        }
        if ($preview !== '') {
            $detail .= ' Response: ' . $preview;
        }
    }

    return ['ok' => false, 'error' => $detail, 'token' => null];
}

function jazz_oms_get_token(): array
{
    static $cachedToken = null;

    if (is_string($cachedToken) && $cachedToken !== '') {
        return ['ok' => true, 'error' => null, 'token' => $cachedToken];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'token' => null];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Jazz OMS.', 'token' => null];
    }

    $url = jazz_oms_base_url() . '/api/token/';
    try {
        $payload = json_encode([
            'username' => jazz_oms_username(),
            'password' => jazz_oms_password(),
        ], JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to encode Jazz OMS credentials for the token request.', 'token' => null];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: NutraAxis-Operations/1.0 (+https://nutraaxisweb.azurewebsites.net)',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        $detail = 'Unable to reach Jazz OMS token endpoint at ' . $url . '.';
        if ($curlError !== '') {
            $detail .= ' cURL: ' . $curlError;
        }
        $detail .= ' Check JAZZ_DOMAIN (subdomain only, e.g. fbflurry-uat01) or set JAZZ_BASE_URL to the full host URL.';

        return ['ok' => false, 'error' => $detail, 'token' => null];
    }

    $responseBody = (string) $responseBody;

    if ($status === 403 && jazz_oms_is_cloudflare_block($responseBody)) {
        return ['ok' => false, 'error' => jazz_oms_cloudflare_error_message(), 'token' => null];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return jazz_oms_token_error(
            'Jazz OMS returned a non-JSON token response',
            $url,
            $status,
            $responseBody
        );
    }

    if (!is_array($data)) {
        return jazz_oms_token_error('Jazz OMS returned an invalid token payload', $url, $status, $responseBody);
    }

    if ($status >= 400) {
        $message = $data['detail'] ?? $data['message'] ?? $data['error'] ?? ('Jazz OMS token request failed (HTTP ' . $status . ').');

        return ['ok' => false, 'error' => is_string($message) ? $message : 'Jazz OMS token request failed.', 'token' => null];
    }

    $token = (string) ($data['token'] ?? '');
    if ($token === '') {
        return jazz_oms_token_error('Jazz OMS did not return a token', $url, $status, $responseBody);
    }

    $cachedToken = $token;

    return ['ok' => true, 'error' => null, 'token' => $token];
}

function jazz_oms_request_headers(string $token): array
{
    return [
        'Authorization: Token ' . $token,
        'Tenant: ' . jazz_oms_tenant_code(),
        'Content-Type: application/json',
        'Accept: application/json',
    ];
}

function jazz_oms_api_get(string $url, ?array $query = null): array
{
    $tokenResult = jazz_oms_get_token();
    if (!$tokenResult['ok']) {
        return $tokenResult + ['data' => null, 'status' => 0];
    }

    if ($query !== null && $query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Jazz OMS.', 'data' => null, 'status' => 0];
    }

    $headers = jazz_oms_request_headers($tokenResult['token']);
    $headers[] = 'User-Agent: NutraAxis-Operations/1.0 (+https://nutraaxisweb.azurewebsites.net)';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING       => '',
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach Jazz OMS.', 'data' => null, 'status' => $status];
    }

    $responseBody = (string) $responseBody;

    if ($status === 403 && jazz_oms_is_cloudflare_block($responseBody)) {
        return ['ok' => false, 'error' => jazz_oms_cloudflare_error_message(), 'data' => null, 'status' => $status];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        if (jazz_oms_is_cloudflare_block($responseBody)) {
            return ['ok' => false, 'error' => jazz_oms_cloudflare_error_message(), 'data' => null, 'status' => $status];
        }

        return ['ok' => false, 'error' => 'Jazz OMS returned an unexpected response.', 'data' => null, 'status' => $status];
    }

    if ($status >= 400) {
        $message = $data['detail'] ?? $data['message'] ?? $data['error'] ?? ('Jazz OMS request failed (HTTP ' . $status . ').');

        return ['ok' => false, 'error' => is_string($message) ? $message : 'Jazz OMS request failed.', 'data' => $data, 'status' => $status];
    }

    return ['ok' => true, 'error' => null, 'data' => $data, 'status' => $status];
}

function jazz_oms_list_inventory(): array
{
    return jazz_oms_fetch_paginated('/api/v1/product/inventory');
}

function jazz_oms_list_skus(): array
{
    return jazz_oms_fetch_paginated('/api/v1/product/sku');
}

function jazz_oms_list_items(): array
{
    return jazz_oms_fetch_paginated('/api/v1/product/item');
}

function jazz_oms_order_endpoint(): string
{
    return '/' . ltrim(trim((string) env('JAZZ_ORDER_ENDPOINT', '/api/v1/order/status')), '/');
}

function jazz_oms_order_detail_endpoint(): string
{
    $override = trim((string) env('JAZZ_ORDER_DETAIL_ENDPOINT', ''));

    return $override !== ''
        ? '/' . ltrim($override, '/')
        : jazz_oms_order_endpoint();
}

function jazz_oms_order_query_filters(array $filters = []): array
{
    $query = [];
    foreach (['status', 'order_number', 'order_date', 'po_number', 'start_date', 'end_date', 'customer_number', 'ordering'] as $key) {
        $value = trim((string) ($filters[$key] ?? ''));
        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    return $query;
}

function jazz_oms_order_list_ordering(array $sortState, string $default = '-order_date'): string
{
    $map = [
        'order_date'      => 'order_date',
        'order_number'    => 'order_number',
        'status'          => 'status',
        'po_number'       => 'po_number',
        'customer_number' => 'customer_number',
        'source_code'     => 'source_code',
    ];

    $sort = strtolower(trim((string) ($sortState['sort'] ?? 'order_date')));
    $field = $map[$sort] ?? 'order_date';
    if ($field === '') {
        return $default;
    }

    $dir = strtolower(trim((string) ($sortState['dir'] ?? 'desc'))) === 'asc' ? '' : '-';

    return $dir . $field;
}

/**
 * List orders for reporting — newest first, with a local merge/sort fallback when the API
 * returns oldest-first pages (common when ordering is ignored).
 *
 * @return array{ok: bool, error: ?string, rows: list<array<string, mixed>>, total: int, page: int, page_size: int, has_next: bool}
 */
function jazz_oms_list_orders_report(int $page = 1, array $filters = []): array
{
    $page = max(1, $page);
    $pageSize = jazz_oms_page_size();
    $filters['ordering'] = trim((string) ($filters['ordering'] ?? '-order_date'));
    if ($filters['ordering'] === '') {
        $filters['ordering'] = '-order_date';
    }

    $first = jazz_oms_list_orders_page(1, $filters);
    if (!$first['ok']) {
        return $first;
    }

    $maxMerge = max(100, min(1000, (int) env('JAZZ_ORDER_MERGE_MAX', '500')));
    $allRows = $first['rows'];
    $total = (int) $first['total'];

    if ($total > count($allRows) && $total <= $maxMerge) {
        $apiPage = 2;
        while ($apiPage <= 200) {
            $next = jazz_oms_list_orders_page($apiPage, $filters);
            if (!$next['ok']) {
                return $next;
            }

            foreach ($next['rows'] as $row) {
                $allRows[] = $row;
            }

            if (!$next['has_next'] || count($allRows) >= $total) {
                break;
            }

            $apiPage++;
        }

        $total = max($total, count($allRows));
    }

    if (count($allRows) > 1) {
        $ascending = jazz_oms_order_rows_are_ascending_by_date($allRows);
        $descending = jazz_oms_order_rows_are_descending_by_date($allRows);
        $wantDesc = str_starts_with($filters['ordering'], '-');

        if (($wantDesc && $ascending) || (!$wantDesc && $descending)) {
            usort($allRows, static function (array $a, array $b): int {
                return strcmp((string) ($b['order_date'] ?? ''), (string) ($a['order_date'] ?? ''));
            });
        } elseif (!$wantDesc && $ascending) {
            usort($allRows, static function (array $a, array $b): int {
                return strcmp((string) ($a['order_date'] ?? ''), (string) ($b['order_date'] ?? ''));
            });
        }
    }

    $offset = ($page - 1) * $pageSize;
    $pageRows = array_slice($allRows, $offset, $pageSize);

    return [
        'ok'        => true,
        'error'     => null,
        'rows'      => $pageRows,
        'total'     => $total,
        'page'      => $page,
        'page_size' => $pageSize,
        'has_next'  => ($offset + count($pageRows)) < $total,
    ];
}

function jazz_oms_order_rows_are_ascending_by_date(array $rows): bool
{
    if (count($rows) < 2) {
        return false;
    }

    $first = strtotime(substr((string) ($rows[0]['order_date'] ?? ''), 0, 19));
    $last = strtotime(substr((string) ($rows[count($rows) - 1]['order_date'] ?? ''), 0, 19));
    if ($first === false || $last === false) {
        return false;
    }

    return $first <= $last;
}

function jazz_oms_order_rows_are_descending_by_date(array $rows): bool
{
    if (count($rows) < 2) {
        return false;
    }

    $first = strtotime(substr((string) ($rows[0]['order_date'] ?? ''), 0, 19));
    $last = strtotime(substr((string) ($rows[count($rows) - 1]['order_date'] ?? ''), 0, 19));
    if ($first === false || $last === false) {
        return false;
    }

    return $first >= $last;
}

/**
 * @return array{ok: bool, error: ?string, rows: list<array<string, mixed>>, total: int, page: int, page_size: int, has_next: bool}
 */
function jazz_oms_list_orders_page(int $page = 1, array $filters = []): array
{
    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return [
            'ok'        => false,
            'error'     => $configError,
            'rows'      => [],
            'total'     => 0,
            'page'      => max(1, $page),
            'page_size' => jazz_oms_page_size(),
            'has_next'  => false,
        ];
    }

    $page = max(1, $page);
    $pageSize = jazz_oms_page_size();
    $offset = ($page - 1) * $pageSize;
    $path = jazz_oms_order_endpoint();
    $url = jazz_oms_base_url() . '/' . ltrim($path, '/');
    $params = array_merge(
        ['limit' => $pageSize, 'offset' => $offset],
        jazz_oms_order_query_filters($filters)
    );

    $result = jazz_oms_api_get($url, $params);
    if (!$result['ok']) {
        return [
            'ok'        => false,
            'error'     => $result['error'],
            'rows'      => [],
            'total'     => 0,
            'page'      => $page,
            'page_size' => $pageSize,
            'has_next'  => false,
        ];
    }

    $data = $result['data'] ?? [];
    $records = $data['results'] ?? $data['data'] ?? (array_is_list($data) ? $data : []);
    $rows = [];
    if (is_array($records)) {
        foreach ($records as $record) {
            if (is_array($record)) {
                $rows[] = $record;
            }
        }
    }

    $total = (int) ($data['count'] ?? $data['total'] ?? 0);
    if ($total <= 0) {
        $total = count($rows);
        if (($data['next'] ?? null) !== null && ($data['next'] ?? '') !== '') {
            $total = max($total, ($page * $pageSize) + 1);
        }
    }

    $hasNext = (is_string($data['next'] ?? null) && ($data['next'] ?? '') !== '')
        || ($total > 0 && ($offset + count($rows)) < $total);

    return [
        'ok'        => true,
        'error'     => null,
        'rows'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'page_size' => $pageSize,
        'has_next'  => $hasNext,
    ];
}

function jazz_oms_list_orders(array $filters = []): array
{
    return jazz_oms_fetch_paginated(jazz_oms_order_endpoint(), jazz_oms_order_query_filters($filters));
}

/**
 * @return array{ok: bool, error: ?string, row: ?array<string, mixed>}
 */
function jazz_oms_get_order(string $orderNumber): array
{
    $orderNumber = trim($orderNumber);
    if ($orderNumber === '') {
        return ['ok' => false, 'error' => 'Order number is required.', 'row' => null];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'row' => null];
    }

    $endpoint = jazz_oms_order_endpoint();
    $detailEndpoint = jazz_oms_order_detail_endpoint();
    $detailPaths = [
        $detailEndpoint . '/' . rawurlencode($orderNumber) . '/',
        $endpoint . '/' . rawurlencode($orderNumber) . '/',
        '/api/v1/order/' . rawurlencode($orderNumber) . '/',
    ];
    $detailQuery = trim((string) env('JAZZ_ORDER_DETAIL_QUERY', ''));
    $detailQueryParams = [];
    if ($detailQuery !== '') {
        parse_str($detailQuery, $detailQueryParams);
    }

    foreach (array_unique($detailPaths) as $path) {
        $url = jazz_oms_base_url() . '/' . ltrim($path, '/');
        $result = jazz_oms_api_get($url, $detailQueryParams !== [] ? $detailQueryParams : null);
        if (!$result['ok'] || !is_array($result['data'])) {
            continue;
        }

        $row = jazz_oms_order_extract_row($result['data']);
        if (is_array($row) && jazz_oms_order_number_matches($row, $orderNumber)) {
            return ['ok' => true, 'error' => null, 'row' => $row];
        }
    }

    $summaryResult = jazz_oms_find_order_summary($orderNumber);
    if (!$summaryResult['ok']) {
        return $summaryResult;
    }

    $summary = $summaryResult['row'];
    $detailId = trim((string) ($summary['id'] ?? ''));
    if ($detailId !== '' && strcasecmp($detailId, $orderNumber) !== 0) {
        foreach (array_unique([$detailEndpoint, $endpoint]) as $basePath) {
            $detailUrl = jazz_oms_base_url() . '/' . ltrim($basePath, '/') . '/' . rawurlencode($detailId) . '/';
            $detailResult = jazz_oms_api_get($detailUrl, $detailQueryParams !== [] ? $detailQueryParams : null);
            if ($detailResult['ok'] && is_array($detailResult['data'])) {
                $detailRow = jazz_oms_order_extract_row($detailResult['data']);
                if (is_array($detailRow)) {
                    return ['ok' => true, 'error' => null, 'row' => $detailRow];
                }
            }
        }
    }

    return ['ok' => true, 'error' => null, 'row' => $summary];
}

/**
 * @return array{ok: bool, error: ?string, row: ?array<string, mixed>}
 */
function jazz_oms_find_order_summary(string $orderNumber): array
{
    $filters = ['order_number' => $orderNumber];
    $page = 1;
    $maxPages = max(1, min(20, (int) ceil(((int) env('JAZZ_ORDER_MERGE_MAX', '500')) / max(1, jazz_oms_page_size()))));

    while ($page <= $maxPages) {
        $result = jazz_oms_list_orders_page($page, $filters);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'row' => null];
        }

        foreach ($result['rows'] as $row) {
            if (is_array($row) && jazz_oms_order_number_matches($row, $orderNumber)) {
                return ['ok' => true, 'error' => null, 'row' => $row];
            }
        }

        if (!$result['has_next']) {
            break;
        }

        $page++;
    }

    return ['ok' => false, 'error' => 'Order ' . $orderNumber . ' not found in Jazz OMS.', 'row' => null];
}

function jazz_oms_order_number_matches(array $row, string $orderNumber): bool
{
    $candidate = trim((string) ($row['order_number'] ?? ''));
    return $candidate !== '' && strcasecmp($candidate, $orderNumber) === 0;
}

/**
 * @param array<string, mixed> $data
 * @return ?array<string, mixed>
 */
function jazz_oms_order_extract_row(array $data): ?array
{
    if (isset($data['order_number']) || isset($data['id'])) {
        return $data;
    }

    foreach (['result', 'order'] as $key) {
        if (isset($data[$key]) && is_array($data[$key])) {
            return $data[$key];
        }
    }

    foreach (['results', 'data'] as $key) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            continue;
        }

        if (isset($data[$key]['order_number']) || isset($data[$key]['id'])) {
            return $data[$key];
        }

        if (array_is_list($data[$key])) {
            foreach ($data[$key] as $row) {
                if (is_array($row)) {
                    return $row;
                }
            }
        }
    }

    return null;
}

/**
 * @return array{ok: bool, error: ?string, rows: list<array<string, mixed>>}
 */
function jazz_oms_fetch_paginated(string $path, array $query = []): array
{
    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'rows' => []];
    }

    $path = '/' . ltrim($path, '/');
    $url = jazz_oms_base_url() . $path;
    $params = array_merge(['limit' => jazz_oms_page_size(), 'offset' => 0], $query);
    $rows = [];
    $pageGuard = 0;

    while ($url !== '' && $pageGuard < 200) {
        $pageGuard++;
        $result = jazz_oms_api_get($url, $pageGuard === 1 ? $params : null);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'], 'rows' => $rows];
        }

        $data = $result['data'] ?? [];
        $records = $data['results'] ?? $data['data'] ?? (array_is_list($data) ? $data : []);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (is_array($record)) {
                    $rows[] = $record;
                }
            }
        }

        $next = $data['next'] ?? null;
        $url = is_string($next) && $next !== '' ? $next : '';
        $params = [];
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows];
}

function jazz_oms_format_cell($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (is_scalar($value)) {
        $text = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1) {
            try {
                return (new DateTimeImmutable($text))->format('M j, Y g:i A');
            } catch (Throwable) {
                return $text;
            }
        }

        return $text;
    }

    $json = json_encode($value, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return '—';
    }

    return strlen($json) > 80 ? substr($json, 0, 77) . '…' : $json;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param list<string> $preferred
 * @return list<string>
 */
function jazz_oms_record_columns(array $rows, array $preferred = []): array
{
    $columns = [];
    $skip = ['attributes', 'barcodes', 'mia_attributes'];

    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            if (!in_array($key, $skip, true)) {
                $columns[$key] = true;
            }
        }
    }

    $ordered = [];
    foreach ($preferred as $key) {
        if (isset($columns[$key])) {
            $ordered[] = $key;
            unset($columns[$key]);
        }
    }

    return array_merge($ordered, array_keys($columns));
}

function jazz_oms_field_label(string $key): string
{
    return ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key) ?? $key));
}

function jazz_oms_order_customer_label(array $row): string
{
    static $cache = [];

    $cacheKey = trim((string) ($row['order_number'] ?? ''));
    if ($cacheKey === '') {
        $cacheKey = md5(json_encode($row) ?: '');
    }
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    foreach (['customer_name', 'customer_full_name', 'name'] as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') {
            return $cache[$cacheKey] = $value;
        }
    }

    $contacts = jazz_oms_order_resolve_contacts($row);
    $contact = $contacts['customer'];
    $name = trim((string) ($contact['name'] ?? ''));
    if ($name !== '') {
        return $cache[$cacheKey] = $name;
    }

    foreach (['company', 'email', 'customer_number'] as $key) {
        $value = trim((string) ($contact[$key] ?? ''));
        if ($value !== '') {
            return $cache[$cacheKey] = $value;
        }
    }

    $fallback = trim((string) ($row['customer_number'] ?? ''));
    return $cache[$cacheKey] = ($fallback !== '' ? $fallback : '—');
}

function jazz_oms_order_contact_label(array $contact): string
{
    $name = trim((string) ($contact['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    foreach (['company', 'email', 'customer_number'] as $key) {
        $value = trim((string) ($contact[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function jazz_oms_enrich_order_rows_customer_names(array $rows): array
{
    foreach ($rows as $index => $row) {
        if (!is_array($row) || trim((string) ($row['customer_name'] ?? '')) !== '') {
            continue;
        }

        $orderNumber = trim((string) ($row['order_number'] ?? ''));
        if ($orderNumber === '') {
            continue;
        }

        $detailResult = jazz_oms_get_order($orderNumber);
        if (!$detailResult['ok'] || !is_array($detailResult['row'] ?? null)) {
            continue;
        }

        $contacts = jazz_oms_order_resolve_contacts($detailResult['row']);
        $customerName = jazz_oms_order_contact_label($contacts['customer']);
        if ($customerName !== '') {
            $rows[$index]['customer_name'] = $customerName;
        }
    }

    return $rows;
}

/**
 * @return array<string, string>
 */
function jazz_oms_order_contact(array $row): array
{
    return jazz_oms_order_party_contact($row, 'customer');
}

/**
 * @return array<string, string>
 */
function jazz_oms_order_ship_to_contact(array $row): array
{
    $shipTo = jazz_oms_order_party_contact($row, 'shipto');
    if (!jazz_oms_contact_is_empty($shipTo)) {
        return $shipTo;
    }

    return jazz_oms_order_party_contact($row, 'customer');
}

/**
 * @return array{customer: array<string, string>, ship_to: array<string, string>, accs_order_number: ?string}
 */
function jazz_oms_order_resolve_contacts(array $row): array
{
    $customer = jazz_oms_order_contact($row);
    $shipTo = jazz_oms_order_ship_to_contact($row);
    $accsOrderNumber = null;

    if (jazz_oms_contact_is_empty($customer) || jazz_oms_contact_is_empty($shipTo)) {
        $fromAccs = jazz_oms_order_contacts_from_accs($row);
        if ($fromAccs !== null) {
            if (jazz_oms_contact_is_empty($customer)) {
                $customer = $fromAccs['customer'];
            }
            if (jazz_oms_contact_is_empty($shipTo)) {
                $shipTo = $fromAccs['ship_to'];
            }
            $accsOrderNumber = $fromAccs['accs_order_number'];
        }
    }

    return [
        'customer'          => $customer,
        'ship_to'           => $shipTo,
        'accs_order_number' => $accsOrderNumber,
    ];
}

function jazz_oms_contact_is_empty(array $contact): bool
{
    foreach (['name', 'email', 'company', 'city', 'state', 'zip', 'address1', 'phone', 'customer_number'] as $key) {
        if (trim((string) ($contact[$key] ?? '')) !== '') {
            return false;
        }
    }

    return true;
}

function jazz_oms_order_source_is_accs(array $row): bool
{
    $source = strtoupper(trim((string) ($row['source_code'] ?? '')));

    return str_contains($source, 'ACCS');
}

/**
 * @return list<string>
 */
function jazz_oms_order_accs_lookup_numbers(array $row): array
{
    $candidates = [];

    foreach (['po_number', 'order_number'] as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        $candidates[] = $value;

        if (preg_match('/^(?:STG|DEV|PROD)-(.+)$/i', $value, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        if (preg_match('/^(.+)-(?:NSB|TEST(?:-\d+)?)$/i', $value, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        if (preg_match('/(\d{6,})/', $value, $matches) === 1) {
            $digits = $matches[1];
            $candidates[] = $digits;
            $candidates[] = str_pad($digits, 9, '0', STR_PAD_LEFT);
        }
    }

    return array_values(array_unique(array_filter(array_map('trim', $candidates))));
}

/**
 * @return ?array{customer: array<string, string>, ship_to: array<string, string>, accs_order_number: string}
 */
function jazz_oms_order_contacts_from_accs(array $row): ?array
{
    require_once __DIR__ . '/adobe-commerce.php';

    if (adobe_commerce_config_error() !== null) {
        return null;
    }

    foreach (jazz_oms_order_accs_lookup_numbers($row) as $candidate) {
        $result = adobe_commerce_fetch_order_by_number($candidate);
        if (!$result['ok'] || !is_array($result['order'] ?? null)) {
            continue;
        }

        $accsOrder = $result['order'];
        $billing = is_array($accsOrder['billing_address'] ?? null) ? $accsOrder['billing_address'] : [];
        $shipping = adobe_commerce_order_shipping_address($accsOrder) ?? $billing;

        return [
            'customer'          => jazz_oms_contact_from_accs_address($billing, $accsOrder),
            'ship_to'           => jazz_oms_contact_from_accs_address($shipping, $accsOrder),
            'accs_order_number' => (string) ($accsOrder['increment_id'] ?? $candidate),
        ];
    }

    return null;
}

/**
 * @param array<string, mixed> $address
 * @param array<string, mixed> $order
 * @return array<string, string>
 */
function jazz_oms_contact_from_accs_address(array $address, array $order): array
{
    $street = $address['street'] ?? [];
    $line1 = is_array($street) ? trim((string) ($street[0] ?? '')) : trim((string) $street);
    $line2 = is_array($street) ? trim((string) ($street[1] ?? '')) : '';

    return [
        'name'             => trim((string) ($address['firstname'] ?? '') . ' ' . (string) ($address['lastname'] ?? '')),
        'first_name'       => trim((string) ($address['firstname'] ?? '')),
        'last_name'        => trim((string) ($address['lastname'] ?? '')),
        'company'          => trim((string) ($address['company'] ?? '')),
        'email'            => trim((string) ($address['email'] ?? $order['customer_email'] ?? '')),
        'phone'            => trim((string) ($address['telephone'] ?? '')),
        'customer_number'  => trim((string) ($order['customer_id'] ?? '')),
        'address1'         => $line1,
        'address2'         => $line2,
        'city'             => trim((string) ($address['city'] ?? '')),
        'state'            => trim((string) ($address['region_code'] ?? $address['region'] ?? '')),
        'zip'              => trim((string) ($address['postcode'] ?? '')),
        'country'          => trim((string) ($address['country_id'] ?? '')),
    ];
}

/**
 * @return array<string, string>
 */
function jazz_oms_order_party_contact(array $row, string $party): array
{
    $partyData = [];
    $address = [];

    if ($party === 'customer') {
        $partyData = is_array($row['customer'] ?? null) ? $row['customer'] : [];
        $address = is_array($partyData['address'] ?? null) ? $partyData['address'] : [];
    } else {
        $shipTo = is_array(($row['shipto'][0] ?? null)) ? $row['shipto'][0] : (is_array(($row['ship_to'][0] ?? null)) ? $row['ship_to'][0] : []);
        $partyData = $shipTo;
        $address = is_array($shipTo['address'] ?? null) ? $shipTo['address'] : [];
    }

    $firstName = trim((string) (
        $partyData['first_name'] ?? $partyData['firstname']
        ?? $address['first_name'] ?? $address['firstname'] ?? ''
    ));
    $lastName = trim((string) (
        $partyData['last_name'] ?? $partyData['lastname']
        ?? $address['last_name'] ?? $address['lastname'] ?? ''
    ));
    $name = trim($firstName . ' ' . $lastName);
    if ($name === '') {
        $name = trim((string) ($partyData['name'] ?? $address['name'] ?? ''));
    }

    return [
        'name'             => $name,
        'first_name'       => $firstName,
        'last_name'        => $lastName,
        'company'          => trim((string) ($partyData['company'] ?? $address['company'] ?? '')),
        'email'            => trim((string) ($partyData['email'] ?? $address['email'] ?? '')),
        'phone'            => trim((string) ($partyData['phone_number'] ?? $partyData['phone'] ?? $address['phone_number'] ?? $address['phone'] ?? '')),
        'customer_number'  => trim((string) ($partyData['customer_number'] ?? $address['customer_number'] ?? $row['customer_number'] ?? '')),
        'address1'         => trim((string) ($partyData['address1'] ?? $address['address1'] ?? '')),
        'address2'         => trim((string) ($partyData['address2'] ?? $address['address2'] ?? '')),
        'city'             => trim((string) ($partyData['city'] ?? $address['city'] ?? '')),
        'state'            => trim((string) ($partyData['state'] ?? $address['state'] ?? '')),
        'zip'              => trim((string) ($partyData['zipcode'] ?? $partyData['zip'] ?? $partyData['postcode'] ?? $address['zipcode'] ?? $address['zip'] ?? '')),
        'country'          => trim((string) ($partyData['country'] ?? $address['country'] ?? '')),
    ];
}

function jazz_oms_order_field(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * @param list<array<string, mixed>> $items
 */
function jazz_oms_order_collect_lines_from_container(array $container, array &$items): void
{
    foreach (['detail_set', 'detail', 'details', 'line_items', 'lines'] as $key) {
        $lines = $container[$key] ?? null;
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }

            if (isset($line['sku']) || isset($line['sku_code']) || isset($line['qty_ordered']) || isset($line['quantity']) || isset($line['line_number'])) {
                $items[] = $line;
            }
        }
    }
}

/**
 * @return list<array<string, mixed>>
 */
function jazz_oms_order_line_items(array $row): array
{
    $items = [];
    jazz_oms_order_collect_lines_from_container($row, $items);

    $shipTos = $row['shipto'] ?? [];
    if (is_array($shipTos)) {
        foreach ($shipTos as $shipTo) {
            if (is_array($shipTo)) {
                jazz_oms_order_collect_lines_from_container($shipTo, $items);
            }
        }
    }

    return $items;
}

function jazz_oms_order_line_name(array $line): string
{
    foreach (['product_name', 'name'] as $key) {
        $value = trim((string) ($line[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function jazz_oms_order_line_description(array $line): string
{
    foreach (['description', 'size_description', 'size_desc', 'item_description'] as $key) {
        $value = trim((string) ($line[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * @param list<string> $skuCodes
 * @return array<string, string> lower-case SKU => ProductName
 */
function jazz_oms_sku_master_product_names(array $skuCodes): array
{
    $normalized = [];
    foreach ($skuCodes as $sku) {
        $sku = trim($sku);
        if ($sku !== '') {
            $normalized[strtolower($sku)] = $sku;
        }
    }

    if ($normalized === []) {
        return [];
    }

    require_once __DIR__ . '/database.php';
    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $stmt = $pdo->prepare(
        "SELECT SKUCode, ProductName FROM dbo.SKUMaster WHERE LOWER(SKUCode) IN ({$placeholders})"
    );
    $stmt->execute(array_keys($normalized));

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $skuKey = strtolower(trim((string) ($row['SKUCode'] ?? '')));
        $name = trim((string) ($row['ProductName'] ?? ''));
        if ($skuKey !== '' && $name !== '') {
            $map[$skuKey] = $name;
        }
    }

    return $map;
}

/**
 * @return ?array<string, array{name: string, description: string}>
 */
function jazz_oms_order_accs_items_by_sku(array $row): ?array
{
    require_once __DIR__ . '/adobe-commerce.php';
    require_once __DIR__ . '/daily-sales-summary.php';

    if (adobe_commerce_config_error() !== null) {
        return null;
    }

    foreach (jazz_oms_order_accs_lookup_numbers($row) as $candidate) {
        $result = adobe_commerce_fetch_order_by_number($candidate);
        if (!$result['ok'] || !is_array($result['order'] ?? null)) {
            continue;
        }

        $items = [];
        foreach ($result['order']['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $skuKey = strtolower($sku);
            $items[$skuKey] = [
                'name'        => trim((string) ($item['name'] ?? '')),
                'description' => daily_sales_summary_item_description($item),
            ];
        }

        return $items;
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $lineItems
 * @return array{name_by_sku: array<string, string>, description_by_sku: array<string, string>}
 */
function jazz_oms_order_line_product_lookups(array $row, array $lineItems): array
{
    $skuCodes = [];
    foreach ($lineItems as $line) {
        $sku = trim((string) ($line['sku'] ?? $line['sku_code'] ?? ''));
        if ($sku !== '') {
            $skuCodes[] = $sku;
        }
    }

    $nameBySku = jazz_oms_sku_master_product_names($skuCodes);
    $descriptionBySku = [];

    $accsItems = jazz_oms_order_accs_items_by_sku($row);
    if ($accsItems !== null) {
        foreach ($accsItems as $skuKey => $item) {
            if (($nameBySku[$skuKey] ?? '') === '' && ($item['name'] ?? '') !== '') {
                $nameBySku[$skuKey] = $item['name'];
            }
            if (($item['description'] ?? '') !== '') {
                $descriptionBySku[$skuKey] = $item['description'];
            }
        }
    }

    return [
        'name_by_sku'        => $nameBySku,
        'description_by_sku' => $descriptionBySku,
    ];
}

/**
 * @param array{name_by_sku: array<string, string>, description_by_sku: array<string, string>} $lookups
 */
function jazz_oms_order_line_product_display(array $line, array $lookups): string
{
    $name = jazz_oms_order_line_name($line);
    if ($name !== '') {
        return $name;
    }

    $skuKey = strtolower(trim((string) ($line['sku'] ?? $line['sku_code'] ?? '')));
    if ($skuKey !== '' && ($lookups['name_by_sku'][$skuKey] ?? '') !== '') {
        return $lookups['name_by_sku'][$skuKey];
    }

    $description = jazz_oms_order_line_description($line);
    if ($description !== '') {
        return $description;
    }

    if ($skuKey !== '' && ($lookups['description_by_sku'][$skuKey] ?? '') !== '') {
        return $lookups['description_by_sku'][$skuKey];
    }

    return '';
}

function jazz_oms_order_item_qty(array $row): int
{
    foreach (['qty_ordered', 'quantity_ordered', 'ordered', 'total_qty_ordered', 'total_ordered'] as $key) {
        if (isset($row[$key]) && $row[$key] !== '' && $row[$key] !== null) {
            return (int) $row[$key];
        }
    }

    $total = 0;
    foreach (jazz_oms_order_line_items($row) as $line) {
        $total += (int) ($line['qty_ordered'] ?? $line['quantity'] ?? 0);
    }

    return $total;
}

function jazz_oms_format_quantity($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float) $value, 0);
}

function jazz_oms_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}
