#!/usr/bin/env php
<?php
/**
 * Import PO production status from a Wells open-order report or similar spreadsheet.
 *
 * Usage:
 *   php scripts/import-po-production-status.php [path-to.xlsx] [--dry-run]
 *
 * Rows are matched by PO number (PO#) and SKU number (SKUNumber / ItemSKU).
 */

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-production-import.php';

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$fileArg = null;

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        continue;
    }
    $fileArg = $arg;
    break;
}

$defaultFile = dirname(__DIR__) . '/docs/imports/Copy of Wells OOR 060926.xlsx';
$filePath = $fileArg !== null ? $fileArg : $defaultFile;

if (!is_file($filePath)) {
    fwrite(STDERR, "File not found: {$filePath}\n");
    exit(1);
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
if ($ext === 'csv') {
    $parsed = po_production_import_parse_csv($filePath);
} elseif (in_array($ext, ['xlsx', 'xls'], true)) {
    $parsed = po_production_import_parse_xlsx($filePath);
} else {
    fwrite(STDERR, "Unsupported file type: .{$ext}\n");
    exit(1);
}

if (!$parsed['ok']) {
    fwrite(STDERR, 'Parse failed: ' . ($parsed['error'] ?? 'Unknown error') . "\n");
    exit(1);
}

$rows = $parsed['rows'];
$isCli = PHP_SAPI === 'cli';
$resolved = po_production_import_resolve_rows($rows, !$isCli);

echo 'Parsed ' . count($rows) . " row(s) from {$filePath}\n";

foreach ($resolved as $row) {
    $mapped = $row['mapped'];
    $match = $row['match'];
    $status = !empty($match['found']) && !empty($match['editable'])
        ? 'ready'
        : (!empty($match['found']) ? 'skip (not editable)' : 'skip (not found)');

    echo sprintf(
        "  row %d: %s | %s | %s | mfg=%s bottle=%s bulk=%s\n",
        (int) $row['row_number'],
        $row['po_number'],
        $row['sku'],
        $status,
        $mapped['mfg_status'],
        $mapped['bottle_packaging_status'],
        $mapped['bulk_test_status']
    );

    foreach ($row['warnings'] as $warning) {
        echo "    warning: {$warning}\n";
    }

    if (!empty($match['error'])) {
        echo '    note: ' . $match['error'] . "\n";
    }
}

if ($dryRun) {
    $ready = count(array_filter($resolved, fn(array $row): bool => !empty($row['match']['found']) && !empty($row['match']['editable'])));
    echo "Dry run complete. {$ready} row(s) would be updated.\n";
    exit(0);
}

$result = po_production_import_apply($rows, null, false);

if (!$result['ok']) {
    fwrite(STDERR, 'Import failed: ' . ($result['error'] ?? 'Unknown error') . "\n");
    exit(1);
}

echo sprintf(
    "Done. Updated %d, skipped %d.\n",
    (int) $result['updated'],
    (int) $result['skipped']
);

if (!empty($result['errors'])) {
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, "  {$error}\n");
    }
}

exit(0);
