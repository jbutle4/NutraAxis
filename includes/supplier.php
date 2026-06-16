<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin.php';

const SUPPLIER_PERMISSION_COLUMN = 'POManagement';

const SUPPLIER_TYPES = [
    'CMO',
    'Marketing',
    'IT Supplier',
    'Independent Contractor',
    'IT Contractor',
    'Education Contractor',
    'Legal/Rgulatory Contractor',
    'Labor Contactor',
    'Other Contractor',
    'Other Supplier',
    'Transportation',
];

const SUPPLIER_LIST_SORT_COLUMNS = [
    'code'     => 'Code',
    'name'     => 'Supplier name',
    'type'     => 'Type',
    'contact'  => 'Contact',
    'status'   => 'Status',
    'pos'      => 'POs',
];

const SUPPLIER_LIST_SORT_SQL = [
    'code'    => 's.SupplierCode',
    'name'    => 's.SupplierName',
    'type'    => 's.SupplierType',
    'contact' => 's.ContactName',
    'status'  => 's.IsActive',
    'pos'     => 'POCount',
];

const SUPPLIER_LIST_SORT_NUMERIC = ['pos'];

function supplier_permission_value(): ?string
{
    return auth_permission_value(SUPPLIER_PERMISSION_COLUMN);
}

function supplier_can_read(): bool
{
    return auth_can_read(SUPPLIER_PERMISSION_COLUMN);
}

function supplier_can_create(): bool
{
    return auth_can_create(SUPPLIER_PERMISSION_COLUMN);
}

function supplier_can_update(): bool
{
    return auth_can_update(SUPPLIER_PERMISSION_COLUMN);
}

function supplier_can_delete(): bool
{
    return auth_can_delete(SUPPLIER_PERMISSION_COLUMN);
}

function supplier_require_read(): void
{
    auth_require_login();
    if (supplier_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Supplier Management.');
}

function supplier_require_create(): void
{
    supplier_require_read();
    if (supplier_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create suppliers.');
}

function supplier_require_update(): void
{
    supplier_require_read();
    if (supplier_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update suppliers.');
}

function supplier_require_delete(): void
{
    supplier_require_read();
    if (supplier_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to deactivate suppliers.');
}

function supplier_status_class(bool $isActive): string
{
    return $isActive ? 'status-received' : 'status-cancelled';
}

function supplier_status_label(bool $isActive): string
{
    return $isActive ? 'Active' : 'Inactive';
}

function supplier_generate_code(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT SupplierCode FROM dbo.Supplier WHERE SupplierCode LIKE 'SUP-%' ORDER BY SupplierID DESC");
    $last = $stmt->fetchColumn();
    $seq = 1;

    if ($last !== false && preg_match('/SUP-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return sprintf('SUP-%03d', $seq);
}

function supplier_to_form(array $supplier): array
{
    return [
        'supplier_id'    => (int) $supplier['SupplierID'],
        'supplier_code'  => (string) ($supplier['SupplierCode'] ?? ''),
        'supplier_name'  => (string) $supplier['SupplierName'],
        'address'        => (string) ($supplier['Address'] ?? ''),
        'contact_name'   => (string) ($supplier['ContactName'] ?? ''),
        'contact_email'  => (string) ($supplier['ContactEmail'] ?? ''),
        'contact_phone'  => (string) ($supplier['ContactPhone'] ?? ''),
        'supplier_type'  => (string) ($supplier['SupplierType'] ?? ''),
        'notes'          => (string) ($supplier['Notes'] ?? ''),
        'is_active'      => !empty($supplier['IsActive']),
    ];
}

function supplier_from_input(array $input): array
{
    return [
        'supplier_code'  => trim($input['supplier_code'] ?? ''),
        'supplier_name'  => trim($input['supplier_name'] ?? ''),
        'address'        => trim($input['address'] ?? ''),
        'contact_name'   => trim($input['contact_name'] ?? ''),
        'contact_email'  => trim($input['contact_email'] ?? ''),
        'contact_phone'  => trim($input['contact_phone'] ?? ''),
        'supplier_type'  => trim($input['supplier_type'] ?? ''),
        'notes'          => trim($input['notes'] ?? ''),
        'is_active'      => (string) ($input['is_active'] ?? '1') === '1',
    ];
}

function supplier_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            s.SupplierID,
            s.SupplierCode,
            s.SupplierName,
            s.Address,
            s.ContactName,
            s.ContactEmail,
            s.ContactPhone,
            s.SupplierType,
            s.IsActive,
            (SELECT COUNT(*) FROM dbo.PurchaseOrder po WHERE po.SupplierID = s.SupplierID) AS POCount
        FROM dbo.Supplier s
        WHERE 1 = 1
    SQL;
    $params = [];

    $status = $filters['status'] ?? 'active';
    if ($status === 'active') {
        $sql .= ' AND s.IsActive = 1';
    } elseif ($status === 'inactive') {
        $sql .= ' AND s.IsActive = 0';
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            s.SupplierName LIKE :q OR
            s.SupplierCode LIKE :q OR
            s.ContactName LIKE :q OR
            s.ContactEmail LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(SUPPLIER_LIST_SORT_COLUMNS, 'name', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(SUPPLIER_LIST_SORT_SQL, $sortState, 'name', 'code');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function supplier_get(int $supplierId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            s.*,
            mu.UserName AS ModifiedByName,
            (SELECT COUNT(*) FROM dbo.PurchaseOrder po WHERE po.SupplierID = s.SupplierID) AS POCount
        FROM dbo.Supplier s
        LEFT JOIN dbo.[User] mu ON mu.UserID = s.ModifiedbyUser
        WHERE s.SupplierID = :id
    SQL);
    $stmt->execute(['id' => $supplierId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function supplier_list_purchase_orders(int $supplierId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            po.POID,
            po.PONumber,
            po.POStatus,
            po.OrderDate,
            po.ExpectedDeliveryDate,
            po.Subtotal,
            u.UserName AS CreatedByName
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.[User] u ON u.UserID = po.CreatedByUser
        WHERE po.SupplierID = :supplier_id
        ORDER BY po.OrderDate DESC, po.POID DESC
    SQL);
    $stmt->execute(['supplier_id' => $supplierId]);

    return $stmt->fetchAll();
}

function supplier_save(array $input, ?int $supplierId = null): array
{
    $data = supplier_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    if ($data['supplier_name'] === '') {
        return ['ok' => false, 'error' => 'Supplier name is required.'];
    }

    if ($data['contact_email'] !== '' && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid contact email address.'];
    }

    if ($data['supplier_type'] !== '' && !in_array($data['supplier_type'], SUPPLIER_TYPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid supplier type.'];
    }

    $pdo = db();

    if ($supplierId === null) {
        $data['supplier_code'] = $data['supplier_code'] !== '' ? $data['supplier_code'] : supplier_generate_code($pdo);
    } elseif ($data['supplier_code'] === '') {
        return ['ok' => false, 'error' => 'Supplier code is required.'];
    }

    $dup = $pdo->prepare('SELECT SupplierID FROM dbo.Supplier WHERE SupplierCode = :code AND SupplierID <> :id');
    $dup->execute(['code' => $data['supplier_code'], 'id' => $supplierId ?? 0]);
    if ($dup->fetch() !== false) {
        return ['ok' => false, 'error' => 'That supplier code is already in use.'];
    }

    $params = [
        'code'    => $data['supplier_code'],
        'name'    => $data['supplier_name'],
        'address' => $data['address'] !== '' ? $data['address'] : null,
        'contact' => $data['contact_name'] !== '' ? $data['contact_name'] : null,
        'email'   => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        'phone'   => $data['contact_phone'] !== '' ? $data['contact_phone'] : null,
        'type'    => $data['supplier_type'] !== '' ? $data['supplier_type'] : null,
        'notes'   => $data['notes'] !== '' ? $data['notes'] : null,
        'active'  => $data['is_active'] ? 1 : 0,
        'actor'   => $actorId,
    ];

    try {
        if ($supplierId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.Supplier (
                    SupplierCode, SupplierName, Address,
                    ContactName, ContactEmail, ContactPhone,
                    SupplierType, Notes,
                    IsActive, ModifiedbyUser
                )
                OUTPUT INSERTED.SupplierID AS inserted_id
                VALUES (
                    :code, :name, :address,
                    :contact, :email, :phone,
                    :type, :notes,
                    :active, :actor
                )
            SQL);
            $stmt->execute($params);
            $supplierId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (supplier_get($supplierId) === null) {
                return ['ok' => false, 'error' => 'Supplier not found.'];
            }

            $params['id'] = $supplierId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.Supplier
                SET SupplierCode = :code,
                    SupplierName = :name,
                    Address = :address,
                    ContactName = :contact,
                    ContactEmail = :email,
                    ContactPhone = :phone,
                    SupplierType = :type,
                    Notes = :notes,
                    IsActive = :active,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE SupplierID = :id
            SQL);
            $stmt->execute($params);
        }

        return ['ok' => true, 'error' => null, 'id' => $supplierId];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save supplier. Please check your entries and try again.'];
    }
}

function supplier_set_active(int $supplierId, bool $active): array
{
    if (supplier_get($supplierId) === null) {
        return ['ok' => false, 'error' => 'Supplier not found.'];
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.Supplier
        SET IsActive = :active,
            ModifiedDate = SYSUTCDATETIME(),
            ModifiedbyUser = :actor
        WHERE SupplierID = :id
    SQL)->execute([
        'active' => $active ? 1 : 0,
        'actor'  => auth_user()['UserID'] ?? null,
        'id'     => $supplierId,
    ]);

    return ['ok' => true, 'error' => null];
}
