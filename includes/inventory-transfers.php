<?php

require_once __DIR__ . '/inventory-posting.php';
require_once __DIR__ . '/inventory-ledger.php';
require_once __DIR__ . '/facility.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/quickbooks.php';

function inventory_transfers_permission_value(): ?string
{
    return inventory_ledger_permission_value();
}

function inventory_transfers_can_read(): bool
{
    return inventory_ledger_can_read();
}

function inventory_transfers_can_update(): bool
{
    return auth_can_update('InventoryReporting') || auth_can_create('InventoryReporting');
}

function inventory_transfers_require_read(): void
{
    auth_require_login();
    if (inventory_transfers_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view facility transfers.');
}

function inventory_transfers_require_update(): void
{
    auth_require_login();
    if (inventory_transfers_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create facility transfers.');
}

function inventory_transfers_list(array $filters = []): array
{
    $pdo = db();
    $where = ['1 = 1'];
    $params = [];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 't.TransferStatus = :status';
        $params['status'] = $status;
    }

    $sql = <<<SQL
        SELECT
            t.TransferID,
            t.SKUCode,
            t.FromFacilityCode,
            t.ToFacilityCode,
            t.FromStatusBucket,
            t.ToStatusBucket,
            t.QtyRequested,
            t.QtyShipped,
            t.QtyReceived,
            t.TransferStatus,
            t.Notes,
            t.CreateDate,
            t.OutboundTransactionID,
            t.InboundTransactionID
        FROM dbo.InvTransfer t
        WHERE %s
        ORDER BY t.TransferID DESC
    SQL;
    $stmt = $pdo->prepare(sprintf($sql, implode(' AND ', $where)));
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function inventory_transfers_get(int $transferId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.InvTransfer WHERE TransferID = :id');
    $stmt->execute(['id' => $transferId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function inventory_transfers_list_facilities(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT FacilityCode, FacilityName, FacilityType, IsMothership, IsActive
        FROM dbo.Facility
        WHERE IsActive = 1
        ORDER BY IsMothership DESC, FacilityCode
    SQL);

    return $stmt->fetchAll();
}

function inventory_transfers_reason_codes(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT ReasonCodeID, ReasonCode, Description
        FROM dbo.InvReasonCode
        WHERE AppliesToTransfer = 1 AND IsActive = 1
        ORDER BY ReasonCode
    SQL);

    return $stmt->fetchAll();
}

function inventory_transfers_ship(int $transferId, ?int $userId = null): array
{
    $transfer = inventory_transfers_get($transferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }
    if (($transfer['TransferStatus'] ?? '') !== 'Pending') {
        return ['ok' => false, 'error' => 'Only pending transfers can be shipped.'];
    }

    $qty = (float) ($transfer['QtyRequested'] ?? 0);
    $from = (string) $transfer['FromFacilityCode'];
    $to = (string) $transfer['ToFacilityCode'];
    $useTransit = facility_get_by_code('TRANSIT') !== null
        && strcasecmp($from, 'CART') === 0
        && strcasecmp($to, 'TRANSIT') !== 0;

    $outFacility = $from;
    $inFacility = $useTransit ? 'TRANSIT' : $to;

    $post = inventory_posting_create_transaction(
        'TransferOut',
        'InvTransfer',
        $transferId,
        [[
            'sku_code' => (string) $transfer['SKUCode'],
            'facility_code' => $outFacility,
            'status_bucket' => (string) ($transfer['FromStatusBucket'] ?? 'OK'),
            'qty_change' => -$qty,
        ]],
        'Transfer ship #' . $transferId,
        $userId
    );
    if (!$post['ok']) {
        return $post;
    }

    $inPost = inventory_posting_create_transaction(
        'TransferIn',
        'InvTransfer',
        $transferId,
        [[
            'sku_code' => (string) $transfer['SKUCode'],
            'facility_code' => $inFacility,
            'status_bucket' => (string) ($transfer['ToStatusBucket'] ?? 'OK'),
            'qty_change' => $qty,
        ]],
        'Transfer ship in-transit #' . $transferId,
        $userId
    );
    if (!$inPost['ok']) {
        return $inPost;
    }

    $pdo = db();
    $nextStatus = $useTransit ? 'InTransit' : 'Received';
    $pdo->prepare(<<<SQL
        UPDATE dbo.InvTransfer
        SET TransferStatus = :status,
            QtyShipped = :qty,
            QtyReceived = CASE WHEN :status2 = N'Received' THEN :qty2 ELSE QtyReceived END,
            OutboundTransactionID = :ship_txn,
            InboundTransactionID = CASE WHEN :status3 = N'Received' THEN :recv_txn ELSE InboundTransactionID END,
            ShippedAt = SYSUTCDATETIME(),
            ReceivedAt = CASE WHEN :status4 = N'Received' THEN SYSUTCDATETIME() ELSE ReceivedAt END,
            ModifiedDate = SYSUTCDATETIME()
        WHERE TransferID = :id
    SQL)->execute([
        'status' => $nextStatus,
        'status2' => $nextStatus,
        'status3' => $nextStatus,
        'status4' => $nextStatus,
        'qty' => $qty,
        'qty2' => $qty,
        'ship_txn' => $post['transaction_id'],
        'recv_txn' => $inPost['transaction_id'],
        'id' => $transferId,
    ]);

    $jeResult = inventory_transfers_maybe_post_qbo_journal($transferId);

    return [
        'ok' => true,
        'error' => null,
        'status' => $nextStatus,
        'qbo' => $jeResult,
    ];
}

function inventory_transfers_receive(int $transferId, ?int $userId = null): array
{
    $transfer = inventory_transfers_get($transferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }
    if (($transfer['TransferStatus'] ?? '') !== 'InTransit') {
        return ['ok' => false, 'error' => 'Only in-transit transfers can be received.'];
    }

    $qty = (float) ($transfer['QtyShipped'] ?? $transfer['QtyRequested'] ?? 0);
    $to = (string) $transfer['ToFacilityCode'];

    $outPost = inventory_posting_create_transaction(
        'TransferOut',
        'InvTransfer',
        $transferId,
        [[
            'sku_code' => (string) $transfer['SKUCode'],
            'facility_code' => 'TRANSIT',
            'status_bucket' => (string) ($transfer['ToStatusBucket'] ?? 'OK'),
            'qty_change' => -$qty,
        ]],
        'Transfer receive from transit #' . $transferId,
        $userId
    );
    if (!$outPost['ok']) {
        return $outPost;
    }

    $inPost = inventory_posting_create_transaction(
        'TransferIn',
        'InvTransfer',
        $transferId,
        [[
            'sku_code' => (string) $transfer['SKUCode'],
            'facility_code' => $to,
            'status_bucket' => (string) ($transfer['ToStatusBucket'] ?? 'OK'),
            'qty_change' => $qty,
        ]],
        'Transfer receive #' . $transferId,
        $userId
    );
    if (!$inPost['ok']) {
        return $inPost;
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.InvTransfer
        SET TransferStatus = N'Received',
            QtyReceived = :qty,
            InboundTransactionID = :recv_txn,
            ReceivedAt = SYSUTCDATETIME(),
            ModifiedDate = SYSUTCDATETIME()
        WHERE TransferID = :id
    SQL)->execute([
        'qty' => $qty,
        'recv_txn' => $inPost['transaction_id'],
        'id' => $transferId,
    ]);

    return ['ok' => true, 'error' => null];
}

/**
 * Optional QBO Journal Entry between inventory asset accounts when env accounts are set.
 * Same-SKU transfers do not change QtyOnHand.
 */
function inventory_transfers_maybe_post_qbo_journal(int $transferId): array
{
    if (!qbo_is_connected()) {
        return ['ok' => true, 'skipped' => true, 'error' => null];
    }

    $transfer = inventory_transfers_get($transferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }

    $fromCode = strtoupper((string) $transfer['FromFacilityCode']);
    $toCode = strtoupper((string) $transfer['ToFacilityCode']);
    $debit = inventory_transfers_asset_account_for_facility($toCode);
    $credit = inventory_transfers_asset_account_for_facility($fromCode);
    if ($debit === '' || $credit === '' || $debit === $credit) {
        return ['ok' => true, 'skipped' => true, 'error' => null, 'reason' => 'Asset accounts not configured for this path.'];
    }

    $docNumber = 'NA-XFER-' . $transferId;
    if (qbo_inventory_sync_log_exists($docNumber)) {
        return ['ok' => true, 'skipped' => true, 'error' => null, 'reason' => 'Already posted.'];
    }

    $sku = trim((string) $transfer['SKUCode']);
    $qty = (float) ($transfer['QtyRequested'] ?? 0);
    $unitCost = inventory_transfers_sku_unit_cost($sku);
    $amount = round(max(0.01, $qty * max(0.01, $unitCost)), 2);

    $result = qbo_post_inventory_transfer_journal_entry(
        $docNumber,
        $debit,
        $credit,
        $amount,
        'Facility transfer ' . $fromCode . ' → ' . $toCode . ' SKU ' . $sku
    );

    qbo_inventory_sync_log_write([
        'doc_number' => $docNumber,
        'sync_type' => 'TransferJE',
        'reference_type' => 'InvTransfer',
        'reference_id' => $transferId,
        'sku_code' => $sku,
        'qty_change' => 0,
        'facility_code' => $toCode,
        'qbo_txn_id' => $result['ok'] ? ($result['txn']['Id'] ?? null) : null,
        'qbo_sync_token' => $result['ok'] ? ($result['txn']['SyncToken'] ?? null) : null,
        'sync_status' => $result['ok'] ? 'Synced' : 'Error',
        'sync_error' => $result['ok'] ? null : ($result['error'] ?? 'Journal entry failed'),
    ]);

    return $result;
}

function inventory_transfers_asset_account_for_facility(string $facilityCode): string
{
    $facilityCode = strtoupper(trim($facilityCode));
    $map = [
        'CART' => trim((string) env('QBO_INV_ASSET_ACCOUNT_CART', '')),
        'CART_COM' => trim((string) env('QBO_INV_ASSET_ACCOUNT_CART', '')),
        'CPPC' => trim((string) env('QBO_INV_ASSET_ACCOUNT_CPPC', '')),
        'WLO' => trim((string) env('QBO_INV_ASSET_ACCOUNT_WPC', '')),
        'WPC_QUEUE' => trim((string) env('QBO_INV_ASSET_ACCOUNT_WPC', '')),
        'WPC_WIP' => trim((string) env('QBO_INV_ASSET_ACCOUNT_WPC', '')),
    ];

    return $map[$facilityCode] ?? '';
}

function inventory_transfers_sku_unit_cost(string $skuCode): float
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COGS FROM dbo.SKUMaster WHERE SKUCode = :sku');
    $stmt->execute(['sku' => $skuCode]);
    $cogs = $stmt->fetchColumn();

    return $cogs === false || $cogs === null ? 0.0 : (float) $cogs;
}

function inventory_transfers_adjust_account_id(): string
{
    return trim((string) env('QBO_INV_ADJUST_ACCOUNT_ID', env('QBO_INV_ASSET_ACCOUNT_CART', '')));
}
