#!/usr/bin/env php
<?php
/**
 * Manual daily sales summary (CLI).
 * Usage: php scripts/daily-sales-summary.php [YYYY-MM-DD]
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/process-runner.php';

$result = process_execute('daily-sales-summary', [
    'date' => $argv[1] ?? null,
], PROCESS_LOG_TRIGGER_MANUAL);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
