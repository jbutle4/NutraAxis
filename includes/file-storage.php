<?php

require_once __DIR__ . '/env.php';

const FILE_STORAGE_BLOB_API_VERSION = '2022-11-02';

function file_storage_config(): ?array
{
    static $config = null;
    static $loaded = false;

    if ($loaded) {
        return $config;
    }

    $loaded = true;

    $connectionString = env_first(['AZURE_STORAGE_CONNECTION_STRING']);
    $parsed = file_storage_parse_connection_string($connectionString);
    if ($parsed === null) {
        return $config = null;
    }

    $account = env_first(['AZURE_STORAGE_ACCOUNT'], $parsed['AccountName'] ?? null);
    $container = env('AZURE_STORAGE_CONTAINER', 'portal-attachments');

    if ($account === null || $account === '' || $container === null || $container === '') {
        return $config = null;
    }

    $protocol = strtolower((string) ($parsed['DefaultEndpointsProtocol'] ?? 'https'));
    if ($protocol !== 'http' && $protocol !== 'https') {
        $protocol = 'https';
    }

    $suffix = (string) ($parsed['EndpointSuffix'] ?? 'core.windows.net');
    $blobHost = $account . '.blob.' . $suffix;

    return $config = [
        'account'            => $account,
        'container'          => $container,
        'account_key'        => (string) ($parsed['AccountKey'] ?? ''),
        'protocol'           => $protocol,
        'blob_host'          => $blobHost,
    ];
}

function file_storage_is_configured(): bool
{
    $config = file_storage_config();

    return $config !== null && $config['account_key'] !== '';
}

function file_storage_parse_connection_string(?string $connectionString): ?array
{
    if ($connectionString === null || trim($connectionString) === '') {
        return null;
    }

    $parts = [];
    foreach (explode(';', $connectionString) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        $equalsAt = strpos($segment, '=');
        if ($equalsAt === false) {
            continue;
        }

        $parts[substr($segment, 0, $equalsAt)] = substr($segment, $equalsAt + 1);
    }

    if (($parts['AccountName'] ?? '') === '' || ($parts['AccountKey'] ?? '') === '') {
        return null;
    }

    return $parts;
}

function file_storage_sanitize_filename(string $fileName): string
{
    $base = basename(str_replace('\\', '/', $fileName));
    $base = preg_replace('/[^\w.\- ()]+/u', '_', $base) ?? 'attachment';
    $base = trim((string) $base, '._');

    return $base !== '' ? $base : 'attachment';
}

function file_storage_build_blob_path(string $domain, int $entityId, int $attachmentId, string $fileName): string
{
    $domain = trim(strtolower(preg_replace('/[^a-z0-9\-]+/i', '-', $domain) ?? 'files'), '-');
    if ($domain === '') {
        $domain = 'files';
    }

    $safeName = file_storage_sanitize_filename($fileName);

    return $domain . '/' . $entityId . '/' . $attachmentId . '-' . $safeName;
}

function file_storage_encode_blob_path(string $blobPath): string
{
    $blobPath = ltrim(str_replace('\\', '/', $blobPath), '/');
    if ($blobPath === '') {
        return '';
    }

    return implode('/', array_map('rawurlencode', explode('/', $blobPath)));
}

function file_storage_upload(string $blobPath, string $content, string $contentType): array
{
    if (!file_storage_is_configured()) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $headers = [
        'Content-Type'   => $contentType !== '' ? $contentType : 'application/octet-stream',
        'x-ms-blob-type' => 'BlockBlob',
    ];

    $response = file_storage_request('PUT', $blobPath, $content, $headers);
    if (!$response['ok']) {
        return [
            'ok'    => false,
            'error' => $response['error'] ?? 'Unable to upload file to blob storage.',
        ];
    }

    return [
        'ok'         => true,
        'error'      => null,
        'blob_path'  => ltrim(str_replace('\\', '/', $blobPath), '/'),
        'etag'       => $response['headers']['etag'] ?? null,
        'size_bytes' => strlen($content),
    ];
}

function file_storage_read(string $blobPath): array
{
    if (!file_storage_is_configured()) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $response = file_storage_request('GET', $blobPath, null, []);
    if (!$response['ok']) {
        return [
            'ok'    => false,
            'error' => $response['error'] ?? 'Unable to read file from blob storage.',
        ];
    }

    return [
        'ok'           => true,
        'error'        => null,
        'content'      => (string) ($response['body'] ?? ''),
        'content_type' => (string) ($response['headers']['content-type'] ?? 'application/octet-stream'),
        'size_bytes'   => strlen((string) ($response['body'] ?? '')),
        'etag'         => $response['headers']['etag'] ?? null,
    ];
}

function file_storage_delete(string $blobPath): array
{
    if (!file_storage_is_configured()) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $response = file_storage_request('DELETE', $blobPath, null, []);
    if (!$response['ok']) {
        return [
            'ok'    => false,
            'error' => $response['error'] ?? 'Unable to delete file from blob storage.',
        ];
    }

    return ['ok' => true, 'error' => null];
}

function file_storage_exists(string $blobPath): bool
{
    if (!file_storage_is_configured()) {
        return false;
    }

    $response = file_storage_request('HEAD', $blobPath, null, []);

    return $response['ok'] && ($response['status'] ?? 0) === 200;
}

function file_storage_request(string $method, string $blobPath, ?string $body, array $extraHeaders): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required for Azure Blob Storage access.'];
    }

    $config = file_storage_config();
    if ($config === null) {
        return ['ok' => false, 'error' => 'Azure Blob Storage is not configured.'];
    }

    $normalizedPath = ltrim(str_replace('\\', '/', $blobPath), '/');
    if ($normalizedPath === '') {
        return ['ok' => false, 'error' => 'Blob path is required.'];
    }

    $encodedPath = file_storage_encode_blob_path($normalizedPath);
    $url = $config['protocol'] . '://' . $config['blob_host'] . '/'
        . rawurlencode($config['container']) . '/' . $encodedPath;

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
        $config['container'],
        $normalizedPath,
        $headers
    );

    if ($authorization === null) {
        return ['ok' => false, 'error' => 'Unable to sign blob storage request.'];
    }

    $headers['Authorization'] = $authorization;

    $curlHeaders = [];
    foreach ($headers as $name => $value) {
        $curlHeaders[] = $name . ': ' . $value;
    }

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 120,
    ];

    if ($method === 'PUT' || $method === 'POST') {
        $curlOptions[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $curlOptions);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    if ($rawResponse === false) {
        return [
            'ok'     => false,
            'status' => $status,
            'error'  => $curlError !== '' ? $curlError : 'Blob storage request failed.',
        ];
    }

    $rawHeaders = substr((string) $rawResponse, 0, $headerSize);
    $responseBody = substr((string) $rawResponse, $headerSize);
    $parsedHeaders = file_storage_parse_response_headers($rawHeaders);

    if ($status >= 200 && $status < 300) {
        return [
            'ok'      => true,
            'status'  => $status,
            'body'    => $responseBody,
            'headers' => $parsedHeaders,
            'error'   => null,
        ];
    }

    $message = file_storage_error_message($status, $responseBody);

    return [
        'ok'      => false,
        'status'  => $status,
        'body'    => $responseBody,
        'headers' => $parsedHeaders,
        'error'   => $message,
    ];
}

function file_storage_authorization_header(
    string $method,
    string $accountName,
    string $accountKey,
    string $container,
    string $blobPath,
    array $headers
): ?string {
    $canonicalizedResource = '/' . $accountName . '/' . $container . '/'
        . file_storage_encode_blob_path($blobPath);

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

function file_storage_canonicalized_headers(array $headers): string
{
    $msHeaders = [];

    foreach ($headers as $name => $value) {
        $lower = strtolower((string) $name);
        if (!str_starts_with($lower, 'x-ms-')) {
            continue;
        }

        $msHeaders[$lower] = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }

    ksort($msHeaders);

    $lines = [];
    foreach ($msHeaders as $name => $value) {
        $lines[] = $name . ':' . $value;
    }

    if ($lines === []) {
        return '';
    }

    return implode("\n", $lines) . "\n";
}

function file_storage_parse_response_headers(string $rawHeaders): array
{
    $parsed = [];
    foreach (preg_split('/\r\n|\n|\r/', $rawHeaders) ?: [] as $line) {
        if ($line === '' || !str_contains($line, ':')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode(':', $line, 2));
        $parsed[strtolower($name)] = $value;
    }

    return $parsed;
}

function file_storage_error_message(int $status, string $body): string
{
    if (preg_match('/<Message>(.*?)<\/Message>/s', $body, $matches) === 1) {
        return html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5);
    }

    return match (true) {
        $status === 404 => 'Blob not found.',
        $status === 403 => 'Blob storage access denied.',
        $status >= 500  => 'Blob storage service error.',
        default         => 'Blob storage request failed (HTTP ' . $status . ').',
    };
}
