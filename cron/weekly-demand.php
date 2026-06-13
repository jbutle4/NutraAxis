<?php
/**
 * Weekly inventory plan job (forecast generation only).
 *
 * Schedule: every Sunday at 1:30 AM US Central.
 * WebJob NCRONTAB: 0 30 1 * * 0
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

$result = process_execute('forecast-plan');
http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
exit;
