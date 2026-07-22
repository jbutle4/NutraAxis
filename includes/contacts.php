<?php

require_once __DIR__ . '/auth.php';

const CONTACTS_PERMISSION_COLUMN = 'ContactsList';

const CONTACT_TYPES = [
    'supplier'   => 'Supplier',
    'contractor' => 'Contractor',
    'education'  => 'Education',
    'marketing'  => 'Marketing',
    'other'      => 'Other',
];

const CONTACT_US_STATES = [
    'AL' => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'DC' => 'District of Columbia',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming',
];

const CONTACTS_LIST_SORT_COLUMNS = [
    'last_name'  => 'Last Name',
    'first_name' => 'First Name',
    'company'    => 'Company',
    'type'       => 'Type',
    'phone'      => 'Phone',
    'email'      => 'Email',
    'supplier'   => 'Related Supplier',
];

const CONTACTS_LIST_SORT_SQL = [
    'last_name'  => 'c.ContactLastName',
    'first_name' => 'c.ContactFirstName',
    'company'    => 'c.ContactCompany',
    'type'       => 'c.ContactType',
    'phone'      => 'c.ContactPhone',
    'email'      => 'c.ContactEmail',
    'supplier'   => 's.SupplierName',
];

function contacts_permission_value(): ?string
{
    return auth_permission_value(CONTACTS_PERMISSION_COLUMN);
}

function contacts_can_read(): bool
{
    return auth_can_read(CONTACTS_PERMISSION_COLUMN);
}

function contacts_can_create(): bool
{
    return auth_can_create(CONTACTS_PERMISSION_COLUMN);
}

function contacts_can_update(): bool
{
    return auth_can_update(CONTACTS_PERMISSION_COLUMN);
}

function contacts_can_delete(): bool
{
    return auth_can_delete(CONTACTS_PERMISSION_COLUMN);
}

function contacts_require_read(): void
{
    auth_require_login();
    if (contacts_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the Contacts List.');
}

function contacts_require_create(): void
{
    contacts_require_read();
    if (contacts_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create contacts.');
}

function contacts_require_update(): void
{
    contacts_require_read();
    if (contacts_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update contacts.');
}

function contacts_require_delete(): void
{
    contacts_require_read();
    if (contacts_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete contacts.');
}

function contacts_type_label(?string $type): string
{
    if ($type === null || $type === '') {
        return '—';
    }

    return CONTACT_TYPES[$type] ?? ucfirst($type);
}

function contacts_display_name(array $contact): string
{
    $first = trim((string) ($contact['ContactFirstName'] ?? ''));
    $last = trim((string) ($contact['ContactLastName'] ?? ''));
    $name = trim($first . ' ' . $last);

    if ($name !== '') {
        return $name;
    }

    $company = trim((string) ($contact['ContactCompany'] ?? ''));

    return $company !== '' ? $company : 'Contact #' . (int) ($contact['ContactID'] ?? 0);
}

function contacts_state_label(?string $state): string
{
    if ($state === null || $state === '') {
        return '—';
    }

    $code = strtoupper($state);

    return CONTACT_US_STATES[$code] ?? $code;
}

function contacts_supplier_options(?int $selectedId = null): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode
        FROM dbo.Supplier
        WHERE IsActive = 1
        ORDER BY SupplierName
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $label = (string) $row['SupplierName'];
        if (!empty($row['SupplierCode'])) {
            $label .= ' (' . $row['SupplierCode'] . ')';
        }
        $options[] = [
            'id'       => (int) $row['SupplierID'],
            'label'    => $label,
            'selected' => $selectedId !== null && (int) $row['SupplierID'] === $selectedId,
        ];
    }

    return $options;
}

function contacts_to_form(array $contact): array
{
    return [
        'contact_id'               => (int) $contact['ContactID'],
        'contact_first_name'       => (string) ($contact['ContactFirstName'] ?? ''),
        'contact_last_name'        => (string) ($contact['ContactLastName'] ?? ''),
        'contact_company'          => (string) ($contact['ContactCompany'] ?? ''),
        'related_supplier_company' => (string) ($contact['RelatedSupplierCompany'] ?? ''),
        'contact_type'             => (string) ($contact['ContactType'] ?? ''),
        'contact_phone'            => (string) ($contact['ContactPhone'] ?? ''),
        'contact_email'            => (string) ($contact['ContactEmail'] ?? ''),
        'contact_address'          => (string) ($contact['ContactAddress'] ?? ''),
        'contact_city'             => (string) ($contact['ContactCity'] ?? ''),
        'contact_state'            => (string) ($contact['ContactState'] ?? ''),
        'contact_zip'              => (string) ($contact['ContactZip'] ?? ''),
        'contact_notes'            => (string) ($contact['ContactNotes'] ?? ''),
    ];
}

function contacts_from_input(array $input): array
{
    return [
        'contact_first_name'       => trim($input['contact_first_name'] ?? ''),
        'contact_last_name'        => trim($input['contact_last_name'] ?? ''),
        'contact_company'          => trim($input['contact_company'] ?? ''),
        'related_supplier_company' => trim($input['related_supplier_company'] ?? ''),
        'contact_type'             => trim($input['contact_type'] ?? ''),
        'contact_phone'            => trim($input['contact_phone'] ?? ''),
        'contact_email'            => trim($input['contact_email'] ?? ''),
        'contact_address'          => trim($input['contact_address'] ?? ''),
        'contact_city'             => trim($input['contact_city'] ?? ''),
        'contact_state'            => trim($input['contact_state'] ?? ''),
        'contact_zip'              => trim($input['contact_zip'] ?? ''),
        'contact_notes'            => trim($input['contact_notes'] ?? ''),
    ];
}

function contacts_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            c.ContactID,
            c.ContactFirstName,
            c.ContactLastName,
            c.ContactCompany,
            c.RelatedSupplierCompany,
            c.ContactType,
            c.ContactPhone,
            c.ContactEmail,
            c.ContactCity,
            c.ContactState,
            s.SupplierName AS RelatedSupplierName
        FROM dbo.ContactsList c
        LEFT JOIN dbo.Supplier s ON s.SupplierID = c.RelatedSupplierCompany
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['type'])) {
        $sql .= ' AND c.ContactType = :type';
        $params['type'] = $filters['type'];
    }

    if (!empty($filters['q'])) {
        [$likeSql, $likeParams] = db_like_or([
            'c.ContactFirstName',
            'c.ContactLastName',
            'c.ContactCompany',
            'c.ContactEmail',
            'c.ContactPhone',
            's.SupplierName'
        ], (string) $filters['q']);
        $sql .= ' AND ' . $likeSql;
        $params = array_merge($params, $likeParams);
    }

    $sortState = table_sort_state(CONTACTS_LIST_SORT_COLUMNS, 'last_name', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(CONTACTS_LIST_SORT_SQL, $sortState, 'last_name', 'last_name');

    if ($params === []) {
        return $pdo->query($sql)->fetchAll();
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function contacts_list_supplier_contacts(): array
{
    $pdo = db();

    return $pdo->query(<<<SQL
        SELECT
            SupplierName,
            ContactName,
            ContactEmail,
            ContactPhone
        FROM dbo.Supplier
        WHERE IsActive = 1
        ORDER BY SupplierName
    SQL)->fetchAll();
}

function contacts_get(int $contactId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            c.*,
            s.SupplierName AS RelatedSupplierName,
            u.UserName AS ModifiedByName
        FROM dbo.ContactsList c
        LEFT JOIN dbo.Supplier s ON s.SupplierID = c.RelatedSupplierCompany
        LEFT JOIN dbo.[User] u ON u.UserID = c.ModifiedbyUser
        WHERE c.ContactID = :id
    SQL);
    $stmt->execute(['id' => $contactId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function contacts_save(array $input, ?int $contactId = null): array
{
    $data = contacts_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    if ($data['contact_first_name'] === '' && $data['contact_last_name'] === '' && $data['contact_company'] === '') {
        return ['ok' => false, 'error' => 'Enter a contact name or company.'];
    }

    if ($data['contact_type'] !== '' && !array_key_exists($data['contact_type'], CONTACT_TYPES)) {
        return ['ok' => false, 'error' => 'Select a valid contact type.'];
    }

    $supplierId = $data['related_supplier_company'] !== '' ? (int) $data['related_supplier_company'] : null;
    if ($supplierId !== null && $supplierId <= 0) {
        $supplierId = null;
    }

    if ($data['contact_state'] !== '' && !array_key_exists(strtoupper($data['contact_state']), CONTACT_US_STATES)) {
        return ['ok' => false, 'error' => 'Select a valid state.'];
    }

    $params = [
        'first_name'  => $data['contact_first_name'] !== '' ? $data['contact_first_name'] : null,
        'last_name'   => $data['contact_last_name'] !== '' ? $data['contact_last_name'] : null,
        'company'     => $data['contact_company'] !== '' ? $data['contact_company'] : null,
        'supplier_id' => $supplierId,
        'type'        => $data['contact_type'] !== '' ? $data['contact_type'] : null,
        'phone'       => $data['contact_phone'] !== '' ? $data['contact_phone'] : null,
        'email'       => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        'address'     => $data['contact_address'] !== '' ? $data['contact_address'] : null,
        'city'        => $data['contact_city'] !== '' ? $data['contact_city'] : null,
        'state'       => $data['contact_state'] !== '' ? strtoupper($data['contact_state']) : null,
        'zip'         => $data['contact_zip'] !== '' ? $data['contact_zip'] : null,
        'notes'       => $data['contact_notes'] !== '' ? $data['contact_notes'] : null,
        'actor'       => $actorId,
    ];

    $pdo = db();

    try {
        if ($contactId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.ContactsList (
                    ContactFirstName, ContactLastName, ContactCompany, RelatedSupplierCompany,
                    ContactType, ContactPhone, ContactEmail, ContactAddress, ContactCity,
                    ContactState, ContactZip, ContactNotes, ModifiedbyUser
                )
                OUTPUT INSERTED.ContactID AS inserted_id
                VALUES (
                    :first_name, :last_name, :company, :supplier_id,
                    :type, :phone, :email, :address, :city,
                    :state, :zip, :notes, :actor
                )
            SQL);
            $stmt->execute($params);
            $contactId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $existing = contacts_get($contactId);
            if ($existing === null) {
                return ['ok' => false, 'error' => 'Contact not found.'];
            }

            $params['id'] = $contactId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.ContactsList
                SET ContactFirstName = :first_name,
                    ContactLastName = :last_name,
                    ContactCompany = :company,
                    RelatedSupplierCompany = :supplier_id,
                    ContactType = :type,
                    ContactPhone = :phone,
                    ContactEmail = :email,
                    ContactAddress = :address,
                    ContactCity = :city,
                    ContactState = :state,
                    ContactZip = :zip,
                    ContactNotes = :notes,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE ContactID = :id
            SQL);
            $stmt->execute($params);
        }

        return ['ok' => true, 'error' => null, 'id' => $contactId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unable to save contact. Please try again.'];
    }
}

function contacts_delete(int $contactId): array
{
    $existing = contacts_get($contactId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Contact not found.'];
    }

    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM dbo.ContactsList WHERE ContactID = :id');
    $stmt->execute(['id' => $contactId]);

    return ['ok' => true, 'error' => null];
}
