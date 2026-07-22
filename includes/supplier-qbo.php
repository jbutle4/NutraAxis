<?php

require_once __DIR__ . '/database.php';

const SUPPLIER_QBO_SYNC_STATUSES = ['NotSynced', 'Synced', 'Error', 'Pending'];

const SUPPLIER_QBO_TEST_VENDOR_NAMES = [
    'smoke vendor llc',
    'test vendor 123',
];

function supplier_qbo_bind_production(): void
{
    require_once __DIR__ . '/quickbooks.php';
    qbo_use_environment(QBO_ENV_PRODUCTION);
}

function supplier_is_skipped_test_vendor(array $supplier): bool
{
    $name = supplier_qbo_normalize_name((string) ($supplier['SupplierName'] ?? ''));
    if (in_array($name, SUPPLIER_QBO_TEST_VENDOR_NAMES, true)) {
        return true;
    }

    if (empty($supplier['IsActive']) && (str_contains($name, 'test') || str_contains($name, 'smoke'))) {
        return true;
    }

    return false;
}

function supplier_qbo_normalize_name(string $name): string
{
    return mb_strtolower(trim($name));
}

function supplier_qbo_sync_status_class(string $status): string
{
    return match ($status) {
        'Synced'    => 'status-received',
        'Pending'   => 'status-submitted',
        'Error'     => 'status-cancelled',
        default     => 'status-draft',
    };
}

function supplier_qbo_sync_status_label(string $status): string
{
    return match ($status) {
        'Synced'    => 'Synced',
        'Pending'   => 'Sync pending',
        'Error'     => 'Sync error',
        default     => 'Not synced',
    };
}

function supplier_prepare_for_qbo_sync(array $supplier): array
{
    if (trim((string) ($supplier['BillAddrLine1'] ?? '')) === '' && trim((string) ($supplier['Address'] ?? '')) !== '') {
        $supplier['BillAddrLine1'] = (string) $supplier['Address'];
    }

    if (trim((string) ($supplier['QBO_DisplayName'] ?? '')) === '') {
        $supplier['QBO_DisplayName'] = (string) ($supplier['SupplierName'] ?? '');
    }

    return $supplier;
}

function supplier_build_qbo_vendor_payload(array $supplier): array
{
    $displayName = trim((string) ($supplier['QBO_DisplayName'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string) ($supplier['SupplierName'] ?? ''));
    }

    $payload = [
        'DisplayName' => $displayName,
        'Active'      => !empty($supplier['IsActive']),
        'Vendor1099'  => !empty($supplier['Vendor1099']),
    ];

    $stringFields = [
        'CompanyName'      => 'CompanyName',
        'PrintOnCheckName' => 'PrintOnCheckName',
        'Title'            => 'Title',
        'GivenName'        => 'GivenName',
        'MiddleName'       => 'MiddleName',
        'FamilyName'       => 'FamilyName',
        'Suffix'           => 'Suffix',
        'TaxIdentifier'    => 'TaxIdentifier',
        'AcctNum'          => 'AcctNum',
    ];
    foreach ($stringFields as $column => $qboField) {
        $value = trim((string) ($supplier[$column] ?? ''));
        if ($value !== '') {
            $payload[$qboField] = $value;
        }
    }

    if (!empty($supplier['ContactEmail'])) {
        $payload['PrimaryEmailAddr'] = ['Address' => (string) $supplier['ContactEmail']];
    }
    if (!empty($supplier['ContactPhone'])) {
        $payload['PrimaryPhone'] = ['FreeFormNumber' => (string) $supplier['ContactPhone']];
    }
    if (!empty($supplier['MobilePhone'])) {
        $payload['Mobile'] = ['FreeFormNumber' => (string) $supplier['MobilePhone']];
    }
    if (!empty($supplier['AlternatePhone'])) {
        $payload['AlternatePhone'] = ['FreeFormNumber' => (string) $supplier['AlternatePhone']];
    }
    if (!empty($supplier['FaxPhone'])) {
        $payload['Fax'] = ['FreeFormNumber' => (string) $supplier['FaxPhone']];
    }
    if (!empty($supplier['WebAddr'])) {
        $payload['WebAddr'] = ['URI' => (string) $supplier['WebAddr']];
    }

    $billAddr = supplier_build_qbo_address_payload($supplier, 'BillAddr');
    if ($billAddr !== null) {
        $payload['BillAddr'] = $billAddr;
    }

    $shipAddr = supplier_build_qbo_address_payload($supplier, 'ShipAddr');
    if ($shipAddr !== null) {
        $payload['ShipAddr'] = $shipAddr;
    }

    if (!empty($supplier['TermRefValue'])) {
        $termRef = ['value' => (string) $supplier['TermRefValue']];
        if (!empty($supplier['TermRefName'])) {
            $termRef['name'] = (string) $supplier['TermRefName'];
        }
        $payload['TermRef'] = $termRef;
    }

    if (!empty($supplier['CurrencyRefValue'])) {
        $currencyRef = ['value' => (string) $supplier['CurrencyRefValue']];
        if (!empty($supplier['CurrencyRefName'])) {
            $currencyRef['name'] = (string) $supplier['CurrencyRefName'];
        }
        $payload['CurrencyRef'] = $currencyRef;
    }

    if (!empty($supplier['QBO_SupplierID'])) {
        $payload['Id'] = (string) $supplier['QBO_SupplierID'];
    }
    if (!empty($supplier['QBO_SyncToken'])) {
        $payload['SyncToken'] = (string) $supplier['QBO_SyncToken'];
    }

    return $payload;
}

function supplier_build_qbo_address_payload(array $supplier, string $prefix): ?array
{
    $line1 = trim((string) ($supplier[$prefix . 'Line1'] ?? ''));
    $line2 = trim((string) ($supplier[$prefix . 'Line2'] ?? ''));
    $city = trim((string) ($supplier[$prefix . 'City'] ?? ''));
    $state = trim((string) ($supplier[$prefix . 'State'] ?? ''));
    $postal = trim((string) ($supplier[$prefix . 'PostalCode'] ?? ''));
    $country = trim((string) ($supplier[$prefix . 'Country'] ?? ''));

    if ($prefix === 'BillAddr' && $line1 === '') {
        $legacy = trim((string) ($supplier['Address'] ?? ''));
        if ($legacy !== '') {
            $line1 = $legacy;
        }
    }

    if ($line1 === '' && $line2 === '' && $city === '' && $state === '' && $postal === '' && $country === '') {
        return null;
    }

    $addr = [];
    if ($line1 !== '') {
        $addr['Line1'] = $line1;
    }
    if ($line2 !== '') {
        $addr['Line2'] = $line2;
    }
    if ($city !== '') {
        $addr['City'] = $city;
    }
    if ($state !== '') {
        $addr['CountrySubDivisionCode'] = $state;
    }
    if ($postal !== '') {
        $addr['PostalCode'] = $postal;
    }
    if ($country !== '') {
        $addr['Country'] = $country;
    }

    return $addr;
}

function supplier_mark_qbo_sync(int $supplierId, string $status, ?string $error = null): void
{
    if (!in_array($status, SUPPLIER_QBO_SYNC_STATUSES, true)) {
        $status = 'Error';
    }

    $pdo = db();
    $errorText = $error !== null && $error !== '' ? $error : null;

    if ($status === 'Synced') {
        $pdo->prepare(<<<SQL
            UPDATE dbo.Supplier
            SET QBO_SyncStatus = :status,
                QBO_SyncError = :error,
                QBO_SyncedAt = SYSUTCDATETIME(),
                ModifiedDate = SYSUTCDATETIME()
            WHERE SupplierID = :id
        SQL)->execute([
            'status' => $status,
            'error'  => $errorText,
            'id'     => $supplierId,
        ]);

        return;
    }

    $pdo->prepare(<<<SQL
        UPDATE dbo.Supplier
        SET QBO_SyncStatus = :status,
            QBO_SyncError = :error,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierID = :id
    SQL)->execute([
        'status' => $status,
        'error'  => $errorText,
        'id'     => $supplierId,
    ]);
}

function supplier_apply_qbo_vendor_response(int $supplierId, array $vendor): void
{
    db()->prepare(<<<SQL
        UPDATE dbo.Supplier
        SET QBO_SupplierID = :qbo_id,
            QBO_SyncToken = :sync_token,
            QBO_DisplayName = :display_name,
            QBO_SyncStatus = N'Synced',
            QBO_SyncError = NULL,
            QBO_SyncedAt = SYSUTCDATETIME(),
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierID = :id
    SQL)->execute([
        'qbo_id'       => (string) ($vendor['Id'] ?? ''),
        'sync_token'   => (string) ($vendor['SyncToken'] ?? ''),
        'display_name' => (string) ($vendor['DisplayName'] ?? ''),
        'id'           => $supplierId,
    ]);
}

function supplier_store_qbo_vendor_identity(int $supplierId, array $vendor): void
{
    $displayName = trim((string) ($vendor['DisplayName'] ?? ''));
    $params = [
        'qbo_id'     => (string) ($vendor['Id'] ?? ''),
        'sync_token' => (string) ($vendor['SyncToken'] ?? ''),
        'id'         => $supplierId,
    ];

    if ($displayName !== '') {
        db()->prepare(<<<SQL
            UPDATE dbo.Supplier
            SET QBO_SupplierID = :qbo_id,
                QBO_SyncToken = :sync_token,
                QBO_DisplayName = :display_name,
                ModifiedDate = SYSUTCDATETIME()
            WHERE SupplierID = :id
        SQL)->execute($params + ['display_name' => $displayName]);

        return;
    }

    db()->prepare(<<<SQL
        UPDATE dbo.Supplier
        SET QBO_SupplierID = :qbo_id,
            QBO_SyncToken = :sync_token,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierID = :id
    SQL)->execute($params);
}

function supplier_clear_qbo_link(int $supplierId): void
{
    db()->prepare(<<<SQL
        UPDATE dbo.Supplier
        SET QBO_SupplierID = NULL,
            QBO_SyncToken = NULL,
            QBO_DisplayName = NULL,
            QBO_SyncStatus = N'NotSynced',
            QBO_SyncError = NULL,
            QBO_SyncedAt = NULL,
            ModifiedDate = SYSUTCDATETIME()
        WHERE SupplierID = :id
    SQL)->execute(['id' => $supplierId]);
}

function supplier_refresh_qbo_sync_token(int $supplierId, ?array $supplier = null): bool
{
    require_once __DIR__ . '/quickbooks.php';

    $supplier = $supplier ?? supplier_get($supplierId);
    if ($supplier === null) {
        return false;
    }

    $freshVendor = qbo_load_fresh_vendor_for_supplier($supplier);
    if (!is_array($freshVendor)) {
        return false;
    }

    supplier_store_qbo_vendor_identity($supplierId, $freshVendor);

    return trim((string) ($freshVendor['SyncToken'] ?? '')) !== '';
}

function supplier_maybe_sync_qbo(int $supplierId): ?string
{
    require_once __DIR__ . '/quickbooks.php';

    supplier_qbo_bind_production();

    if (!qbo_is_connected()) {
        return null;
    }

    $result = qbo_sync_supplier($supplierId);

    return $result['ok'] ? null : ($result['error'] ?? 'QuickBooks sync failed.');
}

function supplier_create_from_qbo_vendor(array $vendor): array
{
    $displayName = trim((string) ($vendor['DisplayName'] ?? ''));
    if ($displayName === '') {
        return ['ok' => false, 'error' => 'QuickBooks vendor has no display name.', 'id' => null];
    }

    $email = trim((string) ($vendor['PrimaryEmailAddr']['Address'] ?? ''));
    $phone = trim((string) ($vendor['PrimaryPhone']['FreeFormNumber'] ?? ''));
    $bill = $vendor['BillAddr'] ?? [];

    $save = supplier_save([
        'supplier_name'  => $displayName,
        'contact_email'  => $email,
        'contact_phone'  => $phone,
        'address'        => trim((string) ($bill['Line1'] ?? '')),
        'is_active'      => !empty($vendor['Active']) ? '1' : '0',
    ], null);

    if (!$save['ok']) {
        return ['ok' => false, 'error' => (string) ($save['error'] ?? 'Unable to create supplier.'), 'id' => null];
    }

    $supplierId = (int) $save['id'];
    supplier_apply_qbo_vendor_response($supplierId, $vendor);

    return ['ok' => true, 'error' => null, 'id' => $supplierId];
}

function supplier_refresh_invoice_vendor_refs(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT si.SupplierInvoiceID, si.SupplierID, si.VendorRefValue, si.VendorRefName,
               s.SupplierName, s.QBO_SupplierID, s.QBO_DisplayName
        FROM dbo.SupplierInvoice si
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        WHERE NULLIF(LTRIM(RTRIM(s.QBO_SupplierID)), '') IS NOT NULL
          AND (
            si.VendorRefValue LIKE 'STUB-%'
            OR NULLIF(LTRIM(RTRIM(si.VendorRefValue)), '') IS NULL
            OR si.VendorRefValue <> s.QBO_SupplierID
          )
    SQL);

    $updated = 0;
    foreach ($stmt->fetchAll() as $row) {
        $vendorId = trim((string) ($row['QBO_SupplierID'] ?? ''));
        if ($vendorId === '') {
            continue;
        }

        $vendorName = trim((string) ($row['QBO_DisplayName'] ?? ''));
        if ($vendorName === '') {
            $vendorName = (string) ($row['SupplierName'] ?? '');
        }

        $pdo->prepare(<<<SQL
            UPDATE dbo.SupplierInvoice
            SET VendorRefValue = :vendor_ref_value,
                VendorRefName = :vendor_ref_name,
                ModifiedDate = SYSUTCDATETIME()
            WHERE SupplierInvoiceID = :id
        SQL)->execute([
            'vendor_ref_value' => $vendorId,
            'vendor_ref_name'  => $vendorName !== '' ? $vendorName : null,
            'id'               => (int) $row['SupplierInvoiceID'],
        ]);
        $updated++;
    }

    return ['ok' => true, 'updated' => $updated];
}
