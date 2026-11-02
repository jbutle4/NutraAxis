<?php
/**
 * Build + email the Inventory Balances workbook to alert subscribers.
 *
 * CLI:  php scripts/send-inventory-balances-report.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/includes/init.php';
require $root . '/includes/inventory-balances-report.php';

$result = inventory_balances_report_send_now(INVENTORY_LEDGER_PROFILE_PRODUCTION);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(!empty($result['ok']) ? 0 : 1);
