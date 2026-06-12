#!/usr/bin/env php
<?php
/**
 * Manual Jazz inventory snapshot (CLI).
 * Usage: php scripts/jazz-inventory-snapshot.php
 */

require dirname(__DIR__) . '/includes/env.php';
require dirname(__DIR__) . '/includes/database.php';
require dirname(__DIR__) . '/includes/process-runner.php';

$result = process_execute('jazz-inventory-snapshot', [], PROCESS_LOG_TRIGGER_MANUAL);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['ok'] ? 0 : 1);
