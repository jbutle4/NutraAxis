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
    'qbo'      => 'QuickBooks',
    'pos'      => 'POs',
];

const SUPPLIER_LIST_SORT_SQL = [
    'code'    => 's.SupplierCode',
    'name'    => 's.SupplierName',
    'type'    => 's.SupplierType',
    'contact' => 's.ContactName',
    'status'  => 's.IsActive',
    'qbo'     => 's.QBO_SyncStatus',
    'pos'     => 'POCount',
];

const SUPPLIER_LIST_SORT_NUMERIC = ['pos'];

const SUPPLIER_QBO_SYNC_STATUSES = ['NotSynced', 'Synced', 'Error', 'Pending'];

const SUPPLIER_CONTRACTOR_TYPES = [
    'Independent Contractor',
    'IT Contractor',
    'Education Contractor',
    'Legal/Rgulatory Contractor',
    'Labor Contactor',
    'Other Contractor',
];

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

function supplier_is_contractor_type(string $supplierType): bool
{
    return in_array($supplierType, SUPPLIER_CONTRACTOR_TYPES, true);
}

function supplier_qbo_sync_status_label(string $status): string
{
    return match ($status) {
        'Synced'    => 'Synced to QuickBooks',
        'Error'     => 'QuickBooks sync error',
        'Pending'   => 'Sync pending',
        default     => 'Not synced to QuickBooks',
    };
}

function supplier_qbo_sync_status_class(string $status): string
{
    return match ($status) {
        'Synced'  => 'status-received',
        'Error'   => 'status-cancelled',
        'Pending' => 'status-submitted',
        default   => 'status-draft',
    };
}

function supplier_qbo_sync_status_short_label(string $status): string
{
    return match ($status) {
        'Synced'  => 'Synced',
        'Error'   => 'Error',
        'Pending' => 'Pending',
        default   => 'Not synced',
    };
}

function supplier_format_address(array $supplier, string $prefix = 'BillAddr'): string
{
    $parts = array_filter([
        trim((string) ($supplier[$prefix . 'Line1'] ?? '')),
        trim((string) ($supplier[$prefix . 'Line2'] ?? '')),
        trim((string) ($supplier[$prefix . 'City'] ?? '')),
        trim((string) ($supplier[$prefix . 'State'] ?? '')),
        trim((string) ($supplier[$prefix . 'PostalCode'] ?? '')),
        trim((string) ($supplier[$prefix . 'Country'] ?? '')),
    ], static fn(string $part): bool => $part !== '');

    if ($parts === [] && $prefix === 'BillAddr') {
        $legacy = trim((string) ($supplier['Address'] ?? ''));

        return $legacy !== '' ? $legacy : '—';
    }

    return $parts !== [] ? implode(', ', $parts) : '—';
}

function supplier_form_payment_terms(): array
{
    if (!is_file(__DIR__ . '/quickbooks.php')) {
        return [];
    }

    require_once __DIR__ . '/quickbooks.php';

    if (!qbo_is_connected()) {
        return [];
    }

    $result = qbo_list_payment_terms();
    if (!$result['ok']) {
        return [];
    }

    return $result['terms'] ?? [];
}

function supplier_resolve_payment_term_name(string $termRefValue, string $termRefName): string
{
    $termRefValue = trim($termRefValue);
    if ($termRefValue === '') {
        return trim($termRefName);
    }

    foreach (supplier_form_payment_terms() as $term) {
        if (($term['id'] ?? '') === $termRefValue) {
            return (string) ($term['name'] ?? $termRefName);
        }
    }

    return trim($termRefName);
}

function supplier_term_ref_value_is_valid(?string $termRefValue): bool
{
    $termRefValue = trim((string) $termRefValue);

    return $termRefValue !== '' && preg_match('/^\d+$/', $termRefValue) === 1;
}

function supplier_normalize_term_ref(array &$data): void
{
    $value = trim((string) ($data['term_ref_value'] ?? ''));
    $name = trim((string) ($data['term_ref_name'] ?? ''));

    if ($value === '' && $name === '') {
        return;
    }

    if (supplier_term_ref_value_is_valid($value)) {
        if ($name === '') {
            $data['term_ref_name'] = supplier_resolve_payment_term_name($value, '');
        }

        return;
    }

    $lookupNames = array_values(array_unique(array_filter([$value, $name])));
    foreach (supplier_form_payment_terms() as $term) {
        $termName = (string) ($term['name'] ?? '');
        foreach ($lookupNames as $lookupName) {
            if ($lookupName !== '' && strcasecmp($termName, $lookupName) === 0) {
                $data['term_ref_value'] = (string) ($term['id'] ?? '');
                $data['term_ref_name'] = $termName;

                return;
            }
        }
    }

    $data['term_ref_name'] = $name !== '' ? $name : $value;
    $data['term_ref_value'] = '';
}

function supplier_prepare_for_qbo_sync(array $supplier): array
{
    $termData = [
        'term_ref_value' => (string) ($supplier['TermRefValue'] ?? ''),
        'term_ref_name'  => (string) ($supplier['TermRefName'] ?? ''),
    ];
    supplier_normalize_term_ref($termData);

    $normalizedValue = $termData['term_ref_value'] !== '' ? $termData['term_ref_value'] : null;
    $normalizedName = $termData['term_ref_name'] !== '' ? $termData['term_ref_name'] : null;
    $currentValue = trim((string) ($supplier['TermRefValue'] ?? ''));
    $currentName = trim((string) ($supplier['TermRefName'] ?? ''));

    if ($normalizedValue !== ($currentValue !== '' ? $currentValue : null)
        || $normalizedName !== ($currentName !== '' ? $currentName : null)) {
        $supplierId = (int) ($supplier['SupplierID'] ?? 0);
        if ($supplierId > 0) {
            $sets = ['ModifiedDate = SYSUTCDATETIME()'];
            $params = ['supplier_id' => $supplierId];

            if ($normalizedValue === null) {
                $sets[] = 'TermRefValue = NULL';
            } else {
                $sets[] = 'TermRefValue = :term_ref_value';
                $params['term_ref_value'] = $normalizedValue;
            }

            if ($normalizedName === null) {
                $sets[] = 'TermRefName = NULL';
            } else {
                $sets[] = 'TermRefName = :term_ref_name';
                $params['term_ref_name'] = $normalizedName;
            }

            db()->prepare(
                'UPDATE dbo.Supplier SET ' . implode(', ', $sets) . ' WHERE SupplierID = :supplier_id'
            )->execute($params);
        }
    }

    $supplier['TermRefValue'] = $normalizedValue;
    $supplier['TermRefName'] = $normalizedName;

    return $supplier;
}

function supplier_to_form(array $supplier): array
{
    $form = [
        'supplier_id'           => (int) $supplier['SupplierID'],
        'supplier_code'         => (string) ($supplier['SupplierCode'] ?? ''),
        'supplier_name'         => (string) $supplier['SupplierName'],
        'address'               => (string) ($supplier['Address'] ?? ''),
        'contact_name'          => (string) ($supplier['ContactName'] ?? ''),
        'contact_email'         => (string) ($supplier['ContactEmail'] ?? ''),
        'contact_phone'         => (string) ($supplier['ContactPhone'] ?? ''),
        'supplier_type'         => (string) ($supplier['SupplierType'] ?? ''),
        'notes'                 => (string) ($supplier['Notes'] ?? ''),
        'is_active'             => !empty($supplier['IsActive']),
        'qbo_supplier_id'       => (string) ($supplier['QBO_SupplierID'] ?? ''),
        'qbo_display_name'      => (string) ($supplier['QBO_DisplayName'] ?? ''),
        'qbo_sync_status'       => (string) ($supplier['QBO_SyncStatus'] ?? 'NotSynced'),
        'qbo_sync_error'        => (string) ($supplier['QBO_SyncError'] ?? ''),
        'qbo_synced_at'         => (string) ($supplier['QBO_SyncedAt'] ?? ''),
        'company_name'          => (string) ($supplier['CompanyName'] ?? ''),
        'print_on_check_name'   => (string) ($supplier['PrintOnCheckName'] ?? ''),
        'title'                 => (string) ($supplier['Title'] ?? ''),
        'given_name'            => (string) ($supplier['GivenName'] ?? ''),
        'middle_name'           => (string) ($supplier['MiddleName'] ?? ''),
        'family_name'           => (string) ($supplier['FamilyName'] ?? ''),
        'suffix'                => (string) ($supplier['Suffix'] ?? ''),
        'bill_addr_line1'       => (string) ($supplier['BillAddrLine1'] ?? ''),
        'bill_addr_line2'       => (string) ($supplier['BillAddrLine2'] ?? ''),
        'bill_addr_city'        => (string) ($supplier['BillAddrCity'] ?? ''),
        'bill_addr_state'       => (string) ($supplier['BillAddrState'] ?? ''),
        'bill_addr_postal_code' => (string) ($supplier['BillAddrPostalCode'] ?? ''),
        'bill_addr_country'     => (string) ($supplier['BillAddrCountry'] ?? 'USA'),
        'ship_addr_line1'       => (string) ($supplier['ShipAddrLine1'] ?? ''),
        'ship_addr_line2'       => (string) ($supplier['ShipAddrLine2'] ?? ''),
        'ship_addr_city'        => (string) ($supplier['ShipAddrCity'] ?? ''),
        'ship_addr_state'       => (string) ($supplier['ShipAddrState'] ?? ''),
        'ship_addr_postal_code' => (string) ($supplier['ShipAddrPostalCode'] ?? ''),
        'ship_addr_country'     => (string) ($supplier['ShipAddrCountry'] ?? ''),
        'tax_identifier'        => (string) ($supplier['TaxIdentifier'] ?? ''),
        'vendor_1099'           => !empty($supplier['Vendor1099']),
        'acct_num'              => (string) ($supplier['AcctNum'] ?? ''),
        'term_ref_value'        => (string) ($supplier['TermRefValue'] ?? ''),
        'term_ref_name'         => (string) ($supplier['TermRefName'] ?? ''),
        'web_addr'              => (string) ($supplier['WebAddr'] ?? ''),
        'mobile_phone'          => (string) ($supplier['MobilePhone'] ?? ''),
        'alternate_phone'       => (string) ($supplier['AlternatePhone'] ?? ''),
        'fax_phone'             => (string) ($supplier['FaxPhone'] ?? ''),
        'currency_ref_value'    => (string) ($supplier['CurrencyRefValue'] ?? ''),
        'currency_ref_name'     => (string) ($supplier['CurrencyRefName'] ?? ''),
    ];

    $termData = [
        'term_ref_value' => $form['term_ref_value'],
        'term_ref_name'  => $form['term_ref_name'],
    ];
    supplier_normalize_term_ref($termData);
    $form['term_ref_value'] = $termData['term_ref_value'];
    $form['term_ref_name'] = $termData['term_ref_name'];

    return $form;
}

function supplier_from_input(array $input): array
{
    $supplierType = trim($input['supplier_type'] ?? '');
    $vendor1099 = array_key_exists('vendor_1099', $input)
        ? (string) ($input['vendor_1099'] ?? '0') === '1'
        : supplier_is_contractor_type($supplierType);

    return [
        'supplier_code'         => trim($input['supplier_code'] ?? ''),
        'supplier_name'         => trim($input['supplier_name'] ?? ''),
        'address'               => trim($input['address'] ?? ''),
        'contact_name'          => trim($input['contact_name'] ?? ''),
        'contact_email'         => trim($input['contact_email'] ?? ''),
        'contact_phone'         => trim($input['contact_phone'] ?? ''),
        'supplier_type'         => $supplierType,
        'notes'                 => trim($input['notes'] ?? ''),
        'is_active'             => (string) ($input['is_active'] ?? '1') === '1',
        'qbo_display_name'      => trim($input['qbo_display_name'] ?? ''),
        'company_name'          => trim($input['company_name'] ?? ''),
        'print_on_check_name'   => trim($input['print_on_check_name'] ?? ''),
        'title'                 => trim($input['title'] ?? ''),
        'given_name'            => trim($input['given_name'] ?? ''),
        'middle_name'           => trim($input['middle_name'] ?? ''),
        'family_name'           => trim($input['family_name'] ?? ''),
        'suffix'                => trim($input['suffix'] ?? ''),
        'bill_addr_line1'       => trim($input['bill_addr_line1'] ?? ''),
        'bill_addr_line2'       => trim($input['bill_addr_line2'] ?? ''),
        'bill_addr_city'        => trim($input['bill_addr_city'] ?? ''),
        'bill_addr_state'       => trim($input['bill_addr_state'] ?? ''),
        'bill_addr_postal_code' => trim($input['bill_addr_postal_code'] ?? ''),
        'bill_addr_country'     => trim($input['bill_addr_country'] ?? '') ?: 'USA',
        'ship_addr_line1'       => trim($input['ship_addr_line1'] ?? ''),
        'ship_addr_line2'       => trim($input['ship_addr_line2'] ?? ''),
        'ship_addr_city'        => trim($input['ship_addr_city'] ?? ''),
        'ship_addr_state'       => trim($input['ship_addr_state'] ?? ''),
        'ship_addr_postal_code' => trim($input['ship_addr_postal_code'] ?? ''),
        'ship_addr_country'     => trim($input['ship_addr_country'] ?? ''),
        'tax_identifier'        => preg_replace('/\D+/', '', trim($input['tax_identifier'] ?? '')),
        'vendor_1099'           => $vendor1099,
        'acct_num'              => trim($input['acct_num'] ?? ''),
        'term_ref_value'        => trim($input['term_ref_value'] ?? ''),
        'term_ref_name'         => supplier_resolve_payment_term_name(
            trim($input['term_ref_value'] ?? ''),
            trim($input['term_ref_name'] ?? '')
        ),
        'web_addr'              => trim($input['web_addr'] ?? ''),
        'mobile_phone'          => trim($input['mobile_phone'] ?? ''),
        'alternate_phone'       => trim($input['alternate_phone'] ?? ''),
        'fax_phone'             => trim($input['fax_phone'] ?? ''),
        'currency_ref_value'    => trim($input['currency_ref_value'] ?? ''),
        'currency_ref_name'     => trim($input['currency_ref_name'] ?? ''),
    ];
}

function supplier_normalize_before_save(array &$data): void
{
    if ($data['bill_addr_line1'] === '' && $data['address'] !== '') {
        $data['bill_addr_line1'] = $data['address'];
    }

    if ($data['print_on_check_name'] === '') {
        $data['print_on_check_name'] = $data['company_name'] !== ''
            ? $data['company_name']
            : $data['supplier_name'];
    }

    if ($data['qbo_display_name'] === '') {
        $data['qbo_display_name'] = $data['supplier_name'];
    }

    if ($data['contact_name'] !== '' && $data['given_name'] === '' && $data['family_name'] === '') {
        $parts = preg_split('/\s+/', $data['contact_name'], 2) ?: [];
        $data['given_name'] = trim((string) ($parts[0] ?? ''));
        $data['family_name'] = trim((string) ($parts[1] ?? ''));
    }

    supplier_normalize_term_ref($data);
}

function supplier_validate_qbo_fields(array $data): ?string
{
    if (str_contains($data['qbo_display_name'], ':')) {
        return 'QuickBooks display name cannot contain a colon.';
    }

    if ($data['vendor_1099'] && $data['tax_identifier'] === '') {
        return 'Tax ID (EIN/SSN) is required for 1099 vendors and contractors.';
    }

    if ($data['tax_identifier'] !== '' && !preg_match('/^\d{9}$/', $data['tax_identifier'])) {
        return 'Tax ID must be a 9-digit EIN or SSN (digits only).';
    }

    if ($data['vendor_1099'] && $data['bill_addr_line1'] === '') {
        return 'Billing address line 1 is required for 1099 vendors and contractors.';
    }

    return null;
}

function supplier_save_params(array $data, ?int $actorId): array
{
    $nullable = static fn(string $value): ?string => $value !== '' ? $value : null;

    return [
        'code'                  => $data['supplier_code'],
        'name'                  => $data['supplier_name'],
        'address'               => $nullable($data['address']),
        'contact'               => $nullable($data['contact_name']),
        'email'                 => $nullable($data['contact_email']),
        'phone'                 => $nullable($data['contact_phone']),
        'type'                  => $nullable($data['supplier_type']),
        'notes'                 => $nullable($data['notes']),
        'active'                => $data['is_active'] ? 1 : 0,
        'qbo_display_name'      => $nullable($data['qbo_display_name']),
        'company_name'          => $nullable($data['company_name']),
        'print_on_check_name'   => $nullable($data['print_on_check_name']),
        'title'                 => $nullable($data['title']),
        'given_name'            => $nullable($data['given_name']),
        'middle_name'           => $nullable($data['middle_name']),
        'family_name'           => $nullable($data['family_name']),
        'suffix'                => $nullable($data['suffix']),
        'bill_addr_line1'       => $nullable($data['bill_addr_line1']),
        'bill_addr_line2'       => $nullable($data['bill_addr_line2']),
        'bill_addr_city'        => $nullable($data['bill_addr_city']),
        'bill_addr_state'       => $nullable($data['bill_addr_state']),
        'bill_addr_postal_code' => $nullable($data['bill_addr_postal_code']),
        'bill_addr_country'     => $nullable($data['bill_addr_country']),
        'ship_addr_line1'       => $nullable($data['ship_addr_line1']),
        'ship_addr_line2'       => $nullable($data['ship_addr_line2']),
        'ship_addr_city'        => $nullable($data['ship_addr_city']),
        'ship_addr_state'       => $nullable($data['ship_addr_state']),
        'ship_addr_postal_code' => $nullable($data['ship_addr_postal_code']),
        'ship_addr_country'     => $nullable($data['ship_addr_country']),
        'tax_identifier'        => $nullable($data['tax_identifier']),
        'vendor_1099'           => $data['vendor_1099'] ? 1 : 0,
        'acct_num'              => $nullable($data['acct_num']),
        'term_ref_value'        => $nullable($data['term_ref_value']),
        'term_ref_name'         => $nullable($data['term_ref_name']),
        'web_addr'              => $nullable($data['web_addr']),
        'mobile_phone'          => $nullable($data['mobile_phone']),
        'alternate_phone'       => $nullable($data['alternate_phone']),
        'fax_phone'             => $nullable($data['fax_phone']),
        'currency_ref_value'    => $nullable($data['currency_ref_value']),
        'currency_ref_name'     => $nullable($data['currency_ref_name']),
        'actor'                 => $actorId,
    ];
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
        'CompanyName'       => 'CompanyName',
        'PrintOnCheckName'  => 'PrintOnCheckName',
        'Title'             => 'Title',
        'GivenName'         => 'GivenName',
        'MiddleName'        => 'MiddleName',
        'FamilyName'        => 'FamilyName',
        'Suffix'            => 'Suffix',
        'TaxIdentifier'     => 'TaxIdentifier',
        'AcctNum'           => 'AcctNum',
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

    if (supplier_term_ref_value_is_valid((string) ($supplier['TermRefValue'] ?? ''))) {
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
        if ($errorText === null) {
            $pdo->prepare(<<<SQL
                UPDATE dbo.Supplier
                SET QBO_SyncStatus = :status,
                    QBO_SyncError = NULL,
                    QBO_SyncedAt = SYSUTCDATETIME(),
                    ModifiedDate = SYSUTCDATETIME()
                WHERE SupplierID = :id
            SQL)->execute([
                'status' => $status,
                'id'     => $supplierId,
            ]);

            return;
        }

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

    if ($errorText === null) {
        $pdo->prepare(<<<SQL
            UPDATE dbo.Supplier
            SET QBO_SyncStatus = :status,
                QBO_SyncError = NULL,
                ModifiedDate = SYSUTCDATETIME()
            WHERE SupplierID = :id
        SQL)->execute([
            'status' => $status,
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
    $pdo = db();
    $pdo->prepare(<<<SQL
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
    $params = [
        'qbo_id'     => (string) ($vendor['Id'] ?? ''),
        'sync_token' => (string) ($vendor['SyncToken'] ?? ''),
        'id'         => $supplierId,
    ];
    $displayName = trim((string) ($vendor['DisplayName'] ?? ''));

    // Avoid repeating :display_name in SQL — PDO ODBC rejects duplicate named params.
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

function supplier_refresh_qbo_sync_token(int $supplierId, ?array $supplier = null): bool
{
    if (!is_file(__DIR__ . '/quickbooks.php')) {
        return false;
    }

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
            s.QBO_SyncStatus,
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

    supplier_normalize_before_save($data);

    $qboError = supplier_validate_qbo_fields($data);
    if ($qboError !== null) {
        return ['ok' => false, 'error' => $qboError];
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

    $params = supplier_save_params($data, $actorId);
    $columns = <<<SQL
                    SupplierCode, SupplierName, Address,
                    ContactName, ContactEmail, ContactPhone,
                    SupplierType, Notes,
                    QBO_DisplayName, CompanyName, PrintOnCheckName,
                    Title, GivenName, MiddleName, FamilyName, Suffix,
                    BillAddrLine1, BillAddrLine2, BillAddrCity, BillAddrState, BillAddrPostalCode, BillAddrCountry,
                    ShipAddrLine1, ShipAddrLine2, ShipAddrCity, ShipAddrState, ShipAddrPostalCode, ShipAddrCountry,
                    TaxIdentifier, Vendor1099, AcctNum,
                    TermRefValue, TermRefName, WebAddr,
                    MobilePhone, AlternatePhone, FaxPhone,
                    CurrencyRefValue, CurrencyRefName,
                    IsActive, ModifiedbyUser
    SQL;
    $values = <<<SQL
                    :code, :name, :address,
                    :contact, :email, :phone,
                    :type, :notes,
                    :qbo_display_name, :company_name, :print_on_check_name,
                    :title, :given_name, :middle_name, :family_name, :suffix,
                    :bill_addr_line1, :bill_addr_line2, :bill_addr_city, :bill_addr_state, :bill_addr_postal_code, :bill_addr_country,
                    :ship_addr_line1, :ship_addr_line2, :ship_addr_city, :ship_addr_state, :ship_addr_postal_code, :ship_addr_country,
                    :tax_identifier, :vendor_1099, :acct_num,
                    :term_ref_value, :term_ref_name, :web_addr,
                    :mobile_phone, :alternate_phone, :fax_phone,
                    :currency_ref_value, :currency_ref_name,
                    :active, :actor
    SQL;

    try {
        if ($supplierId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.Supplier (
                    {$columns}
                )
                OUTPUT INSERTED.SupplierID AS inserted_id
                VALUES (
                    {$values}
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
                    QBO_DisplayName = :qbo_display_name,
                    CompanyName = :company_name,
                    PrintOnCheckName = :print_on_check_name,
                    Title = :title,
                    GivenName = :given_name,
                    MiddleName = :middle_name,
                    FamilyName = :family_name,
                    Suffix = :suffix,
                    BillAddrLine1 = :bill_addr_line1,
                    BillAddrLine2 = :bill_addr_line2,
                    BillAddrCity = :bill_addr_city,
                    BillAddrState = :bill_addr_state,
                    BillAddrPostalCode = :bill_addr_postal_code,
                    BillAddrCountry = :bill_addr_country,
                    ShipAddrLine1 = :ship_addr_line1,
                    ShipAddrLine2 = :ship_addr_line2,
                    ShipAddrCity = :ship_addr_city,
                    ShipAddrState = :ship_addr_state,
                    ShipAddrPostalCode = :ship_addr_postal_code,
                    ShipAddrCountry = :ship_addr_country,
                    TaxIdentifier = :tax_identifier,
                    Vendor1099 = :vendor_1099,
                    AcctNum = :acct_num,
                    TermRefValue = :term_ref_value,
                    TermRefName = :term_ref_name,
                    WebAddr = :web_addr,
                    MobilePhone = :mobile_phone,
                    AlternatePhone = :alternate_phone,
                    FaxPhone = :fax_phone,
                    CurrencyRefValue = :currency_ref_value,
                    CurrencyRefName = :currency_ref_name,
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

function supplier_maybe_sync_qbo(int $supplierId): ?string
{
    if (!is_file(__DIR__ . '/quickbooks.php')) {
        return null;
    }

    require_once __DIR__ . '/quickbooks.php';

    if (!qbo_is_connected()) {
        return null;
    }

    $result = qbo_sync_supplier($supplierId);

    return $result['ok'] ? null : ($result['error'] ?? 'QuickBooks sync failed.');
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
