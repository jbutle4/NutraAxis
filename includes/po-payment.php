<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/po.php';

const PO_PAYMENT_TYPES = ['Check', 'ACH', 'CC'];

const PO_PAYMENT_STATUSES = [
    'Pending',
    'Submitted for Approval',
    'Sent Back for Comment',
    'Transmitted to QBO',
    'Paid',
    'Failed',
    'Cancelled',
];

const PO_PAYMENT_LIST_SORT_COLUMNS = [
    'payment_date'   => 'Payment date',
    'reference'      => 'PO / invoice',
    'supplier'       => 'Supplier',
    'amount'         => 'Amount',
    'type'           => 'Type',
    'status'         => 'Status',
    'confirmation'   => 'Confirmation #',
    'made_by'        => 'Made by',
    'attachments'    => 'Files',
];

const PO_PAYMENT_LIST_SORT_SQL = [
    'payment_date' => 'p.PaymentDate',
    'reference'    => 'ReferenceLabel',
    'supplier'     => 's.SupplierName',
    'amount'       => 'p.PaymentAmount',
    'type'         => 'p.PaymentType',
    'status'       => 'p.PaymentStatus',
    'confirmation' => 'p.PaymentConfNumber',
    'made_by'      => 'p.PaymentMadeBy',
    'attachments'  => 'AttachmentCount',
];

const PO_PAYMENT_TARGETS = ['po', 'invoice'];

const PO_PAYMENT_LIST_SORT_NUMERIC = ['amount', 'attachments'];

function po_payment_status_class(string $status): string
{
    return match ($status) {
        'Pending'                => 'status-draft',
        'Submitted for Approval' => 'status-submitted',
        'Sent Back for Comment'  => 'status-sent-back',
        'Transmitted to QBO'     => 'status-received',
        'Paid'                   => 'status-approved',
        'Failed'                 => 'status-cancelled',
        'Cancelled'              => 'status-cancelled',
        default                  => 'status-draft',
    };
}

function po_payment_format_status(?string $status): string
{
    $status = trim((string) $status);

    return $status !== '' ? $status : '—';
}

function po_payment_target(array $payment): string
{
    return !empty($payment['SupplierInvoiceID']) && empty($payment['POID'])
        ? 'invoice'
        : 'po';
}

function po_payment_reference_label(array $payment): string
{
    if (!empty($payment['PONumber'])) {
        return (string) $payment['PONumber'];
    }

    $docNumber = trim((string) ($payment['InvoiceDocNumber'] ?? ''));
    if ($docNumber !== '') {
        return $docNumber;
    }

    if (!empty($payment['SupplierInvoiceID'])) {
        return 'Invoice #' . (int) $payment['SupplierInvoiceID'];
    }

    return '—';
}

function po_payment_reference_href(array $payment): ?string
{
    if (!empty($payment['POID'])) {
        return '/po-management/view.php?id=' . (int) $payment['POID'];
    }

    if (!empty($payment['SupplierInvoiceID'])) {
        return (function_exists('accounting_path') ? accounting_path('/accounting/supplier-invoices/view.php') : '/accounting/supplier-invoices/view.php') . '?id=' . (int) $payment['SupplierInvoiceID'];
    }

    return null;
}

function po_payment_can_read(): bool
{
    return po_can_access_po_pages();
}

function po_payment_can_create(): bool
{
    return po_can_update();
}

function po_payment_can_update(): bool
{
    return po_can_update();
}

function po_payment_can_delete(): bool
{
    return po_can_delete();
}

function po_payment_require_read(): void
{
    po_require_read();
}

function po_payment_require_create(): void
{
    po_require_update();
}

function po_payment_require_update(): void
{
    po_require_update();
}

function po_payment_require_delete(): void
{
    po_require_delete();
}

function po_payment_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
    }
}

function po_payment_datetime_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function po_payment_to_form(array $payment): array
{
    $target = po_payment_target($payment);

    return [
        'payment_id'          => (int) $payment['PaymentID'],
        'payment_target'      => $target,
        'po_id'               => !empty($payment['POID']) ? (int) $payment['POID'] : '',
        'supplier_invoice_id' => !empty($payment['SupplierInvoiceID']) ? (int) $payment['SupplierInvoiceID'] : '',
        'payment_date'        => po_payment_datetime_input($payment['PaymentDate'] ?? null),
        'payment_amount'      => (string) $payment['PaymentAmount'],
        'payment_type'        => (string) $payment['PaymentType'],
        'payment_status'      => (string) ($payment['PaymentStatus'] ?? 'Paid'),
        'payment_conf_number' => (string) ($payment['PaymentConfNumber'] ?? ''),
        'payment_made_by'     => (string) ($payment['PaymentMadeBy'] ?? ''),
        'payment_comments'    => (string) ($payment['PaymentComments'] ?? ''),
    ];
}

function po_payment_from_input(array $input): array
{
    return [
        'payment_target'      => trim($input['payment_target'] ?? 'po'),
        'po_id'               => trim($input['po_id'] ?? ''),
        'supplier_invoice_id' => trim($input['supplier_invoice_id'] ?? ''),
        'payment_date'        => trim($input['payment_date'] ?? ''),
        'payment_amount'      => trim($input['payment_amount'] ?? ''),
        'payment_type'        => trim($input['payment_type'] ?? ''),
        'payment_status'      => trim($input['payment_status'] ?? ''),
        'payment_conf_number' => trim($input['payment_conf_number'] ?? ''),
        'payment_made_by'     => trim($input['payment_made_by'] ?? ''),
        'payment_comments'    => trim($input['payment_comments'] ?? ''),
    ];
}

function po_payment_parse_datetime(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
}

function po_payment_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            p.PaymentID,
            p.POID,
            p.SupplierInvoiceID,
            p.PaymentDate,
            p.PaymentAmount,
            p.PaymentType,
            p.PaymentStatus,
            p.PaymentConfNumber,
            p.PaymentMadeBy,
            (
                SELECT COUNT(*)
                FROM dbo.POPaymentAttachment pa
                WHERE pa.PaymentID = p.PaymentID
            ) AS AttachmentCount,
            po.PONumber,
            si.DocNumber AS InvoiceDocNumber,
            s.SupplierName,
            COALESCE(po.PONumber, si.DocNumber, N'Invoice #' + CAST(p.SupplierInvoiceID AS NVARCHAR(20))) AS ReferenceLabel
        FROM dbo.POPayment p
        LEFT JOIN dbo.PurchaseOrder po ON po.POID = p.POID
        LEFT JOIN dbo.SupplierInvoice si ON si.SupplierInvoiceID = p.SupplierInvoiceID
        LEFT JOIN dbo.Supplier s ON s.SupplierID = COALESCE(po.SupplierID, si.SupplierID)
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['po_id'])) {
        $sql .= ' AND p.POID = :po_id';
        $params['po_id'] = (int) $filters['po_id'];
    }

    if (!empty($filters['supplier_invoice_id'])) {
        $sql .= ' AND p.SupplierInvoiceID = :supplier_invoice_id';
        $params['supplier_invoice_id'] = (int) $filters['supplier_invoice_id'];
    }

    if (!empty($filters['invoice_only'])) {
        $sql .= ' AND p.SupplierInvoiceID IS NOT NULL';
    }

    if (!empty($filters['po_only'])) {
        $sql .= ' AND p.POID IS NOT NULL';
    }

    if (!empty($filters['type'])) {
        $sql .= ' AND p.PaymentType = :type';
        $params['type'] = $filters['type'];
    }

    if (!empty($filters['status'])) {
        $sql .= ' AND p.PaymentStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            po.PONumber LIKE :q OR
            si.DocNumber LIKE :q OR
            s.SupplierName LIKE :q OR
            p.PaymentConfNumber LIKE :q OR
            p.PaymentMadeBy LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(PO_PAYMENT_LIST_SORT_COLUMNS, 'payment_date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PO_PAYMENT_LIST_SORT_SQL, $sortState, 'payment_date', 'po_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function po_payment_list_for_po(int $poId): array
{
    return po_payment_list(['po_id' => $poId]);
}

function po_payment_total_for_po(int $poId): float
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ISNULL(SUM(PaymentAmount), 0) FROM dbo.POPayment WHERE POID = :po_id');
    $stmt->execute(['po_id' => $poId]);

    return (float) $stmt->fetchColumn();
}

function po_payment_total_for_invoice(int $supplierInvoiceId): float
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ISNULL(SUM(PaymentAmount), 0) FROM dbo.POPayment WHERE SupplierInvoiceID = :invoice_id');
    $stmt->execute(['invoice_id' => $supplierInvoiceId]);

    return (float) $stmt->fetchColumn();
}

function po_payment_get(int $paymentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            p.*,
            po.PONumber,
            si.DocNumber AS InvoiceDocNumber,
            s.SupplierName
        FROM dbo.POPayment p
        LEFT JOIN dbo.PurchaseOrder po ON po.POID = p.POID
        LEFT JOIN dbo.SupplierInvoice si ON si.SupplierInvoiceID = p.SupplierInvoiceID
        LEFT JOIN dbo.Supplier s ON s.SupplierID = COALESCE(po.SupplierID, si.SupplierID)
        WHERE p.PaymentID = :id
    SQL);
    $stmt->execute(['id' => $paymentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_payment_get_invoice(int $supplierInvoiceId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            si.SupplierInvoiceID,
            si.POID,
            si.DocNumber,
            si.TxnDate,
            si.DueDate,
            si.TotalAmt,
            si.SyncStatus,
            si.QBO_BillId,
            si.VendorRefValue,
            s.SupplierName
        FROM dbo.SupplierInvoice si
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        WHERE si.SupplierInvoiceID = :id
    SQL);
    $stmt->execute(['id' => $supplierInvoiceId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_payment_po_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT po.POID, po.PONumber, s.SupplierName, po.POStatus
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        ORDER BY po.OrderDate DESC, po.POID DESC
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $options[] = [
            'id'    => (int) $row['POID'],
            'label' => $row['PONumber'] . ' · ' . $row['SupplierName'] . ' (' . $row['POStatus'] . ')',
        ];
    }

    return $options;
}

function po_payment_invoice_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT
            si.SupplierInvoiceID,
            si.DocNumber,
            si.TxnDate,
            si.TotalAmt,
            si.SyncStatus,
            s.SupplierName
        FROM dbo.SupplierInvoice si
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        WHERE si.POID IS NULL
          AND si.SyncStatus IN (
              N'Draft',
              N'Sent Back for Comment',
              N'Rejected',
              N'Failed',
              N'Posted'
          )
        ORDER BY si.TxnDate DESC, si.SupplierInvoiceID DESC
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $docNumber = trim((string) ($row['DocNumber'] ?? ''));
        $reference = $docNumber !== '' ? $docNumber : 'Invoice #' . (int) $row['SupplierInvoiceID'];
        $amount = po_format_money($row['TotalAmt'] ?? 0);
        $status = trim((string) ($row['SyncStatus'] ?? ''));
        $options[] = [
            'id'    => (int) $row['SupplierInvoiceID'],
            'label' => $reference . ' · ' . $row['SupplierName'] . ' · ' . $amount . ($status !== '' ? ' (' . $status . ')' : ''),
        ];
    }

    return $options;
}

function po_payment_save(array $input, ?int $paymentId = null): array
{
    $data = po_payment_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    $target = $data['payment_target'] !== '' ? $data['payment_target'] : 'po';
    if (!in_array($target, PO_PAYMENT_TARGETS, true)) {
        return ['ok' => false, 'error' => 'Select whether this payment is for a purchase order or supplier invoice.'];
    }

    $poId = null;
    $supplierInvoiceId = null;

    if ($target === 'invoice') {
        $supplierInvoiceId = (int) ($data['supplier_invoice_id'] ?? 0);
        if ($supplierInvoiceId <= 0) {
            return ['ok' => false, 'error' => 'Select a supplier invoice.'];
        }

        $invoice = po_payment_get_invoice($supplierInvoiceId);
        if ($invoice === null) {
            return ['ok' => false, 'error' => 'Supplier invoice not found.'];
        }

        if (!empty($invoice['POID'])) {
            return ['ok' => false, 'error' => 'This invoice is linked to a purchase order. Record the payment against the PO instead.'];
        }
    } else {
        $poId = (int) ($data['po_id'] ?? 0);
        if ($poId <= 0) {
            return ['ok' => false, 'error' => 'Select a purchase order.'];
        }

        if (po_get_order($poId) === null) {
            return ['ok' => false, 'error' => 'Purchase order not found.'];
        }
    }

    $paymentDate = po_payment_parse_datetime($data['payment_date']);
    if ($paymentDate === null) {
        return ['ok' => false, 'error' => 'Enter a valid payment date and time.'];
    }

    if ($data['payment_amount'] === '' || (float) $data['payment_amount'] <= 0) {
        return ['ok' => false, 'error' => 'Enter a payment amount greater than zero.'];
    }

    if (!in_array($data['payment_type'], PO_PAYMENT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid payment type.'];
    }

    $paymentStatus = $data['payment_status'] !== '' ? $data['payment_status'] : ($target === 'invoice' ? 'Pending' : 'Paid');
    if (!in_array($paymentStatus, PO_PAYMENT_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid payment status.'];
    }

    if ($target === 'invoice') {
        $existingPayment = $paymentId !== null ? po_payment_get($paymentId) : null;
        if ($existingPayment !== null && ($existingPayment['PaymentStatus'] ?? '') === 'Submitted for Approval') {
            return ['ok' => false, 'error' => 'This payment is awaiting approval and cannot be edited.'];
        }
        if ($paymentId === null) {
            $paymentStatus = 'Pending';
        } elseif ($existingPayment !== null && ($existingPayment['PaymentStatus'] ?? '') === 'Sent Back for Comment') {
            $paymentStatus = 'Pending';
        }
    }

    $params = [
        'po_id'               => $poId,
        'supplier_invoice_id' => $supplierInvoiceId,
        'payment_date'        => $paymentDate,
        'amount'              => (float) $data['payment_amount'],
        'type'                => $data['payment_type'],
        'status'              => $paymentStatus,
        'conf_number'         => $data['payment_conf_number'] !== '' ? $data['payment_conf_number'] : null,
        'made_by'             => $data['payment_made_by'] !== '' ? $data['payment_made_by'] : null,
        'comments'            => $data['payment_comments'] !== '' ? $data['payment_comments'] : null,
        'actor'               => $actorId,
    ];

    try {
        $pdo = db();

        if ($paymentId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.POPayment (
                    POID, SupplierInvoiceID, PaymentDate, PaymentAmount, PaymentType, PaymentStatus,
                    PaymentConfNumber, PaymentMadeBy, PaymentComments,
                    CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.PaymentID AS inserted_id
                VALUES (
                    :po_id, :supplier_invoice_id, :payment_date, :amount, :type, :status,
                    :conf_number, :made_by, :comments,
                    :actor, :actor
                )
            SQL);
            $stmt->bindValue(':po_id', $poId, $poId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':supplier_invoice_id', $supplierInvoiceId, $supplierInvoiceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':payment_date', $params['payment_date']);
            $stmt->bindValue(':amount', $params['amount']);
            $stmt->bindValue(':type', $params['type']);
            $stmt->bindValue(':status', $params['status']);
            $stmt->bindValue(':conf_number', $params['conf_number']);
            $stmt->bindValue(':made_by', $params['made_by']);
            $stmt->bindValue(':comments', $params['comments']);
            $stmt->bindValue(':actor', $params['actor'], $params['actor'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
            $paymentId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (po_payment_get($paymentId) === null) {
                return ['ok' => false, 'error' => 'Payment not found.'];
            }

            $params['id'] = $paymentId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.POPayment
                SET POID = :po_id,
                    SupplierInvoiceID = :supplier_invoice_id,
                    PaymentDate = :payment_date,
                    PaymentAmount = :amount,
                    PaymentType = :type,
                    PaymentStatus = :status,
                    PaymentConfNumber = :conf_number,
                    PaymentMadeBy = :made_by,
                    PaymentComments = :comments,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE PaymentID = :id
            SQL);
            $stmt->bindValue(':po_id', $poId, $poId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':supplier_invoice_id', $supplierInvoiceId, $supplierInvoiceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':payment_date', $params['payment_date']);
            $stmt->bindValue(':amount', $params['amount']);
            $stmt->bindValue(':type', $params['type']);
            $stmt->bindValue(':status', $params['status']);
            $stmt->bindValue(':conf_number', $params['conf_number']);
            $stmt->bindValue(':made_by', $params['made_by']);
            $stmt->bindValue(':comments', $params['comments']);
            $stmt->bindValue(':actor', $params['actor'], $params['actor'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
        }

        return [
            'ok'                  => true,
            'error'               => null,
            'id'                  => $paymentId,
            'po_id'               => $poId,
            'supplier_invoice_id' => $supplierInvoiceId,
        ];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save payment. Please check your entries and try again.'];
    }
}

function po_payment_delete(int $paymentId): array
{
    $payment = po_payment_get($paymentId);
    if ($payment === null) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM dbo.POPayment WHERE PaymentID = :id')->execute(['id' => $paymentId]);

    return [
        'ok'                  => true,
        'error'               => null,
        'po_id'               => !empty($payment['POID']) ? (int) $payment['POID'] : null,
        'supplier_invoice_id' => !empty($payment['SupplierInvoiceID']) ? (int) $payment['SupplierInvoiceID'] : null,
    ];
}
