<?php
/**
 * Lightweight production diagnostics. Remove or protect after troubleshooting.
 */
require __DIR__ . '/includes/env.php';

function nginx_health_redirect_setting(): string
{
    if (!function_exists('shell_exec')) {
        return 'shell_exec_disabled';
    }

    $output = shell_exec('grep -E "absolute_redirect|port_in_redirect" /etc/nginx/sites-enabled/default 2>/dev/null');

    return trim((string) $output) !== '' ? trim((string) $output) : 'not_configured';
}

function nginx_internal_status(string $path): array
{
    $url = 'http://127.0.0.1:8080' . $path;
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
    @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }

    return ['url' => $url, 'status' => $status];
}

header('Content-Type: application/json; charset=UTF-8');

$checks = [
    'azure_hosted'            => env_is_azure_hosted(),
    'operations_dashboard'    => is_readable(__DIR__ . '/operations-dashboard/index.php'),
    'support_index'           => is_readable(__DIR__ . '/support/index.php'),
    'labeling_batch_printing' => is_readable(__DIR__ . '/labeling-operations/batch-printing/index.php'),
    'sales_daily_summary'     => is_readable(__DIR__ . '/sales-reporting/daily-sales-summary/index.php'),
    'nginx_startup_script'    => is_readable('/home/site/startup.sh'),
    'nginx_baseline_saved'    => is_readable('/home/site/nginx-default.baseline'),
    'nginx_absolute_redirect' => nginx_health_redirect_setting(),
    'request_is_https'        => request_is_https(),
    'env_file_present'        => is_readable(__DIR__ . '/.env'),
    'db_host_set'             => trim((string) env_first(['DB_HOST', 'DB_SERVER'], '')) !== '',
    'db_name_set'             => trim((string) env('DB_NAME', '')) !== '',
    'db_user_set'             => trim((string) env('DB_USER', '')) !== '',
    'db_pass_set'             => trim((string) env_first(['DB_PASS', 'DB_PASSWORD'], '')) !== '',
    'pdo_drivers'             => PDO::getAvailableDrivers(),
    'jazz_domain_prod_set'    => trim((string) env('JAZZ_DOMAIN_PROD', '')) !== '',
    'jazz_username_prod_set'  => trim((string) env('JAZZ_USERNAME_PROD', '')) !== '',
    'jazz_tenant_set'         => trim((string) env('JAZZ_TENANT_CODE', '')) !== '',
    'accs_prod_env_set'       => trim((string) env_first(['ADOBE_COMMERCE_PRODUCTION_ENVIRONMENT', 'ACCS_PRODUCTION_ENVIRONMENT'], '')) !== '',
    'accs_shared_env'         => trim((string) env('ADOBE_COMMERCE_ENVIRONMENT', '')),
    'php_version'             => PHP_VERSION,
    'request_host'            => $_SERVER['HTTP_HOST'] ?? '',
    'request_uri'             => $_SERVER['REQUEST_URI'] ?? '',
    'server_https'            => $_SERVER['HTTPS'] ?? '',
    'forwarded_proto'         => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
    'internal_root'           => nginx_internal_status('/'),
    'internal_ops_dashboard'  => nginx_internal_status('/operations-dashboard/'),
];

try {
    require_once __DIR__ . '/includes/database.php';
    $pdo = db();
    $checks['db_connected'] = true;
    $checks['db_driver'] = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $checks['db_query_ok'] = (bool) $pdo->query('SELECT 1')->fetchColumn();
    $tableStmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?"
    );
    foreach (['ContractRegister', 'SKUMaster', 'LinksIndex'] as $table) {
        $tableStmt->execute([$table]);
        $checks['table_' . strtolower($table)] = (bool) $tableStmt->fetchColumn();
    }

    require_once __DIR__ . '/includes/catalog.php';
    $checks['catalog_list_count'] = count(catalog_list_skus([]));
    $checks['catalog_list_ok'] = true;

    require_once __DIR__ . '/includes/legal.php';
    $checks['legal_list_count'] = count(legal_list_contracts([]));
    $checks['legal_list_ok'] = true;
} catch (Throwable $e) {
    if (!isset($checks['db_connected'])) {
        $checks['db_connected'] = false;
    }
    $checks['db_error'] = $e->getMessage();
    if (!isset($checks['catalog_list_ok'])) {
        $checks['catalog_list_ok'] = false;
        $checks['catalog_list_error'] = $e->getMessage();
    }
    if (!isset($checks['legal_list_ok'])) {
        $checks['legal_list_ok'] = false;
        $checks['legal_list_error'] = $e->getMessage();
    }
}

echo json_encode(['ok' => true, 'checks' => $checks], JSON_PRETTY_PRINT);
