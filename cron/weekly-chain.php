<?php
/**
 * Weekly planning job: monthly sales rollup, then inventory plan.
 *
 * Use this endpoint when both steps should run in one request.
 * WebJob NCRONTAB: 0 0 1 * * 0
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/cron-auth.php';
require dirname(__DIR__) . '/includes/process-runner.php';

header('Content-Type: application/json; charset=utf-8');

$auth = cron_auth_check();
if (!$auth['ok']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $auth['error']], JSON_UNESCAPED_SLASHES);
    exit;
}

$monthlyResult = process_execute('monthly-sales-summary');
$planResult = ['ok' => false, 'error' => 'Skipped because monthly sales rollup failed.', 'skipped' => true];

if ($monthlyResult['ok']) {
    $planResult = process_execute('forecast-plan');
    $planResult['skipped'] = false;
}

$response = [
    'ok'      => $monthlyResult['ok'] && $planResult['ok'],
    'error'   => null,
    'monthly' => $monthlyResult,
    'plan'    => $planResult,
];

if (!$monthlyResult['ok']) {
    $response['error'] = $monthlyResult['error'] ?? 'Monthly sales rollup failed.';
} elseif (!$planResult['ok']) {
    $response['error'] = $planResult['error'] ?? 'Inventory plan failed.';
}

http_response_code($response['ok'] ? 200 : 500);
echo json_encode($response, JSON_UNESCAPED_SLASHES);
exit;
