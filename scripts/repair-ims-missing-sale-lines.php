<?php

declare(strict_types=1);

/**
 * One-time repair: append IMS Sale lines that posted to QBO but were skipped
 * when inventory-sales-sync reused an existing partial Sale transaction header.
 *
 * Usage: php scripts/repair-ims-missing-sale-lines.php [--dry-run]
 */

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-posting.php';

const REPAIR_LEDGER_PROFILE = 'production';
const REPAIR_FACILITY_CODE = 'CART';

$dryRun = in_array('--dry-run', $argv ?? [], true);

data_profile_set('production');

$pdo = db();
db_apply_sql_server_options($pdo);

$stmt = $pdo->query(<<<SQL
    SELECT
        l.DocNumber,
        l.SKUCode,
        l.QtyChange,
        l.ReferenceID AS DetailID,
        h.AccsSalesOrderHeaderID AS HeaderID,
        h.IncrementId
    FROM dbo.QBOInventorySyncLog l
    INNER JOIN dbo.AccsSalesOrderDetail d ON d.AccsSalesOrderDetailID = l.ReferenceID
    INNER JOIN dbo.AccsSalesOrderHeader h ON h.AccsSalesOrderHeaderID = d.AccsSalesOrderHeaderID
    WHERE l.LedgerProfile = N'production'
      AND l.SyncType = N'Sale'
      AND l.SyncStatus = N'Synced'
      AND NOT EXISTS (
          SELECT 1
          FROM dbo.InvTransaction t
          INNER JOIN dbo.InvTransactionLine tl ON tl.TransactionID = t.TransactionID
          WHERE t.LedgerProfile = N'production'
            AND t.TransactionType = N'Sale'
            AND t.ReferenceType = N'AccsSalesOrderHeader'
            AND t.ReferenceID = h.AccsSalesOrderHeaderID
            AND tl.SKUCode = l.SKUCode
            AND tl.QtyChange = l.QtyChange
      )
    ORDER BY h.AccsSalesOrderHeaderID, l.DocNumber
SQL);

$missing = $stmt->fetchAll() ?: [];
if ($missing === []) {
    echo json_encode(['ok' => true, 'dry_run' => $dryRun, 'repaired' => 0, 'message' => 'No missing IMS sale lines.'], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$byHeader = [];
foreach ($missing as $row) {
    $headerId = (int) ($row['HeaderID'] ?? 0);
    if ($headerId <= 0) {
        continue;
    }
    $byHeader[$headerId][] = $row;
}

$repaired = 0;
$errors = [];
$details = [];

try {
    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    foreach ($byHeader as $headerId => $rows) {
        $txnStmt = $pdo->prepare(<<<SQL
            SELECT TOP (1) TransactionID
            FROM dbo.InvTransaction
            WHERE LedgerProfile = :ledger_profile
              AND TransactionType = N'Sale'
              AND ReferenceType = N'AccsSalesOrderHeader'
              AND ReferenceID = :header_id
            ORDER BY TransactionID DESC
        SQL);
        $txnStmt->execute([
            'ledger_profile' => REPAIR_LEDGER_PROFILE,
            'header_id'      => $headerId,
        ]);
        $txnRow = $txnStmt->fetch();
        $transactionId = $txnRow !== false ? (int) ($txnRow['TransactionID'] ?? 0) : 0;

        if ($transactionId <= 0) {
            $incrementId = trim((string) ($rows[0]['IncrementId'] ?? ''));
            $note = 'IMS sale line repair for order ' . ($incrementId !== '' ? $incrementId : (string) $headerId);
            if ($dryRun) {
                $transactionId = -1;
            } else {
                $header = $pdo->prepare(<<<SQL
                    INSERT INTO dbo.InvTransaction (
                        TransactionType, ReferenceType, ReferenceID, Notes, LedgerProfile
                    )
                    OUTPUT INSERTED.TransactionID AS inserted_id
                    VALUES (N'Sale', N'AccsSalesOrderHeader', :header_id, :notes, :ledger_profile)
                SQL);
                $header->execute([
                    'header_id'      => $headerId,
                    'notes'          => $note,
                    'ledger_profile' => REPAIR_LEDGER_PROFILE,
                ]);
                $transactionId = db_fetch_inserted_int($header, 'inserted_id');
            }
        }

        $lineNoStmt = $pdo->prepare('SELECT COALESCE(MAX(LineNumber), 0) AS max_line FROM dbo.InvTransactionLine WHERE TransactionID = :txn_id');
        $lineNoStmt->execute(['txn_id' => $transactionId]);
        $lineNoRow = $lineNoStmt->fetch();
        $lineNumber = (int) ($lineNoRow['max_line'] ?? 0);

        foreach ($rows as $row) {
            $lineNumber++;
            $skuCode = trim((string) ($row['SKUCode'] ?? ''));
            $qtyChange = (float) ($row['QtyChange'] ?? 0);
            $docNumber = trim((string) ($row['DocNumber'] ?? ''));

            if ($skuCode === '' || abs($qtyChange) < 0.0000001) {
                continue;
            }

            if ($dryRun) {
                $details[] = [
                    'header_id' => $headerId,
                    'transaction_id' => $transactionId,
                    'doc_number' => $docNumber,
                    'sku' => $skuCode,
                    'qty_change' => $qtyChange,
                ];
                $repaired++;
                continue;
            }

            $balance = inventory_posting_get_or_create_balance($pdo, $skuCode, REPAIR_FACILITY_CODE, REPAIR_LEDGER_PROFILE);
            $qtyBefore = (float) ($balance['QtyOK'] ?? 0);
            $qtyAfter = $qtyBefore + $qtyChange;
            if ($qtyAfter < -0.0000001) {
                throw new RuntimeException(
                    'Insufficient quantity for ' . $skuCode . ' at ' . REPAIR_FACILITY_CODE
                    . '. Available ' . $qtyBefore . ', change ' . $qtyChange . ' (' . $docNumber . ').'
                );
            }

            $pdo->prepare(<<<SQL
                UPDATE dbo.InvCurrentBalance
                SET QtyOK = :qty_after,
                    LastTransactionID = :txn_id,
                    LastUpdated = SYSUTCDATETIME()
                WHERE BalanceID = :balance_id
            SQL)->execute([
                'qty_after'  => $qtyAfter,
                'txn_id'     => $transactionId,
                'balance_id' => (int) $balance['BalanceID'],
            ]);

            $pdo->prepare(<<<SQL
                INSERT INTO dbo.InvTransactionLine (
                    TransactionID, LineNumber, SKUCode, FacilityCode,
                    StatusBucket, QtyChange, QtyBefore, QtyAfter, Notes
                )
                VALUES (
                    :txn_id, :line_no, :sku, :facility,
                    N'OK', :qty_change, :qty_before, :qty_after, :notes
                )
            SQL)->execute([
                'txn_id'     => $transactionId,
                'line_no'    => $lineNumber,
                'sku'        => $skuCode,
                'facility'   => REPAIR_FACILITY_CODE,
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after'  => $qtyAfter,
                'notes'      => 'IMS sale line repair ' . $docNumber,
            ]);

            $details[] = [
                'header_id' => $headerId,
                'transaction_id' => $transactionId,
                'doc_number' => $docNumber,
                'sku' => $skuCode,
                'qty_change' => $qtyChange,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
            ];
            $repaired++;
        }
    }

    if (!$dryRun) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'ok' => false,
        'dry_run' => $dryRun,
        'error' => $e->getMessage(),
        'repaired' => $repaired,
        'details' => $details,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

$balanceStmt = $pdo->query(<<<SQL
    SELECT SKUCode, FacilityCode, QtyOK, QtyOnHand, QtyAvailable
    FROM dbo.InvCurrentBalance
    WHERE LedgerProfile = N'production'
      AND SKUCode IN (N'NA-GW-014', N'NA-MT-003')
    ORDER BY SKUCode, FacilityCode
SQL);

echo json_encode([
    'ok' => true,
    'dry_run' => $dryRun,
    'repaired' => $repaired,
    'details' => $details,
    'balances' => $balanceStmt->fetchAll() ?: [],
], JSON_PRETTY_PRINT) . PHP_EOL;
