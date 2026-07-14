<?php

declare(strict_types=1);

/**
 * Seed initial COA documents from coa-test/files PDFs.
 * Usage: php scripts/seed-coa-documents.php
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/coa.php';

$seeds = [
    [
        'product_name'       => 'AdrenaAxis',
        'lot_number'         => '37489',
        'expiration_date'    => '2026-04-23',
        'expiration_display' => '04/2028',
        'sort_order'         => 20,
        'file'               => __DIR__ . '/../coa-test/files/coa-na-wilc057-adrenaaxis-lot-37489-04-23-26.pdf',
    ],
    [
        'product_name'       => 'IronAxis',
        'lot_number'         => '37340',
        'expiration_date'    => '2026-03-25',
        'expiration_display' => '02/28',
        'sort_order'         => 10,
        'file'               => __DIR__ . '/../coa-test/files/coa-na-wilc058-ironaxis-lot-37340-03-25-2026.pdf',
    ],
];

$pdo = db();
$count = (int) $pdo->query('SELECT COUNT(*) FROM dbo.CoaDocument')->fetchColumn();
if ($count > 0) {
    echo "CoaDocument already has {$count} row(s); skipping seed.\n";
    exit(0);
}

foreach ($seeds as $seed) {
    if (!is_readable($seed['file'])) {
        fwrite(STDERR, "Missing seed file: {$seed['file']}\n");
        exit(1);
    }

    $tmp = sys_get_temp_dir() . '/coa-seed-' . basename($seed['file']);
    copy($seed['file'], $tmp);

    $file = [
        'name'     => basename($seed['file']),
        'type'     => 'application/pdf',
        'tmp_name' => $tmp,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($tmp),
    ];

    $result = coa_save([
        'product_name'       => $seed['product_name'],
        'lot_number'         => $seed['lot_number'],
        'expiration_date'    => $seed['expiration_date'],
        'expiration_display' => $seed['expiration_display'],
        'is_published'       => '1',
        'sort_order'         => (string) $seed['sort_order'],
    ], $file);

    @unlink($tmp);

    if (!$result['ok']) {
        fwrite(STDERR, "Failed seeding {$seed['product_name']} / {$seed['lot_number']}: {$result['error']}\n");
        exit(1);
    }

    echo "Seeded COA #{$result['id']}: {$seed['product_name']} / {$seed['lot_number']}\n";
}

echo "Done.\n";
