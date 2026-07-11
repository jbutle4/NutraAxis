<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/provider-signup-crypto.php';
require_once __DIR__ . '/provider-signup-npi.php';
require_once __DIR__ . '/provider-signup-mail.php';

const PROVIDER_SIGNUP_PERMISSION_COLUMN = 'ProviderAccountReview';

const PROVIDER_SIGNUP_STATUS_DRAFT = 'Draft';
const PROVIDER_SIGNUP_STATUS_SUBMITTED = 'Submitted';
const PROVIDER_SIGNUP_STATUS_RETURNED = 'Returned';
const PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION = 'Pending Validation';
const PROVIDER_SIGNUP_STATUS_APPROVED = 'Approved';
const PROVIDER_SIGNUP_STATUS_PROVISIONED = 'Provisioned';
const PROVIDER_SIGNUP_STATUS_REJECTED = 'Rejected';

const PROVIDER_SIGNUP_PROVIDER_EDITABLE_STATUSES = [
    PROVIDER_SIGNUP_STATUS_DRAFT,
    PROVIDER_SIGNUP_STATUS_RETURNED,
];

const PROVIDER_SIGNUP_OPS_EDITABLE_STATUSES = [
    PROVIDER_SIGNUP_STATUS_DRAFT,
    PROVIDER_SIGNUP_STATUS_RETURNED,
    PROVIDER_SIGNUP_STATUS_SUBMITTED,
    PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION,
    PROVIDER_SIGNUP_STATUS_APPROVED,
];

const PROVIDER_SIGNUP_STATUSES = [
    PROVIDER_SIGNUP_STATUS_DRAFT,
    PROVIDER_SIGNUP_STATUS_SUBMITTED,
    PROVIDER_SIGNUP_STATUS_RETURNED,
    PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION,
    PROVIDER_SIGNUP_STATUS_APPROVED,
    PROVIDER_SIGNUP_STATUS_PROVISIONED,
    PROVIDER_SIGNUP_STATUS_REJECTED,
];

const PROVIDER_SIGNUP_TAX_ID_TYPES = ['SSN', 'EIN'];
const PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES = ['Checking', 'Savings'];
const PROVIDER_SIGNUP_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const PROVIDER_SIGNUP_LIST_SORT_COLUMNS = [
    'id'        => 'ID',
    'practice'  => 'Practice',
    'provider'  => 'Provider Email',
    'status'    => 'Status',
    'submitted' => 'Submitted',
];

const PROVIDER_SIGNUP_LIST_SORT_SQL = [
    'id'        => 'a.ApplicationID',
    'practice'  => 'a.CompanyName',
    'provider'  => 'a.ProviderEmail',
    'status'    => 'a.Status',
    'submitted' => 'a.SubmittedAt',
];

const PROVIDER_SIGNUP_US_STATES = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
    'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
    'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
    'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
    'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
    'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    'DC' => 'District of Columbia',
];

function provider_signup_permission_value(): ?string
{
    return auth_permission_value(PROVIDER_SIGNUP_PERMISSION_COLUMN);
}

function provider_signup_can_read(): bool
{
    return permission_can_read(provider_signup_permission_value());
}

function provider_signup_can_update(): bool
{
    return permission_can_update(provider_signup_permission_value());
}

function provider_signup_require_read(): void
{
    auth_require_login();
    if (provider_signup_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view provider signup applications.');
}

function provider_signup_require_update(): void
{
    provider_signup_require_read();
    if (provider_signup_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update provider signup applications.');
}

function provider_signup_generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function provider_signup_default_form(): array
{
    return [
        'provider_email'      => '',
        'company_name'        => '',
        'company_legal_name'  => '',
        'company_email'       => '',
        'company_phone'       => '',
        'street_address'      => '',
        'city'                => '',
        'state_code'          => '',
        'postal_code'         => '',
        'admin_first_name'    => '',
        'admin_last_name'     => '',
        'admin_email'         => '',
        'admin_phone'         => '',
        'npi_number'          => '',
        'tax_id_type'         => '',
        'tax_id'              => '',
        'ach_routing_number'  => '',
        'ach_account_number'  => '',
        'ach_account_type'    => 'Checking',
    ];
}

function provider_signup_form_from_post(array $post): array
{
    $form = provider_signup_default_form();
    foreach (array_keys($form) as $key) {
        if (array_key_exists($key, $post)) {
            $form[$key] = trim((string) $post[$key]);
        }
    }

    return $form;
}

function provider_signup_form_from_row(array $row): array
{
    return [
        'provider_email'      => (string) ($row['ProviderEmail'] ?? ''),
        'company_name'        => (string) ($row['CompanyName'] ?? ''),
        'company_legal_name'  => (string) ($row['CompanyLegalName'] ?? ''),
        'company_email'       => (string) ($row['CompanyEmail'] ?? ''),
        'company_phone'       => (string) ($row['CompanyPhone'] ?? ''),
        'street_address'      => (string) ($row['StreetAddress'] ?? ''),
        'city'                => (string) ($row['City'] ?? ''),
        'state_code'          => (string) ($row['StateCode'] ?? ''),
        'postal_code'         => (string) ($row['PostalCode'] ?? ''),
        'admin_first_name'    => (string) ($row['AdminFirstName'] ?? ''),
        'admin_last_name'     => (string) ($row['AdminLastName'] ?? ''),
        'admin_email'         => (string) ($row['AdminEmail'] ?? ''),
        'admin_phone'         => (string) ($row['AdminPhone'] ?? ''),
        'npi_number'          => (string) ($row['NpiNumber'] ?? ''),
        'tax_id_type'         => (string) ($row['TaxIdType'] ?? ''),
        'tax_id'              => '',
        'ach_routing_number'  => (string) ($row['AchRoutingNumber'] ?? ''),
        'ach_account_number'  => '',
        'ach_account_type'    => (string) ($row['AchAccountType'] ?? 'Checking'),
    ];
}

function provider_signup_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function provider_signup_get_by_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.ProviderSignupApplication WHERE AccessToken = :token');
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function provider_signup_get(int $applicationId): ?array
{
    if ($applicationId <= 0) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.ProviderSignupApplication WHERE ApplicationID = :id');
    $stmt->execute(['id' => $applicationId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function provider_signup_provider_can_edit(array $application): bool
{
    return in_array((string) ($application['Status'] ?? ''), PROVIDER_SIGNUP_PROVIDER_EDITABLE_STATUSES, true);
}

function provider_signup_ops_can_approve(array $application): bool
{
    return in_array((string) ($application['Status'] ?? ''), [
        PROVIDER_SIGNUP_STATUS_DRAFT,
        PROVIDER_SIGNUP_STATUS_RETURNED,
        PROVIDER_SIGNUP_STATUS_SUBMITTED,
    ], true);
}

function provider_signup_ops_can_edit(array $application): bool
{
    return in_array((string) ($application['Status'] ?? ''), PROVIDER_SIGNUP_OPS_EDITABLE_STATUSES, true);
}

function provider_signup_provider_can_submit(array $application): bool
{
    return (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_APPROVED;
}

function provider_signup_has_reseller_certificate(int $applicationId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT COUNT(*) FROM dbo.ProviderSignupAttachment
        WHERE ApplicationID = :id AND AttachmentKind = N'ResellerCertificate'
    SQL);
    $stmt->execute(['id' => $applicationId]);

    return (int) $stmt->fetchColumn() > 0;
}

/**
 * @return array{complete: bool, missing: list<string>}
 */
function provider_signup_submit_checklist(array $form, int $applicationId): array
{
    $missing = [];
    $requiredStrings = [
        'company_name'       => 'Practice / company name',
        'company_legal_name' => 'Legal company name',
        'company_email'      => 'Company email',
        'company_phone'      => 'Company phone',
        'street_address'     => 'Street address',
        'city'               => 'City',
        'state_code'         => 'State',
        'postal_code'        => 'Postal code',
        'admin_first_name'   => 'Admin first name',
        'admin_last_name'    => 'Admin last name',
        'admin_email'        => 'Admin email',
        'npi_number'         => 'NPI number',
        'ach_routing_number' => 'ACH routing number',
        'ach_account_type'   => 'ACH account type',
    ];

    foreach ($requiredStrings as $field => $label) {
        if (trim((string) ($form[$field] ?? '')) === '') {
            $missing[] = $label;
        }
    }

    if (!in_array((string) ($form['tax_id_type'] ?? ''), PROVIDER_SIGNUP_TAX_ID_TYPES, true)) {
        $missing[] = 'Tax ID type (SSN or EIN)';
    }

    $taxId = preg_replace('/\D+/', '', (string) ($form['tax_id'] ?? '')) ?? '';
    $hasStoredTax = provider_signup_get($applicationId)['TaxIdEncrypted'] ?? null;
    if ($taxId === '' && trim((string) $hasStoredTax) === '') {
        $missing[] = 'Tax ID (SSN or EIN)';
    }

    $account = preg_replace('/\D+/', '', (string) ($form['ach_account_number'] ?? '')) ?? '';
    $hasStoredAccount = provider_signup_get($applicationId)['AchAccountNumberEncrypted'] ?? null;
    if ($account === '' && trim((string) $hasStoredAccount) === '') {
        $missing[] = 'ACH account number';
    }

    if (!provider_signup_has_reseller_certificate($applicationId)) {
        $missing[] = 'State reseller certificate upload';
    }

    $npi = preg_replace('/\D+/', '', (string) ($form['npi_number'] ?? '')) ?? '';
    if ($npi !== '' && strlen($npi) !== 10) {
        $missing[] = 'Valid 10-digit NPI number';
    }

    $routing = preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? '')) ?? '';
    if ($routing !== '' && strlen($routing) !== 9) {
        $missing[] = 'Valid 9-digit ACH routing number';
    }

    return [
        'complete' => $missing === [],
        'missing'  => $missing,
    ];
}

function provider_signup_banking_validate_format(array $form, int $applicationId): array
{
    $routing = preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? '')) ?? '';
    $account = preg_replace('/\D+/', '', (string) ($form['ach_account_number'] ?? '')) ?? '';
    if ($account === '') {
        $stored = provider_signup_get($applicationId);
        $account = preg_replace('/\D+/', '', provider_signup_decrypt($stored['AchAccountNumberEncrypted'] ?? null)) ?? '';
    }

    if (strlen($routing) !== 9) {
        return [
            'ok'      => false,
            'status'  => 'Invalid',
            'summary' => 'ACH routing number must be 9 digits.',
        ];
    }

    if ($account === '') {
        return [
            'ok'      => false,
            'status'  => 'Invalid',
            'summary' => 'ACH account number is required.',
        ];
    }

    return [
        'ok'      => true,
        'status'  => 'FormatValid',
        'summary' => 'Banking format validated. Plaid verification is pending integration.',
    ];
}

function provider_signup_create_application(string $providerEmail): array
{
    $providerEmail = provider_signup_normalize_email($providerEmail);
    if ($providerEmail === '' || !filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid provider email address is required.', 'application' => null];
    }

    try {
        $pdo = db();
        $token = provider_signup_generate_token();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupApplication (
                AccessToken, Status, ProviderEmail, AdminEmail, CountryCode
            )
            VALUES (?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([
            $token,
            PROVIDER_SIGNUP_STATUS_DRAFT,
            $providerEmail,
            $providerEmail,
            'US',
        ]);

        $application = provider_signup_get_by_token($token);
        if ($application === null) {
            return ['ok' => false, 'error' => 'Unable to load the new application.', 'application' => null];
        }
    } catch (Throwable $e) {
        error_log('provider_signup_create_application: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to create provider application.', 'application' => null];
    }

    try {
        provider_signup_add_review_log((int) $application['ApplicationID'], null, 'Comment', 'Application started by provider.');
    } catch (Throwable $e) {
        error_log('provider_signup_create_application review log: ' . $e->getMessage());
    }

    try {
        provider_signup_mail_application_started($application);
    } catch (Throwable $e) {
        error_log('provider_signup_create_application mail: ' . $e->getMessage());
    }

    return ['ok' => true, 'error' => null, 'application' => $application];
}

function provider_signup_save_draft(string $accessToken, array $form): array
{
    $application = provider_signup_get_by_token($accessToken);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_provider_can_edit($application)) {
        return ['ok' => false, 'error' => 'This application can no longer be edited online.'];
    }

    return provider_signup_persist_form((int) $application['ApplicationID'], $form, false);
}

function provider_signup_submit(string $accessToken, array $form): array
{
    $application = provider_signup_get_by_token($accessToken);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_provider_can_submit($application)) {
        return ['ok' => false, 'error' => 'Operations must approve your application before you can activate your Clinic Store.'];
    }

    $applicationId = (int) $application['ApplicationID'];
    $checklist = provider_signup_submit_checklist($form, $applicationId);
    if (!$checklist['complete']) {
        return [
            'ok'    => false,
            'error' => 'Complete all required fields before activating your store: ' . implode(', ', $checklist['missing']) . '.',
        ];
    }

    $persist = provider_signup_persist_form($applicationId, $form, true);
    if (!$persist['ok']) {
        return $persist;
    }

    $provision = provider_signup_provision($applicationId);
    if (!$provision['ok']) {
        return ['ok' => false, 'error' => $provision['error'] ?? 'Unable to create your Clinic Store.'];
    }

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET Status = :status,
                SubmittedAt = SYSUTCDATETIME(),
                LastSavedAt = SYSUTCDATETIME(),
                ProvisionedAt = SYSUTCDATETIME()
            WHERE ApplicationID = :id
        SQL)->execute([
            'status' => PROVIDER_SIGNUP_STATUS_PROVISIONED,
            'id'     => $applicationId,
        ]);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to finalize your application.'];
    }

    provider_signup_add_review_log($applicationId, null, 'Activated', 'Provider activated Clinic Store after operations approval.');
    $updated = provider_signup_get($applicationId);
    if ($updated !== null) {
        provider_signup_mail_provisioned($updated);
    }

    return ['ok' => true, 'error' => null];
}

function provider_signup_persist_form(int $applicationId, array $form, bool $submitting): array
{
    $taxId = preg_replace('/\D+/', '', (string) ($form['tax_id'] ?? '')) ?? '';
    $account = preg_replace('/\D+/', '', (string) ($form['ach_account_number'] ?? '')) ?? '';
    $existing = provider_signup_get($applicationId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $taxEncrypted = $taxId !== ''
        ? provider_signup_encrypt($taxId)
        : (string) ($existing['TaxIdEncrypted'] ?? null);
    $accountEncrypted = $account !== ''
        ? provider_signup_encrypt($account)
        : (string) ($existing['AchAccountNumberEncrypted'] ?? null);

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET CompanyName = :company_name,
                CompanyLegalName = :company_legal_name,
                CompanyEmail = :company_email,
                CompanyPhone = :company_phone,
                StreetAddress = :street_address,
                City = :city,
                StateCode = :state_code,
                PostalCode = :postal_code,
                AdminFirstName = :admin_first_name,
                AdminLastName = :admin_last_name,
                AdminEmail = :admin_email,
                AdminPhone = :admin_phone,
                NpiNumber = :npi_number,
                TaxIdType = :tax_id_type,
                TaxIdEncrypted = :tax_id_encrypted,
                AchRoutingNumber = :ach_routing_number,
                AchAccountNumberEncrypted = :ach_account_encrypted,
                AchAccountType = :ach_account_type,
                LastSavedAt = SYSUTCDATETIME()
            WHERE ApplicationID = :id
        SQL);
        $stmt->execute([
            'company_name'        => provider_signup_nullable_string($form['company_name'] ?? ''),
            'company_legal_name'  => provider_signup_nullable_string($form['company_legal_name'] ?? ''),
            'company_email'       => provider_signup_nullable_string($form['company_email'] ?? ''),
            'company_phone'       => provider_signup_nullable_string($form['company_phone'] ?? ''),
            'street_address'      => provider_signup_nullable_string($form['street_address'] ?? ''),
            'city'                => provider_signup_nullable_string($form['city'] ?? ''),
            'state_code'          => provider_signup_nullable_string($form['state_code'] ?? ''),
            'postal_code'         => provider_signup_nullable_string($form['postal_code'] ?? ''),
            'admin_first_name'    => provider_signup_nullable_string($form['admin_first_name'] ?? ''),
            'admin_last_name'     => provider_signup_nullable_string($form['admin_last_name'] ?? ''),
            'admin_email'         => provider_signup_nullable_string($form['admin_email'] ?? ''),
            'admin_phone'         => provider_signup_nullable_string($form['admin_phone'] ?? ''),
            'npi_number'          => provider_signup_nullable_string(preg_replace('/\D+/', '', (string) ($form['npi_number'] ?? ''))),
            'tax_id_type'         => in_array((string) ($form['tax_id_type'] ?? ''), PROVIDER_SIGNUP_TAX_ID_TYPES, true)
                ? (string) $form['tax_id_type'] : null,
            'tax_id_encrypted'    => $taxEncrypted !== '' ? $taxEncrypted : null,
            'ach_routing_number'  => provider_signup_nullable_string(preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? ''))),
            'ach_account_encrypted' => $accountEncrypted !== '' ? $accountEncrypted : null,
            'ach_account_type'    => in_array((string) ($form['ach_account_type'] ?? ''), PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES, true)
                ? (string) $form['ach_account_type'] : null,
            'id'                  => $applicationId,
        ]);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save application data.'];
    }

    return ['ok' => true, 'error' => null];
}

function provider_signup_nullable_string(string $value): ?string
{
    $value = trim($value);

    return $value === '' ? null : $value;
}

function provider_signup_save_attachment(string $accessToken, array $file): array
{
    $application = provider_signup_get_by_token($accessToken);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_provider_can_edit($application)) {
        return ['ok' => false, 'error' => 'This application can no longer be edited online.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > PROVIDER_SIGNUP_MAX_ATTACHMENT_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    $content = file_get_contents((string) ($file['tmp_name'] ?? ''));
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    $fileName = (string) ($file['name'] ?? 'reseller-certificate');
    $contentType = trim((string) ($file['type'] ?? 'application/octet-stream'));
    if ($contentType === '') {
        $contentType = 'application/octet-stream';
    }

    $applicationId = (int) $application['ApplicationID'];

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            DELETE FROM dbo.ProviderSignupAttachment
            WHERE ApplicationID = :id AND AttachmentKind = N'ResellerCertificate'
        SQL)->execute(['id' => $applicationId]);

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupAttachment (
                ApplicationID, FileName, ContentType, FileSizeBytes, FileData, AttachmentKind
            )
            VALUES (:application_id, :name, :type, :size, :data, N'ResellerCertificate')
        SQL);
        $stmt->bindValue(':application_id', $applicationId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', (int) ($file['size'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':data', $content, PDO::PARAM_LOB);
        $stmt->execute();

        $idRow = $pdo->query('SELECT CAST(SCOPE_IDENTITY() AS INT) AS inserted_id')->fetch(PDO::FETCH_ASSOC);
        $attachmentId = (int) ($idRow['inserted_id'] ?? 0);

        return ['ok' => true, 'error' => null, 'id' => $attachmentId];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save the reseller certificate.'];
    }
}

function provider_signup_list_attachments(int $applicationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT AttachmentID, FileName, ContentType, FileSizeBytes, AttachmentKind, UploadDate
        FROM dbo.ProviderSignupAttachment
        WHERE ApplicationID = :id
        ORDER BY UploadDate DESC
    SQL);
    $stmt->execute(['id' => $applicationId]);

    return $stmt->fetchAll();
}

function provider_signup_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.ProviderSignupAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function provider_signup_attachment_bytes(array $attachment): string
{
    $fileData = $attachment['FileData'] ?? null;
    if ($fileData === null || $fileData === '') {
        return '';
    }

    if (is_resource($fileData)) {
        $contents = stream_get_contents($fileData);

        return is_string($contents) ? $contents : '';
    }

    return is_string($fileData) ? $fileData : '';
}

function provider_signup_list_applications(array $filters = []): array
{
    $pdo = db();
    $where = [];
    $params = [];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 'a.Status = :status';
        $params['status'] = $status;
    }

    $sql = 'SELECT a.* FROM dbo.ProviderSignupApplication a';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sortState = table_sort_state(
        PROVIDER_SIGNUP_LIST_SORT_COLUMNS,
        'submitted',
        'desc',
        $filters
    );
    $sql .= ' ORDER BY ' . table_sort_sql_clause(
        PROVIDER_SIGNUP_LIST_SORT_SQL,
        $sortState,
        'submitted',
        'submitted'
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function provider_signup_count_by_status(string $status): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dbo.ProviderSignupApplication WHERE Status = :status');
    $stmt->execute(['status' => $status]);

    return (int) $stmt->fetchColumn();
}

function provider_signup_list_review_log(int $applicationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            l.ReviewLogID,
            l.ReviewAction,
            l.Comments,
            l.LogDate,
            u.UserName AS ReviewerName
        FROM dbo.ProviderSignupReviewLog l
        LEFT JOIN dbo.[User] u ON u.UserID = l.ReviewerUserID
        WHERE l.ApplicationID = :id
        ORDER BY l.LogDate DESC, l.ReviewLogID DESC
    SQL);
    $stmt->execute(['id' => $applicationId]);

    return $stmt->fetchAll();
}

function provider_signup_add_review_log(
    int $applicationId,
    ?int $reviewerUserId,
    string $action,
    ?string $comments = null
): void
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.ProviderSignupReviewLog (
            ApplicationID, ReviewerUserID, ReviewAction, Comments
        )
        VALUES (:application_id, :reviewer_user_id, :action, :comments)
    SQL);
    $stmt->execute([
        'application_id'  => $applicationId,
        'reviewer_user_id'=> $reviewerUserId,
        'action'          => $action,
        'comments'        => provider_signup_nullable_string((string) ($comments ?? '')),
    ]);
}

function provider_signup_ops_update(int $applicationId, array $form, string $editNote = ''): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_ops_can_edit($application)) {
        return ['ok' => false, 'error' => 'This application can no longer be edited.'];
    }

    $result = provider_signup_persist_form($applicationId, $form, false);
    if (!$result['ok']) {
        return $result;
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    $note = trim($editNote);
    provider_signup_add_review_log(
        $applicationId,
        $reviewerId,
        'Updated',
        $note !== '' ? $note : 'Application data updated by operations reviewer.'
    );

    return ['ok' => true, 'error' => null];
}

function provider_signup_ops_comment(int $applicationId, string $comments): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $comments = trim($comments);
    if ($comments === '') {
        return ['ok' => false, 'error' => 'Comment text is required.'];
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    provider_signup_add_review_log($applicationId, $reviewerId, 'Comment', $comments);
    provider_signup_mail_commented($application, $comments);

    return ['ok' => true, 'error' => null];
}

function provider_signup_ops_return(int $applicationId, string $comments): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $comments = trim($comments);
    if ($comments === '') {
        return ['ok' => false, 'error' => 'Please explain what the provider needs to update.'];
    }

    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.ProviderSignupApplication
        SET Status = :status, LastSavedAt = SYSUTCDATETIME()
        WHERE ApplicationID = :id
    SQL)->execute([
        'status' => PROVIDER_SIGNUP_STATUS_RETURNED,
        'id'     => $applicationId,
    ]);

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    provider_signup_add_review_log($applicationId, $reviewerId, 'Returned', $comments);
    $updated = provider_signup_get($applicationId);
    if ($updated !== null) {
        provider_signup_mail_returned($updated, $comments);
    }

    return ['ok' => true, 'error' => null];
}

function provider_signup_ops_reject(int $applicationId, string $comments): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $pdo = db();
    $pdo->prepare('UPDATE dbo.ProviderSignupApplication SET Status = :status WHERE ApplicationID = :id')
        ->execute(['status' => PROVIDER_SIGNUP_STATUS_REJECTED, 'id' => $applicationId]);

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    provider_signup_add_review_log($applicationId, $reviewerId, 'Rejected', trim($comments));

    return ['ok' => true, 'error' => null];
}

function provider_signup_ops_validate_npi(int $applicationId): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $result = provider_signup_npi_validate((string) ($application['NpiNumber'] ?? ''));
    $pdo = db();
    $pdo->prepare(<<<SQL
        UPDATE dbo.ProviderSignupApplication
        SET NpiValidatedAt = CASE WHEN :ok = 1 THEN SYSUTCDATETIME() ELSE NULL END,
            NpiValidationStatus = :status,
            NpiValidationSummary = :summary
        WHERE ApplicationID = :id
    SQL)->execute([
        'ok'      => $result['ok'] ? 1 : 0,
        'status'  => $result['status'],
        'summary' => $result['summary'],
        'id'      => $applicationId,
    ]);

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    provider_signup_add_review_log(
        $applicationId,
        $reviewerId,
        'NpiValidated',
        (string) ($result['summary'] ?? '')
    );

    return $result;
}

function provider_signup_ops_approve(int $applicationId, string $comments = ''): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_ops_can_approve($application)) {
        return ['ok' => false, 'error' => 'This application cannot be approved in its current status.'];
    }

    $form = provider_signup_form_from_row($application);
    $checklist = provider_signup_submit_checklist($form, $applicationId);
    if (!$checklist['complete']) {
        return [
            'ok'    => false,
            'error' => 'Complete application data is required before approval: ' . implode(', ', $checklist['missing']) . '.',
        ];
    }

    $npiResult = provider_signup_npi_validate((string) ($form['npi_number'] ?? ''));
    $bankResult = provider_signup_banking_validate_format($form, $applicationId);

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET Status = :status,
                LastSavedAt = SYSUTCDATETIME(),
                NpiValidatedAt = CASE WHEN :npi_ok = 1 THEN SYSUTCDATETIME() ELSE NULL END,
                NpiValidationStatus = :npi_status,
                NpiValidationSummary = :npi_summary,
                BankingValidationStatus = :bank_status,
                BankingValidationSummary = :bank_summary
            WHERE ApplicationID = :id
        SQL)->execute([
            'status'       => PROVIDER_SIGNUP_STATUS_APPROVED,
            'npi_ok'       => $npiResult['ok'] ? 1 : 0,
            'npi_status'   => $npiResult['status'],
            'npi_summary'  => $npiResult['summary'],
            'bank_status'  => $bankResult['status'],
            'bank_summary' => $bankResult['summary'],
            'id'           => $applicationId,
        ]);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to approve the application.'];
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    provider_signup_add_review_log($applicationId, $reviewerId, 'Approved', trim($comments));

    $updated = provider_signup_get($applicationId);
    if ($updated !== null) {
        provider_signup_mail_approved($updated);
    }

    if (!$npiResult['ok']) {
        return [
            'ok'    => true,
            'error' => null,
            'warn'  => 'Application approved, but NPI validation did not pass: ' . ($npiResult['summary'] ?? 'Unknown issue') . '.',
        ];
    }

    return ['ok' => true, 'error' => null];
}

function provider_signup_provision(int $applicationId): array
{
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    return [
        'ok'    => false,
        'error' => 'ACCS provisioning (company + company admin) is not wired yet.',
    ];
}

function provider_signup_status_badge_class(string $status): string
{
    switch ($status) {
        case PROVIDER_SIGNUP_STATUS_DRAFT:
            return 'status-draft';
        case PROVIDER_SIGNUP_STATUS_SUBMITTED:
        case PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION:
            return 'status-submitted';
        case PROVIDER_SIGNUP_STATUS_RETURNED:
            return 'status-received';
        case PROVIDER_SIGNUP_STATUS_APPROVED:
        case PROVIDER_SIGNUP_STATUS_PROVISIONED:
            return 'status-approved';
        case PROVIDER_SIGNUP_STATUS_REJECTED:
            return 'status-cancelled';
        default:
            return 'status-draft';
    }
}

function provider_signup_format_datetime(DateTimeInterface|string|null $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        $dt = $value instanceof DateTimeInterface ? $value : new DateTimeImmutable((string) $value);

        return $dt->format('M j, Y g:i A');
    } catch (Throwable) {
        return is_scalar($value) ? (string) $value : '—';
    }
}
