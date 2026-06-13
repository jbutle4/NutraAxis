<?php
/**
 * Weekly Jazz OMS inventory snapshot job.
 *
 * Schedule: every Sunday at 12:00 PM US Central (America/Chicago).
 * Example cron-job.org / Azure Logic App recurrence:
 *   Time zone: America/Chicago
 *   Cron: 0 12 * * 0
 *
 * Auth (set CRON_SECRET in Azure App Settings):
 *   Preferred: HTTP header X-Cron-Secret: YOUR_SECRET
 *   Alternate: ?key=URL_ENCODED_SECRET (encode &, %, ;, +, #, spaces, etc.)
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

$result = process_execute('jazz-inventory-snapshot');
http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
exit;
