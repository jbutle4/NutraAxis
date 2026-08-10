<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/procurement-ledger.php';
require_once __DIR__ . '/po-payment.php';
require_once __DIR__ . '/supplier-invoice.php';
require_once __DIR__ . '/supplier.php';
require_once __DIR__ . '/quickbooks.php';

/**
 * Verify the PO supplier is ready for QuickBooks sync for this ledger profile.
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   remediation: ?string,
 *   stage: ?string,
 *   vendor_id: ?string,
 *   vendor_action: ?string
 * }
 */
function po_qbo_readiness_check(array $order): array
{
    $supplierId = (int) ($order['SupplierID'] ?? 0);
    if ($supplierId <= 0) {
        return [
            'ok'          => false,
            'error'       => 'This purchase order has no supplier selected.',
            'remediation' => 'Edit the PO and choose an active supplier before submitting for approval.',
            'stage'       => 'ops',
            'vendor_id'     => null,
            'vendor_action' => null,
        ];
    }

    $supplier = supplier_get($supplierId);
    if ($supplier === null) {
        return [
            'ok'          => false,
            'error'       => 'The supplier on this purchase order was not found in Operations.',
            'remediation' => 'Edit the PO and select a valid supplier, or create the supplier in Supplier Management.',
            'stage'       => 'ops',
            'vendor_id'     => null,
            'vendor_action' => null,
        ];
    }

    if (empty($supplier['IsActive'])) {
        return [
            'ok'          => false,
            'error'       => 'Supplier "' . ($supplier['SupplierName'] ?? '') . '" is inactive in Operations.',
            'remediation' => 'Reactivate the supplier or choose a different supplier before submitting for approval.',
            'stage'       => 'ops',
            'vendor_id'     => null,
            'vendor_action' => null,
        ];
    }

    $ledgerProfile = po_order_ledger_profile($order);
    procurement_bind_ledger_profile($ledgerProfile);

    if (!qbo_is_connected()) {
        $label = po_ledger_profile_label($ledgerProfile);

        return [
            'ok'          => false,
            'error'       => 'QuickBooks ' . qbo_environment_label(qbo_environment()) . ' is not connected for ' . $label . ' purchase orders.',
            'remediation' => 'Connect QuickBooks under Accounting before submitting this PO for approval.',
            'stage'       => 'qbo',
            'vendor_id'     => null,
            'vendor_action' => null,
        ];
    }

    require_once __DIR__ . '/qbo-reconcile.php';

    $vendorResolve = qbo_resolve_po_vendor_id($order, $supplier);
    if (!$vendorResolve['ok']) {
        $supplierName = (string) ($supplier['SupplierName'] ?? 'Supplier');
        $remediation = $ledgerProfile === PO_LEDGER_PROFILE_UAT
            ? 'Ensure "' . $supplierName . '" exists in QuickBooks Production so it can be mirrored into Sandbox, '
                . 'or create the vendor manually in Sandbox with the same display name.'
            : 'Update supplier details in Supplier Management and retry, or run Accounting → QBO Sync for suppliers.';

        return [
            'ok'            => false,
            'error'         => (string) ($vendorResolve['error'] ?? 'Supplier is not linked to QuickBooks.'),
            'remediation'   => $remediation,
            'stage'         => 'qbo',
            'vendor_id'     => null,
            'vendor_action' => null,
        ];
    }

    $vendorAction = isset($vendorResolve['action']) ? (string) $vendorResolve['action'] : null;

    return [
        'ok'            => true,
        'error'         => null,
        'remediation'   => null,
        'stage'         => null,
        'vendor_id'     => trim((string) ($vendorResolve['vendor_id'] ?? '')) !== ''
            ? (string) $vendorResolve['vendor_id']
            : null,
        'vendor_action' => $vendorAction,
    ];
}

function po_qbo_has_sync_metadata_columns(): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.PurchaseOrder', 'POQBO_LastSyncError') AS col_len");
        $row = $stmt->fetch();
        $cache = $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        $cache = false;
    }

    return $cache;
}

function po_qbo_mark_sync_success(int $poId): void
{
    if (!po_qbo_has_sync_metadata_columns()) {
        return;
    }

    db()->prepare(<<<SQL
        UPDATE dbo.PurchaseOrder
        SET POQBO_LastSyncError = NULL,
            POQBO_LastSyncAt = SYSUTCDATETIME(),
            ModifiedDate = SYSUTCDATETIME()
        WHERE POID = :id
    SQL)->execute(['id' => $poId]);
}

function po_qbo_mark_sync_failure(int $poId, string $error): void
{
    if (!po_qbo_has_sync_metadata_columns()) {
        return;
    }

    $message = mb_substr(trim($error), 0, 1000);

    db()->prepare(<<<SQL
        UPDATE dbo.PurchaseOrder
        SET POQBO_LastSyncError = :sync_error,
            POQBO_LastSyncAt = SYSUTCDATETIME(),
            ModifiedDate = SYSUTCDATETIME()
        WHERE POID = :id
    SQL)->execute([
        'sync_error' => $message !== '' ? $message : null,
        'id'         => $poId,
    ]);
}

/**
 * Create or verify the QuickBooks purchase order after PO approval.
 *
 * @return array{ok: bool, error: ?string, skipped: bool, qbo_po_id: ?string, action: ?string}
 */
function po_qbo_sync_after_approval(int $poId): array
{
    require_once __DIR__ . '/qbo-reconcile.php';

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.', 'skipped' => true, 'qbo_po_id' => null, 'action' => null];
    }

    if ((string) ($order['POStatus'] ?? '') !== 'Approved') {
        return ['ok' => true, 'error' => null, 'skipped' => true, 'qbo_po_id' => null, 'action' => null];
    }

    $existingQboId = trim((string) ($order['QBO_POID'] ?? ''));
    if ($existingQboId !== '') {
        po_qbo_mark_sync_success($poId);

        return ['ok' => true, 'error' => null, 'skipped' => true, 'qbo_po_id' => $existingQboId, 'action' => 'existing'];
    }

    $result = qbo_create_purchase_order_from_ops($poId);
    if (!$result['ok']) {
        $error = (string) ($result['error'] ?? 'QuickBooks purchase order creation failed.');
        po_qbo_mark_sync_failure($poId, $error);
        error_log('PO QBO sync after approval failed for PO ' . $poId . ': ' . $error);

        return [
            'ok'        => false,
            'error'     => $error,
            'skipped'   => false,
            'qbo_po_id' => null,
            'action'    => null,
        ];
    }

    po_qbo_mark_sync_success($poId);
    $qboPo = is_array($result['po'] ?? null) ? $result['po'] : [];
    $qboPoId = trim((string) ($qboPo['Id'] ?? $order['QBO_POID'] ?? ''));

    return [
        'ok'        => true,
        'error'     => null,
        'skipped'   => false,
        'qbo_po_id' => $qboPoId !== '' ? $qboPoId : null,
        'action'    => (string) ($result['action'] ?? 'created'),
    ];
}

function po_order_total_due(array $order): float
{
    $totalDue = (float) ($order['TotalDue'] ?? 0);
    if ($totalDue > 0) {
        return $totalDue;
    }

    return (float) ($order['Subtotal'] ?? 0) + (float) ($order['ShippingHandling'] ?? 0);
}

/**
 * Mark the PO paid when paid amounts cover the order total.
 */
function po_maybe_mark_paid_from_payments(int $poId): bool
{
    require_once __DIR__ . '/po-approval.php';

    $order = po_get_order($poId);
    if ($order === null) {
        return false;
    }

    if ((string) ($order['POStatus'] ?? '') === PO_STATUS_PAID) {
        return false;
    }

    $totalDue = po_order_total_due($order);
    if ($totalDue <= 0) {
        return false;
    }

    $paid = po_payment_total_applied_to_po($poId);
    if ($paid + 0.009 < $totalDue) {
        return false;
    }

    $result = po_advance_accounting_status($poId, PO_STATUS_PAID);
    if (!$result['ok']) {
        error_log('Unable to mark PO ' . $poId . ' paid after QBO payment sync: ' . ($result['error'] ?? 'unknown'));

        return false;
    }

    return true;
}

function qbo_bill_is_paid(array $bill): bool
{
    if (!isset($bill['Balance'])) {
        return false;
    }

    $balance = (float) $bill['Balance'];
    $total = (float) ($bill['TotalAmt'] ?? 0);

    return $total > 0 && $balance <= 0.01;
}

/**
 * Refresh an Ops supplier invoice from a linked QuickBooks bill, including payment status.
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   paid: bool,
 *   payments_updated: int,
 *   payment_created: bool,
 *   po_marked_paid: bool
 * }
 */
function qbo_refresh_ops_invoice_from_qbo_bill(int $invoiceId, array $bill): array
{
    $summary = [
        'ok'               => true,
        'error'            => null,
        'paid'             => false,
        'payments_updated' => 0,
        'payment_created'  => false,
        'po_marked_paid'   => false,
    ];

    try {
        qbo_apply_bill_link_to_invoice($invoiceId, $bill);
    } catch (Throwable $e) {
        return [
            'ok'               => false,
            'error'            => po_format_exception_message($e, 'refresh this supplier invoice from QuickBooks'),
            'paid'             => false,
            'payments_updated' => 0,
            'payment_created'  => false,
            'po_marked_paid'   => false,
        ];
    }

    if (!qbo_bill_is_paid($bill)) {
        return $summary;
    }

    $summary['paid'] = true;

    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return $summary;
    }

    $pdo = db();
    $payments = po_payment_list(['supplier_invoice_id' => $invoiceId]);
    $openStatuses = [
        'Pending',
        'Submitted for Approval',
        'Sent Back for Comment',
        'Transmitted to QBO',
        'Failed',
    ];

    $updateStmt = $pdo->prepare(<<<SQL
        UPDATE dbo.POPayment
        SET PaymentStatus = N'Paid',
            PaymentComments = CASE
                WHEN PaymentComments IS NULL OR LTRIM(RTRIM(PaymentComments)) = N''
                    THEN N'Synced from QuickBooks bill payment.'
                WHEN PaymentComments NOT LIKE N'%QuickBooks bill payment%'
                    THEN PaymentComments + CHAR(10) + N'Synced from QuickBooks bill payment.'
                ELSE PaymentComments
            END,
            ModifiedDate = SYSUTCDATETIME()
        WHERE PaymentID = :id
    SQL);

    foreach ($payments as $payment) {
        $status = (string) ($payment['PaymentStatus'] ?? '');
        if ($status === 'Paid' || $status === 'Cancelled') {
            continue;
        }

        if (!in_array($status, $openStatuses, true)) {
            continue;
        }

        $updateStmt->execute(['id' => (int) $payment['PaymentID']]);
        $summary['payments_updated']++;
    }

    if ($payments === []) {
        $amount = (float) ($invoice['TotalAmt'] ?? $bill['TotalAmt'] ?? 0);
        if ($amount > 0) {
            $poId = !empty($invoice['POID']) ? (int) $invoice['POID'] : null;
            $ledgerProfile = procurement_row_ledger_profile($invoice);
            $actorId = auth_user()['UserID'] ?? null;
            $paymentDate = date('Y-m-d H:i:s');

            if (po_payment_has_ledger_profile_column()) {
                $insert = $pdo->prepare(<<<SQL
                    INSERT INTO dbo.POPayment (
                        POID, SupplierInvoiceID, PaymentDate, PaymentAmount, PaymentType, PaymentStatus,
                        PaymentComments, LedgerProfile, CreatedByUser, ModifiedbyUser
                    )
                    VALUES (
                        :po_id, :supplier_invoice_id, :payment_date, :amount, N'Check', N'Paid',
                        N'Synced from QuickBooks bill payment.', :ledger_profile, :actor, :actor
                    )
                SQL);
                $insert->execute([
                    'po_id'               => $poId,
                    'supplier_invoice_id' => $invoiceId,
                    'payment_date'        => $paymentDate,
                    'amount'              => $amount,
                    'ledger_profile'      => $ledgerProfile,
                    'actor'               => $actorId,
                ]);
            } else {
                $insert = $pdo->prepare(<<<SQL
                    INSERT INTO dbo.POPayment (
                        POID, SupplierInvoiceID, PaymentDate, PaymentAmount, PaymentType, PaymentStatus,
                        PaymentComments, CreatedByUser, ModifiedbyUser
                    )
                    VALUES (
                        :po_id, :supplier_invoice_id, :payment_date, :amount, N'Check', N'Paid',
                        N'Synced from QuickBooks bill payment.', :actor, :actor
                    )
                SQL);
                $insert->execute([
                    'po_id'               => $poId,
                    'supplier_invoice_id' => $invoiceId,
                    'payment_date'        => $paymentDate,
                    'amount'              => $amount,
                    'actor'               => $actorId,
                ]);
            }

            $summary['payment_created'] = true;
        }
    }

    $poId = !empty($invoice['POID']) ? (int) $invoice['POID'] : 0;
    if ($poId > 0) {
        $summary['po_marked_paid'] = po_maybe_mark_paid_from_payments($poId);
    }

    return $summary;
}
