<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/facility.php';

const INV_STATUS_BUCKETS = ['OK', 'Quarantine', 'OnHold', 'Destroy'];

/**
 * Ensure an InvCurrentBalance row exists and return it (locked when in a transaction).
 */
function inventory_posting_get_or_create_balance(PDO $pdo, string $skuCode, string $facilityCode): array
{
    $stmt = $pdo->prepare(<<<SQL
        SELECT BalanceID, SKUCode, FacilityCode, QtyOK, QtyQuarantine, QtyOnHold, QtyDestroy, QtyReserved
        FROM dbo.InvCurrentBalance WITH (UPDLOCK, HOLDLOCK)
        WHERE SKUCode = :sku AND FacilityCode = :facility
    SQL);
    $stmt->execute(['sku' => $skuCode, 'facility' => $facilityCode]);
    $row = $stmt->fetch();
    if ($row !== false) {
        return $row;
    }

    $insert = $pdo->prepare(<<<SQL
        INSERT INTO dbo.InvCurrentBalance (SKUCode, FacilityCode)
        OUTPUT INSERTED.BalanceID, INSERTED.SKUCode, INSERTED.FacilityCode,
               INSERTED.QtyOK, INSERTED.QtyQuarantine, INSERTED.QtyOnHold,
               INSERTED.QtyDestroy, INSERTED.QtyReserved
        VALUES (:sku, :facility)
    SQL);
    $insert->execute(['sku' => $skuCode, 'facility' => $facilityCode]);

    $created = $insert->fetch();
    if ($created === false) {
        throw new RuntimeException('Unable to create inventory balance row.');
    }

    return $created;
}

function inventory_posting_bucket_column(string $bucket): string
{
    return match ($bucket) {
        'OK' => 'QtyOK',
        'Quarantine' => 'QtyQuarantine',
        'OnHold' => 'QtyOnHold',
        'Destroy' => 'QtyDestroy',
        default => throw new InvalidArgumentException('Invalid status bucket: ' . $bucket),
    };
}

/**
 * Post a signed quantity change to IMS and return transaction id.
 *
 * @param array<int, array{sku_code:string,facility_code:string,status_bucket?:string,qty_change:float|int|string,notes?:string}> $lines
 */
function inventory_posting_create_transaction(
    string $transactionType,
    string $referenceType,
    int $referenceId,
    array $lines,
    ?string $notes = null,
    ?int $createdByUserId = null
): array {
    if ($lines === []) {
        return ['ok' => false, 'error' => 'At least one inventory line is required.', 'transaction_id' => null];
    }

    $allowedTypes = [
        'OpeningBalance', 'POReceipt', 'Sale', 'AdjustmentGain', 'AdjustmentLoss',
        'TransferOut', 'TransferIn', 'CustomerReturn', 'StatusChange', 'JazzSyncReconcile',
    ];
    if (!in_array($transactionType, $allowedTypes, true)) {
        return ['ok' => false, 'error' => 'Invalid inventory transaction type.', 'transaction_id' => null];
    }

    $pdo = db();
    $ownsTransaction = !$pdo->inTransaction();

    try {
        db_apply_sql_server_options($pdo);
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $header = $pdo->prepare(<<<SQL
            INSERT INTO dbo.InvTransaction (
                TransactionType, ReferenceType, ReferenceID, Notes, CreatedByUser
            )
            OUTPUT INSERTED.TransactionID AS inserted_id
            VALUES (
                :type, :ref_type, :ref_id, :notes, :user_id
            )
        SQL);
        $header->execute([
            'type'     => $transactionType,
            'ref_type' => $referenceType,
            'ref_id'   => $referenceId,
            'notes'    => $notes !== null && $notes !== '' ? $notes : null,
            'user_id'  => $createdByUserId,
        ]);
        $transactionId = db_fetch_inserted_int($header, 'inserted_id');
        if ($transactionId <= 0) {
            throw new RuntimeException('Unable to create inventory transaction header.');
        }

        $lineNumber = 0;
        foreach ($lines as $line) {
            $lineNumber++;
            $skuCode = trim((string) ($line['sku_code'] ?? ''));
            $facilityCode = trim((string) ($line['facility_code'] ?? ''));
            $bucket = trim((string) ($line['status_bucket'] ?? 'OK'));
            $qtyChange = (float) ($line['qty_change'] ?? 0);
            $lineNotes = trim((string) ($line['notes'] ?? ''));

            if ($skuCode === '' || $facilityCode === '') {
                throw new RuntimeException('SKU and facility are required on inventory lines.');
            }
            if (!in_array($bucket, INV_STATUS_BUCKETS, true)) {
                throw new RuntimeException('Invalid status bucket on inventory line.');
            }
            if (abs($qtyChange) < 0.0000001) {
                throw new RuntimeException('Inventory line quantity change cannot be zero.');
            }

            $balance = inventory_posting_get_or_create_balance($pdo, $skuCode, $facilityCode);
            $column = inventory_posting_bucket_column($bucket);
            $qtyBefore = (float) ($balance[$column] ?? 0);
            $qtyAfter = $qtyBefore + $qtyChange;
            if ($qtyAfter < -0.0000001) {
                throw new RuntimeException(
                    'Insufficient quantity for ' . $skuCode . ' at ' . $facilityCode
                    . ' (' . $bucket . '). Available ' . $qtyBefore . ', change ' . $qtyChange . '.'
                );
            }

            $update = $pdo->prepare(<<<SQL
                UPDATE dbo.InvCurrentBalance
                SET {$column} = :qty_after,
                    LastTransactionID = :txn_id,
                    LastUpdated = SYSUTCDATETIME()
                WHERE BalanceID = :balance_id
            SQL);
            $update->execute([
                'qty_after'  => $qtyAfter,
                'txn_id'     => $transactionId,
                'balance_id' => (int) $balance['BalanceID'],
            ]);

            $lineInsert = $pdo->prepare(<<<SQL
                INSERT INTO dbo.InvTransactionLine (
                    TransactionID, LineNumber, SKUCode, FacilityCode,
                    StatusBucket, QtyChange, QtyBefore, QtyAfter, Notes
                )
                VALUES (
                    :txn_id, :line_no, :sku, :facility,
                    :bucket, :qty_change, :qty_before, :qty_after, :notes
                )
            SQL);
            $lineInsert->execute([
                'txn_id'     => $transactionId,
                'line_no'    => $lineNumber,
                'sku'        => $skuCode,
                'facility'   => $facilityCode,
                'bucket'     => $bucket,
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after'  => $qtyAfter,
                'notes'      => $lineNotes !== '' ? $lineNotes : null,
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return ['ok' => true, 'error' => null, 'transaction_id' => $transactionId];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => $e->getMessage(), 'transaction_id' => null];
    }
}

function inventory_posting_receipt_lines(int $porId, string $facilityCode = 'CART'): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT d.PORDID, d.ItemSKU, d.QuantityReceived, d.QuantityExpected
        FROM dbo.PORDetail d
        WHERE d.PORID = :por_id
        ORDER BY d.PORDID
    SQL);
    $stmt->execute(['por_id' => $porId]);

    $lines = [];
    foreach ($stmt->fetchAll() as $row) {
        $qty = (float) ($row['QuantityReceived'] ?? 0);
        if ($qty <= 0) {
            $qty = (float) ($row['QuantityExpected'] ?? 0);
        }
        if ($qty <= 0) {
            continue;
        }
        $sku = trim((string) ($row['ItemSKU'] ?? ''));
        if ($sku === '') {
            continue;
        }
        $lines[] = [
            'sku_code'       => $sku,
            'facility_code'  => $facilityCode,
            'status_bucket'  => 'OK',
            'qty_change'     => $qty,
            'notes'          => 'PORDetail ' . (int) $row['PORDID'],
            'detail_id'      => (int) $row['PORDID'],
        ];
    }

    return $lines;
}

function inventory_posting_mark_receipt_posted(int $porId): void
{
    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.POReceipt
        SET PORStatus = CASE WHEN PORStatus = N'Transmitted' THEN N'Complete' ELSE PORStatus END,
            IMSPostedAt = SYSUTCDATETIME(),
            JazzReceivedAt = COALESCE(JazzReceivedAt, SYSUTCDATETIME())
        WHERE PORID = :por_id
    SQL)->execute(['por_id' => $porId]);
}
