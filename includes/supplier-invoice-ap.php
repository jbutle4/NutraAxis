<?php

require_once __DIR__ . '/supplier-invoice.php';
require_once __DIR__ . '/po-receiving.php';
require_once __DIR__ . '/po-approval.php';
require_once __DIR__ . '/po-payment.php';

const SUPPLIER_INVOICE_ASN_UNMATCHED = 'Unmatched';
const SUPPLIER_INVOICE_ASN_DRAFT = 'DraftStarted';
const SUPPLIER_INVOICE_ASN_MATCHED = 'Matched';

function supplier_invoice_has_asn_columns(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $stmt = db()->query("SELECT COL_LENGTH('dbo.SupplierInvoice', 'AsnStatus')");
    $cached = $stmt !== false && $stmt->fetchColumn() !== null;

    return $cached;
}

function supplier_invoice_asn_status(?array $invoice): string
{
    $status = trim((string) ($invoice['AsnStatus'] ?? ''));

    return $status !== '' ? $status : SUPPLIER_INVOICE_ASN_UNMATCHED;
}

function supplier_invoice_asn_is_matched(?array $invoice): bool
{
    return supplier_invoice_asn_status($invoice) === SUPPLIER_INVOICE_ASN_MATCHED;
}

function supplier_invoice_sync_status_for_bill(?array $invoice, array $bill): string
{
    require_once __DIR__ . '/po-qbo-sync.php';

    $current = (string) ($invoice['SyncStatus'] ?? 'Posted');
    if (in_array($current, ['Voided', 'Rejected'], true)) {
        return $current;
    }

    if (!qbo_bill_is_paid($bill)) {
        return 'Posted';
    }

    return supplier_invoice_asn_is_matched($invoice) ? 'Closed' : 'Paid';
}

function supplier_invoice_match_asn(int $invoiceId): array
{
    if (!supplier_invoice_has_asn_columns()) {
        return ['ok' => true, 'matched' => false, 'error' => null];
    }

    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'matched' => false, 'error' => 'Invoice not found.'];
    }

    $poId = (int) ($invoice['POID'] ?? 0);
    if ($poId <= 0) {
        return ['ok' => true, 'matched' => false, 'error' => null];
    }

    $ledger = procurement_row_ledger_profile($invoice);
    $receipts = por_list_for_po($poId, $ledger);
    $chosen = null;
    foreach ($receipts as $receipt) {
        $status = (string) ($receipt['PORStatus'] ?? '');
        if ($status === 'Cancelled') {
            continue;
        }
        $jazz = trim((string) ($receipt['JazzASN'] ?? ''));
        if ($jazz !== '') {
            $chosen = $receipt;
            break;
        }
        if ($chosen === null) {
            $chosen = $receipt;
        }
    }

    if ($chosen === null) {
        return ['ok' => true, 'matched' => false, 'error' => null];
    }

    $jazz = trim((string) ($chosen['JazzASN'] ?? ''));
    $asnStatus = $jazz !== '' || in_array((string) ($chosen['PORStatus'] ?? ''), ['Transmitted', 'Complete'], true)
        ? SUPPLIER_INVOICE_ASN_MATCHED
        : SUPPLIER_INVOICE_ASN_DRAFT;

    db()->prepare(<<<SQL
        UPDATE dbo.SupplierInvoice
        SET AsnStatus = :asn_status,
            PORID = :por_id,
            JazzASN = :jazz_asn,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierInvoiceID = :id
    SQL)->execute([
        'asn_status' => $asnStatus,
        'por_id'     => (int) $chosen['PORID'],
        'jazz_asn'   => $jazz !== '' ? $jazz : null,
        'id'         => $invoiceId,
    ]);

    $invoice = supplier_invoice_get($invoiceId);
    $billId = trim((string) ($invoice['QBO_BillId'] ?? ''));
    if ($billId !== '' && in_array((string) ($invoice['SyncStatus'] ?? ''), ['Posted', 'Paid', 'Closed'], true)) {
        require_once __DIR__ . '/quickbooks.php';
        require_once __DIR__ . '/po-qbo-sync.php';
        $fetch = qbo_api_request('GET', '/bill/' . rawurlencode($billId), ['minorversion' => 65]);
        $bill = is_array($fetch['data']['Bill'] ?? null) ? $fetch['data']['Bill'] : null;
        if (is_array($bill) && qbo_bill_is_paid($bill)) {
            qbo_apply_bill_link_to_invoice($invoiceId, $bill);
        }
    }

    return [
        'ok'      => true,
        'matched' => $asnStatus === SUPPLIER_INVOICE_ASN_MATCHED,
        'draft'   => $asnStatus === SUPPLIER_INVOICE_ASN_DRAFT,
        'receipt' => $chosen,
        'error'   => null,
    ];
}

function supplier_invoice_mark_asn_draft_started(int $invoiceId): void
{
    if (!supplier_invoice_has_asn_columns()) {
        return;
    }

    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null || supplier_invoice_asn_is_matched($invoice)) {
        return;
    }

    db()->prepare(<<<SQL
        UPDATE dbo.SupplierInvoice
        SET AsnStatus = :status,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierInvoiceID = :id
    SQL)->execute([
        'status' => SUPPLIER_INVOICE_ASN_DRAFT,
        'id'     => $invoiceId,
    ]);
}

function supplier_invoice_asn_create_href(array $invoice): ?string
{
    $poId = (int) ($invoice['POID'] ?? 0);
    if ($poId <= 0 || !function_exists('por_can_create') || !por_can_create()) {
        return null;
    }

    $profile = procurement_row_ledger_profile($invoice);

    return por_href_for_profile('/po-receiving/new.php', $profile, [
        'po_id'      => $poId,
        'invoice_id' => (int) ($invoice['SupplierInvoiceID'] ?? 0),
    ]);
}

function supplier_invoice_asn_receipt_href(array $invoice): ?string
{
    $porId = (int) ($invoice['PORID'] ?? 0);
    if ($porId <= 0) {
        return null;
    }

    return por_href_for_profile(
        '/po-receiving/view.php',
        procurement_row_ledger_profile($invoice),
        ['id' => $porId]
    );
}

function supplier_invoice_after_bill_posted(int $invoiceId): void
{
    supplier_invoice_match_asn($invoiceId);
    $invoice = supplier_invoice_get($invoiceId);
    $poId = (int) ($invoice['POID'] ?? 0);
    if ($poId > 0) {
        po_sync_accounting_status_from_invoices($poId);
    }
}

function supplier_invoice_can_confirm_payment(?array $invoice): bool
{
    if ($invoice === null || !supplier_invoice_can_update()) {
        return false;
    }

    $status = (string) ($invoice['SyncStatus'] ?? '');
    $billId = trim((string) ($invoice['QBO_BillId'] ?? ''));
    if ($billId === '' || !in_array($status, ['Posted', 'Failed', 'Paid'], true)) {
        return false;
    }

    $balance = $invoice['Balance'] ?? null;
    if ($balance === null || $balance === '') {
        return $status !== 'Paid';
    }

    return (float) $balance > 0.01;
}

function supplier_invoice_confirm_bill_payment(int $invoiceId, array $input): array
{
    require_once __DIR__ . '/quickbooks.php';
    require_once __DIR__ . '/qbo-insert-approval.php';

    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }
    if (!supplier_invoice_can_confirm_payment($invoice)) {
        return ['ok' => false, 'error' => 'This invoice cannot accept a payment confirmation in its current status.'];
    }

    $amount = round((float) ($input['payment_amount'] ?? 0), 2);
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Enter a payment amount greater than zero.'];
    }

    $date = trim((string) ($input['payment_date'] ?? ''));
    if ($date === '') {
        $date = date('Y-m-d');
    }

    $type = trim((string) ($input['payment_type'] ?? 'ACH'));
    if (!in_array($type, PO_PAYMENT_TYPES, true)) {
        $type = 'ACH';
    }

    $conf = trim((string) ($input['payment_conf_number'] ?? ''));
    $madeBy = trim((string) ($input['payment_made_by'] ?? ''));
    if ($madeBy === '') {
        $madeBy = (string) (auth_user()['UserName'] ?? 'Operations');
    }

    $actorId = auth_user()['UserID'] ?? null;
    $poId = !empty($invoice['POID']) ? (int) $invoice['POID'] : null;
    $ledgerProfile = procurement_row_ledger_profile($invoice);
    $pdo = db();
    db_apply_sql_server_options($pdo);

    try {
        $pdo->beginTransaction();

        if (po_payment_has_ledger_profile_column()) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.POPayment (
                    POID, SupplierInvoiceID, PaymentDate, PaymentAmount, PaymentType, PaymentStatus,
                    PaymentConfNumber, PaymentMadeBy, PaymentComments, LedgerProfile,
                    CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.PaymentID AS inserted_id
                VALUES (
                    :po_id, :invoice_id, :payment_date, :amount, :type, N'Paid',
                    :conf_number, :made_by, :comments, :ledger_profile,
                    :actor, :actor
                )
            SQL);
        } else {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.POPayment (
                    POID, SupplierInvoiceID, PaymentDate, PaymentAmount, PaymentType, PaymentStatus,
                    PaymentConfNumber, PaymentMadeBy, PaymentComments,
                    CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.PaymentID AS inserted_id
                VALUES (
                    :po_id, :invoice_id, :payment_date, :amount, :type, N'Paid',
                    :conf_number, :made_by, :comments,
                    :actor, :actor
                )
            SQL);
        }

        $stmt->bindValue(':po_id', $poId, $poId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $stmt->bindValue(':payment_date', $date . ' 12:00:00');
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':conf_number', $conf !== '' ? $conf : null);
        $stmt->bindValue(':made_by', $madeBy);
        $stmt->bindValue(':comments', 'Confirmed in Operations and posted to QuickBooks as a bill payment.');
        if (po_payment_has_ledger_profile_column()) {
            $stmt->bindValue(':ledger_profile', $ledgerProfile);
        }
        $stmt->bindValue(':actor', $actorId, $actorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
        $paymentId = db_fetch_inserted_int($stmt, 'inserted_id');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => supplier_invoice_format_exception($e)];
    }

    procurement_bind_ledger_profile($ledgerProfile);
    $post = qbo_create_bill_payment_from_invoice_payment($paymentId);
    if (!$post['ok']) {
        $pdo->prepare("UPDATE dbo.POPayment SET PaymentStatus = N'Failed', PaymentComments = :c, ModifiedDate = SYSUTCDATETIME() WHERE PaymentID = :id")
            ->execute([
                'c'  => 'QuickBooks bill payment failed: ' . (string) ($post['error'] ?? 'unknown'),
                'id' => $paymentId,
            ]);

        return ['ok' => false, 'error' => (string) ($post['error'] ?? 'Unable to post the bill payment to QuickBooks.')];
    }

    $billId = trim((string) ($invoice['QBO_BillId'] ?? ''));
    $fetch = qbo_api_request('GET', '/bill/' . rawurlencode($billId), ['minorversion' => 65]);
    if ($fetch['ok'] && is_array($fetch['data']['Bill'] ?? null)) {
        qbo_apply_bill_link_to_invoice($invoiceId, $fetch['data']['Bill']);
    }

    supplier_invoice_after_bill_posted($invoiceId);

    return [
        'ok'              => true,
        'error'           => null,
        'payment_id'      => $paymentId,
        'bill_payment_id' => $post['bill_payment_id'] ?? null,
    ];
}

function po_sync_accounting_status_from_invoices(int $poId): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'advanced' => null];
    }

    $current = (string) ($order['POStatus'] ?? '');
    if (!in_array($current, [PO_STATUS_APPROVED, PO_STATUS_ACCOUNTING, PO_STATUS_PAID], true)) {
        return ['ok' => true, 'advanced' => null];
    }

    $invoices = db()->prepare(<<<SQL
        SELECT SyncStatus
        FROM dbo.SupplierInvoice
        WHERE POID = :po_id
          AND SyncStatus NOT IN (N'Voided', N'Rejected')
    SQL);
    $invoices->execute(['po_id' => $poId]);
    $rows = $invoices->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return ['ok' => true, 'advanced' => null];
    }

    $allSettled = true;
    $hasPostedBill = false;
    foreach ($rows as $row) {
        $status = (string) ($row['SyncStatus'] ?? '');
        if (in_array($status, ['Posted', 'Paid', 'Closed'], true)) {
            $hasPostedBill = true;
        }
        if (!in_array($status, ['Paid', 'Closed'], true)) {
            $allSettled = false;
        }
    }

    $target = null;
    if ($allSettled) {
        $target = PO_STATUS_PAID;
    } elseif ($hasPostedBill && $current === PO_STATUS_APPROVED) {
        $target = PO_STATUS_ACCOUNTING;
    }

    if ($target === null || $target === $current) {
        return ['ok' => true, 'advanced' => null];
    }

    $result = po_advance_accounting_status($poId, $target);

    return ['ok' => $result['ok'], 'advanced' => $result['ok'] ? $target : null, 'error' => $result['error'] ?? null];
}

function supplier_invoice_ap_reconcile(?string $ledgerProfile = null): array
{
    require_once __DIR__ . '/qbo-reconcile.php';
    require_once __DIR__ . '/procurement-ledger.php';

    $profile = po_normalize_ledger_profile($ledgerProfile ?? PO_LEDGER_PROFILE_PRODUCTION);
    $result = qbo_reconcile_bills($profile);

    $pdo = db();
    $sql = 'SELECT SupplierInvoiceID FROM dbo.SupplierInvoice';
    $params = [];
    if (supplier_invoice_has_ledger_profile_column()) {
        $sql .= ' WHERE LedgerProfile = :ledger_profile';
        $params['ledger_profile'] = $profile;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        supplier_invoice_match_asn((int) $row['SupplierInvoiceID']);
    }

    $poSql = 'SELECT DISTINCT POID FROM dbo.SupplierInvoice WHERE POID IS NOT NULL';
    if (supplier_invoice_has_ledger_profile_column()) {
        $poSql .= ' AND LedgerProfile = :ledger_profile';
    }
    $poStmt = $pdo->prepare($poSql);
    $poStmt->execute($params);
    foreach ($poStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        po_sync_accounting_status_from_invoices((int) $row['POID']);
    }

    return $result;
}
