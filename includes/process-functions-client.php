<?php

require_once __DIR__ . '/env.php';

function process_functions_base_url(): string
{
    $configured = trim((string) env(
        'NUTRA_FUNCTIONS_BASE_URL',
        'https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net'
    ));

    return rtrim($configured, '/');
}

/** Processes that must run on Nutra-forecast-tool-prod (production ACCS). */
function process_functions_prod_only_codes(): array
{
    return ['accs-sales-order-sync', 'qbo-coa-sync'];
}

function process_functions_base_url_for(string $processCode = ''): string
{
    if ($processCode !== '' && in_array($processCode, process_functions_prod_only_codes(), true)) {
        $prod = trim((string) env(
            'NUTRA_FUNCTIONS_PROD_BASE_URL',
            'https://nutra-forecast-tool-prod.azurewebsites.net'
        ));
        if ($prod !== '') {
            return rtrim($prod, '/');
        }
    }

    return process_functions_base_url();
}

function process_functions_key_for(string $processCode = ''): string
{
    if ($processCode !== '' && in_array($processCode, process_functions_prod_only_codes(), true)) {
        $prodKey = trim((string) env('NUTRA_FUNCTIONS_PROD_KEY', ''));
        if ($prodKey !== '') {
            return $prodKey;
        }
    }

    return process_functions_key();
}

function process_functions_key(): string
{
    return trim((string) env('NUTRA_FUNCTIONS_KEY', ''));
}

function process_functions_is_configured(): bool
{
    return process_functions_key() !== '';
}

function process_functions_request(array $payload, string $processCode = ''): array
{
    if ($processCode === '' && isset($payload['code'])) {
        $processCode = (string) $payload['code'];
    }

    $key = process_functions_key_for($processCode);
    if ($key === '') {
        return [
            'ok'    => false,
            'error' => 'NUTRA_FUNCTIONS_KEY is not configured in Azure App Service application settings.',
            'log_id' => null,
        ];
    }

    $url = process_functions_base_url_for($processCode) . '/api/process-execute?code=' . rawurlencode($key);
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return [
            'ok'    => false,
            'error' => 'Unable to encode process request payload.',
            'log_id' => null,
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'ok'    => false,
            'error' => 'PHP cURL extension is required to call Azure Functions.',
            'log_id' => null,
        ];
    }

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS     => $body,
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
            'log_id' => null,
        ];
    }

    try {
        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return [
            'ok'    => false,
            'error' => 'Azure Functions returned invalid JSON (HTTP ' . $statusCode . ').',
            'log_id' => null,
        ];
    }

    if (!is_array($decoded)) {
        return [
            'ok'    => false,
            'error' => 'Azure Functions returned an unexpected response.',
            'log_id' => null,
        ];
    }

    if ($statusCode >= 400 && empty($decoded['error'])) {
        $decoded['ok'] = false;
        $decoded['error'] = 'Azure Functions request failed with HTTP ' . $statusCode . '.';
    }

    return $decoded;
}

function process_functions_execute(
    string $code,
    array $params = [],
    string $triggerType = 'Manual',
    ?int $triggeredByUserId = null
): array {
    $payload = [
        'code'         => $code,
        'params'       => $params,
        'trigger_type' => $triggerType,
    ];

    if ($triggeredByUserId !== null && $triggeredByUserId > 0) {
        $payload['triggered_by_user_id'] = $triggeredByUserId;
    }

    return process_functions_request($payload);
}

function process_functions_rerun(int $logId, ?int $triggeredByUserId = null, string $processCode = ''): array
{
    $payload = ['log_id' => $logId];

    if ($triggeredByUserId !== null && $triggeredByUserId > 0) {
        $payload['triggered_by_user_id'] = $triggeredByUserId;
    }

    return process_functions_request($payload, $processCode);
}
