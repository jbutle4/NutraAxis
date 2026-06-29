<?php

require_once __DIR__ . '/cron-auth.php';
require_once __DIR__ . '/jazz-oms.php';

function jazz_inventory_snapshot_normalize_row(array $row): ?array
{
    $sku = trim((string) ($row['sku_code'] ?? ''));
    if ($sku === '') {
        return null;
    }

    return [
        'sku'       => $sku,
        'facility'  => trim((string) ($row['facility_code'] ?? '')) ?: null,
        'available' => (float) ($row['available_quantity'] ?? 0),
        'on_hand'   => (float) ($row['on_hand_quantity'] ?? 0),
        'ordered'   => (float) ($row['qty_ordered'] ?? 0),
        'total'     => (float) ($row['total_quantity'] ?? 0),
    ];
}

function jazz_inventory_snapshot_run(?DateTimeImmutable $snapshotAt = null): array
{
    $snapshotAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $snapshotSql = $snapshotAt->format('Y-m-d H:i:s');

    $listResult = jazz_oms_list_inventory();
    if (!$listResult['ok']) {
        return [
            'ok'          => false,
            'error'       => $listResult['error'],
            'inserted'    => 0,
            'snapshot_at' => $snapshotSql,
        ];
    }

    $rows = $listResult['rows'] ?? [];
    if ($rows === []) {
        return [
            'ok'          => true,
            'error'       => null,
            'inserted'    => 0,
            'snapshot_at' => $snapshotSql,
            'message'     => 'No inventory rows returned from Jazz OMS.',
        ];
    }

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.InventoryBalance (
                SnapshotDateTime, SKU, FacilityCode,
                AvailableQuantity, OnHandQuantity, QtyOrdered, TotalQuantity
            )
            VALUES (
                :snapshot_at, :sku, :facility,
                :available, :on_hand, :ordered, :total
            )
        SQL);

        $inserted = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = jazz_inventory_snapshot_normalize_row($row);
            if ($normalized === null) {
                continue;
            }

            $stmt->execute([
                'snapshot_at' => $snapshotSql,
                'sku'         => $normalized['sku'],
                'facility'    => $normalized['facility'],
                'available'   => $normalized['available'],
                'on_hand'     => $normalized['on_hand'],
                'ordered'     => $normalized['ordered'],
                'total'       => $normalized['total'],
            ]);
            $inserted++;
        }

        $pdo->commit();

        return [
            'ok'          => true,
            'error'       => null,
            'inserted'    => $inserted,
            'snapshot_at' => $snapshotSql,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('jazz_inventory_snapshot_run: ' . $e->getMessage());

        return [
            'ok'          => false,
            'error'       => $e->getMessage(),
            'inserted'    => 0,
            'snapshot_at' => $snapshotSql,
        ];
    }
}

function jazz_inventory_snapshot_cron_auth_check(): array
{
    return cron_auth_check();
}

function jazz_inventory_snapshot_cron_authorized(): bool
{
    return cron_authorized();
}
