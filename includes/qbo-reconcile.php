<?php

require_once __DIR__ . '/supplier.php';
require_once __DIR__ . '/supplier-qbo.php';
require_once __DIR__ . '/quickbooks.php';
require_once __DIR__ . '/po.php';

function qbo_reconcile_bind_production(): void
{
    supplier_qbo_bind_production();
}

/**
 * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
 */
function qbo_reconcile_suppliers_production(): array
{
    qbo_reconcile_bind_production();

    if (!qbo_is_connected()) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => 'QuickBooks Production is not connected.']],
        ];
    }

    $vendorList = qbo_list_vendors();
    if (!$vendorList['ok']) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => (string) ($vendorList['error'] ?? 'Unable to list QuickBooks vendors.')]],
        ];
    }

    $qboByName = [];
    $qboById = [];
    foreach ($vendorList['rows'] as $vendor) {
        if (!is_array($vendor)) {
            continue;
        }
        $id = trim((string) ($vendor['Id'] ?? ''));
        if ($id !== '') {
            $qboById[$id] = $vendor;
        }
        $norm = supplier_qbo_normalize_name((string) ($vendor['DisplayName'] ?? ''));
        if ($norm !== '' && !isset($qboByName[$norm])) {
            $qboByName[$norm] = $vendor;
        }
    }

    $pdo = db();
    $opsSuppliers = $pdo->query('SELECT * FROM dbo.Supplier ORDER BY SupplierName')->fetchAll();
    $matchedQboIds = [];
    $rows = [];
    $summary = [
        'cleared_stale'   => 0,
        'linked'          => 0,
        'created_in_qbo'  => 0,
        'created_in_ops'  => 0,
        'skipped'         => 0,
        'errors'          => 0,
    ];

    foreach ($opsSuppliers as $supplier) {
        $supplierId = (int) $supplier['SupplierID'];
        $name = (string) ($supplier['SupplierName'] ?? '');
        $norm = supplier_qbo_normalize_name($name);

        if (supplier_is_skipped_test_vendor($supplier)) {
            $summary['skipped']++;
            $rows[] = ['action' => 'skipped', 'name' => $name, 'detail' => 'Test/inactive vendor skipped.'];
            continue;
        }

        $qboId = trim((string) ($supplier['QBO_SupplierID'] ?? ''));
        if ($qboId !== '') {
            if (!isset($qboById[$qboId])) {
                supplier_clear_qbo_link($supplierId);
                $summary['cleared_stale']++;
                $rows[] = ['action' => 'cleared_stale', 'name' => $name, 'detail' => "Removed stale QBO vendor ID {$qboId}."];
                $qboId = '';
            } else {
                supplier_apply_qbo_vendor_response($supplierId, $qboById[$qboId]);
                $matchedQboIds[$qboId] = true;
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $name, 'detail' => "Verified QBO vendor ID {$qboId}."];
                continue;
            }
        }

        if ($qboId === '' && isset($qboByName[$norm])) {
            $vendor = $qboByName[$norm];
            supplier_apply_qbo_vendor_response($supplierId, $vendor);
            $matchedQboIds[(string) $vendor['Id']] = true;
            $summary['linked']++;
            $rows[] = ['action' => 'linked', 'name' => $name, 'detail' => 'Linked by display name match.'];
            continue;
        }

        if ($qboId === '' && !empty($supplier['IsActive'])) {
            $sync = qbo_sync_supplier($supplierId);
            if ($sync['ok']) {
                $vendorId = trim((string) ($sync['vendor']['Id'] ?? ''));
                if ($vendorId !== '') {
                    $matchedQboIds[$vendorId] = true;
                }
                $summary['created_in_qbo']++;
                $rows[] = ['action' => 'created_in_qbo', 'name' => $name, 'detail' => 'Created vendor in QuickBooks Production.'];
            } else {
                $summary['errors']++;
                $rows[] = ['action' => 'error', 'name' => $name, 'detail' => (string) ($sync['error'] ?? 'QuickBooks sync failed.')];
            }
        }
    }

    foreach ($vendorList['rows'] as $vendor) {
        if (!is_array($vendor)) {
            continue;
        }
        $vendorId = trim((string) ($vendor['Id'] ?? ''));
        if ($vendorId === '' || isset($matchedQboIds[$vendorId])) {
            continue;
        }

        $displayName = trim((string) ($vendor['DisplayName'] ?? ''));
        $norm = supplier_qbo_normalize_name($displayName);
        if ($norm === '' || in_array($norm, SUPPLIER_QBO_TEST_VENDOR_NAMES, true)) {
            $summary['skipped']++;
            $rows[] = ['action' => 'skipped', 'name' => $displayName, 'detail' => 'QBO-only test vendor skipped.'];
            continue;
        }

        if (empty($vendor['Active'])) {
            $summary['skipped']++;
            $rows[] = ['action' => 'skipped', 'name' => $displayName, 'detail' => 'Inactive QBO vendor skipped.'];
            continue;
        }

        $created = supplier_create_from_qbo_vendor($vendor);
        if ($created['ok']) {
            $matchedQboIds[$vendorId] = true;
            $summary['created_in_ops']++;
            $rows[] = ['action' => 'created_in_ops', 'name' => $displayName, 'detail' => 'Imported into Operations as new supplier.'];
        } else {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $displayName, 'detail' => (string) ($created['error'] ?? 'Import failed.')];
        }
    }

    return ['summary' => $summary, 'rows' => $rows];
}

function qbo_default_expense_account_id(): ?string
{
    $accounts = qbo_list_accounts();
    if (!$accounts['ok']) {
        return null;
    }

    foreach ($accounts['rows'] as $account) {
        if (!is_array($account)) {
            continue;
        }
        $type = strtolower((string) ($account['AccountType'] ?? ''));
        $sub = strtolower((string) ($account['AccountSubType'] ?? ''));
        if ($type === 'expense' || str_contains($sub, 'expense')) {
            $id = trim((string) ($account['Id'] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }
    }

    return null;
}

function qbo_create_purchase_order_from_ops(int $poId): array
{
    qbo_reconcile_bind_production();

    if (!qbo_is_connected()) {
        return ['ok' => false, 'error' => 'QuickBooks Production is not connected.'];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    $existingQboId = trim((string) ($order['QBO_POID'] ?? ''));
    if ($existingQboId !== '') {
        $fetch = qbo_fetch_purchase_order($existingQboId);
        if ($fetch['ok']) {
            return ['ok' => true, 'error' => null, 'po' => $fetch['purchase_order'], 'action' => 'existing'];
        }
    }

    $supplier = supplier_get((int) $order['SupplierID']);
    if ($supplier === null) {
        return ['ok' => false, 'error' => 'Supplier not found for this purchase order.'];
    }

    $vendorId = trim((string) ($supplier['QBO_SupplierID'] ?? ''));
    if ($vendorId === '') {
        $sync = qbo_sync_supplier((int) $supplier['SupplierID']);
        if (!$sync['ok']) {
            return ['ok' => false, 'error' => 'Supplier is not linked to QuickBooks: ' . ($sync['error'] ?? 'sync failed.')];
        }
        $supplier = supplier_get((int) $order['SupplierID']) ?? $supplier;
        $vendorId = trim((string) ($supplier['QBO_SupplierID'] ?? ''));
    }

    if ($vendorId === '') {
        return ['ok' => false, 'error' => 'Supplier has no QuickBooks vendor ID after sync.'];
    }

    $lines = po_get_lines($poId);
    if ($lines === []) {
        return ['ok' => false, 'error' => 'Purchase order has no line items.'];
    }

    $expenseAccountId = qbo_default_expense_account_id();
    $qboLines = [];
    foreach ($lines as $line) {
        $amount = (float) ($line['LineTotal'] ?? 0);
        if ($amount <= 0) {
            $amount = (float) ($line['Quantity'] ?? 0) * (float) ($line['UnitPrice'] ?? 0);
        }
        if ($amount <= 0) {
            continue;
        }

        $description = trim((string) ($line['ItemDescription'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($line['ItemSKU'] ?? 'PO line'));
        }

        $qboLine = [
            'Amount'     => round($amount, 2),
            'DetailType' => 'AccountBasedExpenseLineDetail',
            'Description'=> $description,
            'AccountBasedExpenseLineDetail' => [
                'AccountRef' => ['value' => $expenseAccountId ?? '1'],
            ],
        ];
        $qboLines[] = $qboLine;
    }

    if ($qboLines === []) {
        return ['ok' => false, 'error' => 'No billable PO line amounts found for QuickBooks.'];
    }

    if ($expenseAccountId === null) {
        return ['ok' => false, 'error' => 'No expense account found in QuickBooks for PO line mapping.'];
    }

    $payload = [
        'VendorRef' => ['value' => $vendorId],
        'TxnDate'   => (string) ($order['OrderDate'] ?? date('Y-m-d')),
        'Line'      => $qboLines,
    ];

    if (!empty($order['PONumber'])) {
        $payload['DocNumber'] = (string) $order['PONumber'];
    }

    $result = qbo_api_request('POST', '/purchaseorder', ['minorversion' => 65], $payload);
    if (!$result['ok']) {
        return $result;
    }

    $po = $result['data']['PurchaseOrder'] ?? null;
    if (!is_array($po) || empty($po['Id'])) {
        return ['ok' => false, 'error' => 'QuickBooks did not return a purchase order record.'];
    }

    $connection = qbo_get_connection();
    db()->prepare(<<<SQL
        UPDATE dbo.PurchaseOrder
        SET QBO_POID = :qbo_id,
            POQBOCreated = 1,
            ModifiedDate = SYSUTCDATETIME()
        WHERE POID = :id
    SQL)->execute([
        'qbo_id' => (string) $po['Id'],
        'id'     => $poId,
    ]);

    return ['ok' => true, 'error' => null, 'po' => $po, 'action' => 'created', 'realm_id' => (string) ($connection['RealmID'] ?? '')];
}

function qbo_fetch_purchase_order(string $poId): array
{
    $result = qbo_api_request('GET', '/purchaseorder/' . rawurlencode($poId), ['minorversion' => 65]);
    if (!$result['ok']) {
        return $result;
    }

    $po = $result['data']['PurchaseOrder'] ?? null;
    if (!is_array($po)) {
        return ['ok' => false, 'error' => 'Purchase order not found in QuickBooks.', 'purchase_order' => null];
    }

    return ['ok' => true, 'error' => null, 'purchase_order' => $po];
}

function qbo_import_purchase_order_from_qbo(array $qboPo): array
{
    $docNumber = trim((string) ($qboPo['DocNumber'] ?? ''));
    if ($docNumber === '') {
        $docNumber = 'QBO-PO-' . trim((string) ($qboPo['Id'] ?? ''));
    }

    $pdo = db();
    $existing = $pdo->prepare('SELECT POID FROM dbo.PurchaseOrder WHERE PONumber = :num');
    $existing->execute(['num' => $docNumber]);
    $row = $existing->fetch();
    if ($row !== false) {
        $poId = (int) $row['POID'];
        $pdo->prepare(<<<SQL
            UPDATE dbo.PurchaseOrder
            SET QBO_POID = :qbo_id, POQBOCreated = 1, ModifiedDate = SYSUTCDATETIME()
            WHERE POID = :id
        SQL)->execute([
            'qbo_id' => (string) ($qboPo['Id'] ?? ''),
            'id'     => $poId,
        ]);

        return ['ok' => true, 'error' => null, 'id' => $poId, 'action' => 'linked'];
    }

    $vendorId = trim((string) ($qboPo['VendorRef']['value'] ?? ''));
    $supplierId = null;
    if ($vendorId !== '') {
        $stmt = $pdo->prepare('SELECT SupplierID FROM dbo.Supplier WHERE QBO_SupplierID = :qbo');
        $stmt->execute(['qbo' => $vendorId]);
        $supplierRow = $stmt->fetch();
        if ($supplierRow !== false) {
            $supplierId = (int) $supplierRow['SupplierID'];
        }
    }

    if ($supplierId === null) {
        $vendorName = trim((string) ($qboPo['VendorRef']['name'] ?? 'Unknown vendor'));
        $created = supplier_create_from_qbo_vendor([
            'Id'          => $vendorId,
            'DisplayName' => $vendorName,
            'Active'      => true,
            'SyncToken'   => '0',
        ]);
        if (!$created['ok']) {
            return ['ok' => false, 'error' => 'Unable to import supplier for QBO PO: ' . ($created['error'] ?? ''), 'id' => null];
        }
        $supplierId = (int) $created['id'];
    }

    $actorId = auth_user()['UserID'] ?? 1;
    $total = (float) ($qboPo['TotalAmt'] ?? 0);
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.PurchaseOrder (
            PONumber, SupplierID, POStatus, OrderDate, Subtotal, TotalDue,
            CreatedByUser, QBO_POID, POQBOCreated
        )
        OUTPUT INSERTED.POID AS inserted_id
        VALUES (
            :po_number, :supplier_id, N'Approved', :order_date, :subtotal, :total_due,
            :actor, :qbo_id, 1
        )
    SQL);
    $stmt->execute([
        'po_number'   => $docNumber,
        'supplier_id' => $supplierId,
        'order_date'  => (string) ($qboPo['TxnDate'] ?? date('Y-m-d')),
        'subtotal'    => $total,
        'total_due'   => $total,
        'actor'       => $actorId,
        'qbo_id'      => (string) ($qboPo['Id'] ?? ''),
    ]);
    $poId = db_fetch_inserted_int($stmt, 'inserted_id');

    return ['ok' => true, 'error' => null, 'id' => $poId, 'action' => 'created_in_ops'];
}

/**
 * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
 */
function qbo_reconcile_purchase_orders_production(): array
{
    qbo_reconcile_bind_production();

    if (!qbo_is_connected()) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => 'QuickBooks Production is not connected.']],
        ];
    }

    $qboList = qbo_list_purchase_orders();
    if (!$qboList['ok']) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => (string) ($qboList['error'] ?? 'Unable to list QBO POs.')]],
        ];
    }

    $qboByDoc = [];
    $qboById = [];
    foreach ($qboList['rows'] as $po) {
        if (!is_array($po)) {
            continue;
        }
        $id = trim((string) ($po['Id'] ?? ''));
        if ($id !== '') {
            $qboById[$id] = $po;
        }
        $doc = trim((string) ($po['DocNumber'] ?? ''));
        if ($doc !== '') {
            $qboByDoc[mb_strtolower($doc)] = $po;
        }
    }

    $pdo = db();
    $opsPos = $pdo->query('SELECT POID, PONumber, QBO_POID FROM dbo.PurchaseOrder ORDER BY POID')->fetchAll();
    $matchedQboIds = [];
    $rows = [];
    $summary = [
        'cleared_stale'   => 0,
        'linked'          => 0,
        'created_in_qbo'  => 0,
        'created_in_ops'  => 0,
        'errors'          => 0,
    ];

    foreach ($opsPos as $po) {
        $poId = (int) $po['POID'];
        $poNumber = (string) ($po['PONumber'] ?? '');
        $qboId = trim((string) ($po['QBO_POID'] ?? ''));

        if ($qboId !== '') {
            if (!isset($qboById[$qboId])) {
                $pdo->prepare('UPDATE dbo.PurchaseOrder SET QBO_POID = NULL, POQBOCreated = 0, ModifiedDate = SYSUTCDATETIME() WHERE POID = :id')
                    ->execute(['id' => $poId]);
                $summary['cleared_stale']++;
                $rows[] = ['action' => 'cleared_stale', 'name' => $poNumber, 'detail' => "Removed stale QBO PO ID {$qboId}."];
                $qboId = '';
            } else {
                $matchedQboIds[$qboId] = true;
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $poNumber, 'detail' => "Verified QBO PO ID {$qboId}."];
                continue;
            }
        }

        if ($qboId === '') {
            $match = $qboByDoc[mb_strtolower($poNumber)] ?? null;
            if (is_array($match)) {
                $pdo->prepare('UPDATE dbo.PurchaseOrder SET QBO_POID = :qbo_id, POQBOCreated = 1, ModifiedDate = SYSUTCDATETIME() WHERE POID = :id')
                    ->execute(['qbo_id' => (string) ($match['Id'] ?? ''), 'id' => $poId]);
                $matchedQboIds[(string) ($match['Id'] ?? '')] = true;
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $poNumber, 'detail' => 'Linked by PO number.'];
                continue;
            }

            $create = qbo_create_purchase_order_from_ops($poId);
            if ($create['ok']) {
                $matchedQboIds[(string) ($create['po']['Id'] ?? '')] = true;
                $summary['created_in_qbo']++;
                $rows[] = ['action' => 'created_in_qbo', 'name' => $poNumber, 'detail' => 'Created purchase order in QuickBooks.'];
            } else {
                $summary['errors']++;
                $rows[] = ['action' => 'error', 'name' => $poNumber, 'detail' => (string) ($create['error'] ?? 'Create failed.')];
            }
        }
    }

    foreach ($qboList['rows'] as $qboPo) {
        if (!is_array($qboPo)) {
            continue;
        }
        $qboId = trim((string) ($qboPo['Id'] ?? ''));
        if ($qboId === '' || isset($matchedQboIds[$qboId])) {
            continue;
        }

        $doc = trim((string) ($qboPo['DocNumber'] ?? "QBO-PO-{$qboId}"));
        $import = qbo_import_purchase_order_from_qbo($qboPo);
        if ($import['ok']) {
            $matchedQboIds[$qboId] = true;
            $summary['created_in_ops']++;
            $rows[] = ['action' => 'created_in_ops', 'name' => $doc, 'detail' => 'Imported QBO purchase order into Operations.'];
        } else {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $doc, 'detail' => (string) ($import['error'] ?? 'Import failed.')];
        }
    }

    return ['summary' => $summary, 'rows' => $rows];
}

function qbo_import_bill_from_qbo(array $bill): array
{
    require_once __DIR__ . '/supplier-invoice.php';

    $docNumber = trim((string) ($bill['DocNumber'] ?? ''));
    if ($docNumber === '') {
        return ['ok' => false, 'error' => 'QuickBooks bill has no document number.', 'id' => null];
    }

    $pdo = db();
    $existing = $pdo->prepare('SELECT SupplierInvoiceID FROM dbo.SupplierInvoice WHERE DocNumber = :doc');
    $existing->execute(['doc' => $docNumber]);
    if ($existing->fetch() !== false) {
        return ['ok' => false, 'error' => 'An Operations invoice with this document number already exists.', 'id' => null];
    }

    $vendorId = trim((string) ($bill['VendorRef']['value'] ?? ''));
    $supplierId = null;
    if ($vendorId !== '') {
        $stmt = $pdo->prepare('SELECT SupplierID, SupplierName FROM dbo.Supplier WHERE QBO_SupplierID = :qbo');
        $stmt->execute(['qbo' => $vendorId]);
        $supplierRow = $stmt->fetch();
        if ($supplierRow !== false) {
            $supplierId = (int) $supplierRow['SupplierID'];
        }
    }

    if ($supplierId === null) {
        $vendorName = trim((string) ($bill['VendorRef']['name'] ?? 'Unknown vendor'));
        $created = supplier_create_from_qbo_vendor([
            'Id'          => $vendorId,
            'DisplayName' => $vendorName,
            'Active'      => true,
            'SyncToken'   => '0',
        ]);
        if (!$created['ok']) {
            return ['ok' => false, 'error' => 'Unable to import supplier for bill: ' . ($created['error'] ?? ''), 'id' => null];
        }
        $supplierId = (int) $created['id'];
    }

    $supplier = supplier_get($supplierId);
    if ($supplier === null) {
        return ['ok' => false, 'error' => 'Supplier not found after import.', 'id' => null];
    }

    $total = (float) ($bill['TotalAmt'] ?? 0);
    $expenseAccountId = qbo_default_expense_account_id() ?? '1';
    $save = supplier_invoice_save([
        'supplier_id' => $supplierId,
        'doc_number'  => $docNumber,
        'txn_date'    => (string) ($bill['TxnDate'] ?? date('Y-m-d')),
        'due_date'    => (string) ($bill['DueDate'] ?? ''),
        'lines'       => [[
            'description'       => 'Imported from QuickBooks bill ' . $docNumber,
            'amount'            => (string) $total,
            'detail_type'       => 'AccountBasedExpenseLineDetail',
            'account_ref_value' => $expenseAccountId,
            'account_ref_name'  => 'Imported expense',
        ]],
    ], null);

    if (!$save['ok']) {
        return ['ok' => false, 'error' => (string) ($save['error'] ?? 'Unable to import bill.'), 'id' => null];
    }

    $invoiceId = (int) $save['id'];
    qbo_apply_bill_link_to_invoice($invoiceId, $bill);

    return ['ok' => true, 'error' => null, 'id' => $invoiceId, 'action' => 'created_in_ops'];
}

/**
 * @return array{summary: array<string, int>, rows: list<array<string, mixed>>}
 */
function qbo_reconcile_bills_production(): array
{
    qbo_reconcile_bind_production();

    if (!qbo_is_connected()) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => 'QuickBooks Production is not connected.']],
        ];
    }

    $billList = qbo_list_bills();
    if (!$billList['ok']) {
        return [
            'summary' => ['errors' => 1],
            'rows'    => [['action' => 'error', 'name' => '—', 'detail' => (string) ($billList['error'] ?? 'Unable to list QBO bills.')]],
        ];
    }

    $qboByDoc = [];
    $qboById = [];
    foreach ($billList['rows'] as $bill) {
        if (!is_array($bill)) {
            continue;
        }
        $id = trim((string) ($bill['Id'] ?? ''));
        if ($id !== '') {
            $qboById[$id] = $bill;
        }
        $doc = trim((string) ($bill['DocNumber'] ?? ''));
        if ($doc !== '') {
            $qboByDoc[mb_strtolower($doc)] = $bill;
        }
    }

    $pdo = db();
    $invoices = $pdo->query(<<<SQL
        SELECT SupplierInvoiceID, DocNumber, QBO_BillId, SyncStatus
        FROM dbo.SupplierInvoice
        ORDER BY SupplierInvoiceID
    SQL)->fetchAll();

    $matchedQboIds = [];
    $rows = [];
    $summary = [
        'cleared_stale'   => 0,
        'linked'          => 0,
        'awaiting_approval' => 0,
        'created_in_ops'  => 0,
        'errors'          => 0,
    ];

    foreach ($invoices as $invoice) {
        $invoiceId = (int) $invoice['SupplierInvoiceID'];
        $doc = trim((string) ($invoice['DocNumber'] ?? ''));
        $billId = trim((string) ($invoice['QBO_BillId'] ?? ''));

        if ($billId !== '') {
            if (!isset($qboById[$billId])) {
                $pdo->prepare(<<<SQL
                    UPDATE dbo.SupplierInvoice
                    SET QBO_BillId = NULL, QBO_SyncToken = NULL, QBO_RealmId = NULL,
                        LastSyncError = NULL, ModifiedDate = SYSUTCDATETIME()
                    WHERE SupplierInvoiceID = :id
                SQL)->execute(['id' => $invoiceId]);
                $summary['cleared_stale']++;
                $rows[] = ['action' => 'cleared_stale', 'name' => $doc, 'detail' => "Removed stale QBO bill ID {$billId}."];
                $billId = '';
            } else {
                qbo_apply_bill_link_to_invoice($invoiceId, $qboById[$billId]);
                $matchedQboIds[$billId] = true;
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $doc, 'detail' => 'Verified linked QuickBooks bill.'];
                continue;
            }
        }

        if ($billId === '' && $doc !== '') {
            $match = $qboByDoc[mb_strtolower($doc)] ?? null;
            if (is_array($match)) {
                qbo_apply_bill_link_to_invoice($invoiceId, $match);
                $matchedQboIds[(string) ($match['Id'] ?? '')] = true;
                $summary['linked']++;
                $rows[] = ['action' => 'linked', 'name' => $doc, 'detail' => 'Linked to existing QuickBooks bill by document number.'];
                continue;
            }
        }

        if ($billId === '' && (string) ($invoice['SyncStatus'] ?? '') === 'Submitted for Approval') {
            $summary['awaiting_approval']++;
            $rows[] = ['action' => 'awaiting_approval', 'name' => $doc, 'detail' => 'No QBO bill yet — remains in payment approval queue.'];
        }
    }

    foreach ($billList['rows'] as $bill) {
        if (!is_array($bill)) {
            continue;
        }
        $billId = trim((string) ($bill['Id'] ?? ''));
        if ($billId === '' || isset($matchedQboIds[$billId])) {
            continue;
        }

        $doc = trim((string) ($bill['DocNumber'] ?? "QBO-Bill-{$billId}"));
        $import = qbo_import_bill_from_qbo($bill);
        if ($import['ok']) {
            $matchedQboIds[$billId] = true;
            $summary['created_in_ops']++;
            $rows[] = ['action' => 'created_in_ops', 'name' => $doc, 'detail' => 'Imported QuickBooks bill into Operations.'];
        } else {
            $summary['errors']++;
            $rows[] = ['action' => 'error', 'name' => $doc, 'detail' => (string) ($import['error'] ?? 'Import failed.')];
        }
    }

    return ['summary' => $summary, 'rows' => $rows];
}
