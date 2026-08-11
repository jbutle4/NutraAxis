<?php

require_once __DIR__ . '/env.php';

function process_functions_prod_base_url(): string
{
    $configured = trim((string) env(
        'NUTRA_FUNCTIONS_PROD_BASE_URL',
        env('AZURE_FUNCTION_APP_URL_PRODUCTION', '')
    ));

    return rtrim($configured, '/');
}

function process_functions_prod_key(): string
{
    return trim((string) env(
        'NUTRA_FUNCTIONS_PROD_KEY',
        env('AZURE_FUNCTION_APP_KEY', '')
    ));
}

function process_functions_prod_is_configured(): bool
{
    return process_functions_prod_base_url() !== '' && process_functions_prod_key() !== '';
}

/**
 * Process codes that route to Nutra-forecast-tool-prod when the active Process Log
 * profile is production. UAT profile always uses Nutra-forecast-tool (stage ACCS,
 * QBO sandbox, IMS uat).
 */
function process_functions_prod_codes(): array
{
    return [
        'accs-sales-order-sync',
        'accs-employee-customer-create',
    ];
}

function process_functions_uat_app_label(): string
{
    return 'Nutra-forecast-tool';
}

function process_functions_prod_app_label(): string
{
    return 'Nutra-forecast-tool-prod';
}

function process_functions_uat_target(): array
{
    return [
        'base_url' => process_functions_base_url(),
        'key'      => process_functions_key(),
        'app'      => process_functions_uat_app_label(),
        'profile'  => 'uat',
    ];
}

function process_functions_prod_target(): array
{
    return [
        'base_url' => process_functions_prod_base_url(),
        'key'      => process_functions_prod_key(),
        'app'      => process_functions_prod_app_label(),
        'profile'  => 'production',
    ];
}

function process_functions_should_use_prod_app(string $code): bool
{
    if (function_exists('data_profile_is_uat') && data_profile_is_uat()) {
        return false;
    }

    return in_array($code, process_functions_prod_codes(), true);
}

function process_functions_resolve_target(string $code): array
{
    if (process_functions_should_use_prod_app($code) && process_functions_prod_is_configured()) {
        return process_functions_prod_target();
    }

    return process_functions_uat_target();
}

function process_functions_target_label(string $code): string
{
    $target = process_functions_resolve_target($code);

    return (string) ($target['app'] ?? process_functions_uat_app_label());
}

function process_functions_base_url(): string
{
    $configured = trim((string) env(
        'NUTRA_FUNCTIONS_BASE_URL',
        'https://nutra-forecast-tool-czaxf0eydta6aeeg.eastus2-01.azurewebsites.net'
    ));

    return rtrim($configured, '/');
}

function process_functions_key(): string
{
    return trim((string) env('NUTRA_FUNCTIONS_KEY', ''));
}

function process_functions_is_configured(): bool
{
    return process_functions_key() !== '';
}

function process_functions_request(array $payload): array
{
    $code = (string) ($payload['code'] ?? '');
    $target = process_functions_resolve_target($code);
    $key = $target['key'];
    if ($key === '') {
        $missing = $target['app'] === process_functions_prod_app_label()
            ? 'NUTRA_FUNCTIONS_PROD_KEY'
            : 'NUTRA_FUNCTIONS_KEY';

        return [
            'ok'    => false,
            'error' => $missing . ' is not configured in Azure App Service application settings.',
            'log_id' => null,
        ];
    }

    $url = $target['base_url'] . '/api/process-execute?code=' . rawurlencode($key);
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

function process_functions_rerun(int $logId, ?int $triggeredByUserId = null): array
{
    require_once __DIR__ . '/process-log.php';

    $log = process_log_get($logId);
    $payload = ['log_id' => $logId];
    if ($log !== null && !empty($log['ProcessCode'])) {
        $payload['code'] = (string) $log['ProcessCode'];
    }

    if ($triggeredByUserId !== null && $triggeredByUserId > 0) {
        $payload['triggered_by_user_id'] = $triggeredByUserId;
    }

    return process_functions_request($payload);
}
