<?php

require_once __DIR__ . '/auth.php';

const PO_PERMISSION_COLUMN = 'POManagement';
const PO_APPROVAL_PERMISSION_COLUMN = 'POApproval';

const PO_STATUSES = [
    'Created',
    'Submitted for Approval',
    'Rejected',
    'Approved',
    'Sent Back for Comment',
    'Viewed by Approver',
    'Submitted to Accounting for Payment',
    'Paid',
];

const PO_EDITABLE_STATUSES = ['Created', 'Sent Back for Comment', 'Rejected'];

const PO_POST_APPROVAL_STATUSES = [
    'Approved',
    'Submitted to Accounting for Payment',
    'Paid',
];

const PO_LIST_SORT_COLUMNS = [
    'po_number'          => 'PO Number',
    'supplier'           => 'Supplier',
    'status'             => 'Status',
    'order_date'         => 'Order Date',
    'expected_delivery'  => 'Expected Delivery',
    'total'              => 'Total',
    'created_by'         => 'Created By',
];

const PO_LIST_SORT_SQL = [
    'po_number'         => 'po.PONumber',
    'supplier'          => 's.SupplierName',
    'status'            => 'po.POStatus',
    'order_date'        => 'po.OrderDate',
    'expected_delivery' => 'po.ExpectedDeliveryDate',
    'total'             => 'po.Subtotal',
    'created_by'        => 'u.UserName',
];

const PO_LIST_SORT_NUMERIC = ['total'];

function po_can_edit_order(array $order): bool
{
    if (!po_can_update()) {
        return false;
    }

    $status = $order['POStatus'] ?? '';

    return in_array($status, PO_EDITABLE_STATUSES, true)
        || in_array($status, PO_POST_APPROVAL_STATUSES, true);
}

function po_is_post_approval_edit(array $order): bool
{
    return in_array($order['POStatus'] ?? '', PO_POST_APPROVAL_STATUSES, true);
}

function po_amounts_differ(float $a, float $b): bool
{
    return abs(round($a, 2) - round($b, 2)) > 0.001;
}

function po_requires_reapproval(array $order): bool
{
    return !empty($order['RequiresReapproval']);
}

const PO_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const PO_DEFAULT_BUYER = [
    'buyer_name'             => 'NutraSync / Wells Specialty Pharmacy',
    'buyer_address'          => '3420 Fairlane Farms Road, Suite 200, Wellington, Florida 33414',
    'buyer_contact_name'     => 'Joe Butler',
    'buyer_contact_email'    => 'nutrateam@nfcllc.com',
    'buyer_contact_phone'    => '754-210-1723',
    'payment_terms'          => 'Net 30 Days',
    'delivery_terms'         => '',
    'reference_documents'    => 'Purchase is subject to all terms and conditions contained in the Quality Agreement and Master Supply Agreement between Buyer and Supplier.',
    'special_instructions'   => '',
];

function po_default_header(): array
{
    return array_merge(PO_DEFAULT_BUYER, [
        'po_number'              => '',
        'order_date'             => date('Y-m-d'),
        'expected_delivery_date' => '',
        'supplier_id'            => '',
        'supplier_name'          => '',
        'supplier_address'       => '',
        'delivery_address'       => '',
        'shipping_handling'      => '',
        'notes'                  => '',
        'po_status'              => 'Created',
    ]);
}

function po_resolve_supplier_id(string $name, string $address = ''): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT SupplierID FROM dbo.Supplier
        WHERE IsActive = 1 AND SupplierName = :name
    SQL);
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch();
    if ($row !== false) {
        return (int) $row['SupplierID'];
    }

    $stmt = $pdo->prepare('SELECT SupplierID, SupplierName FROM dbo.Supplier WHERE IsActive = 1');
    $stmt->execute();
    foreach ($stmt->fetchAll() as $supplier) {
        if (stripos($supplier['SupplierName'], $name) !== false || stripos($name, $supplier['SupplierName']) !== false) {
            return (int) $supplier['SupplierID'];
        }
    }

    return null;
}

function po_permission_value(): ?string
{
    return auth_permission_value(PO_PERMISSION_COLUMN);
}

function po_approval_permission_value(): ?string
{
    return auth_permission_value(PO_APPROVAL_PERMISSION_COLUMN);
}

function po_can_read_approval_queue(): bool
{
    return permission_can_read(po_approval_permission_value());
}

function po_can_access_po_pages(): bool
{
    return permission_can_read(po_permission_value()) || po_can_read_approval_queue();
}

function po_can_take_approval_action(): bool
{
    return permission_can_update(po_approval_permission_value());
}

function po_require_read(): void
{
    auth_require_login();
    if (po_can_access_po_pages()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view this module.');
}

function po_require_create(): void
{
    po_require_read();
    if (permission_can_create(po_permission_value())) {
        return;
    }
    auth_render_access_denied('You do not have permission to create purchase orders.');
}

function po_require_update(): void
{
    po_require_read();
    if (permission_can_update(po_permission_value())) {
        return;
    }
    auth_render_access_denied('You do not have permission to update purchase orders.');
}

function po_require_delete(): void
{
    po_require_read();
    if (permission_can_delete(po_permission_value())) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete purchase orders.');
}

function po_can_create(): bool
{
    return permission_can_create(po_permission_value());
}

function po_can_update(): bool
{
    return permission_can_update(po_permission_value());
}

function po_can_delete(): bool
{
    return permission_can_delete(po_permission_value());
}

function po_can_add_notes_and_attachments(array $order): bool
{
    $status = $order['POStatus'] ?? '';

    return po_can_update()
        && (
            in_array($status, PO_EDITABLE_STATUSES, true)
            || in_array($status, PO_POST_APPROVAL_STATUSES, true)
        );
}

function po_status_class(string $status): string
{
    return match ($status) {
        'Created'                              => 'status-draft',
        'Submitted for Approval'               => 'status-submitted',
        'Rejected'                             => 'status-cancelled',
        'Approved'                             => 'status-approved',
        'Sent Back for Comment'                => 'status-submitted',
        'Viewed by Approver'                   => 'status-received',
        'Submitted to Accounting for Payment'  => 'status-received',
        'Paid'                                 => 'status-approved',
        default                                => 'status-draft',
    };
}

function po_format_money($value): string
{
    return '$' . number_format((float) $value, 2);
}

function po_format_qty($value): string
{
    if ($value === null || $value === '') {
        return '0';
    }

    return number_format((float) $value, 0, '.', '');
}

function po_format_exception_message(Throwable $e, string $action = 'save this purchase order'): string
{
    $raw = trim($e->getMessage());
    error_log("PO error while trying to {$action}: {$raw}");

    $detail = preg_replace('/^SQLSTATE\[[^\]]+\]:\s*(?:General error:\s*)?/i', '', $raw) ?? $raw;
    $detail = preg_replace('/\s*\[\d+\]\s*\(severity \d+\)\s*\[.*$/s', '', $detail) ?? $detail;
    $detail = trim($detail);

    if ($detail === '') {
        $detail = 'An unexpected database error occurred.';
    }

    $hints = [];
    if (stripos($raw, 'ANSI_NULLS') !== false || stripos($raw, 'SET options') !== false) {
        $hints[] = 'This is usually fixed by database migration 013 (LineTotal column).';
    }
    if (stripos($raw, 'Invalid column name') !== false) {
        $hints[] = 'The database schema may be out of date — migrations 011–013 may need to be applied.';
    }
    if (stripos($raw, 'UQ_PurchaseOrder_PONumber') !== false || stripos($raw, 'duplicate key') !== false) {
        $hints[] = 'Use a different PO Number or remove the existing purchase order with that number.';
    }
    if (stripos($raw, 'FK_PurchaseOrder_CreatedByUser') !== false) {
        $hints[] = 'Sign out, sign back in, and try again.';
    }
    if (stripos($raw, 'FOREIGN KEY') !== false && stripos($raw, 'Supplier') !== false) {
        $hints[] = 'Confirm the supplier exists and is marked active.';
    }
    if (stripos($raw, 'POAttachment') !== false || stripos($raw, 'Invalid object name') !== false) {
        $hints[] = 'The POAttachment table may be missing — run migration 011.';
    }
    if (stripos($raw, 'POReceipt') !== false || stripos($raw, 'PORDetail') !== false || stripos($raw, 'JazzASN') !== false) {
        $hints[] = 'Run PO Receiving migrations 041 and 042 on the database.';
    }
    if (stripos($raw, 'COUNT field incorrect') !== false) {
        $hints[] = 'This is often an ODBC parameter-binding issue — retry after the latest site update is deployed.';
    }

    $message = "Unable to {$action}: {$detail}";
    if ($hints !== []) {
        $message .= ' Suggestion: ' . implode(' ', $hints);
    }

    return $message;
}

function po_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

function po_format_date_input(?string $value): string
{
    $normalized = po_normalize_date_for_db($value);

    return $normalized ?? '';
}

function po_normalize_date_for_db($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    $formats = ['Y-m-d', 'm/d/Y', 'n/j/Y', 'm-d-Y'];
    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d');
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d', $ts);
    }

    return null;
}

function po_list_suppliers(): array
{
    $pdo = db();

    return $pdo->query(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode, Address, ContactName, ContactEmail, ContactPhone
        FROM dbo.Supplier
        WHERE IsActive = 1
        ORDER BY SupplierName
    SQL)->fetchAll();
}

function po_list_orders(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            po.POID,
            po.PONumber,
            po.POStatus,
            po.OrderDate,
            po.ExpectedDeliveryDate,
            po.Subtotal,
            s.SupplierName,
            u.UserName AS CreatedByName
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        INNER JOIN dbo.[User] u ON u.UserID = po.CreatedByUser
    SQL;

    $params = [];
    $statusFilter = $filters['status'] ?? null;
    if ($statusFilter !== null && $statusFilter !== '' && in_array($statusFilter, PO_STATUSES, true)) {
        $sql .= ' WHERE po.POStatus = :status';
        $params['status'] = $statusFilter;
    }

    $sortState = table_sort_state(PO_LIST_SORT_COLUMNS, 'order_date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PO_LIST_SORT_SQL, $sortState, 'order_date', 'po_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function po_get_order(int $poId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            po.*,
            s.SupplierName,
            s.SupplierCode,
            s.SupplierType,
            s.Address AS SupplierTableAddress,
            s.ContactName,
            s.ContactEmail,
            s.ContactPhone,
            cu.UserName AS CreatedByName,
            mu.UserName AS ModifiedByName
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        INNER JOIN dbo.[User] cu ON cu.UserID = po.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = po.ModifiedbyUser
        WHERE po.POID = :id
    SQL);
    $stmt->execute(['id' => $poId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_get_lines(int $poId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT POLineID, POID, LineNumber, ItemSKU, ItemDescription, QuoteNumber, Quantity, UnitPrice, LineTotal, ExpirationDate, QuantityReceived
        FROM dbo.POLineItem
        WHERE POID = :id
        ORDER BY LineNumber
    SQL);
    $stmt->execute(['id' => $poId]);

    return $stmt->fetchAll();
}

function po_save_notes(int $poId, string $notes): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if (!po_can_add_notes_and_attachments($order)) {
        return ['ok' => false, 'error' => 'Notes cannot be updated for this purchase order.'];
    }

    $notes = trim($notes);
    $actorId = auth_user()['UserID'] ?? null;

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.PurchaseOrder
            SET Notes = :notes,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedbyUser = :modified_by
            WHERE POID = :id
        SQL);
        $stmt->execute([
            'notes'       => $notes !== '' ? $notes : null,
            'modified_by' => $actorId,
            'id'          => $poId,
        ]);

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => po_format_exception_message($e, 'save notes')];
    }
}

function po_parse_lines(array $input): array
{
    $lines = [];
    $rows = $input['lines'] ?? [];

    if (!is_array($rows)) {
        return [];
    }

    $lineNumber = 1;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $description = trim($row['description'] ?? '');
        $quantity = (float) ($row['quantity'] ?? 0);
        $unitPrice = (float) ($row['unit_price'] ?? 0);
        $quoteNumber = trim($row['quote_number'] ?? '');
        $expirationDate = trim($row['expiration_date'] ?? '');

        if ($description === '' && $quantity <= 0) {
            continue;
        }

        if ($description === '' || $quantity <= 0) {
            return ['error' => 'Each line item needs a product title and quantity greater than zero.'];
        }

        $lines[] = [
            'line_number'      => $lineNumber++,
            'sku'              => trim($row['sku'] ?? ''),
            'quote_number'     => $quoteNumber,
            'description'      => $description,
            'quantity'         => $quantity,
            'unit_price'       => max(0, $unitPrice),
            'expiration_date'  => po_normalize_date_for_db($expirationDate),
        ];
    }

    if ($lines === []) {
        return ['error' => 'Add at least one line item to the purchase order.'];
    }

    return ['lines' => $lines];
}

/**
 * SKU choices for PO line items, sourced from SKUMaster.
 */
function po_sku_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT SKUCode, ProductName
        FROM dbo.SKUMaster
        ORDER BY SKUCode
    SQL);

    return $stmt->fetchAll();
}

function po_generate_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query(<<<SQL
        SELECT ISNULL(MAX(POID), 0) + 1 AS NextId
        FROM dbo.PurchaseOrder
    SQL);
    $nextId = (int) $stmt->fetchColumn();

    return sprintf('PO-%s-%06d', $year, $nextId);
}

function po_header_from_input(array $input): array
{
    return [
        'po_number'              => trim($input['po_number'] ?? ''),
        'buyer_name'             => trim($input['buyer_name'] ?? ''),
        'buyer_address'          => trim($input['buyer_address'] ?? ''),
        'buyer_contact_name'     => trim($input['buyer_contact_name'] ?? ''),
        'buyer_contact_email'    => trim($input['buyer_contact_email'] ?? ''),
        'buyer_contact_phone'    => trim($input['buyer_contact_phone'] ?? ''),
        'supplier_address'       => trim($input['supplier_address'] ?? ''),
        'delivery_address'       => trim($input['delivery_address'] ?? ''),
        'payment_terms'          => trim($input['payment_terms'] ?? ''),
        'delivery_terms'         => trim($input['delivery_terms'] ?? ''),
        'reference_documents'    => trim($input['reference_documents'] ?? ''),
        'shipping_handling'      => trim($input['shipping_handling'] ?? ''),
        'special_instructions'   => trim($input['special_instructions'] ?? ''),
        'order_date'             => trim($input['order_date'] ?? ''),
        'expected_delivery_date' => trim($input['expected_delivery_date'] ?? ''),
        'notes'                  => trim($input['notes'] ?? ''),
        'po_status'              => trim($input['po_status'] ?? 'Created'),
    ];
}

function po_save_order(array $input, ?int $poId = null): array
{
    $supplierId = (int) ($input['supplier_id'] ?? 0);
    $header = po_header_from_input($input);
    $orderDate = $header['order_date'];
    $expectedDate = $header['expected_delivery_date'];
    $notes = $header['notes'];
    $status = $header['po_status'];
    $actorId = auth_user()['UserID'] ?? null;
    $customPoNumber = $header['po_number'];
    $shipping = $header['shipping_handling'] !== '' ? (float) $header['shipping_handling'] : null;

    if ($actorId === null || $actorId <= 0) {
        return ['ok' => false, 'error' => 'Your session has expired. Sign in again and retry the import.'];
    }

    if ($supplierId <= 0) {
        return ['ok' => false, 'error' => 'Select a supplier.'];
    }

    if ($orderDate === '') {
        return ['ok' => false, 'error' => 'Order date is required.'];
    }

    if (!in_array($status, PO_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid purchase order status.'];
    }

    $parsedLines = po_parse_lines($input);
    if (isset($parsedLines['error'])) {
        return ['ok' => false, 'error' => $parsedLines['error']];
    }

    $lines = $parsedLines['lines'];
    $subtotal = array_reduce(
        $lines,
        fn(float $sum, array $line): float => $sum + ($line['quantity'] * $line['unit_price']),
        0.0
    );
    $totalDue = $subtotal + ($shipping ?? 0);

    $pdo = db();

    $supplierCheck = $pdo->prepare('SELECT SupplierID, Address FROM dbo.Supplier WHERE SupplierID = :id AND IsActive = 1');
    $supplierCheck->execute(['id' => $supplierId]);
    $supplierRow = $supplierCheck->fetch();
    if ($supplierRow === false) {
        return ['ok' => false, 'error' => 'Select a valid active supplier.'];
    }

    $supplierAddress = $header['supplier_address'] !== '' ? $header['supplier_address'] : ($supplierRow['Address'] ?? null);
    $isInsert = $poId === null;
    $beforeOrder = null;
    $beforeLines = null;
    $requiresReapproval = false;

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if ($isInsert) {
            $poNumber = $customPoNumber !== '' ? $customPoNumber : po_generate_number($pdo);
            $dup = $pdo->prepare('SELECT POID FROM dbo.PurchaseOrder WHERE PONumber = :number');
            $dup->execute(['number' => $poNumber]);
            if ($dup->fetch() !== false) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'PO Number already exists.'];
            }

            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.PurchaseOrder (
                    PONumber, SupplierID, POStatus, OrderDate, ExpectedDeliveryDate,
                    Notes, Subtotal, ShippingHandling, TotalDue,
                    BuyerName, BuyerAddress, BuyerContactName, BuyerContactEmail, BuyerContactPhone,
                    SupplierAddress, DeliveryAddress, PaymentTerms, DeliveryTerms, ReferenceDocuments, SpecialInstructions,
                    CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.POID AS inserted_id
                VALUES (
                    :number, :supplier, :status, :order_date, :expected_date,
                    :notes, :subtotal, :shipping, :total_due,
                    :buyer_name, :buyer_address, :buyer_contact_name, :buyer_contact_email, :buyer_contact_phone,
                    :supplier_address, :delivery_address, :payment_terms, :delivery_terms, :reference_documents, :special_instructions,
                    :created_by, :modified_by
                )
            SQL);
            $stmt->execute(po_save_bind_header($poNumber, $supplierId, $status, $orderDate, $expectedDate, $notes, $subtotal, $shipping, $totalDue, $header, $supplierAddress, $actorId));
            $poId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $existing = po_get_order($poId);
            if ($existing === null) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Purchase order not found.'];
            }

            if (!po_can_edit_order($existing)) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'This purchase order cannot be edited in its current status.'];
            }

            $beforeOrder = $existing;
            $beforeLines = po_get_lines($poId);

            if ($customPoNumber !== '' && $customPoNumber !== $existing['PONumber']) {
                $dup = $pdo->prepare('SELECT POID FROM dbo.PurchaseOrder WHERE PONumber = :number AND POID <> :id');
                $dup->execute(['number' => $customPoNumber, 'id' => $poId]);
                if ($dup->fetch() !== false) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'PO Number already exists.'];
                }
            }

            $poNumber = $customPoNumber !== '' ? $customPoNumber : $existing['PONumber'];
            if (po_is_post_approval_edit($existing)) {
                $status = $existing['POStatus'];
            }

            $requiresReapproval = 0;
            if (po_is_post_approval_edit($existing)) {
                $approvedBaseline = isset($existing['ApprovedTotalDue'])
                    ? (float) $existing['ApprovedTotalDue']
                    : (float) $existing['TotalDue'];
                $requiresReapproval = po_amounts_differ($totalDue, $approvedBaseline);
            $requiresReapprovalFlag = $requiresReapproval ? 1 : 0;
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.PurchaseOrder
                SET PONumber = :number,
                    SupplierID = :supplier,
                    POStatus = :status,
                    OrderDate = :order_date,
                    ExpectedDeliveryDate = :expected_date,
                    Notes = :notes,
                    Subtotal = :subtotal,
                    ShippingHandling = :shipping,
                    TotalDue = :total_due,
                    RequiresReapproval = :requires_reapproval,
                    BuyerName = :buyer_name,
                    BuyerAddress = :buyer_address,
                    BuyerContactName = :buyer_contact_name,
                    BuyerContactEmail = :buyer_contact_email,
                    BuyerContactPhone = :buyer_contact_phone,
                    SupplierAddress = :supplier_address,
                    DeliveryAddress = :delivery_address,
                    PaymentTerms = :payment_terms,
                    DeliveryTerms = :delivery_terms,
                    ReferenceDocuments = :reference_documents,
                    SpecialInstructions = :special_instructions,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :modified_by
                WHERE POID = :id
            SQL);
            $bind = po_save_bind_header($poNumber, $supplierId, $status, $orderDate, $expectedDate, $notes, $subtotal, $shipping, $totalDue, $header, $supplierAddress, $actorId);
            unset($bind['created_by']);
            $bind['id'] = $poId;
            $bind['requires_reapproval'] = $requiresReapprovalFlag;
            $stmt->execute($bind);

            $pdo->prepare('DELETE FROM dbo.POLineItem WHERE POID = :id')->execute(['id' => $poId]);
        }

        $lineStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.POLineItem (POID, LineNumber, ItemSKU, ItemDescription, QuoteNumber, Quantity, UnitPrice, ExpirationDate)
            VALUES (:po, :line, :sku, :description, :quote, :qty, :price, :exp_date)
        SQL);

        foreach ($lines as $line) {
            po_execute_line_insert($lineStmt, $poId, $line);
        }

        $pdo->commit();

        require_once __DIR__ . '/audit.php';
        audit_log_po_save($poId, $isInsert, $beforeOrder, $beforeLines);

        return [
            'ok'                   => true,
            'error'                => null,
            'id'                   => $poId,
            'requires_reapproval'  => $requiresReapproval,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => po_format_exception_message($e, 'save this purchase order')];
    }
}

function po_save_bind_header(
    string $poNumber,
    int $supplierId,
    string $status,
    string $orderDate,
    string $expectedDate,
    string $notes,
    float $subtotal,
    ?float $shipping,
    float $totalDue,
    array $header,
    ?string $supplierAddress,
    ?int $actorId
): array {
    return [
        'number'               => $poNumber,
        'supplier'             => $supplierId,
        'status'               => $status,
        'order_date'           => $orderDate,
        'expected_date'        => $expectedDate !== '' ? $expectedDate : null,
        'notes'                => $notes !== '' ? $notes : null,
        'subtotal'             => $subtotal,
        'shipping'             => $shipping,
        'total_due'            => $totalDue,
        'buyer_name'           => $header['buyer_name'] !== '' ? $header['buyer_name'] : null,
        'buyer_address'        => $header['buyer_address'] !== '' ? $header['buyer_address'] : null,
        'buyer_contact_name'   => $header['buyer_contact_name'] !== '' ? $header['buyer_contact_name'] : null,
        'buyer_contact_email'  => $header['buyer_contact_email'] !== '' ? $header['buyer_contact_email'] : null,
        'buyer_contact_phone'  => $header['buyer_contact_phone'] !== '' ? $header['buyer_contact_phone'] : null,
        'supplier_address'     => $supplierAddress,
        'delivery_address'     => $header['delivery_address'] !== '' ? $header['delivery_address'] : null,
        'payment_terms'        => $header['payment_terms'] !== '' ? $header['payment_terms'] : null,
        'delivery_terms'       => $header['delivery_terms'] !== '' ? $header['delivery_terms'] : null,
        'reference_documents'  => $header['reference_documents'] !== '' ? $header['reference_documents'] : null,
        'special_instructions' => $header['special_instructions'] !== '' ? $header['special_instructions'] : null,
        'created_by'           => $actorId,
        'modified_by'          => $actorId,
    ];
}

function po_execute_line_insert(PDOStatement $stmt, int $poId, array $line): void
{
    $sku = $line['sku'] !== '' ? $line['sku'] : null;
    $quote = ($line['quote_number'] ?? '') !== '' ? $line['quote_number'] : null;
    $expDate = $line['expiration_date'] ?? null;

    $stmt->execute([
        'po'          => $poId,
        'line'        => $line['line_number'],
        'description' => $line['description'],
        'qty'         => $line['quantity'],
        'price'       => $line['unit_price'],
        'sku'         => $sku,
        'quote'       => $quote,
        'exp_date'    => $expDate,
    ]);
}

function po_delete_confirm_message(string $poNumber): string
{
    return 'Delete ' . $poNumber . ' and all related line items, attachments, receipts, payments, supplier invoices, delivery appointments, and approval history? This cannot be undone.';
}

function po_delete_approval_for_entity(PDO $pdo, string $entityType, int $entityId): void
{
    $params = ['entity_type' => $entityType, 'entity_id' => $entityId];

    $pdo->prepare('DELETE FROM dbo.ApprovalLog WHERE EntityType = :entity_type AND EntityID = :entity_id')
        ->execute($params);
    $pdo->prepare('DELETE FROM dbo.ApprovalToken WHERE EntityType = :entity_type AND EntityID = :entity_id')
        ->execute($params);
    $pdo->prepare('DELETE FROM dbo.ApprovalLog WHERE SecondaryEntityType = :entity_type AND SecondaryEntityID = :entity_id')
        ->execute($params);
    $pdo->prepare('DELETE FROM dbo.ApprovalToken WHERE SecondaryEntityType = :entity_type AND SecondaryEntityID = :entity_id')
        ->execute($params);
}

function po_delete_payments_for_scope(PDO $pdo, string $column, int $id): void
{
    $allowedColumns = ['POID', 'SupplierInvoiceID'];
    if (!in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException('Unsupported payment scope column.');
    }

    $stmt = $pdo->prepare("SELECT PaymentID FROM dbo.POPayment WHERE {$column} = :id");
    $stmt->execute(['id' => $id]);
    $paymentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($paymentIds as $paymentId) {
        po_delete_approval_for_entity($pdo, 'POPayment', $paymentId);
    }

    $pdo->prepare("DELETE FROM dbo.POPayment WHERE {$column} = :id")->execute(['id' => $id]);
    $pdo->prepare("DELETE FROM dbo.POPaymentAttachment WHERE {$column} = :id")->execute(['id' => $id]);
}

function po_delete_supplier_invoice_cascade(PDO $pdo, int $invoiceId): void
{
    po_delete_approval_for_entity($pdo, 'SupplierInvoice', $invoiceId);
    po_delete_payments_for_scope($pdo, 'SupplierInvoiceID', $invoiceId);
    $pdo->prepare('DELETE FROM dbo.SupplierInvoice WHERE SupplierInvoiceID = :id')
        ->execute(['id' => $invoiceId]);
}

function po_delete_related_records(PDO $pdo, int $poId): void
{
    $stmt = $pdo->prepare('SELECT SupplierInvoiceID FROM dbo.SupplierInvoice WHERE POID = :po_id');
    $stmt->execute(['po_id' => $poId]);
    foreach (array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)) as $invoiceId) {
        po_delete_supplier_invoice_cascade($pdo, $invoiceId);
    }

    $pdo->prepare(
        'DELETE FROM dbo.DeliveryAppointmentScheduling WHERE POReceiptID IN (SELECT PORID FROM dbo.POReceipt WHERE POID = :po_id)'
    )->execute(['po_id' => $poId]);
    $pdo->prepare('DELETE FROM dbo.DeliveryAppointmentScheduling WHERE POID = :po_id')
        ->execute(['po_id' => $poId]);

    po_delete_approval_for_entity($pdo, 'PurchaseOrder', $poId);
    $pdo->prepare('DELETE FROM dbo.POEventLog WHERE POID = :po_id')->execute(['po_id' => $poId]);

    po_delete_payments_for_scope($pdo, 'POID', $poId);
    $pdo->prepare('DELETE FROM dbo.POReceipt WHERE POID = :po_id')->execute(['po_id' => $poId]);
    $pdo->prepare('DELETE FROM dbo.POProductionStatus WHERE POID = :po_id')->execute(['po_id' => $poId]);
}

function po_delete_order(int $poId): array
{
    if (!po_can_delete()) {
        return ['ok' => false, 'error' => 'You do not have permission to delete purchase orders.'];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    $lines = po_get_lines($poId);
    $pdo = db();

    try {
        $pdo->beginTransaction();
        po_delete_related_records($pdo, $poId);
        $pdo->prepare('DELETE FROM dbo.PurchaseOrder WHERE POID = :id')->execute(['id' => $poId]);
        $pdo->commit();

        require_once __DIR__ . '/audit.php';
        audit_log_po_delete($order, $lines);

        return ['ok' => true, 'error' => null, 'po_number' => (string) $order['PONumber']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => po_format_exception_message($e, 'delete this purchase order')];
    }
}
