<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/inventory-transfers.php';

function inventory_transfer_has_shipment_table(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT OBJECT_ID(N'dbo.InvTransferShipment', N'U') AS oid");
        $row = $stmt->fetch();
        $has = $row !== false && $row['oid'] !== null;
    } catch (Throwable) {
        $has = false;
    }

    return $has;
}

function inventory_transfers_shipment_column_exists(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.InvTransfer', 'ShipmentID') AS col_len");
        $row = $stmt->fetch();
        $has = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $has = false;
    }

    return $has;
}

function inventory_transfers_use_shipment_model(): bool
{
    return inventory_transfer_has_shipment_table() && inventory_transfers_shipment_column_exists();
}

function inventory_transfers_normalize_ship_by_date(mixed $raw): ?string
{
    if ($raw === null || $raw === '') {
        return null;
    }
    if ($raw instanceof DateTimeInterface) {
        return $raw->format('Y-m-d');
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return null;
    }

    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $text, $matches)) {
        return $matches[1];
    }

    $sqlServerish = preg_replace('/:(AM|PM)$/i', ' $1', $text) ?? $text;
    $timestamp = strtotime($sqlServerish);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    try {
        return (new DateTimeImmutable($text))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function inventory_transfers_format_ship_by_display(mixed $raw): string
{
    $normalized = inventory_transfers_normalize_ship_by_date($raw);

    return $normalized ?? '—';
}

/**
 * @param array{notes?:string,jazz_ship_by_date?:string,transfer_status?:string} $fields
 * @return array{ok:bool, error:?string}
 */
function inventory_transfers_shipment_update(int $shipmentId, array $fields, ?int $userId = null): array
{
    $shipment = inventory_transfers_shipment_get($shipmentId);
    if ($shipment === null) {
        return ['ok' => false, 'error' => 'Shipment not found.'];
    }

    $status = (string) ($shipment['TransferStatus'] ?? '');
    if (!in_array($status, ['Pending', 'InTransit'], true)) {
        return ['ok' => false, 'error' => 'Only pending or in-transit shipments can be edited.'];
    }

    $newStatus = trim((string) ($fields['transfer_status'] ?? $status));
    if ($newStatus !== $status) {
        $allowed = $status === 'Pending' && $newStatus === 'Cancelled';
        if (!$allowed) {
            return ['ok' => false, 'error' => 'Status can only be changed from Pending to Cancelled.'];
        }
    }

    $notes = array_key_exists('notes', $fields)
        ? trim((string) $fields['notes'])
        : (string) ($shipment['Notes'] ?? '');

    $shipBySql = null;
    if (array_key_exists('jazz_ship_by_date', $fields)) {
        $raw = trim((string) $fields['jazz_ship_by_date']);
        if ($raw === '') {
            $shipBySql = null;
        } else {
            $shipBySql = inventory_transfers_normalize_ship_by_date($raw);
            if ($shipBySql === null) {
                return ['ok' => false, 'error' => 'Ship-by date must be YYYY-MM-DD.'];
            }
        }
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.InvTransferShipment
        SET Notes = :notes,
            JazzShipByDate = :ship_by,
            TransferStatus = :transfer_status,
            ModifiedDate = SYSUTCDATETIME(),
            ModifiedByUser = :user_id
        WHERE ShipmentID = :shipment_id
          AND LedgerProfile = :ledger_profile
    SQL)->execute([
        'notes'           => $notes !== '' ? $notes : null,
        'ship_by'         => $shipBySql,
        'transfer_status' => $newStatus,
        'user_id'         => $userId,
        'shipment_id'     => $shipmentId,
        'ledger_profile'  => inventory_ledger_profile(),
    ]);

    if (inventory_transfer_has_ship_by_column()) {
        $pdo->prepare(<<<SQL
            UPDATE dbo.InvTransfer
            SET Notes = :notes,
                JazzShipByDate = :ship_by,
                TransferStatus = :transfer_status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :user_id
            WHERE ShipmentID = :shipment_id
              AND LedgerProfile = :ledger_profile
        SQL)->execute([
            'notes'           => $notes !== '' ? $notes : null,
            'ship_by'         => $shipBySql,
            'transfer_status' => $newStatus,
            'user_id'         => $userId,
            'shipment_id'     => $shipmentId,
            'ledger_profile'  => inventory_ledger_profile(),
        ]);
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @param list<int> $shipmentIds
 */
function inventory_transfers_set_shipments_ship_by(array $shipmentIds, string $shipByYmd): int
{
    $shipBySql = inventory_transfers_normalize_ship_by_date($shipByYmd);
    if ($shipBySql === null) {
        return 0;
    }

    $updated = 0;
    foreach ($shipmentIds as $shipmentId) {
        $result = inventory_transfers_shipment_update((int) $shipmentId, [
            'jazz_ship_by_date' => $shipBySql,
        ], auth_user()['UserID'] ?? null);
        if ($result['ok']) {
            $updated++;
        }
    }

    return $updated;
}

/**
 * @return array<string, mixed>|null
 */
function inventory_transfers_shipment_get(int $shipmentId): ?array
{
    if (!inventory_transfers_use_shipment_model()) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.InvTransferShipment
        WHERE ShipmentID = :id AND LedgerProfile = :ledger_profile
    SQL);
    $stmt->execute([
        'id'             => $shipmentId,
        'ledger_profile' => inventory_ledger_profile(),
    ]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function inventory_transfers_shipment_lines(int $shipmentId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.InvTransfer
        WHERE ShipmentID = :shipment_id AND LedgerProfile = :ledger_profile
        ORDER BY LineNumber ASC, TransferID ASC
    SQL);
    $stmt->execute([
        'shipment_id'    => $shipmentId,
        'ledger_profile' => inventory_ledger_profile(),
    ]);

    return $stmt->fetchAll() ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function inventory_transfers_list_shipments(array $filters = []): array
{
    if (!inventory_transfers_use_shipment_model()) {
        return [];
    }

    $pdo = db();
    $where = ['s.LedgerProfile = :ledger_profile'];
    $params = ['ledger_profile' => inventory_ledger_profile()];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 's.TransferStatus = :status';
        $params['status'] = $status;
    }

    $sql = <<<SQL
        SELECT
            s.ShipmentID,
            s.FromFacilityCode,
            s.ToFacilityCode,
            s.TransferStatus,
            s.Notes,
            s.JazzShipByDate,
            s.JazzSendStatus,
            s.JazzShipmentNumber,
            s.JazzSendError,
            s.CreateDate,
            COUNT(l.TransferID) AS LineCount,
            SUM(l.QtyRequested) AS TotalQtyRequested
        FROM dbo.InvTransferShipment s
        INNER JOIN dbo.InvTransfer l ON l.ShipmentID = s.ShipmentID
        WHERE %s
        GROUP BY
            s.ShipmentID, s.FromFacilityCode, s.ToFacilityCode, s.TransferStatus, s.Notes,
            s.JazzShipByDate, s.JazzSendStatus, s.JazzShipmentNumber, s.JazzSendError, s.CreateDate
        ORDER BY s.ShipmentID DESC
    SQL;

    $stmt = $pdo->prepare(sprintf($sql, implode(' AND ', $where)));
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function inventory_transfers_shipment_id_for_line(int $transferId): ?int
{
    if (!inventory_transfers_use_shipment_model()) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT ShipmentID FROM dbo.InvTransfer
        WHERE TransferID = :id AND LedgerProfile = :ledger_profile
    SQL);
    $stmt->execute([
        'id'             => $transferId,
        'ledger_profile' => inventory_ledger_profile(),
    ]);
    $value = $stmt->fetchColumn();
    if ($value === false || $value === null) {
        return null;
    }

    return (int) $value;
}

/**
 * @return array{ok:bool, shipment_id:?int, error:?string}
 */
function inventory_transfers_insert_shipment_header(array $header, int $userId): array
{
    if (!inventory_transfers_use_shipment_model()) {
        return ['ok' => false, 'shipment_id' => null, 'error' => 'Shipment table not available. Run sql/176_inv_transfer_shipment.sql.'];
    }

    $shipBySql = inventory_transfers_normalize_ship_by_date($header['jazz_ship_by_date'] ?? null);
    if (($header['jazz_ship_by_date'] ?? '') !== '' && ($header['jazz_ship_by_date'] ?? null) !== null && $shipBySql === null) {
        return ['ok' => false, 'shipment_id' => null, 'error' => 'Ship-by date must be YYYY-MM-DD.'];
    }

    $reasonCodeId = isset($header['reason_code_id']) ? (int) $header['reason_code_id'] : null;
    $supplierId = isset($header['supplier_id']) ? (int) $header['supplier_id'] : null;
    $notes = trim((string) ($header['notes'] ?? ''));

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.InvTransferShipment (
            LedgerProfile,
            FromFacilityCode,
            ToFacilityCode,
            FromStatusBucket,
            ToStatusBucket,
            ReasonCodeID,
            SupplierID,
            Notes,
            TransferStatus,
            JazzShipByDate,
            RequestedByUser
        )
        OUTPUT INSERTED.ShipmentID AS inserted_id
        VALUES (
            :ledger_profile,
            :from_facility,
            :to_facility,
            :from_bucket,
            :to_bucket,
            :reason_code_id,
            :supplier_id,
            :notes,
            N'Pending',
            :ship_by,
            :requested_by
        )
    SQL);
    $stmt->execute([
        'ledger_profile' => inventory_ledger_profile(),
        'from_facility'   => trim((string) ($header['from_facility_code'] ?? '')),
        'to_facility'     => trim((string) ($header['to_facility_code'] ?? '')),
        'from_bucket'     => trim((string) ($header['from_status_bucket'] ?? 'OK')),
        'to_bucket'       => trim((string) ($header['to_status_bucket'] ?? 'OK')),
        'reason_code_id'  => $reasonCodeId > 0 ? $reasonCodeId : null,
        'supplier_id'     => $supplierId > 0 ? $supplierId : null,
        'notes'           => $notes !== '' ? $notes : null,
        'ship_by'         => $shipBySql,
        'requested_by'    => $userId,
    ]);

    return [
        'ok'          => true,
        'shipment_id' => db_fetch_inserted_int($stmt, 'inserted_id'),
        'error'       => null,
    ];
}

function inventory_transfers_line_group_key(array $transfer): string
{
    $notes = (string) ($transfer['Notes'] ?? '');
    if (preg_match('/(TRN-(?:Ocala|Dallas|Melbourne)-\d{8})/i', $notes, $matches)) {
        return strtoupper($matches[1]);
    }

    $shipBy = '';
    if (!empty($transfer['JazzShipByDate'])) {
        try {
            $shipBy = (new DateTimeImmutable((string) $transfer['JazzShipByDate']))->format('Y-m-d');
        } catch (Throwable) {
            $shipBy = (string) $transfer['JazzShipByDate'];
        }
    }

    return implode('|', [
        (string) ($transfer['LedgerProfile'] ?? ''),
        (string) ($transfer['FromFacilityCode'] ?? ''),
        (string) ($transfer['ToFacilityCode'] ?? ''),
        $shipBy,
        (string) ($transfer['TransferStatus'] ?? ''),
        substr($notes, 0, 120),
    ]);
}

/**
 * Backfill ShipmentID for legacy rows (group multi-line pharmacy batches).
 *
 * @return array{shipments_created:int, lines_linked:int}
 */
function inventory_transfers_backfill_shipments(): array
{
    if (!inventory_transfers_use_shipment_model()) {
        return ['shipments_created' => 0, 'lines_linked' => 0];
    }

    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT *
        FROM dbo.InvTransfer
        WHERE ShipmentID IS NULL
        ORDER BY TransferID ASC
    SQL);
    $rows = $stmt->fetchAll();
    if ($rows === []) {
        return ['shipments_created' => 0, 'lines_linked' => 0];
    }

    $groups = [];
    foreach ($rows as $row) {
        $key = inventory_transfers_line_group_key($row);
        $groups[$key][] = $row;
    }

    $shipmentsCreated = 0;
    $linesLinked = 0;

    foreach ($groups as $groupRows) {
        $lead = $groupRows[0];
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->beginTransaction();
        try {
            $insert = inventory_transfers_insert_shipment_header([
                'from_facility_code' => $lead['FromFacilityCode'] ?? '',
                'to_facility_code'   => $lead['ToFacilityCode'] ?? '',
                'from_status_bucket' => $lead['FromStatusBucket'] ?? 'OK',
                'to_status_bucket'   => $lead['ToStatusBucket'] ?? 'OK',
                'reason_code_id'     => $lead['ReasonCodeID'] ?? null,
                'supplier_id'        => $lead['SupplierID'] ?? null,
                'notes'              => $lead['Notes'] ?? '',
                'jazz_ship_by_date'  => inventory_transfers_normalize_ship_by_date($lead['JazzShipByDate'] ?? null),
            ], (int) ($lead['RequestedByUser'] ?? 1));

            if (!$insert['ok'] || ($insert['shipment_id'] ?? 0) <= 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                fwrite(STDERR, 'Shipment insert failed: ' . ($insert['error'] ?? 'unknown') . PHP_EOL);
                continue;
            }

            $shipmentId = (int) $insert['shipment_id'];
            $shipmentsCreated++;

            $updateShip = $pdo->prepare(<<<SQL
                UPDATE dbo.InvTransferShipment
                SET TransferStatus = :status,
                    JazzShipByDate = :ship_by,
                    JazzShipmentNumber = :jazz_shipment,
                    JazzSendStatus = :jazz_status,
                    JazzSendError = :jazz_error,
                    JazzAsnId = :jazz_asn_id,
                    JazzAsnStatus = :jazz_asn_status,
                    JazzSentAt = :jazz_sent_at,
                    CreateDate = :create_date
                WHERE ShipmentID = :shipment_id
            SQL);
            $createDate = $lead['CreateDate'] ?? null;
            if ($createDate instanceof DateTimeInterface) {
                $createDate = $createDate->format('Y-m-d H:i:s');
            }

            $updateShip->execute([
                'status'         => (string) ($lead['TransferStatus'] ?? 'Pending'),
                'ship_by'        => inventory_transfers_normalize_ship_by_date($lead['JazzShipByDate'] ?? null),
                'jazz_shipment'  => $lead['JazzShipmentNumber'] ?? null,
                'jazz_status'    => $lead['JazzSendStatus'] ?? null,
                'jazz_error'     => $lead['JazzSendError'] ?? null,
                'jazz_asn_id'    => $lead['JazzAsnId'] ?? null,
                'jazz_asn_status'=> $lead['JazzAsnStatus'] ?? null,
                'jazz_sent_at'   => $lead['JazzSentAt'] ?? null,
                'create_date'    => $createDate,
                'shipment_id'    => $shipmentId,
            ]);

            $lineNo = 1;
            $updateLine = $pdo->prepare(<<<SQL
                UPDATE dbo.InvTransfer
                SET ShipmentID = :shipment_id, LineNumber = :line_number
                WHERE TransferID = :transfer_id
            SQL);
            foreach ($groupRows as $lineRow) {
                $updateLine->execute([
                    'shipment_id'  => $shipmentId,
                    'line_number'  => $lineNo,
                    'transfer_id'  => (int) $lineRow['TransferID'],
                ]);
                $lineNo++;
                $linesLinked++;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fwrite(STDERR, 'Backfill group failed: ' . $e->getMessage() . PHP_EOL);
        }
    }

    return ['shipments_created' => $shipmentsCreated, 'lines_linked' => $linesLinked];
}

function inventory_transfers_sync_shipment_from_line(int $transferId): void
{
    if (!inventory_transfers_use_shipment_model()) {
        return;
    }

    $transfer = inventory_transfers_get($transferId);
    if ($transfer === null || empty($transfer['ShipmentID'])) {
        return;
    }

    $shipmentId = (int) $transfer['ShipmentID'];
    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.InvTransferShipment
        SET JazzShipmentNumber = :shipment_number,
            JazzSendStatus = :send_status,
            JazzSendError = :send_error,
            JazzAsnId = :asn_id,
            JazzAsnStatus = :asn_status,
            JazzSentAt = COALESCE(:sent_at, JazzSentAt),
            ModifiedDate = SYSUTCDATETIME()
        WHERE ShipmentID = :shipment_id
          AND LedgerProfile = :ledger_profile
    SQL)->execute([
        'shipment_number' => $transfer['JazzShipmentNumber'] ?? null,
        'send_status'     => $transfer['JazzSendStatus'] ?? null,
        'send_error'      => $transfer['JazzSendError'] ?? null,
        'asn_id'          => $transfer['JazzAsnId'] ?? null,
        'asn_status'      => $transfer['JazzAsnStatus'] ?? null,
        'sent_at'         => $transfer['JazzSentAt'] ?? null,
        'shipment_id'     => $shipmentId,
        'ledger_profile'  => inventory_ledger_profile(),
    ]);

    inventory_transfers_sync_shipment_line_status($shipmentId);
}

function inventory_transfers_sync_shipment_line_status(int $shipmentId): void
{
    if (!inventory_transfers_use_shipment_model()) {
        return;
    }

    $lines = inventory_transfers_shipment_lines($shipmentId);
    if ($lines === []) {
        return;
    }

    $status = (string) ($lines[0]['TransferStatus'] ?? 'Pending');
    foreach ($lines as $line) {
        $lineStatus = (string) ($line['TransferStatus'] ?? '');
        if ($lineStatus !== $status) {
            $status = 'PartiallyReceived';
            break;
        }
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.InvTransferShipment
        SET TransferStatus = :status,
            ModifiedDate = SYSUTCDATETIME()
        WHERE ShipmentID = :shipment_id
          AND LedgerProfile = :ledger_profile
    SQL)->execute([
        'status'         => $status,
        'shipment_id'    => $shipmentId,
        'ledger_profile' => inventory_ledger_profile(),
    ]);
}

/**
 * @return array{ok:bool, error:?string}
 */
function inventory_transfers_ship_shipment(int $shipmentId, ?int $userId = null): array
{
    $lines = inventory_transfers_shipment_lines($shipmentId);
    if ($lines === []) {
        return ['ok' => false, 'error' => 'Shipment has no lines.'];
    }

    foreach ($lines as $line) {
        if (($line['TransferStatus'] ?? '') !== 'Pending') {
            continue;
        }
        $result = inventory_transfers_ship((int) $line['TransferID'], $userId);
        if (!$result['ok']) {
            return $result;
        }
    }

    inventory_transfers_sync_shipment_line_status($shipmentId);

    return ['ok' => true, 'error' => null];
}

/**
 * @return array{ok:bool, error:?string}
 */
function inventory_transfers_receive_shipment(int $shipmentId, ?int $userId = null): array
{
    $lines = inventory_transfers_shipment_lines($shipmentId);
    foreach ($lines as $line) {
        if (($line['TransferStatus'] ?? '') !== 'InTransit') {
            continue;
        }
        $result = inventory_transfers_receive((int) $line['TransferID'], $userId);
        if (!$result['ok']) {
            return $result;
        }
    }

    inventory_transfers_sync_shipment_line_status($shipmentId);

    return ['ok' => true, 'error' => null];
}

function inventory_transfers_shipment_lead_line_id(int $shipmentId): ?int
{
    $lines = inventory_transfers_shipment_lines($shipmentId);

    return $lines !== [] ? (int) ($lines[0]['TransferID'] ?? 0) : null;
}