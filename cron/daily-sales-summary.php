<?php
/**
 * Daily ACCS sales summary job.
 *
 * Schedule: every day at 2:00 AM US Central (America/Chicago).
 * Summarizes the previous calendar day's sold quantity by SKU.
 *
 * WebJob NCRONTAB (with WEBSITE_TIME_ZONE=Central Standard Time or America/Chicago):
 *   0 0 2 * * *
 *
 * Auth: X-Cron-Secret header or ?key= (same CRON_SECRET as other scheduled jobs).
 * Optional backfill: ?date=YYYY-MM-DD
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

$result = process_execute('daily-sales-summary', [
    'date' => $_GET['date'] ?? null,
]);

http_response_code($result['ok'] ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
exit;
