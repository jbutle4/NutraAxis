<?php

require_once __DIR__ . '/database.php';

const FACILITY_INTEGRATION_LOCAL = 'Local';
const FACILITY_INTEGRATION_JAZZ = 'Jazz';
const FACILITY_CMO_STORAGE_CODE = 'CMO';

function facility_get_by_code(string $facilityCode): ?array
{
    $facilityCode = trim($facilityCode);
    if ($facilityCode === '') {
        return null;
    }

    static $cache = [];
    $cacheKey = strtoupper($facilityCode);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            FacilityID,
            FacilityCode,
            FacilityName,
            FacilityType,
            IsActive,
            IsMothership,
            ReceivesPurchaseOrders,
            IntegrationMode,
            ExternalReferenceCode
        FROM dbo.Facility
        WHERE UPPER(FacilityCode) = UPPER(:code)
           OR (
                ExternalReferenceCode IS NOT NULL
                AND UPPER(ExternalReferenceCode) = UPPER(:code)
           )
    SQL);
    $stmt->execute(['code' => $facilityCode]);
    $row = $stmt->fetch();

    $cache[$cacheKey] = $row === false ? null : $row;

    return $cache[$cacheKey];
}

function facility_list_po_receipt_destinations(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT
            FacilityCode,
            FacilityName,
            ExternalReferenceCode,
            IsMothership
        FROM dbo.Facility
        WHERE ReceivesPurchaseOrders = 1
          AND IsActive = 1
        ORDER BY IsMothership DESC, FacilityCode ASC
    SQL);

    return $stmt->fetchAll();
}

function facility_default_po_receipt_code(): string
{
    foreach (facility_list_po_receipt_destinations() as $facility) {
        if (!empty($facility['IsMothership'])) {
            return (string) $facility['FacilityCode'];
        }
    }

    $destinations = facility_list_po_receipt_destinations();

    return $destinations !== [] ? (string) $destinations[0]['FacilityCode'] : 'CART';
}

function facility_is_cmo_storage(?array $facility): bool
{
    if ($facility === null) {
        return false;
    }

    if (strcasecmp((string) ($facility['FacilityCode'] ?? ''), FACILITY_CMO_STORAGE_CODE) === 0) {
        return true;
    }

    return strcasecmp((string) ($facility['FacilityType'] ?? ''), 'CMO') === 0;
}

function facility_cmo_storage_code(): string
{
    return FACILITY_CMO_STORAGE_CODE;
}

function facility_jazz_facility_code(string $facilityCode): string
{
    $facility = facility_get_by_code($facilityCode);
    if ($facility === null) {
        return trim($facilityCode);
    }

    $external = trim((string) ($facility['ExternalReferenceCode'] ?? ''));

    return $external !== '' ? $external : (string) $facility['FacilityCode'];
}

/**
 * PO receipts may only target facilities flagged ReceivesPurchaseOrders (Cart.com mothership).
 * Unknown codes are allowed for legacy Jazz warehouse codes not yet mapped in Facility.
 */
function facility_validate_po_receipt_destination(?string $facilityInput): ?string
{
    $facilityInput = trim((string) $facilityInput);
    if ($facilityInput === '') {
        return null;
    }

    $facility = facility_get_by_code($facilityInput);
    if ($facility === null) {
        return null;
    }

    if (empty($facility['ReceivesPurchaseOrders'])) {
        return 'Purchase order receipts must be received at the Cart.com mothership ('
            . (string) $facility['FacilityCode']
            . ' cannot receive supplier POs). Replenish '
            . (string) $facility['FacilityName']
            . ' using a facility transfer from Cart.com.';
    }

    if (empty($facility['IsActive'])) {
        return 'The selected facility is inactive and cannot receive purchase orders.';
    }

    return null;
}

/**
 * Enforces hub-and-spoke rules: spokes are replenished from the mothership only.
 */
function facility_validate_transfer(string $fromFacilityCode, string $toFacilityCode): ?string
{
    $fromFacilityCode = trim($fromFacilityCode);
    $toFacilityCode = trim($toFacilityCode);

    if ($fromFacilityCode === '' || $toFacilityCode === '') {
        return 'Select both a source and destination facility for the transfer.';
    }

    $from = facility_get_by_code($fromFacilityCode);
    $to = facility_get_by_code($toFacilityCode);

    if ($from === null) {
        return 'Unknown source facility: ' . $fromFacilityCode . '.';
    }

    if ($to === null) {
        return 'Unknown destination facility: ' . $toFacilityCode . '.';
    }

    $fromFacilityCode = (string) $from['FacilityCode'];
    $toFacilityCode = (string) $to['FacilityCode'];

    if (strcasecmp($fromFacilityCode, $toFacilityCode) === 0) {
        return 'Source and destination facility must be different.';
    }

    if (empty($from['IsActive'])) {
        return 'The source facility is inactive.';
    }

    if (empty($to['IsActive'])) {
        return 'The destination facility is inactive.';
    }

    $fromIsTransit = strcasecmp((string) ($from['FacilityType'] ?? ''), 'Transit') === 0;
    $toIsTransit = strcasecmp((string) ($to['FacilityType'] ?? ''), 'Transit') === 0;
    $fromIsMothership = !empty($from['IsMothership']);
    $toIsMothership = !empty($to['IsMothership']);
    $toReceivesPo = !empty($to['ReceivesPurchaseOrders']);
    $fromIsCmo = facility_is_cmo_storage($from);
    $toIsCmo = facility_is_cmo_storage($to);

    if ($fromIsMothership && $toIsCmo) {
        return null;
    }

    if ($fromIsCmo && $toIsMothership) {
        return null;
    }

    if ($toIsCmo && !$fromIsMothership) {
        return 'Material must be sent to CMO storage from the Cart.com mothership.';
    }

    if ($fromIsCmo && !$toIsMothership && !$toIsTransit) {
        return 'CMO storage can only transfer back to the Cart.com mothership.';
    }

    if ($toReceivesPo && !$fromIsMothership) {
        return 'Inventory cannot be transferred into the mothership from a spoke facility through this workflow.';
    }

    if (!$toIsMothership && !$toIsTransit && !$fromIsMothership) {
        return 'Spoke facilities must be replenished from the Cart.com mothership, not from other downstream locations.';
    }

    if (!$toIsMothership && !$toIsTransit && $fromIsTransit) {
        return null;
    }

    if (!$toIsMothership && !$toIsTransit && $fromIsMothership) {
        return null;
    }

    if ($fromIsMothership && $toIsTransit) {
        return null;
    }

    if ($fromIsTransit && !$toIsMothership && !$toIsTransit) {
        return null;
    }

    if ($fromIsMothership && $toIsMothership) {
        return 'Transfers between mothership locations are not supported.';
    }

    if ($fromIsTransit && $toIsTransit) {
        return 'Transfers between transit locations are not supported.';
    }

    return 'This facility transfer path is not allowed. Spoke replenishment must originate at Cart.com.';
}

/**
 * CMO storage moves always involve the Cart.com mothership on one end.
 */
function facility_normalize_transfer_facilities(string &$fromCode, string &$toCode): void
{
    $from = facility_get_by_code($fromCode);
    $to = facility_get_by_code($toCode);
    $mothership = facility_default_po_receipt_code();

    if ($to !== null && facility_is_cmo_storage($to)) {
        $fromCode = $mothership;

        return;
    }

    if ($from !== null && facility_is_cmo_storage($from)) {
        $toCode = $mothership;
    }
}

function facility_transfer_has_supplier_column(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.InvTransfer', 'SupplierID') AS col_len");
        $row = $stmt->fetch();
        $has = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $has = false;
    }

    return $has;
}

function facility_insert_transfer(array $input): array
{
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/inventory-ledger.php';

    $fromCode = trim((string) ($input['from_facility_code'] ?? ''));
    $toCode = trim((string) ($input['to_facility_code'] ?? ''));
    $skuCode = trim((string) ($input['sku_code'] ?? ''));
    $qty = (float) ($input['qty_requested'] ?? 0);
    $fromBucket = trim((string) ($input['from_status_bucket'] ?? 'OK'));
    $toBucket = trim((string) ($input['to_status_bucket'] ?? 'OK'));
    $reasonCodeId = isset($input['reason_code_id']) ? (int) $input['reason_code_id'] : null;
    $supplierId = isset($input['supplier_id']) ? (int) $input['supplier_id'] : null;
    $notes = trim((string) ($input['notes'] ?? ''));

    facility_normalize_transfer_facilities($fromCode, $toCode);

    $validationError = facility_validate_transfer($fromCode, $toCode);
    if ($validationError !== null) {
        return ['ok' => false, 'error' => $validationError, 'transfer_id' => null];
    }

    if ($skuCode === '') {
        return ['ok' => false, 'error' => 'SKU is required.', 'transfer_id' => null];
    }

    if ($qty <= 0) {
        return ['ok' => false, 'error' => 'Transfer quantity must be greater than zero.', 'transfer_id' => null];
    }

    $toIsCmo = facility_is_cmo_storage(facility_get_by_code($toCode));
    if ($toIsCmo && $supplierId <= 0) {
        return ['ok' => false, 'error' => 'Select the CMO partner when sending inventory to CMO storage.', 'transfer_id' => null];
    }

    $allowedBuckets = ['OK', 'Quarantine', 'OnHold', 'Destroy'];
    if (!in_array($fromBucket, $allowedBuckets, true) || !in_array($toBucket, $allowedBuckets, true)) {
        return ['ok' => false, 'error' => 'Select a valid inventory status bucket.', 'transfer_id' => null];
    }

    $userId = auth_user()['UserID'] ?? null;
    if ($userId === null) {
        return ['ok' => false, 'error' => 'You must be signed in to create a transfer.', 'transfer_id' => null];
    }

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);

        if (facility_transfer_has_supplier_column()) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.InvTransfer (
                    SKUCode,
                    FromFacilityCode,
                    ToFacilityCode,
                    FromStatusBucket,
                    ToStatusBucket,
                    QtyRequested,
                    ReasonCodeID,
                    TransferStatus,
                    Notes,
                    RequestedByUser,
                    LedgerProfile,
                    SupplierID
                )
                OUTPUT INSERTED.TransferID AS inserted_id
                VALUES (
                    :sku,
                    :from_facility,
                    :to_facility,
                    :from_bucket,
                    :to_bucket,
                    :qty,
                    :reason_code_id,
                    N'Pending',
                    :notes,
                    :requested_by,
                    :ledger_profile,
                    :supplier_id
                )
            SQL);
            $params = [
                'sku'             => $skuCode,
                'from_facility'   => $fromCode,
                'to_facility'     => $toCode,
                'from_bucket'     => $fromBucket,
                'to_bucket'       => $toBucket,
                'qty'             => $qty,
                'reason_code_id'  => $reasonCodeId > 0 ? $reasonCodeId : null,
                'notes'           => $notes !== '' ? $notes : null,
                'requested_by'    => $userId,
                'ledger_profile'  => inventory_ledger_profile(),
                'supplier_id'     => $supplierId > 0 ? $supplierId : null,
            ];
        } else {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.InvTransfer (
                    SKUCode,
                    FromFacilityCode,
                    ToFacilityCode,
                    FromStatusBucket,
                    ToStatusBucket,
                    QtyRequested,
                    ReasonCodeID,
                    TransferStatus,
                    Notes,
                    RequestedByUser,
                    LedgerProfile
                )
                OUTPUT INSERTED.TransferID AS inserted_id
                VALUES (
                    :sku,
                    :from_facility,
                    :to_facility,
                    :from_bucket,
                    :to_bucket,
                    :qty,
                    :reason_code_id,
                    N'Pending',
                    :notes,
                    :requested_by,
                    :ledger_profile
                )
            SQL);
            $params = [
                'sku'             => $skuCode,
                'from_facility'   => $fromCode,
                'to_facility'     => $toCode,
                'from_bucket'     => $fromBucket,
                'to_bucket'       => $toBucket,
                'qty'             => $qty,
                'reason_code_id'  => $reasonCodeId > 0 ? $reasonCodeId : null,
                'notes'           => $notes !== '' ? $notes : null,
                'requested_by'    => $userId,
                'ledger_profile'  => inventory_ledger_profile(),
            ];
        }

        $stmt->execute($params);

        return [
            'ok'          => true,
            'error'       => null,
            'transfer_id' => db_fetch_inserted_int($stmt, 'inserted_id'),
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unable to save the transfer request.', 'transfer_id' => null];
    }
}
