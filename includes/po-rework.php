<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/facility.php';
require_once __DIR__ . '/supplier.php';

const PO_REWORK_CMO_FACILITY_CODE = FACILITY_CMO_STORAGE_CODE;

function po_rework_has_source_transfer_column(): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.PurchaseOrder', 'SourceTransferID') AS col_len");
        $row = $stmt->fetch();
        $has = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $has = false;
    }

    return $has;
}

function po_rework_transfer_has_supplier_column(): bool
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

/**
 * @return list<array<string, mixed>>
 */
function po_rework_list_cmo_suppliers(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode
        FROM dbo.Supplier
        WHERE IsActive = 1 AND SupplierType = N'CMO'
        ORDER BY SupplierName
    SQL);

    return $stmt->fetchAll() ?: [];
}

function po_rework_get_transfer(int $transferId): ?array
{
    if ($transferId <= 0) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.InvTransfer WHERE TransferID = :id');
    $stmt->execute(['id' => $transferId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_rework_reason_code_id(): ?int
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT ReasonCodeID
        FROM dbo.InvReasonCode
        WHERE ReasonCode = N'CMO_REWORK' AND AppliesToTransfer = 1 AND IsActive = 1
    SQL);
    $row = $stmt->fetch();

    return $row === false ? null : (int) $row['ReasonCodeID'];
}

/**
 * @return array{ok: bool, error: ?string, transfer?: array<string, mixed>}
 */
function po_rework_validate_outbound_transfer(array $transfer): array
{
    $toFacility = facility_get_by_code((string) ($transfer['ToFacilityCode'] ?? ''));
    if (!facility_is_cmo_storage($toFacility)) {
        return ['ok' => false, 'error' => 'Only transfers into CMO storage can start a rework return PO.'];
    }

    if (($transfer['TransferStatus'] ?? '') !== 'Received') {
        return ['ok' => false, 'error' => 'Ship and receive the CMO transfer before creating a rework return PO.'];
    }

    if (!empty($transfer['ReworkReturnPOID'])) {
        return ['ok' => false, 'error' => 'A rework return PO is already linked to this transfer.'];
    }

    $supplierId = (int) ($transfer['SupplierID'] ?? 0);
    if ($supplierId <= 0) {
        return ['ok' => false, 'error' => 'This transfer has no CMO supplier recorded. Edit notes or recreate the transfer with a CMO partner selected.'];
    }

    $supplier = supplier_get($supplierId);
    if ($supplier === null || !$supplier['IsActive']) {
        return ['ok' => false, 'error' => 'The CMO supplier on this transfer is missing or inactive.'];
    }

    return ['ok' => true, 'error' => null, 'transfer' => $transfer];
}

/**
 * Build new-PO form state for a CMO rework return (zero-dollar PO to receive stock back at CART).
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   form?: array<string, mixed>,
 *   lines?: list<array<string, string>>,
 *   source_transfer_id?: int,
 *   source_transfer_label?: string
 * }
 */
function po_rework_form_from_transfer(int $transferId): array
{
    if (!po_can_create()) {
        return ['ok' => false, 'error' => 'You do not have permission to create purchase orders.'];
    }

    $transfer = po_rework_get_transfer($transferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }

    $validated = po_rework_validate_outbound_transfer($transfer);
    if (!$validated['ok']) {
        return ['ok' => false, 'error' => $validated['error']];
    }

    $transfer = $validated['transfer'];
    $supplierId = (int) $transfer['SupplierID'];
    $supplier = supplier_get($supplierId);
    $qty = (float) ($transfer['QtyReceived'] ?? 0);
    if ($qty <= 0) {
        $qty = (float) ($transfer['QtyShipped'] ?? 0);
    }
    if ($qty <= 0) {
        $qty = (float) ($transfer['QtyRequested'] ?? 0);
    }

    $sku = trim((string) ($transfer['SKUCode'] ?? ''));
    $description = $sku;
    $pdo = db();
    $skuStmt = $pdo->prepare('SELECT ProductName FROM dbo.SKUMaster WHERE SKUCode = :sku');
    $skuStmt->execute(['sku' => $sku]);
    $skuRow = $skuStmt->fetch();
    if ($skuRow !== false && trim((string) ($skuRow['ProductName'] ?? '')) !== '') {
        $description = (string) $skuRow['ProductName'];
    }

    $reference = 'CMO rework return · Transfer #' . $transferId;
    $form = array_merge(po_default_header(), [
        'supplier_id'          => (string) $supplierId,
        'supplier_name'        => (string) ($supplier['SupplierName'] ?? ''),
        'supplier_address'     => (string) ($supplier['Address'] ?? ''),
        'order_date'           => date('Y-m-d'),
        'po_status'            => 'Created',
        'reference_documents'  => $reference,
        'special_instructions' => $reference . '. Receive at CART via PO Receiving, then ship the linked CMO → CART return transfer.',
        'notes'                => $reference,
        'payment_terms'        => 'N/A — rework (no charge)',
    ]);

    $lines = [[
        'sku'             => $sku,
        'quote_number'    => '',
        'description'     => $description . ' (rework return)',
        'quantity'        => po_format_qty($qty),
        'unit_price'      => '0',
        'expiration_date' => '',
    ]];

    return [
        'ok'                    => true,
        'error'                 => null,
        'form'                  => $form,
        'lines'                 => $lines,
        'source_transfer_id'    => $transferId,
        'source_transfer_label' => 'Transfer #' . $transferId . ' · ' . $sku,
    ];
}

/**
 * Link a rework return PO to the outbound CMO transfer and stage CMO → CART return transfer.
 *
 * @return array{ok: bool, error: ?string, return_transfer_id?: int}
 */
function po_rework_link_return_po(int $transferId, int $poId): array
{
    $transfer = po_rework_get_transfer($transferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }

    $validated = po_rework_validate_outbound_transfer($transfer);
    if (!$validated['ok']) {
        return ['ok' => false, 'error' => $validated['error']];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if (po_rework_has_source_transfer_column()) {
            $pdo->prepare('UPDATE dbo.PurchaseOrder SET SourceTransferID = :transfer_id WHERE POID = :po_id')
                ->execute(['transfer_id' => $transferId, 'po_id' => $poId]);
        }

        if (po_rework_transfer_has_supplier_column()) {
            $pdo->prepare('UPDATE dbo.InvTransfer SET ReworkReturnPOID = :po_id WHERE TransferID = :transfer_id')
                ->execute(['po_id' => $poId, 'transfer_id' => $transferId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => 'Unable to link the rework return PO to the transfer.'];
    }

    $returnTransfer = po_rework_create_pending_return_transfer($transferId, $poId);

    return [
        'ok'                 => true,
        'error'              => $returnTransfer['ok'] ? null : ($returnTransfer['error'] ?? null),
        'return_transfer_id' => $returnTransfer['transfer_id'] ?? null,
    ];
}

/**
 * @return array{ok: bool, error: ?string, transfer_id?: int}
 */
function po_rework_create_pending_return_transfer(int $outboundTransferId, int $poId): array
{
    $transfer = po_rework_get_transfer($outboundTransferId);
    if ($transfer === null) {
        return ['ok' => false, 'error' => 'Transfer not found.'];
    }

    $qty = (float) ($transfer['QtyReceived'] ?? 0);
    if ($qty <= 0) {
        $qty = (float) ($transfer['QtyShipped'] ?? 0);
    }

    $pdo = db();
    $existing = $pdo->prepare(<<<SQL
        SELECT TransferID
        FROM dbo.InvTransfer
        WHERE FromFacilityCode = :from_facility
          AND ToFacilityCode = :to_facility
          AND SKUCode = :sku
          AND TransferStatus = N'Pending'
          AND Notes LIKE :note_pattern
    SQL);
    $existing->execute([
        'from_facility' => PO_REWORK_CMO_FACILITY_CODE,
        'to_facility'   => facility_default_po_receipt_code(),
        'sku'           => (string) $transfer['SKUCode'],
        'note_pattern'  => '%PO #' . $poId . '%',
    ]);
    $row = $existing->fetch();
    if ($row !== false) {
        return ['ok' => true, 'error' => null, 'transfer_id' => (int) $row['TransferID']];
    }

    return facility_insert_transfer([
        'from_facility_code' => PO_REWORK_CMO_FACILITY_CODE,
        'to_facility_code'   => facility_default_po_receipt_code(),
        'sku_code'           => (string) $transfer['SKUCode'],
        'qty_requested'      => $qty,
        'from_status_bucket' => (string) ($transfer['ToStatusBucket'] ?? 'OK'),
        'to_status_bucket'   => 'OK',
        'reason_code_id'     => po_rework_reason_code_id(),
        'supplier_id'        => (int) ($transfer['SupplierID'] ?? 0) ?: null,
        'notes'              => 'CMO rework return for PO #' . $poId . ' (outbound transfer #' . $outboundTransferId . '). Ship when reworked stock arrives at CART.',
    ]);
}
