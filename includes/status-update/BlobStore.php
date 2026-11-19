<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';
require_once dirname(__DIR__) . '/file-storage.php';

final class StatusUpdateBlobStore
{
    public function container(): string
    {
        $container = trim((string) env('STATUS_BLOB_CONTAINER', 'status-decks'));

        return $container !== '' ? $container : 'status-decks';
    }

    public function isConfigured(): bool
    {
        return file_storage_is_configured();
    }

    public function exists(string $blobPath): bool
    {
        return status_update_blob_exists($blobPath);
    }

    public function get(string $blobPath): array
    {
        return status_update_blob_read($blobPath);
    }

    public function put(string $blobPath, string $bytes): array
    {
        return status_update_blob_upload($blobPath, $bytes);
    }

    public function putIfAbsent(string $blobPath, string $bytes): array
    {
        if ($this->exists($blobPath)) {
            return ['ok' => false, 'error' => 'A file already exists at this path and cannot be overwritten.'];
        }

        return $this->put($blobPath, $bytes);
    }

    public function copy(string $fromPath, string $toPath): array
    {
        $read = $this->get($fromPath);
        if (!$read['ok']) {
            return ['ok' => false, 'error' => $read['error'] ?? 'Unable to read source blob.'];
        }

        return $this->put($toPath, (string) $read['content']);
    }
}

function status_update_blob_config(): ?array
{
    $base = file_storage_config();
    if ($base === null) {
        return null;
    }

    $base['container'] = (new StatusUpdateBlobStore())->container();

    return $base;
}

function status_update_blob_exists(string $blobPath): bool
{
    $config = status_update_blob_config();
    if ($config === null) {
        return false;
    }

    return status_update_blob_request('HEAD', $blobPath, null, $config)['ok'] ?? false;
}

function status_update_blob_read(string $blobPath): array
{
    $config = status_update_blob_config();
    if ($config === null) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $response = status_update_blob_request('GET', $blobPath, null, $config);
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Unable to read blob.'];
    }

    return [
        'ok'           => true,
        'error'        => null,
        'content'      => (string) ($response['body'] ?? ''),
        'content_type' => (string) ($response['headers']['content-type'] ?? 'application/octet-stream'),
    ];
}

function status_update_blob_upload(string $blobPath, string $content): array
{
    $config = status_update_blob_config();
    if ($config === null) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $contentType = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    $response = status_update_blob_request('PUT', $blobPath, $content, $config, [
        'Content-Type'   => $contentType,
        'x-ms-blob-type' => 'BlockBlob',
    ]);
    if (!$response['ok'] && (int) ($response['status'] ?? 0) === 404 && status_update_blob_should_auto_create_container($config)) {
        $ensure = status_update_blob_ensure_container($config);
        if ($ensure['ok']) {
            $response = status_update_blob_request('PUT', $blobPath, $content, $config, [
                'Content-Type'   => $contentType,
                'x-ms-blob-type' => 'BlockBlob',
            ]);
        } elseif ($ensure['error'] !== null) {
            return ['ok' => false, 'error' => $ensure['error']];
        }
    }
    if (!$response['ok']) {
        $container = (string) ($config['container'] ?? '');
        $detail = $response['error'] ?? 'Unable to upload blob.';
        if ((int) ($response['status'] ?? 0) === 404) {
            $detail = 'Blob container "' . $container . '" was not found. In Azure Portal → Storage account → Containers, create "'
                . $container . '" (private), or set STATUS_BLOB_CONTAINER to an existing container (e.g. portal-attachments).';
        }

        return ['ok' => false, 'error' => $detail];
    }

    return ['ok' => true, 'error' => null, 'blob_path' => ltrim($blobPath, '/')];
}

/**
 * @param array<string, mixed> $config
 * @param array<string, string> $extraHeaders
 * @return array{ok: bool, error: ?string, body: ?string, headers: array<string, string>, status: int}
 */
/**
 * Only auto-create dedicated status containers — not the shared portal-attachments bucket.
 *
 * @param array<string, mixed> $config
 */
function status_update_blob_should_auto_create_container(array $config): bool
{
    $current = strtolower(trim((string) ($config['container'] ?? '')));
    if ($current === '') {
        return false;
    }

    $portalContainer = strtolower(trim((string) env('AZURE_STORAGE_CONTAINER', 'portal-attachments')));

    return $current !== $portalContainer;
}

/**
 * @param array<string, mixed> $config
 * @return array{ok: bool, error: ?string}
 */
function status_update_blob_ensure_container(array $config): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required for blob storage.'];
    }

    $container = (string) ($config['container'] ?? '');
    $account = (string) ($config['account'] ?? '');
    $accountKey = (string) ($config['account_key'] ?? '');
    if ($container === '' || $account === '' || $accountKey === '') {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $url = $config['protocol'] . '://' . $config['blob_host'] . '/' . rawurlencode($container) . '?restype=container';
    $date = gmdate('D, d M Y H:i:s T');
    $headers = [
        'Date'             => $date,
        'x-ms-version'     => FILE_STORAGE_BLOB_API_VERSION,
        'Content-Length'   => '0',
    ];

    $canonicalizedResource = '/' . $account . '/' . $container . "\nrestype:container";
    $authorization = status_update_blob_sign_request('PUT', $account, $accountKey, $canonicalizedResource, $headers);
    if ($authorization === null) {
        return ['ok' => false, 'error' => 'Unable to sign blob container request.'];
    }
    $headers['Authorization'] = $authorization;

    $curlHeaders = [];
    foreach ($headers as $name => $value) {
        $curlHeaders[] = $name . ': ' . $value;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_NOBODY         => false,
        CURLOPT_POSTFIELDS     => '',
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($status === 201 || $status === 409) {
        return ['ok' => true, 'error' => null];
    }

    if ($status === 404) {
        return [
            'ok'    => false,
            'error' => 'Storage account or container endpoint returned HTTP 404. Verify AZURE_STORAGE_CONNECTION_STRING on the app.',
        ];
    }

    if ($status === 403) {
        return [
            'ok'    => false,
            'error' => 'Storage denied container creation (HTTP 403). Create container "'
                . $container . '" manually in Azure, or set STATUS_BLOB_CONTAINER to portal-attachments.',
        ];
    }

    return ['ok' => false, 'error' => 'Could not create blob container (HTTP ' . $status . ').'];
}

/**
 * @param array<string, string> $headers
 */
function status_update_blob_sign_request(
    string $method,
    string $accountName,
    string $accountKey,
    string $canonicalizedResource,
    array $headers
): ?string {
    $contentLength = $headers['Content-Length'] ?? null;
    $contentType = $headers['Content-Type'] ?? '';
    $date = (string) ($headers['Date'] ?? '');

    $msHeaderLines = [];
    foreach ($headers as $name => $value) {
        $lower = strtolower((string) $name);
        if (!str_starts_with($lower, 'x-ms-')) {
            continue;
        }
        $msHeaderLines[] = $lower . ':' . trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }
    sort($msHeaderLines);

    $stringToSign = implode("\n", array_merge(
        [
            $method,
            '',
            '',
            $contentLength !== null ? (string) $contentLength : '',
            '',
            (string) $contentType,
            $date,
            '',
            '',
            '',
            '',
            '',
        ],
        $msHeaderLines,
        [$canonicalizedResource]
    ));

    $decodedKey = base64_decode($accountKey, true);
    if ($decodedKey === false) {
        return null;
    }

    $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

    return 'SharedKey ' . $accountName . ':' . $signature;
}

function status_update_blob_request(string $method, string $blobPath, ?string $body, array $config, array $extraHeaders = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required.', 'body' => null, 'headers' => [], 'status' => 0];
    }

    $normalizedPath = ltrim(str_replace('\\', '/', $blobPath), '/');
    if ($normalizedPath === '') {
        return ['ok' => false, 'error' => 'Blob path is required.', 'body' => null, 'headers' => [], 'status' => 0];
    }

    $container = (string) $config['container'];
    $encodedPath = file_storage_encode_blob_path($normalizedPath);
    $url = $config['protocol'] . '://' . $config['blob_host'] . '/'
        . rawurlencode($container) . '/' . $encodedPath;

    $method = strtoupper($method);
    $body = $body ?? '';
    $date = gmdate('D, d M Y H:i:s T');
    $headers = array_merge([
        'Date'         => $date,
        'x-ms-version' => FILE_STORAGE_BLOB_API_VERSION,
    ], $extraHeaders);

    if ($method === 'PUT' || $method === 'POST') {
        $headers['Content-Length'] = (string) strlen($body);
    }

    $authorization = file_storage_authorization_header(
        $method,
        $config['account'],
        $config['account_key'],
        $container,
        $normalizedPath,
        $headers
    );
    if ($authorization === null) {
        return ['ok' => false, 'error' => 'Unable to sign blob request.', 'body' => null, 'headers' => [], 'status' => 0];
    }
    $headers['Authorization'] = $authorization;

    $curlHeaders = [];
    foreach ($headers as $name => $value) {
        $curlHeaders[] = $name . ': ' . $value;
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_TIMEOUT        => 120,
    ];
    if ($method === 'HEAD') {
        $options[CURLOPT_NOBODY] = true;
    }
    if (($method === 'PUT' || $method === 'POST') && $body !== '') {
        $options[CURLOPT_POSTFIELDS] = $body;
    } elseif ($method === 'GET') {
        // default body
    }
    curl_setopt_array($ch, $options);

    if ($method === 'HEAD') {
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'ok'      => $status >= 200 && $status < 300,
            'error'   => $status >= 200 && $status < 300 ? null : 'Blob not found.',
            'body'    => null,
            'headers' => [],
            'status'  => $status,
        ];
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $status < 200 || $status >= 300) {
        return [
            'ok'      => false,
            'error'   => 'Blob request failed (HTTP ' . $status . ').',
            'body'    => null,
            'headers' => [],
            'status'  => $status,
        ];
    }

    return [
        'ok'      => true,
        'error'   => null,
        'body'    => (string) $raw,
        'headers' => [],
        'status'  => $status,
    ];
}