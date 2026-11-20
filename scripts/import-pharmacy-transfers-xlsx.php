#!/usr/bin/env php
<?php
/**
 * Import CART → pharmacy transfer lines from scripts/jazz-order-samples/pharmacy-replen-from-xlsx-uat.json
 * (PharmacyTransfer_JoeButler_20261120.xlsx).
 *
 * Usage:
 *   php scripts/import-pharmacy-transfers-xlsx.php --dry-run
 *   php scripts/import-pharmacy-transfers-xlsx.php --confirm
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/includes/init.php';
require $root . '/includes/inventory-transfers.php';

$dryRun = in_array('--dry-run', $argv, true);
$confirm = in_array('--confirm', $argv, true);
if (!$dryRun && !$confirm) {
    fwrite(STDERR, "Pass --dry-run or --confirm\n");
    exit(1);
}

$jsonPath = $root . '/scripts/jazz-order-samples/pharmacy-replen-from-xlsx-uat.json';
$payload = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($payload) || !isset($payload['orders'])) {
    fwrite(STDERR, "Invalid JSON: {$jsonPath}\n");
    exit(1);
}

$pharmacyByOrderPrefix = [
    'TRN-Ocala-'     => 'OCALA',
    'TRN-Dallas-'    => 'DALLAS',
    'TRN-Melbourne-' => 'MELBOURNE',
];

function resolve_pharmacy_facility(string $orderNumber): ?string
{
    global $pharmacyByOrderPrefix;
    foreach ($pharmacyByOrderPrefix as $prefix => $code) {
        if (str_starts_with($orderNumber, $prefix)) {
            return $code;
        }
    }

    return null;
}

$pdo = db();
$userStmt = $pdo->prepare(<<<SQL
    SELECT TOP (1) UserID, UserName, UserLogin, UserAssignedRole
    FROM dbo.[User]
    WHERE UserName LIKE :joe OR UserLogin LIKE :joe
    ORDER BY UserID
SQL);
$userStmt->execute(['joe' => '%Joe%Butler%']);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRow) {
    $userRow = $pdo->query('SELECT TOP (1) UserID, UserName, UserLogin, UserAssignedRole FROM dbo.[User] ORDER BY UserID')->fetch(PDO::FETCH_ASSOC);
}
if (!$userRow) {
    fwrite(STDERR, "No user for RequestedByUser.\n");
    exit(1);
}

auth_start_session();
$_SESSION[AUTH_SESSION_KEY] = [
    'UserID'             => (int) $userRow['UserID'],
    'UserName'           => (string) $userRow['UserName'],
    'UserLogin'          => (string) $userRow['UserLogin'],
    'UserAssignedRole'   => (int) $userRow['UserAssignedRole'],
    'RoleName'           => '',
];

$fromCode = facility_default_po_receipt_code();
$ledger = inventory_ledger_profile();
$summary = [];

foreach ($payload['orders'] as $order) {
    $orderNumber = (string) ($order['order_number'] ?? '');
    $toCode = resolve_pharmacy_facility($orderNumber);
    if ($toCode === null) {
        fwrite(STDERR, "Skip unknown order: {$orderNumber}\n");
        continue;
    }

    $shipBy = (string) ($order['ship_date'] ?? '');
    $sheet = (string) ($order['attributes']['nutraaxis_transfer_sheet'] ?? $orderNumber);
    $comments = (string) ($order['attributes']['shipment_comments'] ?? 'LTL');
    $notes = trim($sheet . ' · ' . $comments . ' · Jazz ref ' . $orderNumber);

    $details = $order['shipto'][0]['detail_set'] ?? [];
    $lines = [];
    foreach ($details as $detail) {
        $lines[] = [
            'sku_code'      => (string) ($detail['sku'] ?? ''),
            'qty_requested' => (float) ($detail['qty_ordered'] ?? 0),
        ];
    }

    $batchKey = $orderNumber;
    $check = $pdo->prepare(<<<SQL
        SELECT COUNT(*) AS cnt
        FROM dbo.InvTransfer
        WHERE LedgerProfile = :ledger
          AND FromFacilityCode = :from_code
          AND ToFacilityCode = :to_code
          AND JazzShipByDate = :ship_by
          AND TransferStatus = N'Pending'
          AND Notes LIKE :note_hint
    SQL);
    $check->execute([
        'ledger'     => $ledger,
        'from_code'  => $fromCode,
        'to_code'    => $toCode,
        'ship_by'    => $shipBy,
        'note_hint'  => '%' . $orderNumber . '%',
    ]);
    $existing = (int) $check->fetchColumn();

    if ($existing >= count($lines)) {
        $summary[] = [
            'order'      => $orderNumber,
            'to'         => $toCode,
            'ship_by'    => $shipBy,
            'lines'      => count($lines),
            'status'     => 'skipped_existing',
            'transfer_ids' => [],
        ];
        continue;
    }

    if ($dryRun) {
        $summary[] = [
            'order'   => $orderNumber,
            'to'      => $toCode,
            'ship_by' => $shipBy,
            'lines'   => count($lines),
            'status'  => 'would_create',
            'skus'    => array_column($lines, 'sku_code'),
        ];
        continue;
    }

    $result = inventory_transfers_create_batch([
        'from_facility_code' => $fromCode,
        'to_facility_code'   => $toCode,
        'from_status_bucket' => 'OK',
        'to_status_bucket'   => 'OK',
        'notes'              => $notes,
        'jazz_ship_by_date'  => $shipBy,
    ], $lines);

    $summary[] = [
        'order'        => $orderNumber,
        'to'           => $toCode,
        'ship_by'      => $shipBy,
        'lines'        => count($lines),
        'status'       => $result['ok'] ? 'created' : 'error',
        'error'        => $result['error'] ?? null,
        'transfer_ids' => $result['transfer_ids'] ?? [],
        'shipment_id'  => $result['shipment_id'] ?? null,
    ];
}

echo json_encode([
    'dry_run'  => $dryRun,
    'ledger'   => $ledger,
    'from'     => $fromCode,
    'user'     => $userRow['UserName'] ?? null,
    'source'   => basename($jsonPath),
    'batches'  => $summary,
], JSON_PRETTY_PRINT) . "\n";