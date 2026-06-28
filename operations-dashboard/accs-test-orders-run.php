<?php

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accs-test-order-client.php';

auth_require_module_read('operations-dashboard');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (($_POST['dashboard_action'] ?? '') !== 'accs_test_orders') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

$dryRun = !empty($_POST['dry_run']);
$start = accs_test_order_dispatch_background(5, 4, $dryRun);

header('Content-Type: application/json');
echo json_encode($start, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (empty($start['ok'])) {
    exit;
}

accs_test_order_finish_response();
accs_test_order_run_background(5, 4, $dryRun);
exit;
