#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';

$result = inventory_transfers_backfill_shipments();
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";