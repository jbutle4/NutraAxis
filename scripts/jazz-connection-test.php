<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/jazz-oms.php';

data_profile_set('production');

function jazz_test_mask(string $value, int $keepStart = 2, int $keepEnd = 2): string
{
    $value = trim($value);
    if ($value === '') {
        return '(empty)';
    }

    $len = strlen($value);
    if ($len <= $keepStart + $keepEnd) {
        return str_repeat('*', $len);
    }

    return substr($value, 0, $keepStart) . str_repeat('*', max(1, $len - $keepStart - $keepEnd)) . substr($value, -$keepEnd);
}

$report = [
    'profile'      => data_profile(),
    'azure_hosted' => env_is_azure_hosted(),
    'configured'   => jazz_oms_is_configured(),
    'config_error' => jazz_oms_config_error(),
    'base_url'     => jazz_oms_base_url(),
    'domain'       => jazz_oms_domain(),
    'tenant'       => jazz_oms_tenant_code(),
    'username'     => jazz_test_mask(jazz_oms_username()),
    'password_set' => jazz_oms_password() !== '',
    'password_len' => strlen(jazz_oms_password()),
    'env_keys'     => [
        'JAZZ_DOMAIN_PROD'   => jazz_test_mask((string) env('JAZZ_DOMAIN_PROD', '')),
        'JAZZ_USERNAME_PROD' => jazz_test_mask((string) env('JAZZ_USERNAME_PROD', '')),
        'JAZZ_BASE_URL_PROD' => trim((string) env('JAZZ_BASE_URL_PROD', '')),
        'JAZZ_TENANT_CODE'   => jazz_test_mask((string) env('JAZZ_TENANT_CODE', '')),
        'JAZZ_DOMAIN'        => jazz_test_mask((string) env('JAZZ_DOMAIN', '')),
        'JAZZ_USERNAME'      => jazz_test_mask((string) env('JAZZ_USERNAME', '')),
    ],
];

$token = jazz_oms_get_token();
$report['token_ok'] = $token['ok'];
$report['token_error'] = $token['error'];
$report['token_prefix'] = is_string($token['token'] ?? null) && $token['token'] !== ''
    ? jazz_test_mask($token['token'], 4, 4)
    : null;

if (PHP_SAPI === 'cli') {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($token['ok'] ? 0 : 1);
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
