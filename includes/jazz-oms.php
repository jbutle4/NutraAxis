<?php

require_once __DIR__ . '/env.php';

/**
 * Bind this request to Jazz production or UAT credentials.
 * UAT twin pages should call this after setting the portal data profile.
 */
function jazz_oms_use_environment(string $environment): void
{
    $environment = strtolower(trim($environment));
    if (!in_array($environment, ['production', 'uat'], true)) {
        return;
    }

    $GLOBALS['_jazz_oms_environment_override'] = $environment;
}

/**
 * Default is production so unlabeled Jazz pages never silently hit UAT.
 */
function jazz_oms_is_production_environment(): bool
{
    $override = strtolower(trim((string) ($GLOBALS['_jazz_oms_environment_override'] ?? '')));
    if ($override === 'uat') {
        return false;
    }

    return true;
}

function jazz_oms_data_source_label(): string
{
    return jazz_oms_is_production_environment() ? 'Production' : 'UAT';
}

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
    if (jazz_oms_is_production_environment()) {
        $domain = trim((string) env_first([
            'JAZZ_DOMAIN_PROD',
            'JAZZ_PRODUCTION_DOMAIN',
            'JAZZ_DOMAIN',
        ], ''));
    } else {
        $domain = trim((string) env_first([
            'JAZZ_UAT_DOMAIN',
            'JAZZ_DOMAIN',
        ], ''));
    }

    return jazz_oms_normalize_domain($domain);
}

function jazz_oms_username(): string
{
    if (jazz_oms_is_production_environment()) {
        return trim((string) env_first([
            'JAZZ_USERNAME_PROD',
            'JAZZ_PRODUCTION_USERNAME',
            'JAZZ_USERNAME',
        ], ''));
    }

    return trim((string) env_first([
        'JAZZ_UAT_USERNAME',
        'JAZZ_USERNAME',
    ], ''));
}

function jazz_oms_password(): string
{
    if (jazz_oms_is_production_environment()) {
        return (string) env_first([
            'JAZZ_PASSWORD_PROD',
            'JAZZ_PRODUCTION_PASSWORD',
            'JAZZ_PASSWORD',
        ], '');
    }

    return (string) env_first([
        'JAZZ_UAT_PASSWORD',
        'JAZZ_PASSWORD',
    ], '');
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
    if (jazz_oms_is_production_environment()) {
        $override = rtrim(trim((string) env_first([
            'JAZZ_BASE_URL_PROD',
            'JAZZ_PRODUCTION_BASE_URL',
            'JAZZ_BASE_URL',
        ], '')), '/');
    } else {
        $override = rtrim(trim((string) env_first([
            'JAZZ_UAT_BASE_URL',
            'JAZZ_BASE_URL',
        ], '')), '/');
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

    return jazz_oms_is_production_environment()
        ? 'Jazz OMS (production) is not configured. Set JAZZ_DOMAIN_PROD / JAZZ_USERNAME_PROD / JAZZ_PASSWORD_PROD (or legacy JAZZ_*), and JAZZ_TENANT_CODE in application settings.'
        : 'Jazz OMS (UAT) is not configured. Set JAZZ_UAT_DOMAIN / JAZZ_UAT_USERNAME / JAZZ_UAT_PASSWORD (or legacy JAZZ_*), and JAZZ_TENANT_CODE in application settings.';
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

/**
 * @return array{ok: bool, error: ?string, rows: list<array<string, mixed>>}
 */
function jazz_oms_fetch_paginated(string $path): array
{
    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'rows' => []];
    }

    $path = '/' . ltrim($path, '/');
    $url = jazz_oms_base_url() . $path;
    $params = ['limit' => jazz_oms_page_size(), 'offset' => 0];
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

function jazz_oms_format_quantity($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float) $value, 0);
}
