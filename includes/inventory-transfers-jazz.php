<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/facility.php';
require_once __DIR__ . '/jazz-oms.php';
require_once __DIR__ . '/inventory-ledger.php';

function inventory_transfer_has_jazz_columns(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.InvTransfer', 'JazzSendStatus') AS col_len");
        $row = $stmt->fetch();
        $has = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $has = false;
    }

    return $has;
}

function inventory_transfers_jazz_require_before_ship(): bool
{
    $raw = strtolower(trim((string) env('JAZZ_TRANSFER_REQUIRE_BEFORE_SHIP', '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

function inventory_transfers_jazz_send_required(array $transfer): bool
{
    $from = facility_get_by_code((string) ($transfer['FromFacilityCode'] ?? ''));
    if ($from === null) {
        return false;
    }

    return strcasecmp((string) ($from['IntegrationMode'] ?? ''), FACILITY_INTEGRATION_JAZZ) === 0;
}

function inventory_transfers_jazz_send_satisfied(array $transfer): bool
{
    if (!inventory_transfers_jazz_send_required($transfer)) {
        return true;
    }

    if (!inventory_transfer_has_jazz_columns()) {
        return false;
    }

    $status = trim((string) ($transfer['JazzSendStatus'] ?? ''));
    if (strcasecmp($status, 'Sent') === 0 || strcasecmp($status, 'Sending') === 0) {
        return true;
    }

    if (inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        inventory_transfers_jazz_sync_batch_if_sent($transfer);
        $fresh = inventory_transfers_get((int) ($transfer['TransferID'] ?? 0));
        if ($fresh !== null && strcasecmp(trim((string) ($fresh['JazzSendStatus'] ?? '')), 'Sent') === 0) {
            return true;
        }

        return inventory_transfers_jazz_batch_order_sent_reference($transfer) !== null;
    }

    return false;
}

function inventory_transfers_jazz_is_duplicate_order_error(string $error): bool
{
    $haystack = strtolower($error);

    return (str_contains($haystack, 'duplicate') && str_contains($haystack, 'order'))
        || (str_contains($haystack, 'order number') && str_contains($haystack, 'already'))
        || (str_contains($haystack, 'already exists') && str_contains($haystack, 'order'));
}

/**
 * @return array<string, mixed>|null Sent row for this batch order number, if any.
 */
function inventory_transfers_jazz_batch_order_sent_reference(array $transfer): ?array
{
    if (!inventory_transfer_has_jazz_columns()) {
        return null;
    }

    if (!inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        $orderNumber = inventory_transfers_jazz_shipment_number((int) ($transfer['TransferID'] ?? 0));
    } else {
        $orderNumber = inventory_transfers_jazz_batch_order_number($transfer);
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP (1) *
        FROM dbo.InvTransfer
        WHERE LedgerProfile = :ledger_profile
          AND FromFacilityCode = :from_facility
          AND ToFacilityCode = :to_facility
          AND JazzSendStatus = N'Sent'
          AND JazzShipmentNumber = :order_number
        ORDER BY TransferID ASC
    SQL);
    $stmt->execute([
        'ledger_profile' => inventory_ledger_profile(),
        'from_facility'  => (string) ($transfer['FromFacilityCode'] ?? ''),
        'to_facility'    => (string) ($transfer['ToFacilityCode'] ?? ''),
        'order_number'   => $orderNumber,
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

/**
 * When any line in a pharmacy batch is Sent, align remaining Pending peers (no Jazz call).
 */
function inventory_transfers_jazz_sync_batch_if_sent(array $transfer): void
{
    if (!inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        return;
    }

    $sentRef = inventory_transfers_jazz_batch_order_sent_reference($transfer);
    if ($sentRef === null) {
        return;
    }

    $jazzOrderNumber = trim((string) ($sentRef['JazzShipmentNumber'] ?? ''));
    if ($jazzOrderNumber === '') {
        $jazzOrderNumber = inventory_transfers_jazz_batch_order_number($transfer);
    }

    $asnId = isset($sentRef['JazzAsnId']) ? trim((string) $sentRef['JazzAsnId']) : null;
    $asnStatus = isset($sentRef['JazzAsnStatus']) ? trim((string) $sentRef['JazzAsnStatus']) : null;
    if ($asnId === '') {
        $asnId = null;
    }
    if ($asnStatus === '') {
        $asnStatus = null;
    }

    $batchKey = inventory_transfers_jazz_batch_order_number($transfer);
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.InvTransfer
        WHERE LedgerProfile = :ledger_profile
          AND FromFacilityCode = :from_facility
          AND ToFacilityCode = :to_facility
          AND TransferStatus = N'Pending'
          AND (JazzSendStatus IS NULL OR JazzSendStatus <> N'Sent')
        ORDER BY TransferID ASC
    SQL);
    $stmt->execute([
        'ledger_profile' => inventory_ledger_profile(),
        'from_facility'  => (string) ($transfer['FromFacilityCode'] ?? ''),
        'to_facility'    => (string) ($transfer['ToFacilityCode'] ?? ''),
    ]);

    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row) || inventory_transfers_jazz_batch_order_number($row) !== $batchKey) {
            continue;
        }
        inventory_transfers_jazz_mark_sent(
            (int) ($row['TransferID'] ?? 0),
            $asnId,
            $asnStatus,
            null,
            $jazzOrderNumber
        );
    }
}

/**
 * @param list<int> $transferIds
 * @return array{ok:bool, error:?string}
 */
function inventory_transfers_jazz_try_claim_send(array $transferIds): array
{
    if ($transferIds === [] || !inventory_transfer_has_jazz_columns()) {
        return ['ok' => false, 'error' => 'No transfers to send.'];
    }

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($transferIds), '?'));
    $pdo->beginTransaction();

    try {
        $lockSql = <<<SQL
            SELECT TransferID, JazzSendStatus
            FROM dbo.InvTransfer WITH (UPDLOCK, ROWLOCK)
            WHERE TransferID IN ({$placeholders})
              AND LedgerProfile = ?
        SQL;
        $lockStmt = $pdo->prepare($lockSql);
        $lockParams = [...$transferIds, inventory_ledger_profile()];
        $lockStmt->execute($lockParams);
        $rows = $lockStmt->fetchAll();
        if (!is_array($rows) || count($rows) !== count($transferIds)) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'Transfer batch changed; refresh and try again.'];
        }

        foreach ($rows as $row) {
            $status = trim((string) ($row['JazzSendStatus'] ?? ''));
            if (strcasecmp($status, 'Sent') === 0) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'This batch was already sent to Jazz. Refresh the page.'];
            }
            if (strcasecmp($status, 'Sending') === 0) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'This batch is already being sent to Jazz. Wait and refresh.'];
            }
        }

        $claimSql = <<<SQL
            UPDATE dbo.InvTransfer
            SET JazzSendStatus = N'Sending',
                JazzSendError = NULL,
                ModifiedDate = SYSUTCDATETIME()
            WHERE TransferID IN ({$placeholders})
              AND LedgerProfile = ?
              AND (JazzSendStatus IS NULL OR JazzSendStatus = N'Error')
        SQL;
        $claimStmt = $pdo->prepare($claimSql);
        $claimStmt->execute($lockParams);
        if ((int) $claimStmt->rowCount() !== count($transferIds)) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'Could not lock batch for Jazz send (already sent or in progress).'];
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null];
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => 'Could not lock transfers for Jazz send.'];
    }
}

/**
 * @param list<int> $transferIds
 */
function inventory_transfers_jazz_release_send_claim(array $transferIds, ?string $error): void
{
    if ($transferIds === [] || !inventory_transfer_has_jazz_columns()) {
        return;
    }

    $pdo = db();
    $placeholders = implode(',', array_fill(0, count($transferIds), '?'));
    $ledger = inventory_ledger_profile();

    if ($error !== null && $error !== '') {
        $sql = <<<SQL
            UPDATE dbo.InvTransfer
            SET JazzSendStatus = N'Error',
                JazzSendError = ?,
                ModifiedDate = SYSUTCDATETIME()
            WHERE TransferID IN ({$placeholders})
              AND LedgerProfile = ?
              AND JazzSendStatus = N'Sending'
        SQL;
        $pdo->prepare($sql)->execute([substr($error, 0, 500), ...$transferIds, $ledger]);
    } else {
        $sql = <<<SQL
            UPDATE dbo.InvTransfer
            SET JazzSendStatus = NULL,
                JazzSendError = NULL,
                ModifiedDate = SYSUTCDATETIME()
            WHERE TransferID IN ({$placeholders})
              AND LedgerProfile = ?
              AND JazzSendStatus = N'Sending'
        SQL;
        $pdo->prepare($sql)->execute([...$transferIds, $ledger]);
    }
}

function inventory_transfers_jazz_shipment_number(int $transferId): string
{
    return 'NA-XFER-' . $transferId;
}

/**
 * Excel-style order key: TRN-Ocala-20261120 (destination + ship-by date).
 */
function inventory_transfers_jazz_pharmacy_slug(string $toFacilityCode): string
{
    $code = strtoupper(trim($toFacilityCode));
    $labels = [
        'OCALA'     => 'Ocala',
        'DALLAS'    => 'Dallas',
        'MELBOURNE' => 'Melbourne',
    ];
    if (isset($labels[$code])) {
        return $labels[$code];
    }

    $facility = facility_get_by_code($toFacilityCode);
    if ($facility !== null) {
        $name = trim((string) ($facility['FacilityName'] ?? ''));
        if ($name !== '') {
            return preg_replace('/[^A-Za-z0-9]+/', '', $name) ?: $code;
        }
    }

    return ucfirst(strtolower($code));
}

function inventory_transfers_jazz_batch_order_number(array $transfer): string
{
    $shipBy = inventory_transfer_jazz_ship_by_date($transfer);
    $ymd = str_replace('-', '', $shipBy);

    return 'TRN-'
        . inventory_transfers_jazz_pharmacy_slug((string) ($transfer['ToFacilityCode'] ?? ''))
        . '-'
        . $ymd;
}

/**
 * Pending CART → pharmacy lines that share the same Jazz batch order number.
 *
 * @return list<array<string, mixed>>
 */
function inventory_transfers_jazz_batch_peers(array $transfer): array
{
    if (!inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        return [$transfer];
    }

    $batchKey = inventory_transfers_jazz_batch_order_number($transfer);
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.InvTransfer
        WHERE LedgerProfile = :ledger_profile
          AND FromFacilityCode = :from_facility
          AND ToFacilityCode = :to_facility
          AND TransferStatus = N'Pending'
          AND (JazzSendStatus IS NULL OR JazzSendStatus <> N'Sent')
        ORDER BY TransferID ASC
    SQL);
    $stmt->execute([
        'ledger_profile' => inventory_ledger_profile(),
        'from_facility'  => (string) ($transfer['FromFacilityCode'] ?? ''),
        'to_facility'    => (string) ($transfer['ToFacilityCode'] ?? ''),
    ]);
    $rows = $stmt->fetchAll();
    if (!is_array($rows)) {
        return [$transfer];
    }

    $peers = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (inventory_transfers_jazz_batch_order_number($row) === $batchKey) {
            $peers[] = $row;
        }
    }

    return $peers !== [] ? $peers : [$transfer];
}

function inventory_transfers_jazz_shipment_number_for_transfer(array $transfer): string
{
    $stored = trim((string) ($transfer['JazzShipmentNumber'] ?? ''));
    if ($stored !== '') {
        return $stored;
    }

    if (inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        return inventory_transfers_jazz_batch_order_number($transfer);
    }

    return inventory_transfers_jazz_shipment_number((int) ($transfer['TransferID'] ?? 0));
}

function inventory_transfers_jazz_asn_endpoint(): string
{
    $endpoint = trim((string) env(
        'JAZZ_TRANSFER_ASN_ENDPOINT',
        (string) env('JAZZ_ASN_ENDPOINT', '/api/v1/shipping/asn')
    ));

    return '/' . ltrim($endpoint, '/');
}

function inventory_transfers_jazz_order_import_endpoint(): string
{
    $endpoint = trim((string) env('JAZZ_ORDER_IMPORT_ENDPOINT', '/api/v1/order/import'));

    return '/' . ltrim($endpoint, '/');
}

function inventory_transfers_jazz_asn_facility_side(): string
{
    $side = strtolower(trim((string) env('JAZZ_TRANSFER_ASN_FACILITY', 'from')));

    return $side === 'to' ? 'to' : 'from';
}

function inventory_transfers_jazz_shipment_type(): string
{
    $type = strtolower(trim((string) env('JAZZ_TRANSFER_SHIPMENT_TYPE', 'inbound')));

    return in_array($type, ['asn', 'inbound'], true) ? $type : 'inbound';
}

function inventory_transfers_jazz_post_asn_enabled(): bool
{
    $raw = strtolower(trim((string) env('JAZZ_TRANSFER_POST_ASN', '1')));

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

function inventory_transfers_jazz_post_order_enabled(): bool
{
    return inventory_transfers_jazz_order_source_code() !== '';
}

function inventory_transfers_jazz_is_pharmacy_replenishment(array $transfer): bool
{
    $from = facility_get_by_code((string) ($transfer['FromFacilityCode'] ?? ''));
    $to = facility_get_by_code((string) ($transfer['ToFacilityCode'] ?? ''));
    if ($from === null || $to === null) {
        return false;
    }

    return !empty($from['IsMothership']) && facility_is_pharmacy_location($to);
}

function inventory_transfers_jazz_order_source_code(): string
{
    return trim((string) env_first([
        'JAZZ_PHARMACY_ORDER_SOURCE_CODE',
        'JAZZ_ORDER_SOURCE_CODE',
        'JAZZ_TRANSFER_ORDER_SOURCE_CODE',
    ], 'WEB_2026'));
}

function inventory_transfers_jazz_allocation_facility_code(array $transfer): string
{
    $fromJazz = facility_jazz_facility_code((string) ($transfer['FromFacilityCode'] ?? ''));

    return trim((string) env_first([
        'JAZZ_PHARMACY_ALLOCATION_FACILITY',
        'JAZZ_ALLOCATION_FACILITY',
        'JAZZ_CART_FACILITY_CODE',
    ], $fromJazz !== '' ? $fromJazz : 'FBF09'));
}

function inventory_transfers_jazz_replenishment_ship_code(): string
{
    return trim((string) env('JAZZ_ORDER_SHIP_CODE', 'ECONOMY'));
}

/**
 * Pharmacy CART → spoke: order/import only (no inbound ASN at Cart).
 */
function inventory_transfers_jazz_post_asn_for_transfer(array $transfer): bool
{
    if (inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        $raw = strtolower(trim((string) env('JAZZ_PHARMACY_TRANSFER_POST_ASN', '0')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    return inventory_transfers_jazz_post_asn_enabled();
}

function inventory_transfer_jazz_ship_by_date(array $transfer): string
{
    $raw = $transfer['JazzShipByDate'] ?? null;
    if ($raw !== null && $raw !== '') {
        try {
            return (new DateTimeImmutable((string) $raw))->format('Y-m-d');
        } catch (Throwable) {
            // fall through
        }
    }

    return (new DateTimeImmutable('now'))->format('Y-m-d');
}

function inventory_transfers_jazz_asn_facility_code(array $transfer): ?string
{
    $side = inventory_transfers_jazz_asn_facility_side();
    $code = $side === 'to'
        ? (string) ($transfer['ToFacilityCode'] ?? '')
        : (string) ($transfer['FromFacilityCode'] ?? '');
    $jazz = facility_jazz_facility_code($code);

    return $jazz !== '' ? $jazz : null;
}

/**
 * @return array<string, mixed>
 */
function inventory_transfers_jazz_build_asn_payload(array $transfer): array
{
    $transferId = (int) ($transfer['TransferID'] ?? 0);
    $shipmentNumber = inventory_transfers_jazz_shipment_number($transferId);
    $qty = (int) round((float) ($transfer['QtyRequested'] ?? 0));

    $today = (new DateTimeImmutable('now'))->format('Y-m-d');
    $arrival = (new DateTimeImmutable('now +3 days'))->format('Y-m-d');

    $payload = [
        'shipment_number' => $shipmentNumber,
        'po_number'       => $shipmentNumber,
        'shipment_type'   => inventory_transfers_jazz_shipment_type(),
        'arrival_at'      => $arrival,
        'shipped_at'      => $today,
        'details'         => [[
            'sku_code'    => (string) ($transfer['SKUCode'] ?? ''),
            'quantity'    => max(1, $qty),
            'line_number' => '1',
        ]],
        'attributes'      => [
            'nutraaxis_transfer_id'   => $transferId,
            'nutraaxis_from_facility' => (string) ($transfer['FromFacilityCode'] ?? ''),
            'nutraaxis_to_facility'   => (string) ($transfer['ToFacilityCode'] ?? ''),
        ],
    ];

    $facilityCode = inventory_transfers_jazz_asn_facility_code($transfer);
    if ($facilityCode !== null) {
        $payload['facility_code'] = $facilityCode;
    }

    $notes = trim((string) ($transfer['Notes'] ?? ''));
    if ($notes !== '') {
        $payload['attributes']['nutraaxis_notes'] = $notes;
    }

    return $payload;
}

/**
 * @param list<array<string, mixed>> $transfers
 * @return array<string, mixed>|null
 */
function inventory_transfers_jazz_build_pharmacy_batch_payload(array $transfers, string $orderNumber): ?array
{
    if ($transfers === []) {
        return null;
    }

    $sourceCode = inventory_transfers_jazz_order_source_code();
    if ($sourceCode === '') {
        return null;
    }

    $lead = $transfers[0];
    $transferIds = [];
    $skuQty = [];
    foreach ($transfers as $row) {
        $transferIds[] = (int) ($row['TransferID'] ?? 0);
        $sku = trim((string) ($row['SKUCode'] ?? ''));
        $qty = (int) round((float) ($row['QtyRequested'] ?? 0));
        if ($sku === '' || $qty <= 0) {
            continue;
        }
        $skuQty[$sku] = ($skuQty[$sku] ?? 0) + $qty;
    }
    if ($skuQty === []) {
        return null;
    }

    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $shipBy = inventory_transfer_jazz_ship_by_date($lead);

    $toFacility = facility_get_by_code((string) ($lead['ToFacilityCode'] ?? ''));
    $shipToContact = facility_jazz_ship_to_contact($toFacility)
        ?? facility_jazz_transfer_ship_to_fallback();

    $attributes = [
        'allocation_facility'       => inventory_transfers_jazz_allocation_facility_code($lead),
        'shipment_comments'         => 'LTL',
        'nutraaxis_transfer_ids'    => implode(',', $transferIds),
        'nutraaxis_from_facility'   => (string) ($lead['FromFacilityCode'] ?? ''),
        'nutraaxis_to_facility'     => (string) ($lead['ToFacilityCode'] ?? ''),
        'nutraaxis_batch_line_count'=> count($skuQty),
    ];

    $customer = [
        'first_name' => $shipToContact['first_name'],
        'last_name'  => $shipToContact['last_name'],
        'address1'   => $shipToContact['address']['address1'],
        'city'       => $shipToContact['address']['city'],
        'state'      => $shipToContact['address']['state'],
        'zipcode'    => $shipToContact['address']['zipcode'],
        'country'    => $shipToContact['address']['country'],
    ];
    if (!empty($shipToContact['address']['address2'])) {
        $customer['address2'] = $shipToContact['address']['address2'];
    }
    if ($shipToContact['company'] !== '') {
        $customer['company'] = $shipToContact['company'];
    }
    if (!empty($shipToContact['phone'])) {
        $customer['phone'] = $shipToContact['phone'];
    }
    if (!empty($shipToContact['email'])) {
        $customer['email'] = $shipToContact['email'];
    }

    $detailSet = [];
    $lineNumber = 1;
    ksort($skuQty);
    foreach ($skuQty as $sku => $qty) {
        $detailSet[] = [
            'line_number'   => $lineNumber,
            'sku'           => $sku,
            'qty_ordered'   => $qty,
            'current_price' => 0,
        ];
        $lineNumber += 1;
    }

    $shiptoRow = [
        'first_name'          => $shipToContact['first_name'],
        'last_name'           => $shipToContact['last_name'],
        'ship_code'           => inventory_transfers_jazz_replenishment_ship_code(),
        'address'             => $shipToContact['address'],
        'detail_set'          => $detailSet,
        'packer_instructions' => 'Pharmacy transfer — ship LTL freight (not parcel).',
    ];
    if ($shipToContact['company'] !== '') {
        $shiptoRow['company'] = $shipToContact['company'];
    }
    if (!empty($shipToContact['phone'])) {
        $shiptoRow['phone'] = $shipToContact['phone'];
    }
    if (!empty($shipToContact['email'])) {
        $shiptoRow['email'] = $shipToContact['email'];
    }

    return [
        'order_number' => $orderNumber,
        'order_date'   => $now,
        'ship_date'    => $shipBy,
        'source_code'  => $sourceCode,
        'po_number'    => $orderNumber,
        'attributes'   => $attributes,
        'customer'     => $customer,
        'shipto'       => [[
            'shipto_number' => '1',
            ...$shiptoRow,
        ]],
    ];
}

/**
 * Optional internal transfer order import (tenant-specific source code).
 *
 * @return array<string, mixed>|null
 */
function inventory_transfers_jazz_build_order_payload(array $transfer): ?array
{
    $sourceCode = inventory_transfers_jazz_order_source_code();
    if ($sourceCode === '') {
        return null;
    }

    if (inventory_transfers_jazz_is_pharmacy_replenishment($transfer)) {
        $peers = inventory_transfers_jazz_batch_peers($transfer);

        return inventory_transfers_jazz_build_pharmacy_batch_payload(
            $peers,
            inventory_transfers_jazz_batch_order_number($transfer)
        );
    }

    $transferId = (int) ($transfer['TransferID'] ?? 0);
    $orderNumber = inventory_transfers_jazz_shipment_number($transferId);
    $sku = (string) ($transfer['SKUCode'] ?? '');
    $qty = (int) round((float) ($transfer['QtyRequested'] ?? 0));
    if ($sku === '' || $qty <= 0) {
        return null;
    }

    $pharmacyPath = false;
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $shipBy = inventory_transfer_jazz_ship_by_date($transfer);

    $toFacility = facility_get_by_code((string) ($transfer['ToFacilityCode'] ?? ''));
    $shipToContact = facility_jazz_ship_to_contact($toFacility)
        ?? facility_jazz_transfer_ship_to_fallback();

    $attributes = [
        'allocation_facility'     => inventory_transfers_jazz_allocation_facility_code($transfer),
        'nutraaxis_transfer_id'   => $transferId,
        'nutraaxis_from_facility' => (string) ($transfer['FromFacilityCode'] ?? ''),
        'nutraaxis_to_facility'   => (string) ($transfer['ToFacilityCode'] ?? ''),
    ];
    $notes = trim((string) ($transfer['Notes'] ?? ''));
    if ($notes !== '') {
        $attributes['nutraaxis_notes'] = $notes;
    }
    if ($pharmacyPath) {
        $attributes['shipment_comments'] = 'LTL';
    }

    $customer = [
        'first_name' => $shipToContact['first_name'],
        'last_name'  => $shipToContact['last_name'],
        'address1'   => $shipToContact['address']['address1'],
        'city'       => $shipToContact['address']['city'],
        'state'      => $shipToContact['address']['state'],
        'zipcode'    => $shipToContact['address']['zipcode'],
        'country'    => $shipToContact['address']['country'],
    ];
    if (!empty($shipToContact['address']['address2'])) {
        $customer['address2'] = $shipToContact['address']['address2'];
    }
    if ($shipToContact['company'] !== '') {
        $customer['company'] = $shipToContact['company'];
    }
    if (!empty($shipToContact['phone'])) {
        $customer['phone'] = $shipToContact['phone'];
    }
    if (!empty($shipToContact['email'])) {
        $customer['email'] = $shipToContact['email'];
    }

    $shipCode = $pharmacyPath
        ? inventory_transfers_jazz_replenishment_ship_code()
        : trim((string) env('JAZZ_TRANSFER_ORDER_SHIP_CODE', 'INTERNAL_TRANSFER'));

    $shiptoAddress = $shipToContact['address'];
    $shiptoRow = [
        'first_name' => $shipToContact['first_name'],
        'last_name'  => $shipToContact['last_name'],
        'ship_code'  => $shipCode,
        'address'    => $shiptoAddress,
        'detail_set' => [[
            'line_number'   => 1,
            'sku'           => $sku,
            'qty_ordered'   => $qty,
            'current_price' => 0,
        ]],
    ];
    if ($shipToContact['company'] !== '') {
        $shiptoRow['company'] = $shipToContact['company'];
    }
    if (!empty($shipToContact['phone'])) {
        $shiptoRow['phone'] = $shipToContact['phone'];
    }
    if (!empty($shipToContact['email'])) {
        $shiptoRow['email'] = $shipToContact['email'];
    }
    if ($pharmacyPath) {
        $shiptoRow['packer_instructions'] = 'Pharmacy transfer — ship LTL freight (not parcel).';
    }

    $payload = [
        'order_number' => $orderNumber,
        'order_date'   => $now,
        'ship_date'    => $shipBy,
        'source_code'  => $sourceCode,
        'po_number'    => $orderNumber,
        'attributes'   => $attributes,
        'customer'     => $customer,
        'shipto'       => [[
            'shipto_number' => '1',
            ...$shiptoRow,
        ]],
    ];

    if (!$pharmacyPath) {
        $customerNumber = trim((string) env('JAZZ_TRANSFER_ORDER_CUSTOMER_NUMBER', 'NUTRAAXIS_INTERNAL'));
        $payload['customer']['customer_number'] = $customerNumber;
        $businessType = trim((string) env('JAZZ_TRANSFER_ORDER_BUSINESS_TYPE', ''));
        if ($businessType !== '') {
            $payload['business_type_code'] = $businessType;
        }
    }

    return $payload;
}

function inventory_transfers_jazz_is_duplicate_shipment_error(string $error): bool
{
    $haystack = strtolower($error);

    return (str_contains($haystack, 'duplicate') && str_contains($haystack, 'shipment'))
        || str_contains($haystack, 'already has an asn with this shipment number')
        || str_contains($haystack, 'already has an asn with shipment number');
}

function inventory_transfers_jazz_mark_sent(
    int $transferId,
    ?string $asnId,
    ?string $asnStatus,
    ?string $error = null,
    ?string $jazzOrderNumber = null,
): void {
    if (!inventory_transfer_has_jazz_columns()) {
        return;
    }

    $pdo = db();
    $shipmentNumber = $jazzOrderNumber !== null && trim($jazzOrderNumber) !== ''
        ? trim($jazzOrderNumber)
        : inventory_transfers_jazz_shipment_number($transferId);
    $sent = $error === null;

    $pdo->prepare(<<<SQL
        UPDATE dbo.InvTransfer
        SET JazzShipmentNumber = :shipment_number,
            JazzAsnId = :asn_id,
            JazzAsnStatus = :asn_status,
            JazzSendStatus = :send_status,
            JazzSendError = :send_error,
            JazzSentAt = CASE WHEN :sent = 1 THEN SYSUTCDATETIME() ELSE JazzSentAt END,
            ModifiedDate = SYSUTCDATETIME()
        WHERE TransferID = :id
    SQL)->execute([
        'shipment_number' => $shipmentNumber,
        'asn_id'          => $asnId !== null && $asnId !== '' ? $asnId : null,
        'asn_status'      => $asnStatus !== null && $asnStatus !== '' ? $asnStatus : null,
        'send_status'     => $sent ? 'Sent' : 'Error',
        'send_error'      => $error !== null && $error !== '' ? substr($error, 0, 500) : null,
        'sent'            => $sent ? 1 : 0,
        'id'              => $transferId,
    ]);

    if (function_exists('inventory_transfers_sync_shipment_from_line')) {
        inventory_transfers_sync_shipment_from_line($transferId);
    }
}

function inventory_transfers_jazz_lookup_existing_asn(string $shipmentNumber): ?array
{
    require_once __DIR__ . '/po-receiving-asn.php';

    $existing = jazz_oms_find_asns_by_shipment_number($shipmentNumber, 5);
    if ($existing === []) {
        return null;
    }

    return $existing[0];
}

function inventory_transfers_jazz_resolve_asn_status(?string $asnId): ?string
{
    if ($asnId === null || trim($asnId) === '') {
        return null;
    }

    require_once __DIR__ . '/po-receiving-asn.php';
    $result = jazz_oms_get_asn($asnId);
    if (!$result['ok'] || !is_array($result['row'])) {
        return null;
    }

    $status = trim((string) ($result['row']['status'] ?? ''));

    return $status !== '' ? $status : null;
}

function inventory_transfers_send_to_jazz(int $transferId, ?int $userId = null): array
{
    unset($userId);

    require_once __DIR__ . '/inventory-transfers.php';
    require_once __DIR__ . '/inventory-ledger.php';
    inventory_ims_bind_page_environments();

    $transfer = inventory_transfers_get($transferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }

    if (($transfer['TransferStatus'] ?? '') !== 'Pending') {
        return ['ok' => false, 'error' => 'Only pending transfers can be sent to Jazz.'];
    }

    if (!inventory_transfers_jazz_send_required($transfer)) {
        return ['ok' => true, 'error' => null, 'skipped' => true];
    }

    if (!inventory_transfer_has_jazz_columns()) {
        return [
            'ok'    => false,
            'error' => 'Database migration sql/171_alter_inv_transfer_jazz.sql has not been applied. Run it before sending transfers to Jazz.',
        ];
    }

    inventory_transfers_jazz_sync_batch_if_sent($transfer);
    $transfer = inventory_transfers_get($transferId) ?? $transfer;
    if (inventory_transfers_jazz_send_satisfied($transfer)) {
        return ['ok' => true, 'error' => null, 'skipped' => true, 'reason' => 'already_sent'];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError];
    }

    $postOrder = inventory_transfers_jazz_post_order_enabled();
    $postAsn = inventory_transfers_jazz_post_asn_for_transfer($transfer);
    if (!$postAsn && !$postOrder) {
        return [
            'ok'    => false,
            'error' => 'Jazz transfer posting is disabled. Configure JAZZ_ORDER_SOURCE_CODE (or JAZZ_PHARMACY_ORDER_SOURCE_CODE) for order import.',
        ];
    }

    $isPharmacyBatch = inventory_transfers_jazz_is_pharmacy_replenishment($transfer);
    $batchPeers = $isPharmacyBatch ? inventory_transfers_jazz_batch_peers($transfer) : [$transfer];
    $peerIds = array_values(array_filter(array_map(
        static fn(array $peer): int => (int) ($peer['TransferID'] ?? 0),
        $batchPeers
    )));
    if ($peerIds === []) {
        return ['ok' => false, 'error' => 'No pending transfer lines in this Jazz batch.'];
    }

    $claim = inventory_transfers_jazz_try_claim_send($peerIds);
    if (!$claim['ok']) {
        return ['ok' => false, 'error' => $claim['error'] ?? 'Could not start Jazz send.'];
    }

    $jazzOrderNumber = $isPharmacyBatch
        ? inventory_transfers_jazz_batch_order_number($transfer)
        : inventory_transfers_jazz_shipment_number($transferId);
    $shipmentNumber = $jazzOrderNumber;
    $asnId = null;
    $asnStatus = null;
    $warnings = [];

    if (inventory_transfers_jazz_post_order_enabled()) {
        $orderPayload = inventory_transfers_jazz_build_order_payload($transfer);
        if ($orderPayload === null) {
            inventory_transfers_jazz_release_send_claim($peerIds, 'Unable to build the Jazz transfer order payload.');

            return ['ok' => false, 'error' => 'Unable to build the Jazz transfer order payload.'];
        }

        $orderResult = jazz_oms_api_post(
            jazz_oms_base_url() . inventory_transfers_jazz_order_import_endpoint(),
            $orderPayload
        );
        if (!$orderResult['ok']) {
            $error = (string) ($orderResult['error'] ?? 'Jazz order import failed.');
            if (stripos($error, 'source not found') !== false) {
                $configuredSource = inventory_transfers_jazz_order_source_code();
                $error .= ' Production Jazz must have order source «'
                    . $configuredSource
                    . '» (UAT has it; prod often does not). Ask Jazz IT to add that source or set Azure '
                    . 'JAZZ_PHARMACY_ORDER_SOURCE_CODE to the production source id.';
            }
            if (inventory_transfers_jazz_is_duplicate_order_error($error)) {
                foreach ($batchPeers as $peer) {
                    inventory_transfers_jazz_mark_sent(
                        (int) ($peer['TransferID'] ?? 0),
                        null,
                        null,
                        null,
                        $jazzOrderNumber
                    );
                }

                return [
                    'ok'       => true,
                    'error'    => null,
                    'skipped'  => true,
                    'reason'   => 'already_sent',
                    'warning'  => 'Jazz already has order ' . $jazzOrderNumber . '; marked sent locally (no duplicate post).',
                ];
            }

            inventory_transfers_jazz_release_send_claim($peerIds, $error);

            return ['ok' => false, 'error' => 'Jazz rejected the transfer order: ' . $error];
        }

        $orderAck = jazz_oms_extract_ack_id($orderResult['data'] ?? null, ['order_number', 'id']);
        if ($orderAck === null) {
            $warnings[] = 'Jazz accepted the transfer order but did not return an order id.';
        }
        if ($isPharmacyBatch && count($batchPeers) > 1) {
            $warnings[] = 'Posted batch order ' . $jazzOrderNumber . ' with ' . count($batchPeers) . ' transfer lines.';
        }
    }

    if ($postAsn) {
        $facilityCode = inventory_transfers_jazz_asn_facility_code($transfer);
        if ($facilityCode === null) {
            inventory_transfers_jazz_release_send_claim($peerIds, 'Unable to resolve Jazz facility code.');

            return ['ok' => false, 'error' => 'Unable to resolve a Jazz facility code for this transfer path. Set Facility.ExternalReferenceCode or JAZZ_TRANSFER_ASN_FACILITY.'];
        }

        $asnPayload = inventory_transfers_jazz_build_asn_payload($transfer);
        $asnPayload['facility_code'] = $facilityCode;

        $asnResult = jazz_oms_api_post(
            jazz_oms_base_url() . inventory_transfers_jazz_asn_endpoint(),
            $asnPayload
        );

        if (!$asnResult['ok']) {
            $error = (string) ($asnResult['error'] ?? 'Jazz ASN request failed.');
            if (inventory_transfers_jazz_is_duplicate_shipment_error($error)) {
                $existing = inventory_transfers_jazz_lookup_existing_asn($shipmentNumber);
                if ($existing !== null) {
                    $asnId = trim((string) ($existing['id'] ?? ''));
                    if ($asnId === '') {
                        $asnId = por_asn_jazz_id_from_row($existing);
                    }
                    $asnStatus = trim((string) ($existing['status'] ?? ''));
                } else {
                    inventory_transfers_jazz_release_send_claim($peerIds, $error);

                    return [
                        'ok'    => false,
                        'error' => 'Jazz reports a duplicate shipment number for ' . $shipmentNumber
                            . ' but the existing ASN could not be loaded. Resolve in Jazz OMS, then retry.',
                    ];
                }
            } else {
                inventory_transfers_jazz_release_send_claim($peerIds, $error);

                return ['ok' => false, 'error' => 'Jazz rejected the transfer ASN: ' . $error];
            }
        } else {
            require_once __DIR__ . '/po-receiving-asn.php';
            $asnId = por_asn_extract_ack($asnResult['data'] ?? null);
            if ($asnId === null) {
                $warnings[] = 'Jazz accepted the transfer ASN but did not return an ASN id.';
            }
        }
    }

    if ($asnId !== null && ($asnStatus === null || $asnStatus === '')) {
        $asnStatus = inventory_transfers_jazz_resolve_asn_status($asnId);
    }

    foreach ($batchPeers as $peer) {
        inventory_transfers_jazz_mark_sent(
            (int) ($peer['TransferID'] ?? 0),
            $asnId,
            $asnStatus !== null && $asnStatus !== '' ? $asnStatus : null,
            null,
            $jazzOrderNumber
        );
    }

    return [
        'ok'            => true,
        'error'         => null,
        'asn_id'        => $asnId,
        'jazz_order'    => $jazzOrderNumber,
        'batch_lines'   => count($batchPeers),
        'warning'       => $warnings !== [] ? implode(' ', $warnings) : null,
    ];
}