<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/accounting.php';

const QBO_OAUTH_STATE_KEY = 'qbo_oauth_state';

function qbo_environment(): string
{
    $env = strtolower(trim((string) env('QBO_ENVIRONMENT', 'sandbox')));

    return $env === 'production' ? 'production' : 'sandbox';
}

function qbo_environment_label(): string
{
    return qbo_environment() === 'production' ? 'Production' : 'Sandbox';
}

function qbo_uses_sandbox_oauth(): bool
{
    return qbo_environment() !== 'production';
}

/**
 * Resolve an inventory COA account setting for the active QBO_ENVIRONMENT.
 * Prefer env-specific keys so sandbox + production Ids can both live in App Settings:
 *   QBO_INV_ASSET_ACCOUNT_CART_SANDBOX / QBO_INV_ASSET_ACCOUNT_CART_PROD
 * Legacy unsuffixed keys remain a fallback for the active environment.
 */
function qbo_inv_account_setting(string $baseKey): string
{
    $baseKey = trim($baseKey);
    if ($baseKey === '') {
        return '';
    }

    if (qbo_environment() === 'production') {
        return trim((string) env_first([$baseKey . '_PROD', $baseKey], ''));
    }

    return trim((string) env_first([$baseKey . '_SANDBOX', $baseKey], ''));
}

function qbo_client_id(): string
{
    if (qbo_environment() === 'production') {
        return trim((string) env_first(['QBO_CLIENT_ID_PROD', 'QBO_CLIENT_ID'], ''));
    }

    return trim((string) env('QBO_CLIENT_ID', ''));
}

function qbo_client_secret(): string
{
    if (qbo_environment() === 'production') {
        return (string) env_first(['QBO_CLIENT_SECRET_PROD', 'QBO_CLIENT_SECRET'], '');
    }

    return (string) env('QBO_CLIENT_SECRET', '');
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
    return qbo_config_error() === null;
}

function qbo_config_error(): ?string
{
    $clientId = qbo_client_id();
    $clientSecret = qbo_client_secret();
    $redirectUri = qbo_redirect_uri();

    if ($clientId === '' || $clientSecret === '') {
        if (qbo_environment() === 'production') {
            return 'QuickBooks production OAuth is not configured. Set QBO_ENVIRONMENT=production and add QBO_CLIENT_ID_PROD plus QBO_CLIENT_SECRET_PROD from the Production keys section of your Intuit Developer app (NutraAxis_Operations).';
        }

        return 'QuickBooks is not configured. Set QBO_CLIENT_ID, QBO_CLIENT_SECRET, and QBO_REDIRECT_URI in application settings.';
    }

    if ($redirectUri === '') {
        return 'QuickBooks redirect URI is not configured. Set QBO_REDIRECT_URI or SITE_URL in application settings.';
    }

    return null;
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

    if ($row === false) {
        return null;
    }

    $configuredEnv = qbo_environment();
    $storedEnv = strtolower(trim((string) ($row['Environment'] ?? '')));
    if ($storedEnv !== '' && $storedEnv !== $configuredEnv) {
        qbo_disconnect();

        return null;
    }

    return $row;
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
        'client_id'     => qbo_client_id(),
        'response_type' => 'code',
        'scope'         => 'com.intuit.quickbooks.accounting',
        'redirect_uri'  => qbo_redirect_uri(),
        'state'         => $state,
    ]);

    return 'https://appcenter.intuit.com/connect/oauth2?' . $params;
}

function qbo_token_request(array $fields): array
{
    $clientId = qbo_client_id();
    $clientSecret = qbo_client_secret();

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

function qbo_api_request(string $method, string $path, ?array $query = null, ?array $body = null): array
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

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . (string) $connection['AccessToken'],
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 45,
    ];
    if ($body !== null) {
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return ['ok' => false, 'error' => 'Unable to encode QuickBooks request payload.', 'data' => null];
        }
        $curlOptions[CURLOPT_POSTFIELDS] = $encoded;
    }
    curl_setopt_array($ch, $curlOptions);

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
        $rawMessage = qbo_api_format_fault_message($data);

        return [
            'ok'        => false,
            'error'     => qbo_humanize_error($rawMessage),
            'raw_error' => $rawMessage,
            'data'      => $data,
            'status'    => $status,
        ];
    }

    return ['ok' => true, 'error' => null, 'data' => $data];
}

function qbo_api_upload_request(string $fileName, string $contentType, string $fileContent, array $metadata): array
{
    $configError = qbo_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'data' => null];
    }

    $tokenResult = qbo_ensure_access_token();
    if (!$tokenResult['ok']) {
        return $tokenResult;
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to QuickBooks.', 'data' => null];
    }

    $connection = $tokenResult['connection'];
    $realmId = (string) $connection['RealmID'];
    $url = qbo_api_base_url() . '/v3/company/' . rawurlencode($realmId) . '/upload?minorversion=65';

    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
    if ($metadataJson === false) {
        return ['ok' => false, 'error' => 'Unable to encode QuickBooks attachment metadata.', 'data' => null];
    }

    $boundary = '-------------' . bin2hex(random_bytes(12));
    $safeFileName = str_replace(['"', "\r", "\n"], '', $fileName);
    $body = ''
        . "--{$boundary}\r\n"
        . 'Content-Disposition: form-data; name="file_content_0"; filename="' . $safeFileName . "\"\r\n"
        . 'Content-Type: ' . $contentType . "\r\n"
        . "Content-Transfer-Encoding: binary\r\n\r\n"
        . $fileContent . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"file_metadata_0\"\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $metadataJson . "\r\n"
        . "--{$boundary}--\r\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Bearer ' . (string) $connection['AccessToken'],
            'Content-Type: multipart/form-data; boundary=' . $boundary,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT    => 120,
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
        return ['ok' => false, 'error' => 'QuickBooks returned an unexpected upload response.', 'data' => null];
    }

    if ($status >= 400) {
        $rawMessage = qbo_api_format_fault_message($data);

        return [
            'ok'        => false,
            'error'     => qbo_humanize_error($rawMessage),
            'raw_error' => $rawMessage,
            'data'      => $data,
            'status'    => $status,
        ];
    }

    return ['ok' => true, 'error' => null, 'data' => $data];
}

function qbo_list_entity_attachables(string $entityType, string $entityId): array
{
    $entityType = trim($entityType);
    $entityId = trim($entityId);
    if ($entityType === '' || $entityId === '') {
        return ['ok' => false, 'error' => 'Entity type and ID are required.', 'attachables' => []];
    }

    $escapedId = str_replace("'", "\\'", $entityId);
    $escapedType = str_replace("'", "\\'", $entityType);
    $queries = [
        "SELECT Id, FileName, Size, SyncToken, Note, ContentType FROM Attachable WHERE AttachableRef.EntityRef.Type = '{$escapedType}' AND AttachableRef.EntityRef.value = '{$escapedId}'",
        "SELECT Id, FileName, Size, SyncToken, Note, ContentType FROM Attachable WHERE AttachableRef.EntityRef.value = '{$escapedId}'",
    ];

    foreach ($queries as $sql) {
        $result = qbo_query($sql, 100);
        if (!$result['ok']) {
            continue;
        }

        $rows = qbo_extract_rows($result['data'] ?? [], ['Attachable']);
        if ($rows !== []) {
            return ['ok' => true, 'error' => null, 'attachables' => $rows];
        }
    }

    return ['ok' => true, 'error' => null, 'attachables' => []];
}

function qbo_delete_attachable(string $attachableId, string $syncToken): array
{
    $attachableId = trim($attachableId);
    $syncToken = trim($syncToken);
    if ($attachableId === '' || $syncToken === '') {
        return ['ok' => false, 'error' => 'QuickBooks attachment ID and sync token are required.'];
    }

    return qbo_api_request('POST', '/attachable', ['operation' => 'delete', 'minorversion' => 65], [
        'Id'        => $attachableId,
        'SyncToken' => $syncToken,
    ]);
}

function qbo_upload_entity_attachment(
    string $entityType,
    string $entityId,
    string $fileName,
    string $contentType,
    string $fileContent,
    ?string $note = null
): array {
    $entityType = trim($entityType);
    $entityId = trim($entityId);
    $fileName = trim($fileName);
    if ($entityType === '' || $entityId === '' || $fileName === '') {
        return ['ok' => false, 'error' => 'Entity, file name, and content are required for QuickBooks upload.'];
    }

    if ($fileContent === '') {
        return ['ok' => false, 'error' => 'Attachment file is empty.'];
    }

    $metadata = [
        'AttachableRef' => [[
            'EntityRef' => [
                'type'  => $entityType,
                'value' => $entityId,
            ],
        ]],
        'FileName'    => $fileName,
        'ContentType' => $contentType !== '' ? $contentType : 'application/octet-stream',
    ];
    if ($note !== null && trim($note) !== '') {
        $metadata['Note'] = trim($note);
    }

    $result = qbo_api_upload_request($fileName, $metadata['ContentType'], $fileContent, $metadata);
    if (!$result['ok']) {
        return $result;
    }

    $attachable = $result['data']['AttachableResponse'][0]['Attachable']
        ?? $result['data']['Attachable']
        ?? null;

    return [
        'ok'         => is_array($attachable),
        'error'      => is_array($attachable) ? null : 'QuickBooks did not return attachment details.',
        'attachable' => is_array($attachable) ? $attachable : null,
        'data'       => $result['data'],
    ];
}

function qbo_api_format_fault_message(array $data): string
{
    $fault = $data['Fault']['Error'][0] ?? $data['fault']['error'][0] ?? [];
    $message = trim((string) ($fault['Message'] ?? $fault['message'] ?? ''));
    $detail = trim((string) ($fault['Detail'] ?? $fault['detail'] ?? ''));

    if ($detail !== '') {
        if ($message !== '' && stripos($detail, $message) !== false) {
            return $detail;
        }

        return trim($message !== '' ? $message . ' ' . $detail : $detail);
    }

    return $message !== '' ? $message : 'QuickBooks request failed.';
}

function qbo_error_is_stale_object(string $message): bool
{
    return stripos($message, 'Stale Object') !== false;
}

function qbo_sku_item_type(): string
{
    $type = trim((string) env('QBO_SKU_ITEM_TYPE', 'Inventory'));

    return strcasecmp($type, 'NonInventory') === 0 ? 'NonInventory' : 'Inventory';
}

function qbo_item_matches_sku_sync_mode(?array $item): bool
{
    if (!is_array($item)) {
        return false;
    }

    $type = trim((string) ($item['Type'] ?? ''));

    return qbo_sku_uses_inventory_tracking()
        ? strcasecmp($type, 'Inventory') === 0
        : strcasecmp($type, 'NonInventory') === 0;
}

function qbo_sku_uses_inventory_tracking(): bool
{
    return qbo_sku_item_type() === 'Inventory';
}

function qbo_sku_sync_mode_label(): string
{
    return qbo_sku_uses_inventory_tracking()
        ? 'Inventory (quantity tracking in QuickBooks)'
        : 'Non-inventory product (Essentials-compatible)';
}

function qbo_error_is_inventory_subscription_limit(string $message): bool
{
    $message = strtolower($message);

    return str_contains($message, 'feature not supported')
        || (str_contains($message, 'essentials') && str_contains($message, 'not included'))
        || str_contains($message, 'inventory') && str_contains($message, 'subscription');
}

function qbo_humanize_error(string $message): string
{
    if (str_starts_with($message, 'QuickBooks rejected the update because')) {
        return $message;
    }

    if (qbo_error_is_inventory_subscription_limit($message)) {
        return 'QuickBooks Online Essentials does not support inventory items with quantity tracking. '
            . 'NutraAxis automatically syncs SKUs as Non-inventory product items on Essentials (no quantity tracking in QuickBooks). '
            . 'Upgrade to QuickBooks Online Plus or Advanced for full inventory quantity tracking.';
    }

    if (!qbo_error_is_stale_object($message)) {
        return $message;
    }

    $entity = stripos($message, 'item') !== false ? 'item' : 'vendor';

    return 'QuickBooks rejected the update because this ' . $entity . ' was changed in QuickBooks since the last sync. '
        . 'The name shown in the error is whoever QuickBooks recorded as the last editor — not necessarily someone working in NutraAxis right now. '
        . 'Save again or click Sync to QuickBooks to retry with the latest QuickBooks version.';
}

function qbo_sync_show_exception_detail(): bool
{
    return !env_is_azure_hosted() || qbo_uses_sandbox_oauth();
}

function qbo_sync_format_exception(Throwable $e, string $action = 'sync this supplier to QuickBooks'): string
{
    $raw = trim($e->getMessage());
    error_log("QBO supplier sync error while trying to {$action} ({$e->getFile()}:{$e->getLine()}): {$raw}");

    $detail = preg_replace('/^SQLSTATE\[[^\]]+\]:\s*(?:General error:\s*)?/i', '', $raw) ?? $raw;
    $detail = preg_replace('/\s*\[\d+\]\s*\(severity \d+\)\s*\[.*$/s', '', $detail) ?? $detail;
    $detail = trim($detail);

    if ($detail === '') {
        $detail = 'An unexpected error occurred.';
    }

    $hints = [];
    if (stripos($raw, 'COUNT field incorrect') !== false || stripos($raw, 'Invalid parameter number') !== false) {
        $hints[] = 'This is often an ODBC parameter-binding issue.';
    }
    if (stripos($raw, 'Invalid column name') !== false) {
        $hints[] = 'Run the latest SKUMaster or Supplier QBO migration on the database.';
    }

    $message = "QuickBooks sync failed: {$detail}";
    if ($hints !== []) {
        $message .= ' ' . implode(' ', $hints);
    }

    return $message;
}

function qbo_fetch_vendor(string $vendorId): array
{
    $vendorId = trim($vendorId);
    if ($vendorId === '') {
        return ['ok' => false, 'error' => 'Vendor ID is required.', 'vendor' => null];
    }

    $result = qbo_api_request('GET', '/vendor/' . rawurlencode($vendorId), ['minorversion' => 65]);
    if (!$result['ok']) {
        return [
            'ok'     => false,
            'error'  => $result['error'] ?? 'Unable to read vendor from QuickBooks.',
            'vendor' => null,
        ];
    }

    $vendor = qbo_extract_vendor($result['data']);
    if ($vendor === null) {
        return ['ok' => false, 'error' => 'QuickBooks did not return vendor details.', 'vendor' => null];
    }

    return ['ok' => true, 'error' => null, 'vendor' => $vendor];
}

function qbo_find_vendor_by_display_name(string $displayName): array
{
    $displayName = trim($displayName);
    if ($displayName === '') {
        return ['ok' => false, 'error' => 'Display name is required.', 'vendor' => null];
    }

    $escaped = str_replace("'", "\\'", $displayName);
    $result = qbo_query("SELECT Id, SyncToken, DisplayName, Active FROM Vendor WHERE DisplayName = '" . $escaped . "'");
    if (!$result['ok']) {
        return [
            'ok'     => false,
            'error'  => $result['error'] ?? 'Unable to search QuickBooks vendors.',
            'vendor' => null,
        ];
    }

    $rows = qbo_extract_rows($result['data'], ['Vendor']);
    $vendor = is_array($rows[0] ?? null) ? $rows[0] : null;
    if ($vendor === null) {
        return ['ok' => false, 'error' => 'Vendor not found in QuickBooks.', 'vendor' => null];
    }

    return ['ok' => true, 'error' => null, 'vendor' => $vendor];
}

function qbo_apply_fresh_vendor_identity(array $payload, ?array $freshVendor): array
{
    if (!is_array($freshVendor)) {
        return $payload;
    }

    $vendorId = trim((string) ($freshVendor['Id'] ?? ''));
    $syncToken = trim((string) ($freshVendor['SyncToken'] ?? ''));
    if ($vendorId !== '') {
        $payload['Id'] = $vendorId;
    }
    if ($syncToken !== '') {
        $payload['SyncToken'] = $syncToken;
    }

    return $payload;
}

function qbo_load_fresh_vendor_for_supplier(array $supplier): ?array
{
    $qboId = trim((string) ($supplier['QBO_SupplierID'] ?? ''));
    if ($qboId !== '') {
        $fetch = qbo_fetch_vendor($qboId);

        return $fetch['ok'] ? ($fetch['vendor'] ?? null) : null;
    }

    $displayName = trim((string) ($supplier['QBO_DisplayName'] ?? $supplier['SupplierName'] ?? ''));
    if ($displayName === '') {
        return null;
    }

    $fetch = qbo_find_vendor_by_display_name($displayName);

    return $fetch['ok'] ? ($fetch['vendor'] ?? null) : null;
}

function qbo_create_bill_from_supplier_invoice(int $invoiceId): array
{
    require_once __DIR__ . '/supplier-invoice.php';

    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }

    $lines = supplier_invoice_get_lines($invoiceId);
    if ($lines === []) {
        return ['ok' => false, 'error' => 'Supplier invoice has no line items.'];
    }

    $billLines = [];
    foreach ($lines as $line) {
        $billLine = [
            'Amount'     => (float) $line['Amount'],
            'DetailType' => (string) $line['DetailType'],
        ];
        if (!empty($line['Description'])) {
            $billLine['Description'] = (string) $line['Description'];
        }

        if ($line['DetailType'] === 'AccountBasedExpenseLineDetail') {
            $billLine['AccountBasedExpenseLineDetail'] = [
                'AccountRef' => ['value' => (string) $line['AccountRefValue']],
            ];
        } else {
            $detail = [
                'ItemRef' => ['value' => (string) $line['ItemRefValue']],
            ];
            if ($line['Qty'] !== null) {
                $detail['Qty'] = (float) $line['Qty'];
            }
            if ($line['UnitPrice'] !== null) {
                $detail['UnitPrice'] = (float) $line['UnitPrice'];
            }
            $billLine['ItemBasedExpenseLineDetail'] = $detail;
        }

        $billLines[] = $billLine;
    }

    $payload = [
        'VendorRef' => ['value' => (string) $invoice['VendorRefValue']],
        'TxnDate'   => (string) $invoice['TxnDate'],
        'Line'      => $billLines,
    ];

    if (!empty($invoice['DocNumber'])) {
        $payload['DocNumber'] = (string) $invoice['DocNumber'];
    }
    if (!empty($invoice['DueDate'])) {
        $payload['DueDate'] = (string) $invoice['DueDate'];
    }
    if (!empty($invoice['APAccountRefValue'])) {
        $payload['APAccountRef'] = ['value' => (string) $invoice['APAccountRefValue']];
    }
    if (!empty($invoice['PrivateNote'])) {
        $payload['PrivateNote'] = (string) $invoice['PrivateNote'];
    }
    if (!empty($invoice['Memo'])) {
        $payload['PrivateNote'] = trim(((string) ($payload['PrivateNote'] ?? '')) . "\n" . (string) $invoice['Memo']);
    }
    if (!empty($invoice['GlobalTaxCalculation'])) {
        $payload['GlobalTaxCalculation'] = (string) $invoice['GlobalTaxCalculation'];
    }

    $result = qbo_api_request('POST', '/bill', ['minorversion' => 65], $payload);
    if (!$result['ok']) {
        return $result;
    }

    $bill = $result['data']['Bill'] ?? null;
    if (!is_array($bill)) {
        return ['ok' => false, 'error' => 'QuickBooks did not return a bill record.'];
    }

    $connection = qbo_get_connection();
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.SupplierInvoice
        SET QBO_BillId = :bill_id,
            QBO_SyncToken = :sync_token,
            QBO_RealmId = :realm_id,
            Balance = :balance,
            SyncStatus = N'Posted',
            LastSyncError = NULL,
            LastSyncAt = SYSUTCDATETIME(),
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierInvoiceID = :id
    SQL);
    $stmt->execute([
        'bill_id'    => (string) ($bill['Id'] ?? ''),
        'sync_token' => (string) ($bill['SyncToken'] ?? ''),
        'realm_id'   => (string) ($connection['RealmID'] ?? ''),
        'balance'    => isset($bill['Balance']) ? (float) $bill['Balance'] : (float) $invoice['TotalAmt'],
        'id'         => $invoiceId,
    ]);

    return ['ok' => true, 'error' => null, 'bill_id' => (string) ($bill['Id'] ?? '')];
}

function qbo_create_bill_payment_from_invoice_payment(int $paymentId): array
{
    require_once __DIR__ . '/po-payment.php';
    require_once __DIR__ . '/supplier-invoice.php';

    $payment = po_payment_get($paymentId);
    if ($payment === null || empty($payment['SupplierInvoiceID'])) {
        return ['ok' => false, 'error' => 'Invoice payment not found.'];
    }

    $invoice = supplier_invoice_get((int) $payment['SupplierInvoiceID']);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Linked supplier invoice not found.'];
    }

    $billId = trim((string) ($invoice['QBO_BillId'] ?? ''));
    if ($billId === '') {
        return ['ok' => false, 'error' => 'Supplier invoice has no QuickBooks bill ID. Post the bill before recording payment.'];
    }

    $vendorId = trim((string) ($invoice['VendorRefValue'] ?? ''));
    if ($vendorId === '') {
        return ['ok' => false, 'error' => 'Supplier invoice is missing a QuickBooks vendor ID.'];
    }

    $amount = (float) ($payment['PaymentAmount'] ?? 0);
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Payment amount must be greater than zero.'];
    }

    $paymentType = (string) ($payment['PaymentType'] ?? 'Check');
    $payload = [
        'VendorRef' => ['value' => $vendorId],
        'TotalAmt'  => $amount,
        'Line'      => [[
            'Amount'    => $amount,
            'LinkedTxn' => [[
                'TxnId'   => $billId,
                'TxnType' => 'Bill',
            ]],
        ]],
    ];

    if ($paymentType === 'CC') {
        $ccAccountId = trim((string) env('QBO_PAYMENT_CC_ACCOUNT_ID', ''));
        if ($ccAccountId === '') {
            return ['ok' => false, 'error' => 'QBO_PAYMENT_CC_ACCOUNT_ID is not configured for credit card payments.'];
        }

        $payload['PayType'] = 'CreditCard';
        $payload['CreditCardPayment'] = [
            'CCAccountRef' => ['value' => $ccAccountId],
        ];
    } else {
        $bankAccountId = trim((string) env('QBO_PAYMENT_BANK_ACCOUNT_ID', ''));
        if ($bankAccountId === '') {
            return ['ok' => false, 'error' => 'QBO_PAYMENT_BANK_ACCOUNT_ID is not configured for check/ACH payments.'];
        }

        $payload['PayType'] = 'Check';
        $payload['CheckPayment'] = [
            'BankAccountRef' => ['value' => $bankAccountId],
        ];
    }

    if (!empty($payment['PaymentConfNumber'])) {
        $payload['DocNumber'] = (string) $payment['PaymentConfNumber'];
    }

    $paymentDate = trim((string) ($payment['PaymentDate'] ?? ''));
    if ($paymentDate !== '') {
        $payload['TxnDate'] = substr($paymentDate, 0, 10);
    }

    $result = qbo_api_request('POST', '/billpayment', ['minorversion' => 65], $payload);
    if (!$result['ok']) {
        return $result;
    }

    $billPayment = $result['data']['BillPayment'] ?? null;
    if (!is_array($billPayment)) {
        return ['ok' => false, 'error' => 'QuickBooks did not return a bill payment record.'];
    }

    return [
        'ok'               => true,
        'error'            => null,
        'bill_payment_id'  => (string) ($billPayment['Id'] ?? ''),
    ];
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

function qbo_list_product_items(int $pageSize = 100, int $maxPages = 100): array
{
    if (!qbo_is_connected()) {
        return ['ok' => false, 'error' => 'QuickBooks is not connected.', 'rows' => []];
    }

    $baseSql = "SELECT * FROM Item WHERE Type IN ('Inventory', 'NonInventory') ORDERBY Name";
    $rows = [];
    $start = 1;

    for ($page = 0; $page < $maxPages; $page++) {
        $sql = $baseSql . ' STARTPOSITION ' . $start . ' MAXRESULTS ' . $pageSize;
        $result = qbo_api_request('GET', '/query', [
            'query'        => $sql,
            'minorversion' => 65,
        ]);
        if (!$result['ok']) {
            if ($rows !== []) {
                return ['ok' => true, 'error' => null, 'rows' => $rows];
            }

            return [
                'ok'    => false,
                'error' => $result['error'] ?? 'Unable to load inventory items from QuickBooks.',
                'rows'  => [],
            ];
        }

        $batch = qbo_extract_rows($result['data'] ?? [], ['Item']);
        if ($batch === []) {
            break;
        }

        foreach ($batch as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        if (count($batch) < $pageSize) {
            break;
        }

        $start += $pageSize;
    }

    return ['ok' => true, 'error' => null, 'rows' => $rows];
}

function qbo_list_inventory_items(int $pageSize = 100, int $maxPages = 100): array
{
    return qbo_list_product_items($pageSize, $maxPages);
}

function qbo_list_vendors(): array
{
    $result = qbo_query('SELECT * FROM Vendor ORDERBY DisplayName');
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null, 'rows' => qbo_extract_rows($result['data'], ['Vendor'])];
}

function qbo_list_payment_terms(): array
{
    $result = qbo_query('SELECT Id, Name, Active FROM Term ORDERBY Name', 200);
    if (!$result['ok']) {
        return $result;
    }

    $rows = qbo_extract_rows($result['data'], ['Term']);
    $terms = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (array_key_exists('Active', $row) && $row['Active'] === false) {
            continue;
        }

        $id = trim((string) ($row['Id'] ?? ''));
        $name = trim((string) ($row['Name'] ?? ''));
        if ($id === '' || $name === '') {
            continue;
        }

        $terms[] = ['id' => $id, 'name' => $name];
    }

    return ['ok' => true, 'error' => null, 'terms' => $terms];
}

function qbo_coa_realm_id(): ?string
{
    $connection = qbo_get_connection();
    if ($connection !== null) {
        $realmId = trim((string) ($connection['RealmID'] ?? ''));

        return $realmId !== '' ? $realmId : null;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query('SELECT TOP 1 RealmID FROM dbo.QBO_COA ORDER BY SyncedAt DESC');
        $row = $stmt->fetch();

        if ($row !== false) {
            $realmId = trim((string) ($row['RealmID'] ?? ''));

            return $realmId !== '' ? $realmId : null;
        }
    } catch (Throwable) {
        // Cache may not exist yet.
    }

    return null;
}

function qbo_coa_row_to_account(array $row): array
{
    return [
        'Id'                            => (string) ($row['QBO_AccountId'] ?? ''),
        'AcctNum'                       => $row['AcctNum'] ?? null,
        'Name'                          => (string) ($row['Name'] ?? ''),
        'FullyQualifiedName'            => $row['FullyQualifiedName'] ?? null,
        'AccountType'                   => $row['AccountType'] ?? null,
        'AccountSubType'                => $row['AccountSubType'] ?? null,
        'Classification'                => $row['Classification'] ?? null,
        'CurrentBalance'                => $row['CurrentBalance'] !== null ? (float) $row['CurrentBalance'] : null,
        'CurrentBalanceWithSubAccounts' => $row['CurrentBalanceWithSubAccounts'] !== null
            ? (float) $row['CurrentBalanceWithSubAccounts']
            : null,
        'Active'                        => !empty($row['Active']),
        'Description'                   => $row['Description'] ?? null,
        'SyncToken'                     => $row['QBO_SyncToken'] ?? null,
    ];
}

function qbo_coa_list_accounts(bool $activeOnly = true): array
{
    try {
        $pdo = db();
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Database is not available.', 'rows' => [], 'synced_at' => null];
    }

    $realmId = qbo_coa_realm_id();
    $sql = <<<SQL
        SELECT
            QBO_AccountId,
            QBO_SyncToken,
            Name,
            AcctNum,
            FullyQualifiedName,
            AccountType,
            AccountSubType,
            Classification,
            CurrentBalance,
            CurrentBalanceWithSubAccounts,
            Active,
            Description,
            SyncedAt
        FROM dbo.QBO_COA
    SQL;
    $params = [];
    $clauses = [];

    if ($realmId !== null) {
        $clauses[] = 'RealmID = :realm_id';
        $params['realm_id'] = $realmId;
    }

    if ($activeOnly) {
        $clauses[] = 'Active = 1';
    }

    if ($clauses !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    $sql .= ' ORDER BY Name';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Chart of accounts cache is not available.', 'rows' => [], 'synced_at' => null];
    }

    $syncedAt = null;
    foreach ($rows as $row) {
        if (!empty($row['SyncedAt'])) {
            $syncedAt = (string) $row['SyncedAt'];
            break;
        }
    }

    return [
        'ok'        => true,
        'error'     => null,
        'rows'      => array_map('qbo_coa_row_to_account', $rows),
        'synced_at' => $syncedAt,
        'source'    => 'qbo_coa',
    ];
}

function qbo_list_accounts(): array
{
    return qbo_coa_list_accounts();
}

function qbo_extract_vendor(?array $data): ?array
{
    if (!is_array($data)) {
        return null;
    }

    if (isset($data['Vendor']) && is_array($data['Vendor'])) {
        return $data['Vendor'];
    }

    if (isset($data['QueryResponse']['Vendor'][0]) && is_array($data['QueryResponse']['Vendor'][0])) {
        return $data['QueryResponse']['Vendor'][0];
    }

    if (isset($data['QueryResponse']['Vendor']) && is_array($data['QueryResponse']['Vendor']) && !isset($data['QueryResponse']['Vendor'][0])) {
        return $data['QueryResponse']['Vendor'];
    }

    return null;
}

function qbo_reconcile_supplier_vendor(int $supplierId, array $supplier): ?array
{
    $freshVendor = qbo_load_fresh_vendor_for_supplier($supplier);
    if (!is_array($freshVendor)) {
        return null;
    }

    $vendorId = trim((string) ($freshVendor['Id'] ?? ''));
    if ($vendorId === '') {
        return null;
    }

    $fullFetch = qbo_fetch_vendor($vendorId);
    $vendor = ($fullFetch['ok'] && is_array($fullFetch['vendor'])) ? $fullFetch['vendor'] : $freshVendor;

    try {
        supplier_apply_qbo_vendor_response($supplierId, $vendor);
    } catch (Throwable) {
        return null;
    }

    return $vendor;
}

function qbo_sync_supplier(int $supplierId): array
{
    require_once __DIR__ . '/supplier.php';

    try {
        if (!qbo_is_connected()) {
            return ['ok' => false, 'error' => 'QuickBooks is not connected.'];
        }

        $supplier = supplier_get($supplierId);
        if ($supplier === null) {
            return ['ok' => false, 'error' => 'Supplier not found.'];
        }

        supplier_mark_qbo_sync($supplierId, 'Pending');

        $supplier = supplier_prepare_for_qbo_sync($supplier);
        $payload = supplier_build_qbo_vendor_payload($supplier);
        if (trim((string) ($payload['DisplayName'] ?? '')) === '') {
            supplier_mark_qbo_sync($supplierId, 'Error', 'Supplier name is required for QuickBooks sync.');

            return ['ok' => false, 'error' => 'Supplier name is required for QuickBooks sync.'];
        }

        $hadQboId = trim((string) ($supplier['QBO_SupplierID'] ?? '')) !== '';
        $freshVendor = qbo_load_fresh_vendor_for_supplier($supplier);
        if (is_array($freshVendor)) {
            supplier_store_qbo_vendor_identity($supplierId, $freshVendor);
            $payload = qbo_apply_fresh_vendor_identity($payload, $freshVendor);
        }

        $result = qbo_api_request('POST', '/vendor', ['minorversion' => 65], $payload);
        $rawError = (string) ($result['raw_error'] ?? $result['error'] ?? '');
        if (!$result['ok'] && qbo_error_is_stale_object($rawError)) {
            $freshVendor = qbo_load_fresh_vendor_for_supplier($supplier);
            if (is_array($freshVendor)) {
                supplier_store_qbo_vendor_identity($supplierId, $freshVendor);
                $payload = qbo_apply_fresh_vendor_identity(
                    supplier_build_qbo_vendor_payload($supplier),
                    $freshVendor
                );
                $result = qbo_api_request('POST', '/vendor', ['minorversion' => 65], $payload);
            }
        }

        if (!$result['ok']) {
            $reconciled = qbo_reconcile_supplier_vendor($supplierId, $supplier);
            if ($reconciled !== null) {
                return [
                    'ok'          => true,
                    'error'       => null,
                    'warning'     => 'QuickBooks reported a sync conflict, but the vendor is present in QuickBooks and has been linked here.',
                    'reconciled'  => true,
                    'action'      => 'reconciled',
                    'vendor'      => $reconciled,
                ];
            }

            $error = (string) ($result['error'] ?? 'QuickBooks vendor sync failed.');
            supplier_mark_qbo_sync($supplierId, 'Error', $error);

            return ['ok' => false, 'error' => $error];
        }

        $vendor = qbo_extract_vendor($result['data']);
        if ($vendor === null || empty($vendor['Id'])) {
            $reconciled = qbo_reconcile_supplier_vendor($supplierId, $supplier);
            if ($reconciled !== null) {
                return [
                    'ok'         => true,
                    'error'      => null,
                    'warning'    => 'QuickBooks saved the vendor, but returned an unexpected response. The vendor has been linked from QuickBooks.',
                    'reconciled' => true,
                    'action'     => 'reconciled',
                    'vendor'     => $reconciled,
                ];
            }

            supplier_mark_qbo_sync($supplierId, 'Error', 'QuickBooks did not return a vendor ID.');

            return ['ok' => false, 'error' => 'QuickBooks did not return a vendor ID.'];
        }

        try {
            supplier_apply_qbo_vendor_response($supplierId, $vendor);
        } catch (Throwable $e) {
            $reconciled = qbo_reconcile_supplier_vendor($supplierId, $supplier);
            if ($reconciled !== null) {
                return [
                    'ok'         => true,
                    'error'      => null,
                    'warning'    => 'QuickBooks saved the vendor, but NutraAxis could not store every sync field. The vendor link was refreshed from QuickBooks.',
                    'reconciled' => true,
                    'action'     => 'reconciled',
                    'vendor'     => $reconciled,
                ];
            }

            supplier_mark_qbo_sync($supplierId, 'Error', 'Unable to save QuickBooks sync results locally.');

            return ['ok' => false, 'error' => 'QuickBooks saved the vendor, but NutraAxis could not save the sync result.'];
        }

        return [
            'ok'      => true,
            'error'   => null,
            'action'  => $hadQboId || is_array($freshVendor) ? 'updated' : 'created',
            'vendor'  => $vendor,
        ];
    } catch (Throwable $e) {
        $errorMsg = qbo_sync_format_exception($e);
        try {
            supplier_mark_qbo_sync($supplierId, 'Error', mb_substr($errorMsg, 0, 500));
        } catch (Throwable) {
            // ignore secondary persistence failure
        }

        return ['ok' => false, 'error' => $errorMsg];
    }
}

function qbo_extract_item(?array $data): ?array
{
    if (!is_array($data)) {
        return null;
    }

    if (isset($data['Item']) && is_array($data['Item'])) {
        return $data['Item'];
    }

    if (isset($data['QueryResponse']['Item'][0]) && is_array($data['QueryResponse']['Item'][0])) {
        return $data['QueryResponse']['Item'][0];
    }

    if (isset($data['QueryResponse']['Item']) && is_array($data['QueryResponse']['Item']) && !isset($data['QueryResponse']['Item'][0])) {
        return $data['QueryResponse']['Item'];
    }

    return null;
}

function qbo_fetch_item(string $itemId): array
{
    $itemId = trim($itemId);
    if ($itemId === '') {
        return ['ok' => false, 'error' => 'Item ID is required.', 'item' => null];
    }

    $result = qbo_api_request('GET', '/item/' . rawurlencode($itemId), ['minorversion' => 65]);
    if (!$result['ok']) {
        return [
            'ok'    => false,
            'error' => $result['error'] ?? 'Unable to read item from QuickBooks.',
            'item'  => null,
        ];
    }

    $item = qbo_extract_item($result['data']);
    if ($item === null) {
        return ['ok' => false, 'error' => 'QuickBooks did not return item details.', 'item' => null];
    }

    return ['ok' => true, 'error' => null, 'item' => $item];
}

function qbo_item_search_types(): array
{
    $preferred = qbo_sku_item_type();
    if ($preferred === 'NonInventory') {
        return ['NonInventory'];
    }

    return array_values(array_unique([$preferred, 'Inventory', 'NonInventory']));
}

function qbo_find_item_by_field(string $field, string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return ['ok' => false, 'error' => 'Search value is required.', 'item' => null];
    }

    if (!in_array($field, ['Sku', 'Name'], true)) {
        return ['ok' => false, 'error' => 'Unsupported QuickBooks item search field.', 'item' => null];
    }

    $escaped = str_replace("'", "\\'", $value);
    $select = 'SELECT Id, SyncToken, Name, Sku, Active, Type FROM Item WHERE ' . $field . " = '" . $escaped . "'";
    $lastError = 'Item not found in QuickBooks.';

    foreach (qbo_item_search_types() as $type) {
        $result = qbo_query($select . " AND Type = '" . $type . "'");
        if (!$result['ok']) {
            $lastError = $result['error'] ?? 'Unable to search QuickBooks items.';

            continue;
        }

        $rows = qbo_extract_rows($result['data'], ['Item']);
        $item = is_array($rows[0] ?? null) ? $rows[0] : null;
        if ($item !== null) {
            return ['ok' => true, 'error' => null, 'item' => $item];
        }
    }

    $result = qbo_query($select);
    if (!$result['ok']) {
        return [
            'ok'    => false,
            'error' => $result['error'] ?? $lastError,
            'item'  => null,
        ];
    }

    $rows = qbo_extract_rows($result['data'], ['Item']);
    $item = is_array($rows[0] ?? null) ? $rows[0] : null;
    if ($item === null) {
        return ['ok' => false, 'error' => $lastError, 'item' => null];
    }

    return ['ok' => true, 'error' => null, 'item' => $item];
}

function qbo_find_item_by_sku(string $sku): array
{
    $sku = trim($sku);
    if ($sku === '') {
        return ['ok' => false, 'error' => 'SKU is required.', 'item' => null];
    }

    $result = qbo_find_item_by_field('Sku', $sku);
    if (!$result['ok']) {
        $result['error'] = $result['error'] ?? 'Unable to search QuickBooks items by SKU.';
    }

    return $result;
}

function qbo_find_item_by_name(string $name): array
{
    $name = trim($name);
    if ($name === '') {
        return ['ok' => false, 'error' => 'Item name is required.', 'item' => null];
    }

    $result = qbo_find_item_by_field('Name', $name);
    if (!$result['ok']) {
        $result['error'] = $result['error'] ?? 'Unable to search QuickBooks items by name.';
    }

    return $result;
}

function qbo_apply_fresh_item_identity(array $payload, ?array $freshItem): array
{
    if (!is_array($freshItem)) {
        return $payload;
    }

    $itemId = trim((string) ($freshItem['Id'] ?? ''));
    $syncToken = trim((string) ($freshItem['SyncToken'] ?? ''));
    if ($itemId !== '') {
        $payload['Id'] = $itemId;
    }
    if ($syncToken !== '') {
        $payload['SyncToken'] = $syncToken;
    }

    return $payload;
}

function qbo_load_fresh_item_for_sku(array $sku): ?array
{
    $qboId = trim((string) ($sku['QBO_ItemID'] ?? ''));
    if ($qboId !== '') {
        $fetch = qbo_fetch_item($qboId);
        if ($fetch['ok'] && qbo_item_matches_sku_sync_mode($fetch['item'] ?? null)) {
            return $fetch['item'] ?? null;
        }
    }

    $skuCode = trim((string) ($sku['SKUCode'] ?? ''));
    if ($skuCode !== '') {
        $fetch = qbo_find_item_by_sku($skuCode);
        if ($fetch['ok'] && is_array($fetch['item'])) {
            return $fetch['item'];
        }
    }

    require_once __DIR__ . '/catalog.php';
    $displayName = catalog_build_qbo_item_name($sku);
    if ($displayName === '') {
        return null;
    }

    $fetch = qbo_find_item_by_name($displayName);

    return $fetch['ok'] ? ($fetch['item'] ?? null) : null;
}

function qbo_create_or_update_item(array $payload): array
{
    return qbo_api_request('POST', '/item', ['minorversion' => 65], $payload);
}

function qbo_reconcile_sku_item(int $skuId, array $sku): ?array
{
    require_once __DIR__ . '/catalog.php';

    $freshItem = qbo_load_fresh_item_for_sku($sku);
    if (!is_array($freshItem)) {
        return null;
    }

    $itemId = trim((string) ($freshItem['Id'] ?? ''));
    if ($itemId === '') {
        return null;
    }

    $fullFetch = qbo_fetch_item($itemId);
    $item = ($fullFetch['ok'] && is_array($fullFetch['item'])) ? $fullFetch['item'] : $freshItem;

    try {
        catalog_apply_qbo_item_response($skuId, $item);
    } catch (Throwable) {
        return null;
    }

    return $item;
}

function qbo_sync_sku_item_attempt(int $skuId, array $sku, bool $isCreate, ?array $freshItem, string $itemType): array
{
    $payload = catalog_build_qbo_item_payload($sku, $isCreate, $itemType);
    if (trim((string) ($payload['Name'] ?? '')) === '') {
        return [
            'ok'    => false,
            'error' => 'Item name is required for QuickBooks sync.',
            'payload' => $payload,
            'result'  => null,
        ];
    }

    if (is_array($freshItem)) {
        $payload = qbo_apply_fresh_item_identity($payload, $freshItem);
    }

    $result = qbo_create_or_update_item($payload);
    $rawError = (string) ($result['raw_error'] ?? $result['error'] ?? '');
    if (!$result['ok'] && qbo_error_is_stale_object($rawError)) {
        $freshItem = qbo_load_fresh_item_for_sku($sku);
        if (is_array($freshItem)) {
            catalog_store_qbo_item_identity($skuId, $freshItem);
            $sku = catalog_get_sku($skuId) ?? $sku;
            $payload = qbo_apply_fresh_item_identity(
                catalog_build_qbo_item_payload($sku, false, $itemType),
                $freshItem
            );
            $result = qbo_create_or_update_item($payload);
        }
    }

    return [
        'ok'      => (bool) ($result['ok'] ?? false),
        'error'   => (string) ($result['error'] ?? 'QuickBooks item sync failed.'),
        'raw_error' => (string) ($result['raw_error'] ?? $result['error'] ?? ''),
        'payload' => $payload,
        'result'  => $result,
        'sku'     => $sku,
    ];
}

function qbo_sync_sku_to_quickbooks(int $skuId): array
{
    require_once __DIR__ . '/catalog.php';

    try {
        if (!qbo_is_connected()) {
            return ['ok' => false, 'error' => 'QuickBooks is not connected.'];
        }

        $sku = catalog_get_sku($skuId);
        if ($sku === null) {
            return ['ok' => false, 'error' => 'SKU not found.'];
        }

        $validationError = catalog_validate_qbo_item_ready($sku);
        if ($validationError !== null) {
            catalog_mark_qbo_sync($skuId, 'Error', $validationError);

            return ['ok' => false, 'error' => $validationError];
        }

        catalog_mark_qbo_sync($skuId, 'Pending');

        $freshItem = qbo_load_fresh_item_for_sku($sku);
        if (is_array($freshItem)) {
            catalog_store_qbo_item_identity($skuId, $freshItem);
            $sku = catalog_get_sku($skuId) ?? $sku;
        }

        $hadQboId = trim((string) ($sku['QBO_ItemID'] ?? '')) !== '';
        $isCreate = !$hadQboId && !is_array($freshItem);
        $syncWarning = null;
        $attempt = qbo_sync_sku_item_attempt(
            $skuId,
            $sku,
            $isCreate,
            $freshItem,
            qbo_sku_uses_inventory_tracking() ? 'Inventory' : 'NonInventory'
        );
        $sku = $attempt['sku'] ?? $sku;

        if ($attempt['result'] === null) {
            catalog_mark_qbo_sync($skuId, 'Error', $attempt['error']);

            return ['ok' => false, 'error' => $attempt['error']];
        }

        $result = $attempt['result'];

        if (!$attempt['ok'] && qbo_sku_uses_inventory_tracking()
            && qbo_error_is_inventory_subscription_limit($attempt['raw_error'])) {
            $freshItem = qbo_load_fresh_item_for_sku($sku);
            $fallbackAttempt = qbo_sync_sku_item_attempt(
                $skuId,
                $sku,
                $isCreate && !is_array($freshItem),
                $freshItem,
                'NonInventory'
            );
            $sku = $fallbackAttempt['sku'] ?? $sku;
            if ($fallbackAttempt['result'] !== null) {
                $result = $fallbackAttempt['result'];
            }
            if ($fallbackAttempt['ok']) {
                $syncWarning = 'QuickBooks Online Essentials does not support inventory quantity tracking. '
                    . 'This SKU was synced as a Non-inventory product item (pricing and accounts preserved; no quantity on hand in QuickBooks). '
                    . 'Upgrade to QuickBooks Online Plus or Advanced for inventory items with quantity tracking.';
            }
        }

        if (!$result['ok']) {
            $reconciled = qbo_reconcile_sku_item($skuId, $sku);
            if ($reconciled !== null) {
                return [
                    'ok'         => true,
                    'error'      => null,
                    'warning'    => 'QuickBooks reported a sync conflict, but the product item is present in QuickBooks and has been linked here.',
                    'reconciled' => true,
                    'action'     => 'reconciled',
                    'item'       => $reconciled,
                ];
            }

            $error = (string) ($result['error'] ?? 'QuickBooks item sync failed.');
            catalog_mark_qbo_sync($skuId, 'Error', $error);

            return ['ok' => false, 'error' => $error];
        }

        $item = qbo_extract_item($result['data']);
        if ($item === null || empty($item['Id'])) {
            $reconciled = qbo_reconcile_sku_item($skuId, $sku);
            if ($reconciled !== null) {
                return [
                    'ok'         => true,
                    'error'      => null,
                    'warning'    => 'QuickBooks saved the item, but returned an unexpected response. The item has been linked from QuickBooks.',
                    'reconciled' => true,
                    'action'     => 'reconciled',
                    'item'       => $reconciled,
                ];
            }

            catalog_mark_qbo_sync($skuId, 'Error', 'QuickBooks did not return an item ID.');

            return ['ok' => false, 'error' => 'QuickBooks did not return an item ID.'];
        }

        try {
            catalog_apply_qbo_item_response($skuId, $item);
        } catch (Throwable) {
            $reconciled = qbo_reconcile_sku_item($skuId, $sku);
            if ($reconciled !== null) {
                return [
                    'ok'         => true,
                    'error'      => null,
                    'warning'    => 'QuickBooks saved the item, but NutraAxis could not store every sync field. The item link was refreshed from QuickBooks.',
                    'reconciled' => true,
                    'action'     => 'reconciled',
                    'item'       => $reconciled,
                ];
            }

            catalog_mark_qbo_sync($skuId, 'Error', 'Unable to save QuickBooks sync results locally.');

            return ['ok' => false, 'error' => 'QuickBooks saved the item, but NutraAxis could not save the sync result.'];
        }

        $success = [
            'ok'     => true,
            'error'  => null,
            'action' => $hadQboId || is_array($freshItem) ? 'updated' : 'created',
            'item'   => $item,
        ];
        if ($syncWarning !== null) {
            $success['warning'] = $syncWarning;
        }

        return $success;
    } catch (Throwable $e) {
        $errorMsg = qbo_sync_format_exception($e, 'sync this SKU to QuickBooks');
        try {
            catalog_mark_qbo_sync($skuId, 'Error', mb_substr($errorMsg, 0, 500));
        } catch (Throwable) {
            // ignore secondary persistence failure
        }

        return ['ok' => false, 'error' => $errorMsg];
    }
}

function qbo_create_inventory_adjustment(array $payload): array
{
    return qbo_api_request('POST', '/inventoryadjustment', ['minorversion' => 65], $payload);
}

/**
 * Build and post a QBO InventoryAdjustment.
 *
 * @param array<int, array{qbo_item_id:string,qty_change:float|int|string,sku_code?:string}> $lines
 */
function qbo_post_inventory_adjustment_qty(
    string $docNumber,
    array $lines,
    string $adjustAccountId,
    ?string $privateNote = null
): array {
    $docNumber = trim($docNumber);
    if ($docNumber === '') {
        return ['ok' => false, 'error' => 'DocNumber is required for inventory adjustments.', 'txn' => null];
    }
    if ($lines === []) {
        return ['ok' => false, 'error' => 'At least one adjustment line is required.', 'txn' => null];
    }
    $adjustAccountId = trim($adjustAccountId);
    if ($adjustAccountId === '') {
        return ['ok' => false, 'error' => 'Inventory adjustment account Id is required.', 'txn' => null];
    }

    $detailLines = [];
    $lineId = 0;
    foreach ($lines as $line) {
        $itemId = trim((string) ($line['qbo_item_id'] ?? ''));
        $qty = (float) ($line['qty_change'] ?? 0);
        if ($itemId === '' || abs($qty) < 0.0000001) {
            continue;
        }
        $lineId++;
        $detailLines[] = [
            'Id' => (string) $lineId,
            'DetailType' => 'ItemAdjustmentLineDetail',
            'ItemAdjustmentLineDetail' => [
                'ItemRef' => ['value' => $itemId],
                'QtyDiff' => $qty,
            ],
        ];
    }

    if ($detailLines === []) {
        return ['ok' => false, 'error' => 'No valid inventory adjustment lines to post.', 'txn' => null];
    }

    $payload = [
        'DocNumber' => $docNumber,
        'TxnDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'AdjustAccountRef' => ['value' => $adjustAccountId],
        'Line' => $detailLines,
    ];
    if ($privateNote !== null && trim($privateNote) !== '') {
        $payload['PrivateNote'] = trim($privateNote);
    }

    $result = qbo_create_inventory_adjustment($payload);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Inventory adjustment failed.', 'txn' => null, 'data' => $result['data'] ?? null];
    }

    $txn = $result['data']['InventoryAdjustment'] ?? null;

    return ['ok' => true, 'error' => null, 'txn' => is_array($txn) ? $txn : null, 'data' => $result['data'] ?? null];
}

function qbo_create_journal_entry(array $payload): array
{
    return qbo_api_request('POST', '/journalentry', ['minorversion' => 65], $payload);
}

/**
 * Post a two-sided Journal Entry moving value between inventory asset accounts.
 */
function qbo_post_inventory_transfer_journal_entry(
    string $docNumber,
    string $debitAccountId,
    string $creditAccountId,
    float $amount,
    ?string $privateNote = null
): array {
    $docNumber = trim($docNumber);
    $debitAccountId = trim($debitAccountId);
    $creditAccountId = trim($creditAccountId);
    if ($docNumber === '' || $debitAccountId === '' || $creditAccountId === '') {
        return ['ok' => false, 'error' => 'DocNumber and both account Ids are required.', 'txn' => null];
    }
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Journal entry amount must be greater than zero.', 'txn' => null];
    }

    $payload = [
        'DocNumber' => $docNumber,
        'TxnDate' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'Line' => [
            [
                'Id' => '0',
                'Description' => $privateNote ?? 'Inventory facility transfer',
                'Amount' => round($amount, 2),
                'DetailType' => 'JournalEntryLineDetail',
                'JournalEntryLineDetail' => [
                    'PostingType' => 'Debit',
                    'AccountRef' => ['value' => $debitAccountId],
                ],
            ],
            [
                'Id' => '1',
                'Description' => $privateNote ?? 'Inventory facility transfer',
                'Amount' => round($amount, 2),
                'DetailType' => 'JournalEntryLineDetail',
                'JournalEntryLineDetail' => [
                    'PostingType' => 'Credit',
                    'AccountRef' => ['value' => $creditAccountId],
                ],
            ],
        ],
    ];
    if ($privateNote !== null && trim($privateNote) !== '') {
        $payload['PrivateNote'] = trim($privateNote);
    }

    $result = qbo_create_journal_entry($payload);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Journal entry failed.', 'txn' => null];
    }

    $txn = $result['data']['JournalEntry'] ?? null;

    return ['ok' => true, 'error' => null, 'txn' => is_array($txn) ? $txn : null];
}

function qbo_inventory_sync_log_exists(string $docNumber): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT 1 FROM dbo.QBOInventorySyncLog WHERE DocNumber = :doc');
    $stmt->execute(['doc' => $docNumber]);

    return (bool) $stmt->fetchColumn();
}

function qbo_inventory_sync_log_write(array $row): void
{
    qbo_inventory_sync_log_upsert($row);
}

/**
 * Insert or update a QBO inventory sync-log row by DocNumber (allows Error retries).
 */
function qbo_inventory_sync_log_upsert(array $row): void
{
    $pdo = db();
    $pdo->prepare(<<<SQL
        MERGE dbo.QBOInventorySyncLog AS target
        USING (SELECT :doc AS DocNumber) AS source
            ON target.DocNumber = source.DocNumber
        WHEN MATCHED THEN
            UPDATE SET
                SyncType = :sync_type,
                ReferenceType = :ref_type,
                ReferenceID = :ref_id,
                ReferenceLineKey = :line_key,
                SKUCode = :sku,
                QtyChange = :qty,
                FacilityCode = :facility,
                QBO_TxnId = :txn_id,
                QBO_SyncToken = :sync_token,
                SyncStatus = :status,
                SyncError = :error,
                SyncedAt = CASE WHEN :status2 = N'Synced' THEN SYSUTCDATETIME() ELSE NULL END
        WHEN NOT MATCHED THEN
            INSERT (
                DocNumber, SyncType, ReferenceType, ReferenceID, ReferenceLineKey,
                SKUCode, QtyChange, FacilityCode, QBO_TxnId, QBO_SyncToken,
                SyncStatus, SyncError, SyncedAt
            )
            VALUES (
                :doc2, :sync_type2, :ref_type2, :ref_id2, :line_key2,
                :sku2, :qty2, :facility2, :txn_id2, :sync_token2,
                :status3, :error2,
                CASE WHEN :status4 = N'Synced' THEN SYSUTCDATETIME() ELSE NULL END
            );
    SQL)->execute([
        'doc' => $row['doc_number'],
        'sync_type' => $row['sync_type'],
        'ref_type' => $row['reference_type'],
        'ref_id' => (int) $row['reference_id'],
        'line_key' => $row['reference_line_key'] ?? null,
        'sku' => $row['sku_code'],
        'qty' => (float) $row['qty_change'],
        'facility' => $row['facility_code'] ?? null,
        'txn_id' => $row['qbo_txn_id'] ?? null,
        'sync_token' => $row['qbo_sync_token'] ?? null,
        'status' => $row['sync_status'],
        'status2' => $row['sync_status'],
        'error' => $row['sync_error'] ?? null,
        'doc2' => $row['doc_number'],
        'sync_type2' => $row['sync_type'],
        'ref_type2' => $row['reference_type'],
        'ref_id2' => (int) $row['reference_id'],
        'line_key2' => $row['reference_line_key'] ?? null,
        'sku2' => $row['sku_code'],
        'qty2' => (float) $row['qty_change'],
        'facility2' => $row['facility_code'] ?? null,
        'txn_id2' => $row['qbo_txn_id'] ?? null,
        'sync_token2' => $row['qbo_sync_token'] ?? null,
        'status3' => $row['sync_status'],
        'error2' => $row['sync_error'] ?? null,
        'status4' => $row['sync_status'],
    ]);
}

function qbo_inventory_sync_log_status(string $docNumber): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT SyncStatus FROM dbo.QBOInventorySyncLog WHERE DocNumber = :doc');
    $stmt->execute(['doc' => $docNumber]);
    $status = $stmt->fetchColumn();

    return $status === false || $status === null ? null : (string) $status;
}

