<?php

require_once __DIR__ . '/inventory-posting.php';
require_once __DIR__ . '/inventory-ledger.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/quickbooks.php';

function inventory_adjustments_permission_value(): ?string
{
    return inventory_ledger_permission_value();
}

function inventory_adjustments_can_read(): bool
{
    return inventory_ledger_can_read();
}

function inventory_adjustments_can_update(): bool
{
    return auth_can_update('InventoryReporting') || auth_can_create('InventoryReporting');
}

function inventory_adjustments_require_read(): void
{
    auth_require_login();
    if (inventory_adjustments_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view inventory adjustments.');
}

function inventory_adjustments_require_update(): void
{
    auth_require_login();
    if (inventory_adjustments_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create or approve inventory adjustments.');
}

/**
 * @return array<int, array<string, mixed>>
 */
function inventory_adjustments_list(array $filters = []): array
{
    $pdo = db();
    $where = ['1 = 1'];
    $params = [];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 'a.AdjStatus = :status';
        $params['status'] = $status;
    }

    $sql = <<<SQL
        SELECT
            a.AdjustmentID,
            a.AdjustmentDate,
            a.SKUCode,
            a.FacilityCode,
            a.StatusBucket,
            a.QtyAdjusted,
            a.QtyBefore,
            a.QtyAfter,
            a.AdjStatus,
            a.Notes,
            a.CreateDate,
            a.TransactionID,
            a.ApprovedAt,
            rc.ReasonCode,
            rc.Description AS ReasonDescription
        FROM dbo.InvAdjustment a
        LEFT JOIN dbo.InvReasonCode rc ON rc.ReasonCodeID = a.ReasonCodeID
        WHERE %s
        ORDER BY a.AdjustmentID DESC
    SQL;
    $stmt = $pdo->prepare(sprintf($sql, implode(' AND ', $where)));
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

/**
 * @return array<string, mixed>|null
 */
function inventory_adjustments_get(int $adjustmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.*,
            rc.ReasonCode,
            rc.Description AS ReasonDescription,
            rc.DefaultDirection
        FROM dbo.InvAdjustment a
        LEFT JOIN dbo.InvReasonCode rc ON rc.ReasonCodeID = a.ReasonCodeID
        WHERE a.AdjustmentID = :id
    SQL);
    $stmt->execute(['id' => $adjustmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/**
 * @return array<int, array<string, mixed>>
 */
function inventory_adjustments_list_facilities(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT FacilityCode, FacilityName, FacilityType, IsMothership, IsActive
        FROM dbo.Facility
        WHERE IsActive = 1
        ORDER BY IsMothership DESC, FacilityCode
    SQL);

    return $stmt->fetchAll() ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function inventory_adjustments_reason_codes(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT ReasonCodeID, ReasonCode, Description, DefaultDirection
        FROM dbo.InvReasonCode
        WHERE AppliesToAdjustment = 1 AND IsActive = 1
        ORDER BY ReasonCode
    SQL);

    return $stmt->fetchAll() ?: [];
}

function inventory_adjustments_doc_number(int $adjustmentId): string
{
    return 'NA-ADJ-' . $adjustmentId;
}

function inventory_adjustments_adjust_account_id(): string
{
    return trim((string) env('QBO_INV_ADJUST_ACCOUNT_ID', env('QBO_INV_ASSET_ACCOUNT_CART', '')));
}

/**
 * Resolve live QBO Inventory Item Id for a SKU (prefer Inventory type).
 *
 * @return array{ok:bool,error:?string,item_id:string}
 */
function inventory_adjustments_resolve_qbo_item_id(string $skuCode): array
{
    $skuCode = trim($skuCode);
    if ($skuCode === '') {
        return ['ok' => false, 'error' => 'SKU is required.', 'item_id' => ''];
    }

    if (qbo_is_connected()) {
        $found = qbo_find_item_by_sku($skuCode);
        if ($found['ok']) {
            $candidates = [];
            if (isset($found['items']) && is_array($found['items'])) {
                $candidates = $found['items'];
            } elseif (isset($found['item']) && is_array($found['item'])) {
                $candidates = [$found['item']];
            } elseif (isset($found['data']['QueryResponse']['Item'])) {
                $raw = $found['data']['QueryResponse']['Item'];
                $candidates = isset($raw[0]) ? $raw : [$raw];
            }

            $inventory = null;
            foreach ($candidates as $candidate) {
                if (!is_array($candidate)) {
                    continue;
                }
                if (strcasecmp((string) ($candidate['Type'] ?? ''), 'Inventory') === 0) {
                    $inventory = $candidate;
                    if (($candidate['Active'] ?? true) !== false) {
                        break;
                    }
                }
            }

            $itemId = trim((string) ($inventory['Id'] ?? ''));
            if ($itemId !== '') {
                $pdo = db();
                $pdo->prepare(<<<SQL
                    UPDATE dbo.SKUMaster
                    SET QBO_ItemID = :item_id,
                        QBO_SyncStatus = N'Synced',
                        QBO_SyncError = NULL,
                        QBO_SyncedAt = SYSUTCDATETIME()
                    WHERE SKUCode = :sku
                      AND (
                        QBO_ItemID IS NULL
                        OR LTRIM(RTRIM(QBO_ItemID)) = N''
                        OR QBO_ItemID <> :item_id2
                      )
                SQL)->execute([
                    'item_id' => $itemId,
                    'item_id2' => $itemId,
                    'sku' => $skuCode,
                ]);

                return ['ok' => true, 'error' => null, 'item_id' => $itemId];
            }
        }
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT QBO_ItemID FROM dbo.SKUMaster WHERE SKUCode = :sku');
    $stmt->execute(['sku' => $skuCode]);
    $localId = trim((string) ($stmt->fetchColumn() ?: ''));
    if ($localId !== '') {
        return ['ok' => true, 'error' => null, 'item_id' => $localId];
    }

    return [
        'ok' => false,
        'error' => 'No QuickBooks Inventory item Id found for SKU ' . $skuCode . '.',
        'item_id' => '',
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{ok:bool,error:?string,adjustment_id:?int}
 */
function inventory_adjustments_create(array $input): array
{
    $skuCode = trim((string) ($input['sku_code'] ?? ''));
    $facilityCode = trim((string) ($input['facility_code'] ?? ''));
    $bucket = trim((string) ($input['status_bucket'] ?? 'OK'));
    $reasonCodeId = (int) ($input['reason_code_id'] ?? 0);
    $notes = trim((string) ($input['notes'] ?? ''));
    $direction = strtoupper(trim((string) ($input['direction'] ?? '-')));
    $qtyAbs = abs((float) ($input['qty'] ?? 0));

    if ($skuCode === '') {
        return ['ok' => false, 'error' => 'SKU is required.', 'adjustment_id' => null];
    }
    if ($facilityCode === '') {
        return ['ok' => false, 'error' => 'Facility is required.', 'adjustment_id' => null];
    }
    if (!in_array($bucket, INV_STATUS_BUCKETS, true)) {
        return ['ok' => false, 'error' => 'Select a valid status bucket.', 'adjustment_id' => null];
    }
    if ($reasonCodeId <= 0) {
        return ['ok' => false, 'error' => 'Reason code is required.', 'adjustment_id' => null];
    }
    if ($qtyAbs < 0.0000001) {
        return ['ok' => false, 'error' => 'Quantity must be greater than zero.', 'adjustment_id' => null];
    }
    if ($direction !== '+' && $direction !== '-') {
        return ['ok' => false, 'error' => 'Direction must be shrink (−) or gain (+).', 'adjustment_id' => null];
    }

    $qtyAdjusted = $direction === '+' ? $qtyAbs : -$qtyAbs;

    $userId = auth_user()['UserID'] ?? null;
    if ($userId === null) {
        return ['ok' => false, 'error' => 'You must be signed in to create an adjustment.', 'adjustment_id' => null];
    }

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);

        $skuStmt = $pdo->prepare('SELECT 1 FROM dbo.SKUMaster WHERE SKUCode = :sku');
        $skuStmt->execute(['sku' => $skuCode]);
        if (!$skuStmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'SKU was not found in SKU Master.', 'adjustment_id' => null];
        }

        $facStmt = $pdo->prepare('SELECT 1 FROM dbo.Facility WHERE FacilityCode = :code AND IsActive = 1');
        $facStmt->execute(['code' => $facilityCode]);
        if (!$facStmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'Facility was not found or is inactive.', 'adjustment_id' => null];
        }

        $reasonStmt = $pdo->prepare(<<<SQL
            SELECT ReasonCodeID
            FROM dbo.InvReasonCode
            WHERE ReasonCodeID = :id AND AppliesToAdjustment = 1 AND IsActive = 1
        SQL);
        $reasonStmt->execute(['id' => $reasonCodeId]);
        if (!$reasonStmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'Select a valid adjustment reason code.', 'adjustment_id' => null];
        }

        $balance = inventory_posting_get_or_create_balance($pdo, $skuCode, $facilityCode);
        $column = inventory_posting_bucket_column($bucket);
        $qtyBefore = (float) ($balance[$column] ?? 0);
        $qtyAfter = $qtyBefore + $qtyAdjusted;
        if ($qtyAfter < -0.0000001) {
            return [
                'ok' => false,
                'error' => 'Insufficient ' . $bucket . ' qty for ' . $skuCode . ' at ' . $facilityCode
                    . ' (have ' . $qtyBefore . ', shrink ' . $qtyAbs . ').',
                'adjustment_id' => null,
            ];
        }

        $insert = $pdo->prepare(<<<SQL
            INSERT INTO dbo.InvAdjustment (
                SKUCode, FacilityCode, StatusBucket,
                QtyAdjusted, QtyBefore, QtyAfter,
                ReasonCodeID, Notes, AdjStatus, CreatedByUser
            )
            OUTPUT INSERTED.AdjustmentID AS inserted_id
            VALUES (
                :sku, :facility, :bucket,
                :qty_adj, :qty_before, :qty_after,
                :reason_id, :notes, N'Pending', :user_id
            )
        SQL);
        $insert->execute([
            'sku' => $skuCode,
            'facility' => $facilityCode,
            'bucket' => $bucket,
            'qty_adj' => $qtyAdjusted,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'reason_id' => $reasonCodeId,
            'notes' => $notes !== '' ? $notes : null,
            'user_id' => (int) $userId,
        ]);
        $adjustmentId = db_fetch_inserted_int($insert, 'inserted_id');

        return ['ok' => true, 'error' => null, 'adjustment_id' => $adjustmentId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'adjustment_id' => null];
    }
}

/**
 * @return array{ok:bool,error:?string,qbo_ok:?bool,qbo_error:?string}
 */
function inventory_adjustments_approve(int $adjustmentId, ?int $userId = null): array
{
    $adjustment = inventory_adjustments_get($adjustmentId);
    if ($adjustment === null) {
        return ['ok' => false, 'error' => 'Adjustment not found.', 'qbo_ok' => null, 'qbo_error' => null];
    }
    if (($adjustment['AdjStatus'] ?? '') !== 'Pending' && ($adjustment['AdjStatus'] ?? '') !== 'Approved') {
        return ['ok' => false, 'error' => 'Only pending (or approved-unposted) adjustments can be approved/posted.', 'qbo_ok' => null, 'qbo_error' => null];
    }

    $qty = (float) ($adjustment['QtyAdjusted'] ?? 0);
    if (abs($qty) < 0.0000001) {
        return ['ok' => false, 'error' => 'Adjustment quantity is zero.', 'qbo_ok' => null, 'qbo_error' => null];
    }

    $txnId = (int) ($adjustment['TransactionID'] ?? 0);
    if ($txnId <= 0) {
        $txnType = $qty > 0 ? 'AdjustmentGain' : 'AdjustmentLoss';
        $post = inventory_posting_create_transaction(
            $txnType,
            'InvAdjustment',
            $adjustmentId,
            [[
                'sku_code' => (string) $adjustment['SKUCode'],
                'facility_code' => (string) $adjustment['FacilityCode'],
                'status_bucket' => (string) ($adjustment['StatusBucket'] ?? 'OK'),
                'qty_change' => $qty,
            ]],
            'Inventory adjustment #' . $adjustmentId,
            $userId
        );
        if (!$post['ok']) {
            return [
                'ok' => false,
                'error' => $post['error'] ?? 'IMS posting failed.',
                'qbo_ok' => null,
                'qbo_error' => null,
            ];
        }
        $txnId = (int) ($post['transaction_id'] ?? 0);

        $pdo = db();
        $lineStmt = $pdo->prepare(<<<SQL
            SELECT TOP (1) QtyBefore, QtyAfter
            FROM dbo.InvTransactionLine
            WHERE TransactionID = :txn_id
            ORDER BY LineNumber
        SQL);
        $lineStmt->execute(['txn_id' => $txnId]);
        $line = $lineStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $pdo->prepare(<<<SQL
            UPDATE dbo.InvAdjustment
            SET AdjStatus = N'Approved',
                ApprovedByUser = :user_id,
                ApprovedAt = SYSUTCDATETIME(),
                TransactionID = :txn_id,
                QtyBefore = :qty_before,
                QtyAfter = :qty_after
            WHERE AdjustmentID = :id
              AND AdjStatus IN (N'Pending', N'Approved')
        SQL)->execute([
            'user_id' => $userId,
            'txn_id' => $txnId,
            'qty_before' => (float) ($line['QtyBefore'] ?? $adjustment['QtyBefore'] ?? 0),
            'qty_after' => (float) ($line['QtyAfter'] ?? $adjustment['QtyAfter'] ?? 0),
            'id' => $adjustmentId,
        ]);
    } elseif (($adjustment['AdjStatus'] ?? '') === 'Pending') {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.InvAdjustment
            SET AdjStatus = N'Approved',
                ApprovedByUser = :user_id,
                ApprovedAt = SYSUTCDATETIME()
            WHERE AdjustmentID = :id
        SQL)->execute([
            'user_id' => $userId,
            'id' => $adjustmentId,
        ]);
    }

    $qboResult = inventory_adjustments_post_qbo($adjustmentId);
    if (!$qboResult['ok'] && empty($qboResult['skipped'])) {
        return [
            'ok' => true,
            'error' => null,
            'qbo_ok' => false,
            'qbo_error' => $qboResult['error'] ?? 'QBO posting failed.',
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'qbo_ok' => empty($qboResult['skipped']) ? true : null,
        'qbo_error' => null,
    ];
}

/**
 * @return array{ok:bool,error:?string}
 */
function inventory_adjustments_reject(int $adjustmentId, ?int $userId = null): array
{
    $adjustment = inventory_adjustments_get($adjustmentId);
    if ($adjustment === null) {
        return ['ok' => false, 'error' => 'Adjustment not found.'];
    }
    if (($adjustment['AdjStatus'] ?? '') !== 'Pending') {
        return ['ok' => false, 'error' => 'Only pending adjustments can be rejected.'];
    }
    if ((int) ($adjustment['TransactionID'] ?? 0) > 0) {
        return ['ok' => false, 'error' => 'Cannot reject an adjustment that already posted to IMS.'];
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.InvAdjustment
        SET AdjStatus = N'Rejected',
            ApprovedByUser = :user_id,
            ApprovedAt = SYSUTCDATETIME()
        WHERE AdjustmentID = :id
          AND AdjStatus = N'Pending'
    SQL)->execute([
        'user_id' => $userId,
        'id' => $adjustmentId,
    ]);

    return ['ok' => true, 'error' => null];
}

/**
 * @return array{ok:bool,error:?string,skipped?:bool,txn?:?array}
 */
function inventory_adjustments_post_qbo(int $adjustmentId): array
{
    $adjustment = inventory_adjustments_get($adjustmentId);
    if ($adjustment === null) {
        return ['ok' => false, 'error' => 'Adjustment not found.'];
    }
    if (($adjustment['AdjStatus'] ?? '') !== 'Approved') {
        return ['ok' => false, 'error' => 'Adjustment must be Approved before QBO post.'];
    }

    $docNumber = inventory_adjustments_doc_number($adjustmentId);
    $existingStatus = qbo_inventory_sync_log_status($docNumber);
    if ($existingStatus === 'Synced') {
        return ['ok' => true, 'skipped' => true, 'error' => null, 'reason' => 'Already synced.'];
    }

    if (!qbo_is_connected()) {
        qbo_inventory_sync_log_upsert([
            'doc_number' => $docNumber,
            'sync_type' => 'Adjustment',
            'reference_type' => 'InvAdjustment',
            'reference_id' => $adjustmentId,
            'sku_code' => (string) $adjustment['SKUCode'],
            'qty_change' => (float) $adjustment['QtyAdjusted'],
            'facility_code' => (string) $adjustment['FacilityCode'],
            'sync_status' => 'Skipped',
            'sync_error' => 'QuickBooks is not connected.',
        ]);

        return ['ok' => true, 'skipped' => true, 'error' => null, 'reason' => 'QuickBooks not connected.'];
    }

    $accountId = inventory_adjustments_adjust_account_id();
    if ($accountId === '') {
        return ['ok' => false, 'error' => 'Set QBO_INV_ADJUST_ACCOUNT_ID on the App Service.'];
    }

    $resolved = inventory_adjustments_resolve_qbo_item_id((string) $adjustment['SKUCode']);
    if (!$resolved['ok'] || $resolved['item_id'] === '') {
        qbo_inventory_sync_log_upsert([
            'doc_number' => $docNumber,
            'sync_type' => 'Adjustment',
            'reference_type' => 'InvAdjustment',
            'reference_id' => $adjustmentId,
            'sku_code' => (string) $adjustment['SKUCode'],
            'qty_change' => (float) $adjustment['QtyAdjusted'],
            'facility_code' => (string) $adjustment['FacilityCode'],
            'sync_status' => 'Error',
            'sync_error' => $resolved['error'] ?? 'Missing QBO item Id.',
        ]);

        return ['ok' => false, 'error' => $resolved['error'] ?? 'Missing QBO item Id.'];
    }

    $qty = (float) $adjustment['QtyAdjusted'];
    $result = qbo_post_inventory_adjustment_qty(
        $docNumber,
        [[
            'qbo_item_id' => $resolved['item_id'],
            'qty_change' => $qty,
            'sku_code' => (string) $adjustment['SKUCode'],
        ]],
        $accountId,
        'NutraAxis adjustment #' . $adjustmentId
            . ' ' . (string) ($adjustment['ReasonCode'] ?? '')
            . ' ' . (string) $adjustment['FacilityCode']
    );

    qbo_inventory_sync_log_upsert([
        'doc_number' => $docNumber,
        'sync_type' => 'Adjustment',
        'reference_type' => 'InvAdjustment',
        'reference_id' => $adjustmentId,
        'sku_code' => (string) $adjustment['SKUCode'],
        'qty_change' => $qty,
        'facility_code' => (string) $adjustment['FacilityCode'],
        'qbo_txn_id' => $result['ok'] ? ($result['txn']['Id'] ?? null) : null,
        'qbo_sync_token' => $result['ok'] ? ($result['txn']['SyncToken'] ?? null) : null,
        'sync_status' => $result['ok'] ? 'Synced' : 'Error',
        'sync_error' => $result['ok'] ? null : ($result['error'] ?? 'Inventory adjustment failed'),
    ]);

    return $result;
}
