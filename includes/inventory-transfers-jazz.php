<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/facility.php';
require_once __DIR__ . '/jazz-oms.php';

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

    return strcasecmp($status, 'Sent') === 0;
}

function inventory_transfers_jazz_shipment_number(int $transferId): string
{
    return 'NA-XFER-' . $transferId;
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
    return trim((string) env('JAZZ_TRANSFER_ORDER_SOURCE_CODE', '')) !== '';
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
 * Optional internal transfer order import (tenant-specific source code).
 *
 * @return array<string, mixed>|null
 */
function inventory_transfers_jazz_build_order_payload(array $transfer): ?array
{
    $sourceCode = trim((string) env('JAZZ_TRANSFER_ORDER_SOURCE_CODE', ''));
    if ($sourceCode === '') {
        return null;
    }

    $transferId = (int) ($transfer['TransferID'] ?? 0);
    $shipmentNumber = inventory_transfers_jazz_shipment_number($transferId);
    $sku = (string) ($transfer['SKUCode'] ?? '');
    $qty = (int) round((float) ($transfer['QtyRequested'] ?? 0));
    if ($sku === '' || $qty <= 0) {
        return null;
    }

    $customerNumber = trim((string) env(
        'JAZZ_TRANSFER_ORDER_CUSTOMER_NUMBER',
        'NUTRAAXIS_INTERNAL'
    ));
    $shipCode = trim((string) env('JAZZ_TRANSFER_ORDER_SHIP_CODE', 'INTERNAL_TRANSFER'));
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $fromJazz = facility_jazz_facility_code((string) ($transfer['FromFacilityCode'] ?? ''));

    $attributes = [
        'allocation_facility'       => $fromJazz,
        'nutraaxis_transfer_id'     => $transferId,
        'nutraaxis_from_facility'   => (string) ($transfer['FromFacilityCode'] ?? ''),
        'nutraaxis_to_facility'     => (string) ($transfer['ToFacilityCode'] ?? ''),
    ];

    $businessType = trim((string) env('JAZZ_TRANSFER_ORDER_BUSINESS_TYPE', ''));
    $payload = [
        'order_number' => $shipmentNumber,
        'order_date'   => $now,
        'source_code'  => $sourceCode,
        'attributes'   => $attributes,
        'customer'     => [
            'customer_number' => $customerNumber,
            'first_name'      => 'NutraAxis',
            'last_name'       => 'Facility Transfer',
        ],
        'shipto'       => [[
            'shipto_number'   => '1',
            'first_name'      => 'Internal',
            'last_name'       => 'Transfer',
            'ship_code'       => $shipCode,
            'address'         => [
                'address1' => 'Internal facility transfer',
                'city'     => 'Plano',
                'state'    => 'TX',
                'zipcode'  => '75074',
                'country'  => 'US',
            ],
            'detail_set'      => [[
                'sku'           => $sku,
                'qty_ordered'   => $qty,
                'current_price' => '0',
            ]],
            'subtotal_amount' => '0',
            'total_amount'    => '0',
        ]],
    ];

    if ($businessType !== '') {
        $payload['business_type_code'] = $businessType;
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

function inventory_transfers_jazz_mark_sent(int $transferId, ?string $asnId, ?string $asnStatus, ?string $error = null): void
{
    if (!inventory_transfer_has_jazz_columns()) {
        return;
    }

    $pdo = db();
    $shipmentNumber = inventory_transfers_jazz_shipment_number($transferId);
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

    if (inventory_transfers_jazz_send_satisfied($transfer)) {
        return ['ok' => true, 'error' => null, 'skipped' => true, 'reason' => 'already_sent'];
    }

    $configError = jazz_oms_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError];
    }

    if (!inventory_transfers_jazz_post_asn_enabled() && !inventory_transfers_jazz_post_order_enabled()) {
        return [
            'ok'    => false,
            'error' => 'Jazz transfer posting is disabled. Set JAZZ_TRANSFER_POST_ASN=1 and/or configure JAZZ_TRANSFER_ORDER_SOURCE_CODE.',
        ];
    }

    $shipmentNumber = inventory_transfers_jazz_shipment_number($transferId);
    $asnId = null;
    $asnStatus = null;
    $warnings = [];

    if (inventory_transfers_jazz_post_order_enabled()) {
        $orderPayload = inventory_transfers_jazz_build_order_payload($transfer);
        if ($orderPayload === null) {
            return ['ok' => false, 'error' => 'Unable to build the Jazz transfer order payload.'];
        }

        $orderResult = jazz_oms_api_post(
            jazz_oms_base_url() . inventory_transfers_jazz_order_import_endpoint(),
            $orderPayload
        );
        if (!$orderResult['ok']) {
            $error = (string) ($orderResult['error'] ?? 'Jazz order import failed.');
            inventory_transfers_jazz_mark_sent($transferId, null, null, $error);

            return ['ok' => false, 'error' => 'Jazz rejected the transfer order: ' . $error];
        }

        $orderAck = jazz_oms_extract_ack_id($orderResult['data'] ?? null, ['order_number', 'id']);
        if ($orderAck === null) {
            $warnings[] = 'Jazz accepted the transfer order but did not return an order id.';
        }
    }

    if (inventory_transfers_jazz_post_asn_enabled()) {
        $facilityCode = inventory_transfers_jazz_asn_facility_code($transfer);
        if ($facilityCode === null) {
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
                    inventory_transfers_jazz_mark_sent($transferId, null, null, $error);

                    return [
                        'ok'    => false,
                        'error' => 'Jazz reports a duplicate shipment number for ' . $shipmentNumber
                            . ' but the existing ASN could not be loaded. Resolve in Jazz OMS, then retry.',
                    ];
                }
            } else {
                inventory_transfers_jazz_mark_sent($transferId, null, null, $error);

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

    inventory_transfers_jazz_mark_sent(
        $transferId,
        $asnId,
        $asnStatus !== null && $asnStatus !== '' ? $asnStatus : null,
        null
    );

    return [
        'ok'       => true,
        'error'    => null,
        'asn_id'   => $asnId,
        'warning'  => $warnings !== [] ? implode(' ', $warnings) : null,
    ];
}