<?php

require_once __DIR__ . '/accounting.php';
require_once __DIR__ . '/po.php';

const SUPPLIER_INVOICE_SYNC_STATUSES = [
    'Draft',
    'Submitted for Approval',
    'Sent Back for Comment',
    'Rejected',
    'Posted',
    'Failed',
    'Voided',
];

const SUPPLIER_INVOICE_DETAIL_TYPES = [
    'AccountBasedExpenseLineDetail' => 'Expense account',
    'ItemBasedExpenseLineDetail'    => 'Inventory item',
];

const SUPPLIER_INVOICE_TAX_CALCULATIONS = [
    ''                => '—',
    'TaxExcluded'     => 'Tax excluded',
    'TaxInclusive'    => 'Tax inclusive',
    'NotApplicable'   => 'Not applicable',
];

const SUPPLIER_INVOICE_LIST_SORT_COLUMNS = [
    'txn_date'   => 'Invoice date',
    'doc_number' => 'Invoice #',
    'supplier'   => 'Supplier',
    'total'      => 'Total',
    'balance'    => 'Balance',
    'due_date'   => 'Due date',
    'status'     => 'Sync status',
    'po_number'  => 'PO',
];

const SUPPLIER_INVOICE_LIST_SORT_SQL = [
    'txn_date'   => 'si.TxnDate',
    'doc_number' => 'si.DocNumber',
    'supplier'   => 's.SupplierName',
    'total'      => 'si.TotalAmt',
    'balance'    => 'si.Balance',
    'due_date'   => 'si.DueDate',
    'status'     => 'si.SyncStatus',
    'po_number'  => 'po.PONumber',
];

const SUPPLIER_INVOICE_LIST_SORT_NUMERIC = ['total', 'balance'];

function supplier_invoice_can_read(): bool
{
    return accounting_can_read();
}

function supplier_invoice_can_create(): bool
{
    return accounting_can_create();
}

function supplier_invoice_can_update(): bool
{
    return accounting_can_update();
}

function supplier_invoice_can_delete(): bool
{
    return accounting_can_delete()
        || auth_can_delete(ADMIN_PERMISSION_COLUMNS['users']);
}

function supplier_invoice_is_deletable(?array $invoice): bool
{
    return supplier_invoice_is_editable($invoice);
}

function supplier_invoice_may_delete(?array $invoice): bool
{
    if (!supplier_invoice_is_deletable($invoice)) {
        return false;
    }

    return supplier_invoice_can_delete();
}

function supplier_invoice_is_qbo_stub_mode(): bool
{
    return filter_var(env('QBO_INSERT_STUB', '1'), FILTER_VALIDATE_BOOLEAN);
}

function supplier_invoice_require_read(): void
{
    accounting_require_read();
}

function supplier_invoice_latest_send_back(array $approvalLog): ?array
{
    foreach ($approvalLog as $entry) {
        if (($entry['ApproverResult'] ?? '') === 'Sent Back with Comments') {
            return $entry;
        }
    }

    return null;
}

function supplier_invoice_format_log_comments(mixed $comments): string
{
    require_once __DIR__ . '/admin.php';
    $comments = trim(admin_db_to_string($comments));

    return $comments !== '' ? $comments : 'No comment text was recorded.';
}

function supplier_invoice_normalize_form_date(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }

    require_once __DIR__ . '/admin.php';
    $text = trim(admin_db_to_string($value));
    if ($text === '') {
        return '';
    }

    // SQL Server freestyle strings sometimes use ":AM"/":PM".
    $normalized = preg_replace('/:([AaPp][Mm])\b/', ' $1', $text) ?? $text;

    try {
        return (new DateTimeImmutable($normalized))->format('Y-m-d');
    } catch (Throwable) {
        return strlen($text) >= 10 && preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1
            ? substr($text, 0, 10)
            : '';
    }
}

function supplier_invoice_reference(array $invoice): string
{
    $docNumber = trim((string) ($invoice['DocNumber'] ?? ''));
    if ($docNumber !== '') {
        return $docNumber;
    }

    return 'Invoice #' . (int) $invoice['SupplierInvoiceID'];
}

function supplier_invoice_require_create(): void
{
    accounting_require_create();
}

function supplier_invoice_require_update(): void
{
    accounting_require_update();
}

function supplier_invoice_require_delete(): void
{
    accounting_require_read();
    if (supplier_invoice_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete Accounting records.');
}

function supplier_invoice_status_class(string $status): string
{
    return match ($status) {
        'Draft'                  => 'status-draft',
        'Submitted for Approval' => 'status-submitted',
        'Sent Back for Comment'  => 'status-sent-back',
        'Rejected'               => 'status-cancelled',
        'Posted'                 => 'status-approved',
        'Failed'                 => 'status-cancelled',
        'Voided'                 => 'status-cancelled',
        default                  => 'status-draft',
    };
}

function supplier_invoice_posted_is_reopenable(?array $invoice): bool
{
    if ($invoice === null) {
        return false;
    }

    if ((string) ($invoice['SyncStatus'] ?? '') !== 'Posted') {
        return false;
    }

    // In QBO insert test mode, Posted means approval was recorded but no QuickBooks bill was created.
    if (supplier_invoice_is_qbo_stub_mode()) {
        return true;
    }

    return trim((string) ($invoice['QBO_BillId'] ?? '')) === '';
}

function supplier_invoice_is_editable(?array $invoice): bool
{
    if ($invoice === null) {
        return true;
    }

    if (in_array((string) ($invoice['SyncStatus'] ?? ''), [
        'Draft',
        'Sent Back for Comment',
        'Rejected',
        'Failed',
    ], true)) {
        return true;
    }

    return supplier_invoice_posted_is_reopenable($invoice);
}

function supplier_invoice_is_locked(?array $invoice): bool
{
    if ($invoice === null) {
        return false;
    }

    $status = (string) ($invoice['SyncStatus'] ?? '');

    if ($status === 'Posted' && supplier_invoice_posted_is_reopenable($invoice)) {
        return false;
    }

    return in_array($status, ['Posted', 'Voided', 'Submitted for Approval'], true);
}

function supplier_invoice_list_suppliers(): array
{
    $pdo = db();

    return $pdo->query(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode, QBO_SupplierID
        FROM dbo.Supplier
        WHERE IsActive = 1
        ORDER BY SupplierName
    SQL)->fetchAll();
}

function supplier_invoice_po_options(?int $supplierId = null): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT po.POID, po.PONumber, po.POStatus, s.SupplierName
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
    SQL;
    $params = [];

    if ($supplierId !== null && $supplierId > 0) {
        $sql .= ' WHERE po.SupplierID = :supplier_id';
        $params['supplier_id'] = $supplierId;
    }

    $sql .= ' ORDER BY po.OrderDate DESC, po.POID DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $options[] = [
            'id'    => (int) $row['POID'],
            'label' => $row['PONumber'] . ' · ' . $row['SupplierName'] . ' (' . $row['POStatus'] . ')',
        ];
    }

    return $options;
}

function supplier_invoice_get_supplier(int $supplierId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode, QBO_SupplierID
        FROM dbo.Supplier
        WHERE SupplierID = :id
    SQL);
    $stmt->execute(['id' => $supplierId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function supplier_invoice_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            si.SupplierInvoiceID,
            si.SupplierID,
            si.POID,
            si.DocNumber,
            si.TxnDate,
            si.DueDate,
            si.TotalAmt,
            si.Balance,
            si.SyncStatus,
            si.QBO_BillId,
            s.SupplierName,
            po.PONumber,
            (
                SELECT ISNULL(SUM(p.PaymentAmount), 0)
                FROM dbo.POPayment p
                WHERE p.SupplierInvoiceID = si.SupplierInvoiceID
            ) AS PaidAmt
        FROM dbo.SupplierInvoice si
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        LEFT JOIN dbo.PurchaseOrder po ON po.POID = si.POID
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['supplier_id'])) {
        $sql .= ' AND si.SupplierID = :supplier_id';
        $params['supplier_id'] = (int) $filters['supplier_id'];
    }

    if (!empty($filters['status'])) {
        $sql .= ' AND si.SyncStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['without_po'])) {
        $sql .= ' AND si.POID IS NULL';
    }

    if (!empty($filters['po_id'])) {
        $sql .= ' AND si.POID = :po_id';
        $params['po_id'] = (int) $filters['po_id'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            si.DocNumber LIKE :q OR
            s.SupplierName LIKE :q OR
            po.PONumber LIKE :q OR
            si.Memo LIKE :q OR
            si.PrivateNote LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(SUPPLIER_INVOICE_LIST_SORT_COLUMNS, 'txn_date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(
        SUPPLIER_INVOICE_LIST_SORT_SQL,
        $sortState,
        'txn_date',
        'doc_number'
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function supplier_invoice_get(int $invoiceId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            si.*,
            s.SupplierName,
            s.SupplierCode,
            s.QBO_SupplierID,
            po.PONumber,
            cu.UserName AS CreatedByName,
            mu.UserName AS ModifiedByName
        FROM dbo.SupplierInvoice si
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        LEFT JOIN dbo.PurchaseOrder po ON po.POID = si.POID
        LEFT JOIN dbo.[User] cu ON cu.UserID = si.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = si.ModifiedByUser
        WHERE si.SupplierInvoiceID = :id
    SQL);
    $stmt->execute(['id' => $invoiceId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function supplier_invoice_get_lines(int $invoiceId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.SupplierInvoiceLine
        WHERE SupplierInvoiceID = :id
        ORDER BY LineNumber
    SQL);
    $stmt->execute(['id' => $invoiceId]);

    return $stmt->fetchAll();
}

function supplier_invoice_default_line(): array
{
    return [
        'description'        => '',
        'amount'             => '',
        'detail_type'        => 'AccountBasedExpenseLineDetail',
        'account_ref_value'  => '',
        'account_ref_name'   => '',
        'item_ref_value'     => '',
        'item_ref_name'      => '',
        'qty'                => '',
        'unit_price'         => '',
    ];
}

function supplier_invoice_from_input(array $input): array
{
    return [
        'supplier_id'            => trim($input['supplier_id'] ?? ''),
        'po_id'                  => trim($input['po_id'] ?? ''),
        'doc_number'             => trim($input['doc_number'] ?? ''),
        'txn_date'               => trim($input['txn_date'] ?? ''),
        'due_date'               => trim($input['due_date'] ?? ''),
        'memo'                   => trim($input['memo'] ?? ''),
        'private_note'           => trim($input['private_note'] ?? ''),
        'sync_status'            => trim($input['sync_status'] ?? 'Draft'),
        'global_tax_calculation' => trim($input['global_tax_calculation'] ?? ''),
        'currency_ref_value'     => trim($input['currency_ref_value'] ?? ''),
        'ap_account_ref_value'   => trim($input['ap_account_ref_value'] ?? ''),
        'ap_account_ref_name'    => trim($input['ap_account_ref_name'] ?? ''),
    ];
}

function supplier_invoice_to_form(array $invoice, array $lines = []): array
{
    $formLines = [];
    foreach ($lines as $line) {
        $formLines[] = [
            'description'       => (string) ($line['Description'] ?? ''),
            'amount'            => (string) ($line['Amount'] ?? ''),
            'detail_type'       => (string) ($line['DetailType'] ?? 'AccountBasedExpenseLineDetail'),
            'account_ref_value' => (string) ($line['AccountRefValue'] ?? ''),
            'account_ref_name'  => (string) ($line['AccountRefName'] ?? ''),
            'item_ref_value'    => (string) ($line['ItemRefValue'] ?? ''),
            'item_ref_name'     => (string) ($line['ItemRefName'] ?? ''),
            'qty'               => $line['Qty'] !== null ? (string) $line['Qty'] : '',
            'unit_price'        => $line['UnitPrice'] !== null ? (string) $line['UnitPrice'] : '',
        ];
    }

    if ($formLines === []) {
        $formLines[] = supplier_invoice_default_line();
    }

    return [
        'supplier_id'            => (int) ($invoice['SupplierID'] ?? 0),
        'po_id'                  => !empty($invoice['POID']) ? (int) $invoice['POID'] : '',
        'doc_number'             => (string) ($invoice['DocNumber'] ?? ''),
        'txn_date'               => supplier_invoice_normalize_form_date($invoice['TxnDate'] ?? null) ?: date('Y-m-d'),
        'due_date'               => supplier_invoice_normalize_form_date($invoice['DueDate'] ?? null),
        'memo'                   => (string) ($invoice['Memo'] ?? ''),
        'private_note'           => (string) ($invoice['PrivateNote'] ?? ''),
        'sync_status'            => (string) ($invoice['SyncStatus'] ?? 'Draft'),
        'global_tax_calculation' => (string) ($invoice['GlobalTaxCalculation'] ?? ''),
        'currency_ref_value'     => (string) ($invoice['CurrencyRefValue'] ?? ''),
        'ap_account_ref_value'   => (string) ($invoice['APAccountRefValue'] ?? ''),
        'ap_account_ref_name'    => (string) ($invoice['APAccountRefName'] ?? ''),
        'lines'                  => $formLines,
    ];
}

function supplier_invoice_parse_lines(array $input): array
{
    $lines = [];
    $rows = $input['lines'] ?? [];

    if (!is_array($rows)) {
        return ['error' => 'Add at least one invoice line.'];
    }

    $lineNumber = 1;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $description = trim($row['description'] ?? '');
        $amountRaw = trim($row['amount'] ?? '');
        $detailType = trim($row['detail_type'] ?? 'AccountBasedExpenseLineDetail');

        if ($description === '' && $amountRaw === '') {
            continue;
        }

        if ($amountRaw === '' || (float) $amountRaw < 0) {
            return ['error' => 'Each line needs an amount of zero or greater.'];
        }

        if (!array_key_exists($detailType, SUPPLIER_INVOICE_DETAIL_TYPES)) {
            return ['error' => 'Select a valid line detail type.'];
        }

        $accountRefValue = trim($row['account_ref_value'] ?? '');
        $accountRefName = trim($row['account_ref_name'] ?? '');
        $itemRefValue = trim($row['item_ref_value'] ?? '');
        $itemRefName = trim($row['item_ref_name'] ?? '');

        if ($detailType === 'AccountBasedExpenseLineDetail' && $accountRefValue === '') {
            if (!supplier_invoice_is_qbo_stub_mode()) {
                return ['error' => 'Account-based lines require a QuickBooks account ID.'];
            }
            $accountRefValue = 'STUB-ACCT';
            $accountRefName = $accountRefName !== '' ? $accountRefName : 'Stub expense account';
        }

        if ($detailType === 'ItemBasedExpenseLineDetail' && $itemRefValue === '') {
            if (!supplier_invoice_is_qbo_stub_mode()) {
                return ['error' => 'Item-based lines require a QuickBooks item ID.'];
            }
            $itemRefValue = 'STUB-ITEM';
            $itemRefName = $itemRefName !== '' ? $itemRefName : 'Stub inventory item';
        }

        $lines[] = [
            'line_number'       => $lineNumber++,
            'description'       => $description !== '' ? $description : null,
            'amount'            => round((float) $amountRaw, 2),
            'detail_type'       => $detailType,
            'account_ref_value' => $accountRefValue !== '' ? $accountRefValue : null,
            'account_ref_name'  => $accountRefName !== '' ? $accountRefName : null,
            'item_ref_value'    => $itemRefValue !== '' ? $itemRefValue : null,
            'item_ref_name'     => $itemRefName !== '' ? $itemRefName : null,
            'qty'               => trim($row['qty'] ?? '') !== '' ? (float) $row['qty'] : null,
            'unit_price'        => trim($row['unit_price'] ?? '') !== '' ? (float) $row['unit_price'] : null,
        ];
    }

    if ($lines === []) {
        return ['error' => 'Add at least one invoice line.'];
    }

    return ['lines' => $lines];
}

function supplier_invoice_save(array $input, ?int $invoiceId = null): array
{
    $data = supplier_invoice_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    $supplierId = (int) ($data['supplier_id'] ?? 0);
    if ($supplierId <= 0) {
        return ['ok' => false, 'error' => 'Select a supplier.'];
    }

    $supplier = supplier_invoice_get_supplier($supplierId);
    if ($supplier === null) {
        return ['ok' => false, 'error' => 'Supplier not found.'];
    }

    $vendorRefValue = trim((string) ($supplier['QBO_SupplierID'] ?? ''));
    if ($vendorRefValue === '') {
        if (supplier_invoice_is_qbo_stub_mode()) {
            $code = trim((string) ($supplier['SupplierCode'] ?? ''));
            $vendorRefValue = 'STUB-' . ($code !== '' ? $code : (string) $supplierId);
        } else {
            return ['ok' => false, 'error' => 'Supplier must have a QuickBooks vendor ID before creating an invoice.'];
        }
    }

    if ($data['txn_date'] === '') {
        return ['ok' => false, 'error' => 'Enter an invoice date.'];
    }

    $existing = null;
    if ($invoiceId !== null) {
        $existing = supplier_invoice_get($invoiceId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Invoice not found.'];
        }
    }

    $syncStatus = 'Draft';
    if ($existing !== null) {
        $syncStatus = (string) ($existing['SyncStatus'] ?? 'Draft');
        if (!supplier_invoice_is_editable($existing)) {
            return ['ok' => false, 'error' => 'This invoice cannot be edited in its current status.'];
        }
    }

    $parsedLines = supplier_invoice_parse_lines($input);
    if (isset($parsedLines['error'])) {
        return ['ok' => false, 'error' => $parsedLines['error']];
    }

    $lines = $parsedLines['lines'];
    $totalAmt = round(array_sum(array_column($lines, 'amount')), 2);

    $poId = $data['po_id'] !== '' ? (int) $data['po_id'] : null;
    if ($poId !== null) {
        $po = po_get_order($poId);
        if ($po === null) {
            return ['ok' => false, 'error' => 'Purchase order not found.'];
        }
        if ((int) $po['SupplierID'] !== $supplierId) {
            return ['ok' => false, 'error' => 'Selected PO belongs to a different supplier.'];
        }
    }

    $taxCalc = $data['global_tax_calculation'];
    if ($taxCalc !== '' && !in_array($taxCalc, ['TaxExcluded', 'TaxInclusive', 'NotApplicable'], true)) {
        return ['ok' => false, 'error' => 'Select a valid tax calculation option.'];
    }

    $params = [
        'supplier_id'            => $supplierId,
        'po_id'                  => $poId,
        'doc_number'             => $data['doc_number'] !== '' ? $data['doc_number'] : null,
        'txn_date'               => $data['txn_date'],
        'due_date'               => $data['due_date'] !== '' ? $data['due_date'] : null,
        'vendor_ref_value'       => $vendorRefValue,
        'vendor_ref_name'        => (string) $supplier['SupplierName'],
        'ap_account_ref_value'   => $data['ap_account_ref_value'] !== '' ? $data['ap_account_ref_value'] : null,
        'ap_account_ref_name'    => $data['ap_account_ref_name'] !== '' ? $data['ap_account_ref_name'] : null,
        'currency_ref_value'     => $data['currency_ref_value'] !== '' ? $data['currency_ref_value'] : null,
        'global_tax_calculation' => $taxCalc !== '' ? $taxCalc : null,
        'private_note'           => $data['private_note'] !== '' ? $data['private_note'] : null,
        'memo'                   => $data['memo'] !== '' ? $data['memo'] : null,
        'total_amt'              => $totalAmt,
        'sync_status'            => $syncStatus,
        'actor'                  => $actorId,
    ];

    try {
        $pdo = db();
        $pdo->beginTransaction();

        if ($invoiceId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.SupplierInvoice (
                    SupplierID, POID, DocNumber, TxnDate, DueDate,
                    VendorRefValue, VendorRefName, APAccountRefValue, APAccountRefName,
                    CurrencyRefValue, GlobalTaxCalculation, PrivateNote, Memo,
                    TotalAmt, SyncStatus, CreatedByUser, ModifiedByUser
                )
                OUTPUT INSERTED.SupplierInvoiceID AS inserted_id
                VALUES (
                    :supplier_id, :po_id, :doc_number, :txn_date, :due_date,
                    :vendor_ref_value, :vendor_ref_name, :ap_account_ref_value, :ap_account_ref_name,
                    :currency_ref_value, :global_tax_calculation, :private_note, :memo,
                    :total_amt, :sync_status, :created_by_user, :modified_by_user
                )
            SQL);
            supplier_invoice_bind_params($stmt, supplier_invoice_insert_params($params));
            $stmt->execute();
            $invoiceId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $params['id'] = $invoiceId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.SupplierInvoice
                SET SupplierID = :supplier_id,
                    POID = :po_id,
                    DocNumber = :doc_number,
                    TxnDate = :txn_date,
                    DueDate = :due_date,
                    VendorRefValue = :vendor_ref_value,
                    VendorRefName = :vendor_ref_name,
                    APAccountRefValue = :ap_account_ref_value,
                    APAccountRefName = :ap_account_ref_name,
                    CurrencyRefValue = :currency_ref_value,
                    GlobalTaxCalculation = :global_tax_calculation,
                    PrivateNote = :private_note,
                    Memo = :memo,
                    TotalAmt = :total_amt,
                    SyncStatus = :sync_status,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedByUser = :modified_by_user
                WHERE SupplierInvoiceID = :id
            SQL);
            supplier_invoice_bind_params($stmt, supplier_invoice_update_params($params));
            $stmt->execute();

            $pdo->prepare('DELETE FROM dbo.SupplierInvoiceLine WHERE SupplierInvoiceID = :id')
                ->execute(['id' => $invoiceId]);
        }

        $lineStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.SupplierInvoiceLine (
                SupplierInvoiceID, LineNumber, Description, Amount, DetailType,
                AccountRefValue, AccountRefName, ItemRefValue, ItemRefName, Qty, UnitPrice
            )
            VALUES (
                :invoice_id, :line_number, :description, :amount, :detail_type,
                :account_ref_value, :account_ref_name, :item_ref_value, :item_ref_name, :qty, :unit_price
            )
        SQL);

        foreach ($lines as $line) {
            supplier_invoice_bind_params($lineStmt, [
                'invoice_id'        => $invoiceId,
                'line_number'       => $line['line_number'],
                'description'       => $line['description'],
                'amount'            => $line['amount'],
                'detail_type'       => $line['detail_type'],
                'account_ref_value' => $line['account_ref_value'],
                'account_ref_name'  => $line['account_ref_name'],
                'item_ref_value'    => $line['item_ref_value'],
                'item_ref_name'     => $line['item_ref_name'],
                'qty'               => $line['qty'],
                'unit_price'        => $line['unit_price'],
            ]);
            $lineStmt->execute();
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null, 'id' => $invoiceId];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('supplier_invoice_save failed: ' . $e->getMessage());

        $message = 'Unable to save supplier invoice. Please check your entries and try again.';
        if (supplier_invoice_is_qbo_stub_mode()) {
            $message .= ' (' . $e->getMessage() . ')';
        }

        return ['ok' => false, 'error' => $message];
    }
}

function supplier_invoice_bind_params(PDOStatement $stmt, array $params): void
{
    $intParams = [
        'supplier_id',
        'po_id',
        'created_by_user',
        'modified_by_user',
        'id',
        'invoice_id',
        'line_number',
    ];

    foreach ($params as $key => $value) {
        $name = ':' . $key;
        if ($value === null) {
            $stmt->bindValue($name, null, PDO::PARAM_NULL);
            continue;
        }

        if (in_array($key, $intParams, true)) {
            $stmt->bindValue($name, (int) $value, PDO::PARAM_INT);
            continue;
        }

        $stmt->bindValue($name, $value);
    }
}

function supplier_invoice_shared_params(array $params): array
{
    return [
        'supplier_id'            => $params['supplier_id'],
        'po_id'                  => $params['po_id'],
        'doc_number'             => $params['doc_number'],
        'txn_date'               => $params['txn_date'],
        'due_date'               => $params['due_date'],
        'vendor_ref_value'       => $params['vendor_ref_value'],
        'vendor_ref_name'        => $params['vendor_ref_name'],
        'ap_account_ref_value'   => $params['ap_account_ref_value'],
        'ap_account_ref_name'    => $params['ap_account_ref_name'],
        'currency_ref_value'     => $params['currency_ref_value'],
        'global_tax_calculation' => $params['global_tax_calculation'],
        'private_note'           => $params['private_note'],
        'memo'                   => $params['memo'],
        'total_amt'              => $params['total_amt'],
        'sync_status'            => $params['sync_status'],
    ];
}

function supplier_invoice_insert_params(array $params): array
{
    return array_merge(supplier_invoice_shared_params($params), [
        'created_by_user'  => $params['actor'],
        'modified_by_user' => $params['actor'],
    ]);
}

function supplier_invoice_update_params(array $params): array
{
    return array_merge(supplier_invoice_shared_params($params), [
        'modified_by_user' => $params['actor'],
        'id'               => $params['id'],
    ]);
}

function supplier_invoice_delete(int $invoiceId): array
{
    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    if (!supplier_invoice_may_delete($invoice)) {
        if (!supplier_invoice_is_deletable($invoice)) {
            return ['ok' => false, 'error' => 'This invoice cannot be deleted in its current status.'];
        }

        return ['ok' => false, 'error' => 'You do not have permission to delete supplier invoices.'];
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dbo.POPayment WHERE SupplierInvoiceID = :id');
    $stmt->execute(['id' => $invoiceId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return ['ok' => false, 'error' => 'Delete payments linked to this invoice before deleting it.'];
    }

    $pdo->prepare('DELETE FROM dbo.SupplierInvoice WHERE SupplierInvoiceID = :id')->execute(['id' => $invoiceId]);

    return ['ok' => true, 'error' => null];
}
