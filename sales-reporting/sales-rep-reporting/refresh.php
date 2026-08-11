<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/sales-rep-reporting.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

sales_rep_reporting_require_refresh();

$listPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $listPath, true, 302);
    exit;
}

$user = auth_user();
$userId = is_array($user) ? (int) ($user['UserID'] ?? 0) : 0;

$result = process_execute(
    'accs-sales-order-sync',
    [],
    PROCESS_LOG_TRIGGER_MANUAL,
    $userId > 0 ? $userId : null
);

if (!empty($result['ok'])) {
    header('Location: ' . $listPath . '?notice=refresh_success', true, 302);
    exit;
}

$error = trim((string) ($result['error'] ?? 'ACCS sales order sync failed.'));
header(
    'Location: ' . $listPath . '?notice=refresh_failed&error=' . rawurlencode($error),
    true,
    302
);
exit;
