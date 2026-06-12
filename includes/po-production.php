<?php

require_once __DIR__ . '/po.php';

const PO_MFG_STATUSES = ['Not Started', 'In Production', 'Complete', 'On Hold', 'Issue'];
const PO_BOTTLE_PACKAGING_STATUSES = ['Not Started', 'In Progress', 'Complete', 'Issue'];
const PO_BULK_TEST_STATUSES = ['Not Started', 'Submitted', 'Passed', 'Failed', 'On Hold'];
const PO_BOTTLE_TEST_STATUSES = ['Not Started', 'Submitted', 'Passed', 'Failed', 'On Hold'];

const PO_PRODUCTION_EDITABLE_STATUSES = [
    'Approved',
    'Submitted to Accounting for Payment',
    'Paid',
];

function po_production_defaults(): array
{
    return [
        'MfgStatus'             => 'Not Started',
        'BottlePackagingStatus' => 'Not Started',
        'BulkTestStatus'        => 'Not Started',
        'BottleTestStatus'      => 'Not Started',
        'TargetShipDate'        => null,
        'ActualShipDate'        => null,
        'PalletCount'           => null,
        'EstWeightLbs'          => null,
        'Comments'              => null,
        'LastUpdatedByUser'     => null,
        'LastUpdatedDate'       => null,
        'LastUpdatedByName'     => null,
    ];
}

function po_can_edit_production_status(array $order): bool
{
    return po_can_update()
        && in_array($order['POStatus'] ?? '', PO_PRODUCTION_EDITABLE_STATUSES, true);
}

function po_get_production_status_map(int $poId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            ps.*,
            u.UserName AS LastUpdatedByName
        FROM dbo.POProductionStatus ps
        LEFT JOIN dbo.[User] u ON u.UserID = ps.LastUpdatedByUser
        WHERE ps.POID = :po
    SQL);
    $stmt->execute(['po' => $poId]);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['POLineID']] = $row;
    }

    return $map;
}

function po_production_for_line(int $poLineId, ?array $row = null): array
{
    $defaults = po_production_defaults();

    if ($row === null) {
        return array_merge($defaults, ['POLineID' => $poLineId]);
    }

    return array_merge($defaults, $row, ['POLineID' => $poLineId]);
}

function po_validate_production_choice(string $value, array $allowed): ?string
{
    return in_array($value, $allowed, true) ? $value : null;
}

function po_normalize_production_date(?string $value): ?string
{
    $value = trim((string) $value);

    return $value !== '' ? $value : null;
}

function po_upsert_production_line(int $poId, int $poLineId, array $row, ?int $actorId, ?PDO $pdo = null): void
{
    $mfg = po_validate_production_choice(trim($row['mfg_status'] ?? ''), PO_MFG_STATUSES);
    $bottle = po_validate_production_choice(trim($row['bottle_packaging_status'] ?? ''), PO_BOTTLE_PACKAGING_STATUSES);
    $bulk = po_validate_production_choice(trim($row['bulk_test_status'] ?? ''), PO_BULK_TEST_STATUSES);
    $bottleTest = po_validate_production_choice(trim($row['bottle_test_status'] ?? ''), PO_BOTTLE_TEST_STATUSES);

    if ($mfg === null || $bottle === null || $bulk === null || $bottleTest === null) {
        throw new InvalidArgumentException('One or more production status values are invalid.');
    }

    $palletCount = trim((string) ($row['pallet_count'] ?? ''));
    $estWeight = trim((string) ($row['est_weight_lbs'] ?? ''));
    $pallets = $palletCount !== '' ? (int) $palletCount : null;
    $weight = $estWeight !== '' ? (float) $estWeight : null;

    if ($pallets !== null && $pallets < 0) {
        throw new InvalidArgumentException('Pallet count cannot be negative.');
    }

    if ($weight !== null && $weight < 0) {
        throw new InvalidArgumentException('Estimated weight cannot be negative.');
    }

    $pdo = $pdo ?? db();
    $params = [
        'po'          => $poId,
        'line'        => $poLineId,
        'mfg'         => $mfg,
        'bottle'      => $bottle,
        'bulk'        => $bulk,
        'bottle_test' => $bottleTest,
        'target_ship' => po_normalize_production_date($row['target_ship_date'] ?? null),
        'actual_ship' => po_normalize_production_date($row['actual_ship_date'] ?? null),
        'pallets'     => $pallets,
        'weight'      => $weight,
        'comments'    => trim((string) ($row['comments'] ?? '')) !== '' ? trim((string) $row['comments']) : null,
        'actor'       => $actorId,
    ];

    $existsStmt = $pdo->prepare('SELECT ProductionStatusID FROM dbo.POProductionStatus WHERE POLineID = :line');
    $existsStmt->execute(['line' => $poLineId]);

    if ($existsStmt->fetch() !== false) {
        $updateStmt = $pdo->prepare(<<<SQL
            UPDATE dbo.POProductionStatus
            SET MfgStatus = :mfg,
                BottlePackagingStatus = :bottle,
                BulkTestStatus = :bulk,
                BottleTestStatus = :bottle_test,
                TargetShipDate = :target_ship,
                ActualShipDate = :actual_ship,
                PalletCount = :pallets,
                EstWeightLbs = :weight,
                Comments = :comments,
                LastUpdatedByUser = :actor,
                LastUpdatedDate = SYSUTCDATETIME()
            WHERE POLineID = :line AND POID = :po
        SQL);
        $updateStmt->execute($params);

        return;
    }

    $insertStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.POProductionStatus (
            POID, POLineID, MfgStatus, BottlePackagingStatus, BulkTestStatus, BottleTestStatus,
            TargetShipDate, ActualShipDate, PalletCount, EstWeightLbs, Comments,
            LastUpdatedByUser, LastUpdatedDate
        )
        VALUES (
            :po, :line, :mfg, :bottle, :bulk, :bottle_test,
            :target_ship, :actual_ship, :pallets, :weight, :comments,
            :actor, SYSUTCDATETIME()
        )
    SQL);
    $insertStmt->execute($params);
}

function po_find_line_by_po_and_sku(string $poNumber, string $sku): ?array
{
    $poNumber = trim($poNumber);
    $sku = trim($sku);
    if ($poNumber === '' || $sku === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            li.POLineID,
            li.POID,
            li.LineNumber,
            li.ItemSKU,
            li.ItemDescription,
            po.PONumber,
            po.POStatus
        FROM dbo.POLineItem li
        INNER JOIN dbo.PurchaseOrder po ON po.POID = li.POID
        WHERE po.PONumber = :po_number
          AND li.ItemSKU = :sku
    SQL);
    $stmt->execute([
        'po_number' => $poNumber,
        'sku'       => $sku,
    ]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_save_production_statuses(int $poId, array $input): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if (!po_can_edit_production_status($order)) {
        return ['ok' => false, 'error' => 'Production status cannot be updated for this purchase order.'];
    }

    $lines = po_get_lines($poId);
    if ($lines === []) {
        return ['ok' => false, 'error' => 'This purchase order has no line items.'];
    }

    $submitted = $input['production'] ?? [];
    if (!is_array($submitted)) {
        return ['ok' => false, 'error' => 'No production status data was submitted.'];
    }

    $actorId = auth_user()['UserID'] ?? null;
    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        foreach ($lines as $line) {
            $poLineId = (int) $line['POLineID'];
            $row = is_array($submitted[$poLineId] ?? null) ? $submitted[$poLineId] : [];

            try {
                po_upsert_production_line($poId, $poLineId, $row, $actorId, $pdo);
            } catch (InvalidArgumentException $e) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => $e->getMessage() . ' on line ' . (int) $line['LineNumber'] . '.'];
            }
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => po_format_exception_message($e, 'save production status')];
    }
}
