<?php

require_once __DIR__ . '/inventory-ledger.php';

function inventory_movement_recon_require_read(): void
{
    inventory_ledger_require_read();
}

/**
 * @return array{ok:bool,error:?string,run:?array<string,mixed>,lines:array<int,array<string,mixed>>}
 */
function inventory_movement_recon_latest(array $filters = []): array
{
    $pdo = db();
    $movement = trim((string) ($filters['movement'] ?? ''));
    $severity = trim((string) ($filters['severity'] ?? ''));
    $runId = (int) ($filters['run_id'] ?? 0);

    try {
        if ($runId > 0) {
            $runStmt = $pdo->prepare(<<<SQL
                SELECT TOP (1) *
                FROM dbo.InventoryMovementReconRun
                WHERE ReconRunID = :runId
            SQL);
            $runStmt->execute(['runId' => $runId]);
        } else {
            $runStmt = $pdo->query(<<<SQL
                SELECT TOP (1) *
                FROM dbo.InventoryMovementReconRun
                ORDER BY ReconRunID DESC
            SQL);
        }
        $run = $runStmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            return [
                'ok' => true,
                'error' => null,
                'run' => null,
                'lines' => [],
            ];
        }

        $sql = <<<SQL
            SELECT *
            FROM dbo.InventoryMovementReconLine
            WHERE ReconRunID = :runId
        SQL;
        $params = ['runId' => (int) $run['ReconRunID']];
        if ($movement !== '') {
            $sql .= ' AND MovementType = :movement';
            $params['movement'] = $movement;
        }
        if ($severity !== '') {
            $sql .= ' AND Severity = :severity';
            $params['severity'] = $severity;
        }
        $sql .= ' ORDER BY Severity ASC, MovementType ASC, ReconLineID ASC';

        $lineStmt = $pdo->prepare($sql);
        $lineStmt->execute($params);
        $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'ok' => true,
            'error' => null,
            'run' => $run,
            'lines' => $lines,
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'Movement recon tables are not available: ' . $e->getMessage(),
            'run' => null,
            'lines' => [],
        ];
    }
}

/**
 * @return array<int,array<string,mixed>>
 */
function inventory_movement_recon_recent_runs(int $limit = 10): array
{
    $pdo = db();
    $limit = max(1, min($limit, 50));

    try {
        $stmt = $pdo->query(<<<SQL
            SELECT TOP ($limit)
                ReconRunID,
                StartedAt,
                FinishedAt,
                TriggerType,
                LookbackDays,
                Status,
                ReceiptExceptions,
                SaleExceptions,
                TransferExceptions,
                AdjustmentExceptions,
                TotalExceptions,
                SummaryMessage
            FROM dbo.InventoryMovementReconRun
            ORDER BY ReconRunID DESC
        SQL);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
