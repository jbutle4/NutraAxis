<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/accounting.php';

const QBO_OAUTH_STATE_KEY = 'qbo_oauth_state';

function qbo_environment(): string
{
    $env = strtolower(trim((string) env('QBO_ENVIRONMENT', 'sandbox')));

    return $env === 'production' ? 'production' : 'sandbox';
}

function qbo_redirect_uri(): string
{
    $configured = trim((string) env('QBO_REDIRECT_URI', ''));
    if ($configured !== '') {
        return $configured;
    }

    $siteUrl = rtrim(trim((string) env('SITE_URL', '')), '/');
    if ($siteUrl !== '') {
        return $siteUrl . '/accounting/callback.php';
    }

    return '';
}

function qbo_is_configured(): bool
{
    return trim((string) env('QBO_CLIENT_ID', '')) !== ''
        && trim((string) env('QBO_CLIENT_SECRET', '')) !== ''
        && qbo_redirect_uri() !== '';
}

function qbo_config_error(): ?string
{
    if (qbo_is_configured()) {
        return null;
    }

    return 'QuickBooks is not configured. Set QBO_CLIENT_ID, QBO_CLIENT_SECRET, and QBO_REDIRECT_URI in application settings.';
}

function qbo_api_base_url(): string
{
    return qbo_environment() === 'production'
        ? 'https://quickbooks.api.intuit.com'
        : 'https://sandbox-quickbooks.api.intuit.com';
}

function qbo_get_connection(): ?array
{
    try {
        $pdo = db();
    } catch (Throwable) {
        return null;
    }

    $stmt = $pdo->query('SELECT TOP 1 * FROM dbo.QBOConnection ORDER BY ConnectionID DESC');
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function qbo_is_connected(): bool
{
    return qbo_get_connection() !== null;
}

function qbo_save_connection(array $data): void
{
    $pdo = db();
    $pdo->exec('DELETE FROM dbo.QBOConnection');

    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.QBOConnection (
            RealmID, CompanyName, AccessToken, RefreshToken, AccessTokenExpiresAt,
            Environment, ConnectedByUser, ConnectedAt, UpdatedAt
        )
        VALUES (
            :realm, :company, :access, :refresh, :expires,
            :environment, :user, SYSUTCDATETIME(), SYSUTCDATETIME()
        )
    SQL);

    $stmt->execute([
        'realm'       => $data['realm_id'],
        'company'     => $data['company_name'] ?? null,
        'access'      => $data['access_token'],
        'refresh'     => $data['refresh_token'],
        'expires'     => $data['access_token_expires_at'],
        'environment' => $data['environment'] ?? qbo_environment(),
        'user'        => (int) ($data['connected_by_user'] ?? 0),
    ]);
}

function qbo_disconnect(): void
{
    $pdo = db();
    $pdo->exec('DELETE FROM dbo.QBOConnection');
}

function qbo_start_oauth_state(): string
{
    auth_start_session();
    $state = bin2hex(random_bytes(16));
    $_SESSION[QBO_OAUTH_STATE_KEY] = $state;

    return $state;
}

function qbo_validate_oauth_state(?string $state): bool
{
    auth_start_session();
    $expected = $_SESSION[QBO_OAUTH_STATE_KEY] ?? null;
    unset($_SESSION[QBO_OAUTH_STATE_KEY]);

    return is_string($state) && is_string($expected) && hash_equals($expected, $state);
}

function qbo_authorize_url(): string
{
    $state = qbo_start_oauth_state();
    $params = http_build_query([
        'client_id'     => env('QBO_CLIENT_ID', ''),
        'response_type' => 'code',
        'scope'         => 'com.intuit.quickbooks.accounting',
        'redirect_uri'  => qbo_redirect_uri(),
        'state'         => $state,
    ]);

    return 'https://appcenter.intuit.com/connect/oauth2?' . $params;
}

function qbo_token_request(array $fields): array
{
    $clientId = trim((string) env('QBO_CLIENT_ID', ''));
    $clientSecret = (string) env('QBO_CLIENT_SECRET', '');

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required for QuickBooks OAuth.', 'data' => null];
    }

    $ch = curl_init('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach QuickBooks OAuth.', 'data' => null];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'QuickBooks OAuth returned an unexpected response.', 'data' => null];
    }

    if ($status >= 400) {
        $message = $data['error_description'] ?? $data['error'] ?? 'QuickBooks OAuth failed.';

        return ['ok' => false, 'error' => is_string($message) ? $message : 'QuickBooks OAuth failed.', 'data' => $data];
    }

    return ['ok' => true, 'error' => null, 'data' => $data];
}

function qbo_exchange_code(string $code, string $realmId): array
{
    $result = qbo_token_request([
        'grant_type'   => 'authorization_code',
        'code'         => $code,
        'redirect_uri' => qbo_redirect_uri(),
    ]);

    if (!$result['ok']) {
        return $result;
    }

    return qbo_store_token_response($result['data'], $realmId);
}

function qbo_store_token_response(array $data, ?string $realmId = null): array
{
    $accessToken = (string) ($data['access_token'] ?? '');
    $refreshToken = (string) ($data['refresh_token'] ?? '');
    $expiresIn = (int) ($data['expires_in'] ?? 3600);

    if ($accessToken === '' || $refreshToken === '') {
        return ['ok' => false, 'error' => 'QuickBooks did not return connection tokens.'];
    }

    $connection = qbo_get_connection();
    $realmId = $realmId ?? (string) ($connection['RealmID'] ?? '');
    if ($realmId === '') {
        return ['ok' => false, 'error' => 'QuickBooks company realm ID is missing.'];
    }

    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . max(60, $expiresIn - 60) . ' seconds')
        ->format('Y-m-d H:i:s');

    $companyName = $connection['CompanyName'] ?? null;
    if ($companyName === null || $companyName === '') {
        $info = qbo_fetch_company_name($realmId, $accessToken);
        if ($info['ok']) {
            $companyName = $info['name'];
        }
    }

    qbo_save_connection([
        'realm_id'                => $realmId,
        'company_name'            => $companyName,
        'access_token'            => $accessToken,
        'refresh_token'           => $refreshToken,
        'access_token_expires_at' => $expiresAt,
        'environment'             => qbo_environment(),
        'connected_by_user'       => auth_user()['UserID'] ?? 0,
    ]);

    return ['ok' => true, 'error' => null];
}

function qbo_fetch_company_name(string $realmId, string $accessToken): array
{
    $url = qbo_api_base_url() . '/v3/company/' . rawurlencode($realmId) . '/companyinfo/' . rawurlencode($realmId) . '?minorversion=65';

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'name' => null];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $responseBody = curl_exec($ch);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'name' => null];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'name' => null];
    }

    $name = trim((string) ($data['CompanyInfo']['CompanyName'] ?? ''));

    return ['ok' => $name !== '', 'name' => $name !== '' ? $name : null];
}

function qbo_refresh_access_token(): array
{
    $connection = qbo_get_connection();
    if ($connection === null) {
        return ['ok' => false, 'error' => 'QuickBooks is not connected.'];
    }

    $result = qbo_token_request([
        'grant_type'    => 'refresh_token',
        'refresh_token' => (string) $connection['RefreshToken'],
    ]);

    if (!$result['ok']) {
        return $result;
    }

    return qbo_store_token_response($result['data'], (string) $connection['RealmID']);
}

function qbo_ensure_access_token(): array
{
    $connection = qbo_get_connection();
    if ($connection === null) {
        return ['ok' => false, 'error' => 'QuickBooks is not connected. An Accounting user with Update access must connect QuickBooks first.'];
    }

    try {
        $expires = new DateTimeImmutable((string) $connection['AccessTokenExpiresAt'], new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($expires > $now) {
            return ['ok' => true, 'error' => null, 'connection' => $connection];
        }
    } catch (Throwable) {
        // fall through to refresh
    }

    $refresh = qbo_refresh_access_token();
    if (!$refresh['ok']) {
        return $refresh;
    }

    $connection = qbo_get_connection();

    return ['ok' => true, 'error' => null, 'connection' => $connection];
}

function qbo_api_request(string $method, string $path, ?array $query = null): array
{
    $configError = qbo_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'data' => null];
    }

    $tokenResult = qbo_ensure_access_token();
    if (!$tokenResult['ok']) {
        return $tokenResult;
    }

    $connection = $tokenResult['connection'];
    $realmId = (string) $connection['RealmID'];
    $url = qbo_api_base_url() . '/v3/company/' . rawurlencode($realmId) . $path;

    if ($query !== null && $query !== []) {
        $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to QuickBooks.', 'data' => null];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . (string) $connection['AccessToken'],
        ],
        CURLOPT_TIMEOUT        => 45,
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach QuickBooks.', 'data' => null];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'QuickBooks returned an unexpected response.', 'data' => null];
    }

    if ($status >= 400) {
        $fault = $data['Fault']['Error'][0]['Message'] ?? $data['fault']['error'][0]['message'] ?? null;
        $detail = $data['Fault']['Error'][0]['Detail'] ?? null;
        $message = trim((string) ($fault ?? 'QuickBooks request failed.'));
        if ($detail) {
            $message .= ' ' . $detail;
        }

        return ['ok' => false, 'error' => $message, 'data' => $data, 'status' => $status];
    }

    return ['ok' => true, 'error' => null, 'data' => $data];
}

function qbo_query(string $sql, int $maxResults = 100): array
{
    $sql = trim($sql);
    if (!str_contains(strtoupper($sql), 'MAXRESULTS')) {
        $sql .= ' MAXRESULTS ' . $maxResults;
    }

    $result = qbo_api_request('GET', '/query', [
        'query'        => $sql,
        'minorversion' => 65,
    ]);

    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'data' => $result['data']];
}

function qbo_extract_rows(array $data, array $entityKeys): array
{
    $response = $data['QueryResponse'] ?? [];
    foreach ($entityKeys as $key) {
        if (!empty($response[$key]) && is_array($response[$key])) {
            return $response[$key];
        }
    }

    return [];
}

function qbo_list_bills(): array
{
    $result = qbo_query('SELECT * FROM Bill ORDERBY TxnDate DESC');
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['Bill'])];
}

function qbo_list_invoices(): array
{
    $result = qbo_query('SELECT * FROM Invoice ORDERBY TxnDate DESC');
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['Invoice'])];
}

function qbo_list_purchase_orders(): array
{
    $result = qbo_query('SELECT * FROM PurchaseOrder ORDERBY TxnDate DESC');
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['PurchaseOrder'])];
}

function qbo_list_inventory_items(): array
{
    $result = qbo_query("SELECT * FROM Item WHERE Type = 'Inventory' ORDERBY Name");
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['Item'])];
}

function qbo_list_vendors(): array
{
    $result = qbo_query('SELECT * FROM Vendor ORDERBY DisplayName');
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['Vendor'])];
}

function qbo_list_accounts(): array
{
    $result = qbo_query('SELECT * FROM Account ORDERBY Name');
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['Account'])];
}
