<?php

require_once __DIR__ . '/inventory-jazz-ims-recon.php';
require_once __DIR__ . '/inventory-posting.php';
require_once __DIR__ . '/auth.php';

function inventory_jazz_ims_align_require_read(): void
{
    inventory_jazz_ims_recon_require_read();
}

function inventory_jazz_ims_align_can_update(): bool
{
    return auth_can_update('InventoryReporting') || auth_can_create('InventoryReporting');
}

function inventory_jazz_ims_align_require_update(): void
{
    auth_require_login();
    if (inventory_jazz_ims_align_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to align IMS CART from Jazz.');
}

/**
 * @return array<int, array<string, mixed>>
 */
function inventory_jazz_ims_align_sku_master_set(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT SKUCode FROM dbo.SKUMaster');
    $set = [];
    foreach ($stmt->fetchAll() as $row) {
        $sku = strtoupper(trim((string) ($row['SKUCode'] ?? '')));
        if ($sku !== '') {
            $set[$sku] = (string) $row['SKUCode'];
        }
    }

    return $set;
}

/**
 * Build proposed CART align lines from Jazz vs IMS recon rows.
 *
 * @return array{ok:bool,error:?string,lines:array<int,array<string,mixed>>,jazz_env:string,jazz_facility_codes:array<int,string>}
 */
function inventory_jazz_ims_align_preview(string $jazzEnvironment = 'production', bool $zeroMissingJazz = false): array
{
    $recon = inventory_jazz_ims_recon_build_rows($jazzEnvironment);
    if (!$recon['ok']) {
        return [
            'ok' => false,
            'error' => $recon['error'] ?? 'Unable to build Jazz/IMS preview.',
            'lines' => [],
            'jazz_env' => $jazzEnvironment,
            'jazz_facility_codes' => [],
        ];
    }

    $skuMaster = inventory_jazz_ims_align_sku_master_set();
    $lines = [];

    foreach ($recon['rows'] as $row) {
        if (!is_array($row)) {
            continue;
        }

        $skuKey = strtoupper(trim((string) ($row['sku'] ?? '')));
        if ($skuKey === '' || !isset($skuMaster[$skuKey])) {
            continue;
        }

        $hasJazz = !empty($row['has_jazz']);
        $hasIms = !empty($row['has_ims']);
        $jazzRaw = $hasJazz ? (float) $row['jazz_on_hand'] : null;
        $imsQty = $hasIms ? (float) $row['ims_qty'] : 0.0;

        if (!$hasJazz) {
            if (!$zeroMissingJazz || !$hasIms) {
                continue;
            }
            $jazzRaw = 0.0;
        }

        // Jazz can report negative on-hand; IMS QtyOK cannot go below zero.
        $jazzTarget = max(0.0, (float) $jazzRaw);
        $delta = $jazzTarget - (float) $imsQty;
        if (abs($delta) < 0.0001) {
            continue;
        }

        $lines[] = [
            'sku_code' => $skuMaster[$skuKey],
            'facility_code' => 'CART',
            'status_bucket' => 'OK',
            'jazz_on_hand' => (float) $jazzRaw,
            'jazz_target' => $jazzTarget,
            'ims_qty' => (float) $imsQty,
            'qty_change' => $delta,
            'jazz_facility' => (string) ($row['jazz_facility'] ?? ''),
            'clamped' => $jazzRaw !== null && (float) $jazzRaw < 0,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'lines' => $lines,
        'jazz_env' => (string) ($recon['jazz_env'] ?? $jazzEnvironment),
        'jazz_facility_codes' => $recon['jazz_facility_codes'] ?? [],
    ];
}

/**
 * @return array{ok:bool,error:?string,align_run_id:?int,dry_run:bool,posted:int,skipped:int,transaction_id:?int,summary:?string}
 */
function inventory_jazz_ims_align_run(
    string $jazzEnvironment = 'production',
    bool $dryRun = true,
    bool $zeroMissingJazz = false,
    ?int $userId = null
): array {
    $preview = inventory_jazz_ims_align_preview($jazzEnvironment, $zeroMissingJazz);
    if (!$preview['ok']) {
        return [
            'ok' => false,
            'error' => $preview['error'],
            'align_run_id' => null,
            'dry_run' => $dryRun,
            'posted' => 0,
            'skipped' => 0,
            'transaction_id' => null,
            'summary' => null,
        ];
    }

    $lines = $preview['lines'];
    $pdo = db();
    db_apply_sql_server_options($pdo);

    $insert = $pdo->prepare(<<<SQL
        INSERT INTO dbo.InventoryJazzImsAlignRun (
            JazzEnvironment, DryRun, ZeroMissingJazz, Status,
            CandidateCount, TriggeredByUserID
        )
        OUTPUT INSERTED.AlignRunID AS inserted_id
        VALUES (
            :env, :dry, :zero, N'Running',
            :candidates, :user_id
        )
    SQL);
    $insert->execute([
        'env' => $preview['jazz_env'],
        'dry' => $dryRun ? 1 : 0,
        'zero' => $zeroMissingJazz ? 1 : 0,
        'candidates' => count($lines),
        'user_id' => $userId,
    ]);
    $runId = db_fetch_inserted_int($insert, 'inserted_id');

    if ($lines === []) {
        $summary = 'No CART qty deltas to align.';
        $pdo->prepare(<<<SQL
            UPDATE dbo.InventoryJazzImsAlignRun
            SET FinishedAt = SYSUTCDATETIME(),
                Status = :status,
                PostedCount = 0,
                SkippedCount = 0,
                SummaryMessage = :summary
            WHERE AlignRunID = :id
        SQL)->execute([
            'status' => $dryRun ? 'DryRun' : 'Success',
            'summary' => $summary,
            'id' => $runId,
        ]);

        return [
            'ok' => true,
            'error' => null,
            'align_run_id' => $runId,
            'dry_run' => $dryRun,
            'posted' => 0,
            'skipped' => 0,
            'transaction_id' => null,
            'summary' => $summary,
        ];
    }

    if ($dryRun) {
        $summary = 'Dry run — ' . count($lines) . ' SKU delta(s) would post as JazzSyncReconcile to CART (IMS only; QBO unchanged).';
        $pdo->prepare(<<<SQL
            UPDATE dbo.InventoryJazzImsAlignRun
            SET FinishedAt = SYSUTCDATETIME(),
                Status = N'DryRun',
                PostedCount = 0,
                SkippedCount = :skipped,
                SummaryMessage = :summary
            WHERE AlignRunID = :id
        SQL)->execute([
            'skipped' => count($lines),
            'summary' => $summary,
            'id' => $runId,
        ]);

        return [
            'ok' => true,
            'error' => null,
            'align_run_id' => $runId,
            'dry_run' => true,
            'posted' => 0,
            'skipped' => count($lines),
            'transaction_id' => null,
            'summary' => $summary,
            'lines' => $lines,
        ];
    }

    $postLines = [];
    foreach ($lines as $line) {
        $postLines[] = [
            'sku_code' => $line['sku_code'],
            'facility_code' => 'CART',
            'status_bucket' => 'OK',
            'qty_change' => $line['qty_change'],
            'notes' => 'Jazz OH ' . $line['jazz_on_hand']
                . (empty($line['clamped']) ? '' : ' (clamped→0)')
                . ' ← IMS ' . $line['ims_qty'],
        ];
    }

    $post = inventory_posting_create_transaction(
        'JazzSyncReconcile',
        'JazzImsAlignRun',
        $runId,
        $postLines,
        'Jazz→IMS CART align run #' . $runId . ' (' . $preview['jazz_env'] . ')',
        $userId
    );

    if (!$post['ok']) {
        $pdo->prepare(<<<SQL
            UPDATE dbo.InventoryJazzImsAlignRun
            SET FinishedAt = SYSUTCDATETIME(),
                Status = N'Failed',
                ErrorMessage = :error
            WHERE AlignRunID = :id
        SQL)->execute([
            'error' => substr((string) ($post['error'] ?? 'IMS post failed'), 0, 500),
            'id' => $runId,
        ]);

        return [
            'ok' => false,
            'error' => $post['error'] ?? 'IMS post failed.',
            'align_run_id' => $runId,
            'dry_run' => false,
            'posted' => 0,
            'skipped' => 0,
            'transaction_id' => null,
            'summary' => null,
        ];
    }

    $txnId = (int) ($post['transaction_id'] ?? 0);
    $summary = 'Posted ' . count($postLines) . ' CART line(s) via JazzSyncReconcile txn ' . $txnId
        . ' — QBO QtyOnHand unchanged.';
    $pdo->prepare(<<<SQL
        UPDATE dbo.InventoryJazzImsAlignRun
        SET FinishedAt = SYSUTCDATETIME(),
            Status = N'Success',
            PostedCount = :posted,
            SkippedCount = 0,
            TransactionID = :txn_id,
            SummaryMessage = :summary
        WHERE AlignRunID = :id
    SQL)->execute([
        'posted' => count($postLines),
        'txn_id' => $txnId > 0 ? $txnId : null,
        'summary' => $summary,
        'id' => $runId,
    ]);

    return [
        'ok' => true,
        'error' => null,
        'align_run_id' => $runId,
        'dry_run' => false,
        'posted' => count($postLines),
        'skipped' => 0,
        'transaction_id' => $txnId > 0 ? $txnId : null,
        'summary' => $summary,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function inventory_jazz_ims_align_recent_runs(int $limit = 10): array
{
    $limit = max(1, min($limit, 50));
    try {
        $pdo = db();
        $stmt = $pdo->query(<<<SQL
            SELECT TOP ($limit) *
            FROM dbo.InventoryJazzImsAlignRun
            ORDER BY AlignRunID DESC
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
