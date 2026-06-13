<?php
/**
 * Retry watcher — checks for failed jobs due for automatic retry.
 *
 * Schedule: every 5 minutes.
 * WebJob NCRONTAB: 0 star/5 * * * * (every 5 minutes)
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/cron-auth.php';
require dirname(__DIR__) . '/includes/process-retry-watcher.php';

header('Content-Type: application/json; charset=utf-8');

$auth = cron_auth_check();
if (!$auth['ok']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $auth['error']], JSON_UNESCAPED_SLASHES);
    exit;
}

$result = process_retry_watcher_run();
http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
exit;
