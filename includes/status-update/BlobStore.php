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
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'] ?? 'Unable to upload blob.'];
    }

    return ['ok' => true, 'error' => null, 'blob_path' => ltrim($blobPath, '/')];
}

/**
 * @param array<string, mixed> $config
 * @param array<string, string> $extraHeaders
 * @return array{ok: bool, error: ?string, body: ?string, headers: array<string, string>, status: int}
 */
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