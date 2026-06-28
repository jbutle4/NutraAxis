<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/process-functions-client.php';

function accs_test_order_functions_key(): string
{
    foreach (['ACCS_TEST_ORDER_CREATION_KEY', 'NUTRA_FUNCTIONS_KEY', 'AZURE_FUNCTION_APP_KEY'] as $envKey) {
        $value = trim((string) env($envKey, ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function accs_test_order_create(int $count = 5, int $lineCount = 4, bool $dryRun = false): array
{
    $key = accs_test_order_functions_key();
    if ($key === '') {
        return [
            'ok'    => false,
            'error' => 'Function key is not configured. Set ACCS_TEST_ORDER_CREATION_KEY or NUTRA_FUNCTIONS_KEY in App Settings.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok'    => false,
            'error' => 'PHP cURL extension is required to call Azure Functions.',
        ];
    }

    $query = [
        'code'       => $key,
        'count'      => max(1, min(20, $count)),
        'line_count' => max(1, min(20, $lineCount)),
    ];

    if ($dryRun) {
        $query['dry_run'] = 'true';
    }

    $url = process_functions_base_url() . '/api/accs-test-order-creation?' . http_build_query($query);

    $headers = ['Accept: application/json'];
    $secret = trim((string) env('ACCS_TEST_ORDER_CREATION_SECRET', ''));
    if ($secret !== '') {
        $headers[] = 'x-nutraaxis-test-secret: ' . $secret;
    }

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 600,
        CURLOPT_CONNECTTIMEOUT => 20,
    ]);

    $responseBody = curl_exec($handle);
    $curlError = curl_error($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    if ($responseBody === false) {
        return [
            'ok'    => false,
            'error' => 'Azure Functions request failed: ' . ($curlError !== '' ? $curlError : 'unknown cURL error'),
        ];
    }

    try {
        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        if ($statusCode === 401) {
            return [
                'ok'    => false,
                'error' => 'Function key rejected (HTTP 401). Configure ACCS_TEST_ORDER_CREATION_KEY on nutraaxisweb with the accs-test-order-creation function key.',
            ];
        }

        return [
            'ok'    => false,
            'error' => 'Azure Functions returned invalid JSON (HTTP ' . $statusCode . ').',
            'raw'   => $responseBody,
        ];
    }

    if (!is_array($decoded)) {
        return [
            'ok'    => false,
            'error' => 'Azure Functions returned an unexpected response.',
        ];
    }

    if ($statusCode >= 400 && empty($decoded['error'])) {
        $decoded['ok'] = false;
        $decoded['error'] = 'Azure Functions request failed with HTTP ' . $statusCode . '.';
    }

    $decoded['http_status'] = $statusCode;

    return $decoded;
}

function accs_test_order_result_message(array $result): string
{
    if (empty($result['ok'])) {
        return (string) ($result['error'] ?? 'Test order creation failed.');
    }

    if (!empty($result['dry_run'])) {
        $preview = (int) ($result['requested_count'] ?? 0);
        return 'Dry run completed — previewed ' . $preview . ' test order(s) without placing them on ACCS Stage.';
    }

    $created = (int) ($result['created_count'] ?? 0);
    $requested = (int) ($result['requested_count'] ?? $created);
    $orders = is_array($result['orders'] ?? null) ? $result['orders'] : [];
    $incrementIds = [];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        $incrementId = trim((string) ($order['increment_id'] ?? ''));
        if ($incrementId !== '') {
            $incrementIds[] = $incrementId;
        }
    }

    $message = 'Command executed — created ' . $created . ' of ' . $requested . ' ACCS Stage test order(s).';
    if ($incrementIds !== []) {
        $message .= ' Orders: ' . implode(', ', $incrementIds) . '.';
    }

    $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
    if ($errors !== []) {
        $message .= ' ' . count($errors) . ' error(s) occurred during the run.';
    }

    return $message;
}

function accs_test_order_started_message(): string
{
    return 'Test order creation started in the background. Five ACCS Stage orders are being placed now — allow 1–2 minutes, then check ACCS Stage or Process Log for results.';
}

/**
 * Send the HTTP response to the client, then continue running in the same request.
 */
function accs_test_order_finish_response(): void
{
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    ignore_user_abort(true);

    while (ob_get_level() > 0) {
        ob_end_flush();
    }

    flush();
}

function accs_test_order_dispatch_background(int $count = 5, int $lineCount = 4, bool $dryRun = false): array
{
    $key = accs_test_order_functions_key();
    if ($key === '') {
        return [
            'ok'      => false,
            'message' => 'Function key is not configured. Set ACCS_TEST_ORDER_CREATION_KEY or NUTRA_FUNCTIONS_KEY in App Settings.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok'      => false,
            'message' => 'PHP cURL extension is required to call Azure Functions.',
        ];
    }

    return [
        'ok'      => true,
        'message' => accs_test_order_started_message(),
        'async'   => true,
    ];
}

function accs_test_order_run_background(int $count = 5, int $lineCount = 4, bool $dryRun = false): array
{
    $result = accs_test_order_create($count, $lineCount, $dryRun);
    $summary = accs_test_order_result_message($result);
    error_log('[accs-test-orders] ' . $summary);

    return $result;
}
