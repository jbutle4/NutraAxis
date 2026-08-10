<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/attachment-storage.php';
require_once __DIR__ . '/provider-signup-crypto.php';
require_once __DIR__ . '/provider-signup-npi.php';
require_once __DIR__ . '/provider-signup-mail.php';
require_once __DIR__ . '/provider-signup-npi-snapshot.php';
require_once __DIR__ . '/provider-signup-accs.php';
require_once __DIR__ . '/provider-signup-accs-config.php';
require_once __DIR__ . '/provider-signup-recaptcha.php';

const PROVIDER_SIGNUP_PERMISSION_COLUMN = 'ProviderAccountReview';

/** Current Practitioner Reseller & Advertising Policy document version (PDF filename date). */
const PROVIDER_SIGNUP_POLICY_VERSION = '2026-07-14';

const PROVIDER_SIGNUP_POLICY_PDF_PATH = '/assets/docs/NutraAxis_Practitioner_Reseller_Policy_20260714.pdf';

const PROVIDER_SIGNUP_POLICY_ACK_STATEMENT = 'I acknowledge that I have received and will comply with the NutraAxis Labs, LLC Practitioner and Reseller Policies, including but not limited to the iMAP Policy.';

const PROVIDER_SIGNUP_EMAIL_CHALLENGE_TTL_MINUTES = 60;
const PROVIDER_SIGNUP_EMAIL_CHALLENGE_MAX_PER_EMAIL_HOUR = 5;
const PROVIDER_SIGNUP_EMAIL_CHALLENGE_MAX_PER_IP_HOUR = 20;

const PROVIDER_SIGNUP_STATUS_DRAFT = 'Draft';
const PROVIDER_SIGNUP_STATUS_SUBMITTED = 'Submitted for Review';
const PROVIDER_SIGNUP_STATUS_RETURNED = 'Returned';
const PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION = 'Pending Validation';
const PROVIDER_SIGNUP_STATUS_APPROVED = 'Approved';
const PROVIDER_SIGNUP_STATUS_PROVISIONED = 'Provisioned';
const PROVIDER_SIGNUP_STATUS_REJECTED = 'Rejected';

const PROVIDER_SIGNUP_PROVIDER_EDITABLE_STATUSES = [
    PROVIDER_SIGNUP_STATUS_DRAFT,
    PROVIDER_SIGNUP_STATUS_RETURNED,
];

/** Statuses where providers may upload certificates / ACH after submit (not full form edit). */
const PROVIDER_SIGNUP_PROVIDER_DOCUMENTS_STATUSES = [
    PROVIDER_SIGNUP_STATUS_SUBMITTED,
    PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION,
    PROVIDER_SIGNUP_STATUS_APPROVED,
    PROVIDER_SIGNUP_STATUS_PROVISIONED,
];

const PROVIDER_SIGNUP_OPS_EDITABLE_STATUSES = [
    PROVIDER_SIGNUP_STATUS_DRAFT,
    PROVIDER_SIGNUP_STATUS_RETURNED,
    PROVIDER_SIGNUP_STATUS_SUBMITTED,
    PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION,
    PROVIDER_SIGNUP_STATUS_APPROVED,
];

const PROVIDER_SIGNUP_OPS_REVERT_SOURCE_STATUSES = [
    PROVIDER_SIGNUP_STATUS_SUBMITTED,
    PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION,
    PROVIDER_SIGNUP_STATUS_APPROVED,
    PROVIDER_SIGNUP_STATUS_REJECTED,
];

const PROVIDER_SIGNUP_OPS_REVERT_TARGET_STATUSES = [
    PROVIDER_SIGNUP_STATUS_DRAFT,
    PROVIDER_SIGNUP_STATUS_RETURNED,
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

const PROVIDER_SIGNUP_CLINIC_TYPES = [
    'Medical Weight Management / Obesity Medicine',
    'Endocrinology / Metabolic Health',
    'Functional Medicine',
    'Integrative Medicine',
    'Anti-Aging / Longevity Medicine',
    'Regenerative Medicine',
    'Hormone Replacement Therapy (HRT) Clinic',
    'Menopause / Women\'s Health',
    'Obstetrics & Gynecology (OB/GYN)',
    'Urology / Men\'s Health',
    'Concierge Medicine',
    'Direct Primary Care',
    'Cash-Pay Medical Practice',
    'Primary Care / Family Medicine',
    'Internal Medicine',
    'GLP-1 / Medical Weight-Loss Clinic',
    'Wellness Clinic',
    'Medical Spa / Med Spa',
    'Sleep Medicine',
    'Stress Management / Behavioral Wellness',
    'Gastroenterology',
    'Digestive / Gut Health Clinic',
    'Other',
];

const PROVIDER_SIGNUP_LIST_SORT_COLUMNS = [
    'id'              => 'ID',
    'practice'        => 'Practice',
    'provider'        => 'Provider Email',
    'status'          => 'Status',
    'created'         => 'Created',
    'submitted'       => 'Submitted',
    'accs_env'        => 'ACCS Env',
    'step_clinic'     => 'Clinic',
    'step_admin'      => 'Admin',
    'step_catalog'    => 'Catalog',
    'step_assign'     => 'Assign',
    'step_roles'      => 'Roles',
    'config_complete' => 'Config',
];

const PROVIDER_SIGNUP_LIST_SORT_SQL = [
    'id'              => 'a.ApplicationID',
    'practice'        => 'a.CompanyName',
    'provider'        => 'a.ProviderEmail',
    'status'          => 'a.Status',
    'created'         => 'a.CreatedAt',
    'submitted'       => 'a.SubmittedAt',
    'accs_env'        => 'a.AccsEnvironment',
    'step_clinic'     => 'a.AccsStepClinicDone',
    'step_admin'      => 'a.AccsStepAdminDone',
    'step_catalog'    => 'a.AccsStepSharedCatalogDone',
    'step_assign'     => 'a.AccsStepCatalogAssignDone',
    'step_roles'      => 'a.AccsStepRolesDone',
    'config_complete' => 'a.AccsConfigurationComplete',
];

const PROVIDER_SIGNUP_LIST_PAGE_SIZE = 25;

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

function provider_signup_is_valid_clinic_type(string $clinicType): bool
{
    return in_array(trim($clinicType), PROVIDER_SIGNUP_CLINIC_TYPES, true);
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
        'clinic_type'         => '',
        'admin_first_name'    => '',
        'admin_last_name'     => '',
        'admin_email'         => '',
        'admin_phone'         => '',
        'npi_number'          => '',
        'tax_id_type'         => '',
        'tax_id'              => '',
        'ach_routing_number'  => '',
        'ach_account_number'  => '',
        'ach_account_type'    => '',
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
        'clinic_type'         => (string) ($row['ClinicType'] ?? ''),
        'admin_first_name'    => (string) ($row['AdminFirstName'] ?? ''),
        'admin_last_name'     => (string) ($row['AdminLastName'] ?? ''),
        'admin_email'         => (string) ($row['AdminEmail'] ?? ''),
        'admin_phone'         => (string) ($row['AdminPhone'] ?? ''),
        'npi_number'          => (string) ($row['NpiNumber'] ?? ''),
        'tax_id_type'         => (string) ($row['TaxIdType'] ?? ''),
        'tax_id'              => '',
        'ach_routing_number'  => (string) ($row['AchRoutingNumber'] ?? ''),
        'ach_account_number'  => '',
        'ach_account_type'    => (string) ($row['AchAccountType'] ?? ''),
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

/**
 * Provider may upload reseller certificate and/or ACH after the application is submitted.
 */
function provider_signup_provider_can_complete_documents(array $application): bool
{
    if (provider_signup_provider_can_edit($application)) {
        return true;
    }

    return in_array(
        (string) ($application['Status'] ?? ''),
        PROVIDER_SIGNUP_PROVIDER_DOCUMENTS_STATUSES,
        true
    );
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
    return provider_signup_provider_can_edit($application);
}

function provider_signup_ops_can_provision(array $application): bool
{
    return (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_APPROVED;
}

/** @return list<string> */
function provider_signup_config_step_keys(): array
{
    return ['clinic', 'admin', 'shared_catalog', 'catalog_assign', 'roles'];
}

function provider_signup_ops_can_mark_config_step(array $application): bool
{
    return in_array((string) ($application['Status'] ?? ''), [
        PROVIDER_SIGNUP_STATUS_APPROVED,
        PROVIDER_SIGNUP_STATUS_PROVISIONED,
    ], true);
}

function provider_signup_config_step_done(array $application, string $step): bool
{
    return match ($step) {
        'clinic'         => !empty($application['AccsStepClinicDone']),
        'admin'          => !empty($application['AccsStepAdminDone']),
        'shared_catalog' => !empty($application['AccsStepSharedCatalogDone']),
        'catalog_assign' => !empty($application['AccsStepCatalogAssignDone']),
        'roles'          => !empty($application['AccsStepRolesDone']),
        default          => false,
    };
}

function provider_signup_config_steps_complete(array $application): bool
{
    foreach (provider_signup_config_step_keys() as $step) {
        if (!provider_signup_config_step_done($application, $step)) {
            return false;
        }
    }

    return true;
}

function provider_signup_config_step_table_title(array $application, string $step): string
{
    return match ($step) {
        'clinic' => provider_signup_config_step_done($application, 'clinic')
            ? 'Clinic company · ID ' . (int) ($application['AccsCompanyId'] ?? 0)
            : 'Clinic company not complete',
        'admin' => provider_signup_config_step_done($application, 'admin')
            ? 'Clinic admin · customer ID ' . (int) ($application['AccsCustomerId'] ?? 0)
            : 'Clinic admin not complete',
        'shared_catalog' => provider_signup_config_step_done($application, 'shared_catalog')
            ? 'Shared catalog · ID ' . (int) ($application['AccsSharedCatalogId'] ?? 0)
            : 'Shared catalog not complete',
        'catalog_assign' => provider_signup_config_step_done($application, 'catalog_assign')
            ? trim(
                (($application['AccsCatalogCategoryCount'] ?? null) !== null
                    ? (int) $application['AccsCatalogCategoryCount'] . ' categories' : '')
                . (($application['AccsCatalogCategoryCount'] ?? null) !== null
                    && ($application['AccsCatalogProductCount'] ?? null) !== null ? ', ' : '')
                . (($application['AccsCatalogProductCount'] ?? null) !== null
                    ? (int) $application['AccsCatalogProductCount'] . ' products' : '')
            ) ?: 'Categories and products assigned'
            : 'Categories and products not complete',
        'roles' => provider_signup_config_step_done($application, 'roles')
            ? (trim((string) ($application['AccsRolesSummary'] ?? '')) !== ''
                ? 'Roles: ' . (string) $application['AccsRolesSummary']
                : 'Company roles complete')
            : 'Company roles not complete',
        default => '',
    };
}

/**
 * @return array{done: bool, label: string, title: string}
 */
function provider_signup_config_step_table_cell(array $application, string $step): array
{
    $done = provider_signup_config_step_done($application, $step);

    return [
        'done'  => $done,
        'label' => $done ? 'Yes' : '—',
        'title' => provider_signup_config_step_table_title($application, $step),
    ];
}

/**
 * @return array{status: ?string, q: ?string, page: int, sort: string, dir: string}
 */
function provider_signup_list_filters_from_request(): array
{
    $status = trim((string) ($_GET['status'] ?? ''));
    $search = trim((string) ($_GET['q'] ?? ''));

    return [
        'status' => $status !== '' ? $status : null,
        'q'      => $search !== '' ? $search : null,
        'page'   => max(1, (int) ($_GET['page'] ?? 1)),
    ] + table_sort_state(PROVIDER_SIGNUP_LIST_SORT_COLUMNS, 'submitted', 'desc', $_GET);
}

function provider_signup_list_page_href(array $filters, int $page): string
{
    $query = array_filter([
        'status' => ($filters['status'] ?? null) !== null && ($filters['status'] ?? '') !== ''
            ? (string) $filters['status'] : null,
        'q'      => ($filters['q'] ?? null) !== null && ($filters['q'] ?? '') !== ''
            ? (string) $filters['q'] : null,
        'sort'   => $filters['sort'] ?? null,
        'dir'    => $filters['dir'] ?? null,
        'page'   => $page > 1 ? $page : null,
    ], static fn($value) => $value !== null && $value !== '');

    return '/operations-dashboard/signup-review/?' . http_build_query($query);
}

/**
 * @param array<string, mixed> $filters
 * @return array{where: list<string>, params: array<string, mixed>}
 */
function provider_signup_list_applications_where(array $filters): array
{
    $where = [];
    $params = [];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $where[] = 'a.Status = :status';
        $params['status'] = $status;
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $searchParts = [];
        if (ctype_digit($search)) {
            $searchParts[] = 'a.ApplicationID = :app_id';
            $params['app_id'] = (int) $search;
        }
        [$likeSql, $likeParams] = db_like_or([
            'a.CompanyName',
            'a.CompanyLegalName',
            'a.ProviderEmail',
            'a.AdminEmail',
            'a.AdminFirstName',
            'a.AdminLastName',
            'a.NpiNumber',
            'CAST(a.AccsCompanyId AS NVARCHAR(20))',
            'CAST(a.AccsCustomerId AS NVARCHAR(20))',
            'CAST(a.AccsSharedCatalogId AS NVARCHAR(20))',
            'a.AccsRolesSummary',
            'a.AccsEnvironment',
        ], $search, 'q');
        $searchParts[] = $likeSql;
        $params = array_merge($params, $likeParams);
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    return ['where' => $where, 'params' => $params];
}

/**
 * @param array<string, mixed> $filters
 * @return array{
 *   rows: list<array<string, mixed>>,
 *   total: int,
 *   page: int,
 *   per_page: int,
 *   has_prev: bool,
 *   has_next: bool
 * }
 */
function provider_signup_list_applications_page(array $filters = []): array
{
    $pdo = db();
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = PROVIDER_SIGNUP_LIST_PAGE_SIZE;
    $offset = ($page - 1) * $perPage;

    $whereData = provider_signup_list_applications_where($filters);
    $where = $whereData['where'];
    $params = $whereData['params'];

    $fromSql = 'FROM dbo.ProviderSignupApplication a';
    if ($where !== []) {
        $fromSql .= ' WHERE ' . implode(' AND ', $where);
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $fromSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sortState = table_sort_state(
        PROVIDER_SIGNUP_LIST_SORT_COLUMNS,
        'submitted',
        'desc',
        $filters
    );
    $orderSql = ' ORDER BY ' . table_sort_sql_clause(
        PROVIDER_SIGNUP_LIST_SORT_SQL,
        $sortState,
        'submitted',
        'id'
    );

    $dataSql = 'SELECT a.* ' . $fromSql . $orderSql
        . ' OFFSET ' . (int) $offset . ' ROWS FETCH NEXT ' . (int) $perPage . ' ROWS ONLY';
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    return [
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'has_prev' => $page > 1,
        'has_next' => ($offset + $perPage) < $total,
    ];
}

/**
 * @return list<array{key: string, label: string, done: bool, completed_at: ?string, detail: string}>
 */
function provider_signup_config_steps(array $application): array
{
    $companyId = (int) ($application['AccsCompanyId'] ?? 0);
    $customerId = (int) ($application['AccsCustomerId'] ?? 0);
    $catalogId = (int) ($application['AccsSharedCatalogId'] ?? 0);
    $categoryCount = $application['AccsCatalogCategoryCount'] ?? null;
    $productCount = $application['AccsCatalogProductCount'] ?? null;
    $rolesSummary = trim((string) ($application['AccsRolesSummary'] ?? ''));

    $clinicDetail = $companyId > 0
        ? 'Company ID ' . $companyId . (
            trim((string) ($application['AccsClinicId'] ?? '')) !== ''
                ? ' · Clinic ID ' . (string) $application['AccsClinicId']
                : ''
        )
        : '';

    $adminDetail = $customerId > 0 ? 'Customer ID ' . $customerId : '';

    $catalogDetail = $catalogId > 0 ? 'Shared catalog ID ' . $catalogId : '';

    $assignDetail = '';
    if ($categoryCount !== null || $productCount !== null) {
        $assignDetail = trim(
            ($categoryCount !== null ? (int) $categoryCount . ' categories' : '')
            . ($categoryCount !== null && $productCount !== null ? ', ' : '')
            . ($productCount !== null ? (int) $productCount . ' products' : '')
        );
    }

    return [
        [
            'key'          => 'clinic',
            'label'        => 'Clinic (company)',
            'done'         => provider_signup_config_step_done($application, 'clinic'),
            'completed_at' => $application['AccsStepClinicAt'] ?? null,
            'detail'       => $clinicDetail,
        ],
        [
            'key'          => 'admin',
            'label'        => 'Clinic admin',
            'done'         => provider_signup_config_step_done($application, 'admin'),
            'completed_at' => $application['AccsStepAdminAt'] ?? null,
            'detail'       => $adminDetail,
        ],
        [
            'key'          => 'shared_catalog',
            'label'        => 'Shared catalog',
            'done'         => provider_signup_config_step_done($application, 'shared_catalog'),
            'completed_at' => $application['AccsStepSharedCatalogAt'] ?? null,
            'detail'       => $catalogDetail,
        ],
        [
            'key'          => 'catalog_assign',
            'label'        => 'Categories & products',
            'done'         => provider_signup_config_step_done($application, 'catalog_assign'),
            'completed_at' => $application['AccsStepCatalogAssignAt'] ?? null,
            'detail'       => $assignDetail,
        ],
        [
            'key'          => 'roles',
            'label'        => 'Company roles',
            'done'         => provider_signup_config_step_done($application, 'roles'),
            'completed_at' => $application['AccsStepRolesAt'] ?? null,
            'detail'       => $rolesSummary,
        ],
    ];
}

/**
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_recompute_configuration_complete(int $applicationId): array
{
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $complete = provider_signup_config_steps_complete($application);

    try {
        $pdo = db();
        if ($complete) {
            $pdo->prepare(<<<SQL
                UPDATE dbo.ProviderSignupApplication
                SET AccsConfigurationComplete = 1,
                    AccsConfigurationCompletedAt = COALESCE(AccsConfigurationCompletedAt, SYSUTCDATETIME()),
                    LastSavedAt = SYSUTCDATETIME()
                WHERE ApplicationID = ?
            SQL)->execute([$applicationId]);
        } else {
            $pdo->prepare(<<<SQL
                UPDATE dbo.ProviderSignupApplication
                SET AccsConfigurationComplete = 0,
                    AccsConfigurationCompletedAt = NULL,
                    LastSavedAt = SYSUTCDATETIME()
                WHERE ApplicationID = ?
            SQL)->execute([$applicationId]);
        }
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to update configuration completion status.'];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @param array<string, mixed> $extra
 * @return array{ok: bool, error: ?string, configuration_complete?: bool}
 */
function provider_signup_ops_mark_config_step(int $applicationId, string $step, array $extra = []): array
{
    provider_signup_require_update();

    $step = strtolower(trim($step));
    if (!in_array($step, provider_signup_config_step_keys(), true)) {
        return ['ok' => false, 'error' => 'Unknown configuration step.'];
    }

    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_ops_can_mark_config_step($application)) {
        return [
            'ok'    => false,
            'error' => 'Configuration steps can only be marked when the application is Approved or Provisioned.',
        ];
    }

    if (provider_signup_config_step_done($application, $step)) {
        return ['ok' => false, 'error' => 'This configuration step is already marked complete.'];
    }

    $setClauses = ['LastSavedAt = SYSUTCDATETIME()'];
    $params = [];
    $logDetail = '';

    switch ($step) {
        case 'clinic':
            $companyId = (int) ($extra['accs_company_id'] ?? $application['AccsCompanyId'] ?? 0);
            if ($companyId <= 0) {
                return ['ok' => false, 'error' => 'ACCS company ID is required to mark the clinic step complete.'];
            }
            $setClauses[] = 'AccsCompanyId = :accs_company_id';
            $setClauses[] = 'AccsClinicId = COALESCE(AccsClinicId, :accs_clinic_id)';
            $setClauses[] = 'AccsEnvironment = COALESCE(AccsEnvironment, :accs_environment)';
            $setClauses[] = 'AccsStepClinicDone = 1';
            $setClauses[] = 'AccsStepClinicAt = SYSUTCDATETIME()';
            $params['accs_company_id'] = $companyId;
            $params['accs_clinic_id'] = trim((string) ($extra['accs_clinic_id'] ?? $application['AccsClinicId'] ?? (string) $companyId));
            $params['accs_environment'] = provider_signup_accs_target_environment();
            $logDetail = 'company ID ' . $companyId;
            break;

        case 'admin':
            $customerId = (int) ($extra['accs_customer_id'] ?? $application['AccsCustomerId'] ?? 0);
            if ($customerId <= 0) {
                return ['ok' => false, 'error' => 'ACCS customer ID is required to mark the clinic admin step complete.'];
            }
            $setClauses[] = 'AccsCustomerId = :accs_customer_id';
            $setClauses[] = 'AccsStepAdminDone = 1';
            $setClauses[] = 'AccsStepAdminAt = SYSUTCDATETIME()';
            $params['accs_customer_id'] = $customerId;
            $logDetail = 'customer ID ' . $customerId;
            break;

        case 'shared_catalog':
            $catalogId = (int) ($extra['accs_shared_catalog_id'] ?? $application['AccsSharedCatalogId'] ?? 0);
            if ($catalogId <= 0) {
                return ['ok' => false, 'error' => 'Shared catalog ID is required to mark the shared catalog step complete.'];
            }
            $setClauses[] = 'AccsSharedCatalogId = :accs_shared_catalog_id';
            $setClauses[] = 'AccsStepSharedCatalogDone = 1';
            $setClauses[] = 'AccsStepSharedCatalogAt = SYSUTCDATETIME()';
            $params['accs_shared_catalog_id'] = $catalogId;
            $logDetail = 'shared catalog ID ' . $catalogId;
            break;

        case 'catalog_assign':
            if (!provider_signup_config_step_done($application, 'shared_catalog')) {
                return [
                    'ok'    => false,
                    'error' => 'Mark the shared catalog step complete before categories and products.',
                ];
            }
            $categoryCount = trim((string) ($extra['accs_catalog_category_count'] ?? ''));
            $productCount = trim((string) ($extra['accs_catalog_product_count'] ?? ''));
            if ($categoryCount !== '' && (int) $categoryCount < 0) {
                return ['ok' => false, 'error' => 'Category count cannot be negative.'];
            }
            if ($productCount !== '' && (int) $productCount < 0) {
                return ['ok' => false, 'error' => 'Product count cannot be negative.'];
            }
            $setClauses[] = 'AccsStepCatalogAssignDone = 1';
            $setClauses[] = 'AccsStepCatalogAssignAt = SYSUTCDATETIME()';
            if ($categoryCount !== '') {
                $setClauses[] = 'AccsCatalogCategoryCount = :accs_catalog_category_count';
                $params['accs_catalog_category_count'] = (int) $categoryCount;
            }
            if ($productCount !== '') {
                $setClauses[] = 'AccsCatalogProductCount = :accs_catalog_product_count';
                $params['accs_catalog_product_count'] = (int) $productCount;
            }
            $logDetail = trim(
                ($categoryCount !== '' ? $categoryCount . ' categories' : '')
                . ($categoryCount !== '' && $productCount !== '' ? ', ' : '')
                . ($productCount !== '' ? $productCount . ' products' : '')
            );
            if ($logDetail === '') {
                $logDetail = 'categories and products assigned';
            }
            break;

        case 'roles':
            if (!provider_signup_config_step_done($application, 'clinic')
                && (int) ($application['AccsCompanyId'] ?? 0) <= 0) {
                return [
                    'ok'    => false,
                    'error' => 'Mark the clinic step complete (or provision the company) before company roles.',
                ];
            }
            $rolesSummary = trim((string) ($extra['accs_roles_summary'] ?? ''));
            $setClauses[] = 'AccsStepRolesDone = 1';
            $setClauses[] = 'AccsStepRolesAt = SYSUTCDATETIME()';
            if ($rolesSummary !== '') {
                $setClauses[] = 'AccsRolesSummary = :accs_roles_summary';
                $params['accs_roles_summary'] = mb_substr($rolesSummary, 0, 500);
                $logDetail = $rolesSummary;
            } else {
                $logDetail = 'company roles configured';
            }
            break;
    }

    $params['id'] = $applicationId;
    $sql = 'UPDATE dbo.ProviderSignupApplication SET ' . implode(', ', $setClauses) . ' WHERE ApplicationID = :id';

    try {
        $pdo = db();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save configuration step.'];
    }

    $recompute = provider_signup_recompute_configuration_complete($applicationId);
    if (!$recompute['ok']) {
        return $recompute;
    }

    $stepLabels = [
        'clinic'         => 'Clinic (company)',
        'admin'          => 'Clinic admin',
        'shared_catalog' => 'Shared catalog',
        'catalog_assign' => 'Categories & products',
        'roles'          => 'Company roles',
    ];
    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    provider_signup_add_review_log(
        $applicationId,
        $reviewerId > 0 ? $reviewerId : null,
        'Comment',
        'Clinic configuration step marked complete: '
        . ($stepLabels[$step] ?? $step)
        . ($logDetail !== '' ? ' (' . $logDetail . ').' : '.')
    );

    $updated = provider_signup_get($applicationId);

    return [
        'ok'                     => true,
        'error'                  => null,
        'configuration_complete' => $updated !== null && provider_signup_config_steps_complete($updated),
    ];
}

/**
 * @param array{
 *   ok: bool,
 *   shared_catalog_id?: ?int,
 *   category_count?: ?int,
 *   product_count?: ?int,
 *   roles_summary?: ?string,
 *   steps?: array<string, array{done: bool}>
 * } $result
 * @return array{ok: bool, error: ?string, configuration_complete?: bool}
 */
function provider_signup_persist_accs_config_result(int $applicationId, array $result): array
{
    if (empty($result['ok'])) {
        return ['ok' => false, 'error' => (string) ($result['error'] ?? 'ACCS configuration failed.')];
    }

    $setClauses = ['LastSavedAt = SYSUTCDATETIME()'];
    $params = ['application_id' => $applicationId];

    $steps = $result['steps'] ?? [];
    if (!empty($steps['shared_catalog']['done'])) {
        $setClauses[] = 'AccsStepSharedCatalogDone = 1';
        $setClauses[] = 'AccsStepSharedCatalogAt = COALESCE(AccsStepSharedCatalogAt, SYSUTCDATETIME())';
        if (!empty($result['shared_catalog_id'])) {
            $setClauses[] = 'AccsSharedCatalogId = :accs_shared_catalog_id';
            $params['accs_shared_catalog_id'] = (int) $result['shared_catalog_id'];
        }
    }

    if (!empty($steps['catalog_assign']['done'])) {
        $setClauses[] = 'AccsStepCatalogAssignDone = 1';
        $setClauses[] = 'AccsStepCatalogAssignAt = COALESCE(AccsStepCatalogAssignAt, SYSUTCDATETIME())';
        if (isset($result['category_count']) && $result['category_count'] !== null) {
            $setClauses[] = 'AccsCatalogCategoryCount = :accs_catalog_category_count';
            $params['accs_catalog_category_count'] = (int) $result['category_count'];
        }
        if (isset($result['product_count']) && $result['product_count'] !== null) {
            $setClauses[] = 'AccsCatalogProductCount = :accs_catalog_product_count';
            $params['accs_catalog_product_count'] = (int) $result['product_count'];
        }
    }

    if (!empty($steps['roles']['done'])) {
        $setClauses[] = 'AccsStepRolesDone = 1';
        $setClauses[] = 'AccsStepRolesAt = COALESCE(AccsStepRolesAt, SYSUTCDATETIME())';
        if (!empty($result['roles_summary'])) {
            $setClauses[] = 'AccsRolesSummary = :accs_roles_summary';
            $params['accs_roles_summary'] = (string) $result['roles_summary'];
        }
    }

    try {
        $pdo = db();
        $pdo->prepare(
            'UPDATE dbo.ProviderSignupApplication SET ' . implode(', ', $setClauses) . ' WHERE ApplicationID = :application_id'
        )->execute($params);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save ACCS configuration results.'];
    }

    $recompute = provider_signup_recompute_configuration_complete($applicationId);
    if (!$recompute['ok']) {
        return $recompute;
    }

    $updated = provider_signup_get($applicationId);

    return [
        'ok'                     => true,
        'error'                  => null,
        'configuration_complete' => $updated !== null && provider_signup_config_steps_complete($updated),
    ];
}

/**
 * @return array{ok: bool, error: ?string, configuration_complete?: bool, already?: bool}
 */
function provider_signup_ops_complete_accs_configuration(int $applicationId): array
{
    provider_signup_require_update();

    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if ((string) ($application['Status'] ?? '') !== PROVIDER_SIGNUP_STATUS_PROVISIONED) {
        return ['ok' => false, 'error' => 'ACCS configuration automation requires a provisioned application.'];
    }

    if ((int) ($application['AccsCompanyId'] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'ACCS company ID is required before clinic configuration can run.'];
    }

    if (provider_signup_config_steps_complete($application)) {
        return ['ok' => true, 'error' => null, 'configuration_complete' => true, 'already' => true];
    }

    $result = provider_signup_accs_complete_clinic_configuration($application);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'ACCS clinic configuration failed.'];
    }

    $persist = provider_signup_persist_accs_config_result($applicationId, $result);
    if (!$persist['ok']) {
        return $persist;
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    $detailParts = array_filter([
        !empty($result['shared_catalog_id']) ? 'catalog ID ' . (int) $result['shared_catalog_id'] : null,
        isset($result['category_count'], $result['product_count'])
            ? (int) $result['category_count'] . ' categories / ' . (int) $result['product_count'] . ' products'
            : null,
        !empty($result['roles_summary']) ? 'roles: ' . (string) $result['roles_summary'] : null,
    ]);
    provider_signup_add_review_log(
        $applicationId,
        $reviewerId > 0 ? $reviewerId : null,
        'Comment',
        'ACCS clinic configuration completed'
        . ($detailParts !== [] ? ' (' . implode('; ', $detailParts) . ').' : '.')
    );

    return [
        'ok'                     => true,
        'error'                  => null,
        'configuration_complete' => !empty($persist['configuration_complete']),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function provider_signup_list_applications_needing_accs_config(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT TOP ($limit) *
            FROM dbo.ProviderSignupApplication
            WHERE Status = :status
              AND AccsCompanyId IS NOT NULL
              AND AccsConfigurationComplete = 0
            ORDER BY ProvisionedAt DESC, ApplicationID DESC
        SQL);
        $stmt->execute(['status' => PROVIDER_SIGNUP_STATUS_PROVISIONED]);
    } catch (Throwable) {
        return [];
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function provider_signup_ops_can_revert(array $application): bool
{
    return in_array((string) ($application['Status'] ?? ''), PROVIDER_SIGNUP_OPS_REVERT_SOURCE_STATUSES, true);
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

function provider_signup_policy_pdf_url(): string
{
    return PROVIDER_SIGNUP_POLICY_PDF_PATH;
}

function provider_signup_has_current_policy_ack(array $application): bool
{
    $acknowledgedAt = trim((string) ($application['PolicyAcknowledgedAt'] ?? ''));
    $version = trim((string) ($application['PolicyVersion'] ?? ''));

    return $acknowledgedAt !== '' && $version === PROVIDER_SIGNUP_POLICY_VERSION;
}

/**
 * @return array{ok: bool, error: ?string, already?: bool}
 */
function provider_signup_acknowledge_policy(string $accessToken): array
{
    $application = provider_signup_get_by_token($accessToken);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_provider_can_edit($application)) {
        return ['ok' => false, 'error' => 'This application can no longer be edited online.'];
    }

    if (provider_signup_has_current_policy_ack($application)) {
        return ['ok' => true, 'error' => null, 'already' => true];
    }

    $email = provider_signup_normalize_email((string) ($application['ProviderEmail'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid provider email is required to acknowledge the policy.'];
    }

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET PolicyAcknowledgedAt = SYSUTCDATETIME(),
                PolicyAcknowledgedByEmail = :email,
                PolicyVersion = :version,
                LastSavedAt = SYSUTCDATETIME()
            WHERE ApplicationID = :id
        SQL)->execute([
            'email'   => $email,
            'version' => PROVIDER_SIGNUP_POLICY_VERSION,
            'id'      => (int) $application['ApplicationID'],
        ]);
    } catch (Throwable $e) {
        error_log('provider_signup_acknowledge_policy: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to record policy acknowledgement.'];
    }

    try {
        provider_signup_add_review_log(
            (int) $application['ApplicationID'],
            null,
            'PolicyAcknowledged',
            'Policy ' . PROVIDER_SIGNUP_POLICY_VERSION . ' acknowledged by ' . $email . '.'
        );
    } catch (Throwable $e) {
        error_log('provider_signup_acknowledge_policy review log: ' . $e->getMessage());
    }

    return ['ok' => true, 'error' => null];
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
        'clinic_type'        => 'Clinic type',
        'admin_first_name'   => 'Admin first name',
        'admin_last_name'    => 'Admin last name',
        'admin_email'        => 'Admin email',
        'npi_number'         => 'NPI #',
    ];

    foreach ($requiredStrings as $field => $label) {
        if (trim((string) ($form[$field] ?? '')) === '') {
            $missing[] = $label;
        }
    }

    if (!provider_signup_is_valid_clinic_type((string) ($form['clinic_type'] ?? ''))) {
        $missing[] = 'Clinic type';
    }

    if (!in_array((string) ($form['tax_id_type'] ?? ''), PROVIDER_SIGNUP_TAX_ID_TYPES, true)) {
        $missing[] = 'Tax ID type (SSN or EIN)';
    }

    $taxId = preg_replace('/\D+/', '', (string) ($form['tax_id'] ?? '')) ?? '';
    $hasStoredTax = provider_signup_get($applicationId)['TaxIdEncrypted'] ?? null;
    if ($taxId === '' && trim((string) $hasStoredTax) === '') {
        $missing[] = 'Tax ID (SSN or EIN)';
    }

    $application = provider_signup_get($applicationId);
    if ($application === null || !provider_signup_has_current_policy_ack($application)) {
        $missing[] = 'Practitioner Reseller Policy acknowledgement';
    }

    $npi = preg_replace('/\D+/', '', (string) ($form['npi_number'] ?? '')) ?? '';
    if ($npi !== '' && strlen($npi) !== 10) {
        $missing[] = 'Valid 10-digit NPI #';
    }

    // ACH format checks only when the provider started filling payout fields.
    $routing = preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? '')) ?? '';
    if ($routing !== '' && strlen($routing) !== 9) {
        $missing[] = 'Valid 9-digit ACH routing #';
    }

    $type = (string) ($form['ach_account_type'] ?? '');
    if ($type !== '' && !in_array($type, PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES, true)) {
        $missing[] = 'ACH account type';
    }

    return [
        'complete' => $missing === [],
        'missing'  => $missing,
    ];
}

function provider_signup_has_ach_info(array $form, int $applicationId): bool
{
    $stored = provider_signup_get($applicationId) ?? [];
    $routing = preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? '')) ?? '';
    if ($routing === '') {
        $routing = preg_replace('/\D+/', '', (string) ($stored['AchRoutingNumber'] ?? '')) ?? '';
    }

    $type = trim((string) ($form['ach_account_type'] ?? ''));
    if ($type === '') {
        $type = (string) ($stored['AchAccountType'] ?? '');
    }

    $account = preg_replace('/\D+/', '', (string) ($form['ach_account_number'] ?? '')) ?? '';
    if ($account === '') {
        $account = preg_replace(
            '/\D+/',
            '',
            provider_signup_decrypt($stored['AchAccountNumberEncrypted'] ?? null)
        ) ?? '';
    }

    return strlen($routing) === 9
        && $account !== ''
        && in_array($type, PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES, true);
}

/**
 * Optional post-submit documents: reseller certificate and ACH.
 * These do not block submit, but the provider is warned about taxable status / no payouts.
 *
 * @return list<string>
 */
function provider_signup_optional_documents_warnings(array $form, int $applicationId): array
{
    $warnings = [];

    if (!provider_signup_has_reseller_certificate($applicationId)) {
        $warnings[] = 'No state reseller certificate was uploaded. Your account will default to taxable status until a certificate is uploaded and validated.';
    }

    if (!provider_signup_has_ach_info($form, $applicationId)) {
        $warnings[] = 'ACH payout details are incomplete. Your clinic can still be provisioned, but you cannot receive a payout until ACH information is received and validated.';
    }

    return $warnings;
}

/**
 * Review warnings that should block approve/provision unless ops explicitly overrides.
 *
 * @return list<array{key: string, label: string, message: string}>
 */
function provider_signup_ops_review_warnings(array $application, int $applicationId, bool $includeProvisionChecks = false): array
{
    $warnings = [];
    $form = provider_signup_form_from_row($application);

    $npiStatus = trim((string) ($application['NpiValidationStatus'] ?? ''));
    if ($npiStatus !== 'Validated') {
        $summary = trim((string) ($application['NpiValidationSummary'] ?? ''));
        $warnings[] = [
            'key'     => 'npi',
            'label'   => 'NPI validation',
            'message' => $summary !== ''
                ? $summary
                : ($npiStatus !== '' ? $npiStatus : 'NPI has not been validated.'),
        ];
    }

    if (!provider_signup_has_reseller_certificate($applicationId)) {
        $warnings[] = [
            'key'     => 'reseller_certificate',
            'label'   => 'Reseller certificate',
            'message' => 'No state reseller certificate uploaded. The account will default to taxable status until a certificate is validated.',
        ];
    }

    $bankResult = provider_signup_banking_validate_format($form, $applicationId);
    $bankStatus = (string) ($bankResult['status'] ?? '');
    if ($bankStatus === 'NotProvided' || $bankStatus === 'Invalid' || !($bankResult['ok'] ?? false)) {
        $warnings[] = [
            'key'     => 'banking',
            'label'   => 'Banking / ACH',
            'message' => (string) ($bankResult['summary'] ?? 'Banking details are incomplete or invalid.'),
        ];
    }

    if ($includeProvisionChecks) {
        $adminEmail = strtolower(trim((string) ($application['AdminEmail'] ?? '')));
        if ($adminEmail !== '') {
            $existing = provider_signup_accs_search_customer_by_email($adminEmail);
            if (($existing['ok'] ?? false)
                && is_array($existing['customer'] ?? null)
                && !empty($existing['customer']['id'])) {
                $warnings[] = [
                    'key'     => 'existing_accs_admin',
                    'label'   => 'Existing ACCS account',
                    'message' => 'Admin email already exists in ACCS as customer #'
                        . (int) $existing['customer']['id']
                        . '. Provisioning will link that account as company admin (no new password).',
                ];
            }
        }
    }

    return $warnings;
}

function provider_signup_ops_review_override_confirmed(array $input): bool
{
    return isset($input['review_override']) && (string) $input['review_override'] === '1';
}

/**
 * @param list<array{key: string, label: string, message: string}> $warnings
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_ops_require_review_override_or_fail(array $warnings, bool $overrideConfirmed, string $actionLabel): array
{
    if ($warnings === [] || $overrideConfirmed) {
        return ['ok' => true, 'error' => null];
    }

    $lines = array_map(
        static fn (array $warning): string => $warning['label'] . ' — ' . $warning['message'],
        $warnings
    );

    return [
        'ok'    => false,
        'error' => 'Cannot ' . $actionLabel . ' until review warnings are acknowledged. '
            . implode(' ', $lines)
            . ' Check "Acknowledge review warnings and proceed" to override.',
    ];
}

/**
 * @param list<array{key: string, label: string, message: string}> $warnings
 */
function provider_signup_ops_format_review_override_log(array $warnings): string
{
    if ($warnings === []) {
        return '';
    }

    $parts = array_map(
        static fn (array $warning): string => $warning['label'],
        $warnings
    );

    return 'Review warnings overridden: ' . implode('; ', $parts) . '.';
}

function provider_signup_banking_validate_format(array $form, int $applicationId): array
{
    $routing = preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? '')) ?? '';
    $account = preg_replace('/\D+/', '', (string) ($form['ach_account_number'] ?? '')) ?? '';
    $type = (string) ($form['ach_account_type'] ?? '');
    $stored = provider_signup_get($applicationId);
    if ($account === '') {
        $account = preg_replace('/\D+/', '', provider_signup_decrypt($stored['AchAccountNumberEncrypted'] ?? null)) ?? '';
    }
    if ($routing === '') {
        $routing = preg_replace('/\D+/', '', (string) ($stored['AchRoutingNumber'] ?? '')) ?? '';
    }
    if ($type === '') {
        $type = (string) ($stored['AchAccountType'] ?? '');
    }

    if ($routing === '' && $account === '' && $type === '') {
        return [
            'ok'      => true,
            'status'  => 'NotProvided',
            'summary' => 'ACH details not provided. Payouts cannot be issued until banking information is received and validated.',
        ];
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

    if (!in_array($type, PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES, true)) {
        return [
            'ok'      => false,
            'status'  => 'Invalid',
            'summary' => 'ACH account type is required.',
        ];
    }

    return [
        'ok'      => true,
        'status'  => 'FormatValid',
        'summary' => 'Banking format validated. Plaid verification is pending integration.',
    ];
}

/**
 * @param bool $sendProviderContinueEmail When true, emails the provider a continue link (and notifies ops).
 * @param bool $notifyOps When false (and provider continue is false), skip all start emails — used for Operations backend create.
 */
function provider_signup_create_application(
    string $providerEmail,
    bool $sendProviderContinueEmail = true,
    bool $notifyOps = true
): array {
    $providerEmail = provider_signup_normalize_email($providerEmail);
    if ($providerEmail === '' || !filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid provider email address is required.', 'application' => null];
    }

    $existing = provider_signup_find_resumable_by_email($providerEmail);
    if ($existing !== null) {
        return ['ok' => true, 'error' => null, 'application' => $existing, 'resumed' => true];
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
        $logMessage = $sendProviderContinueEmail || $notifyOps
            ? 'Application started by provider after email confirmation.'
            : 'Application shell created by Operations (backend create).';
        provider_signup_add_review_log((int) $application['ApplicationID'], null, 'Comment', $logMessage);
    } catch (Throwable $e) {
        error_log('provider_signup_create_application review log: ' . $e->getMessage());
    }

    try {
        if ($sendProviderContinueEmail) {
            provider_signup_mail_application_started($application);
        } elseif ($notifyOps) {
            provider_signup_mail_application_started_ops($application);
        }
    } catch (Throwable $e) {
        error_log('provider_signup_create_application mail: ' . $e->getMessage());
    }

    return ['ok' => true, 'error' => null, 'application' => $application, 'resumed' => false];
}

function provider_signup_request_ip(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        $parts = preg_split('/\s*,\s*/', $candidate) ?: [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }
    }

    return '';
}

/**
 * @return ?array<string, mixed>
 */
function provider_signup_find_resumable_by_email(string $providerEmail): ?array
{
    $providerEmail = provider_signup_normalize_email($providerEmail);
    if ($providerEmail === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP 1 *
        FROM dbo.ProviderSignupApplication
        WHERE ProviderEmail = :email
          AND Status IN (:draft, :returned)
        ORDER BY ApplicationID DESC
    SQL);
    $stmt->execute([
        'email'    => $providerEmail,
        'draft'    => PROVIDER_SIGNUP_STATUS_DRAFT,
        'returned' => PROVIDER_SIGNUP_STATUS_RETURNED,
    ]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Begin signup: captcha + email ownership challenge. Does not create an application yet.
 *
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_request_email_challenge(string $providerEmail, ?string $recaptchaResponse): array
{
    $providerEmail = provider_signup_normalize_email($providerEmail);
    if ($providerEmail === '' || !filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid provider email address is required.'];
    }

    $captcha = provider_signup_recaptcha_verify($recaptchaResponse, provider_signup_request_ip());
    if (!$captcha['ok']) {
        $e2eSecret = trim((string) env('PROVIDER_SIGNUP_E2E_START_SECRET', ''));
        $provided = (string) ($_POST['e2e_start_secret'] ?? '');
        $bypassOk = $e2eSecret !== '' && $provided !== '' && hash_equals($e2eSecret, $provided);
        if (!$bypassOk) {
            return ['ok' => false, 'error' => $captcha['error'] ?? 'Captcha verification failed.'];
        }
    }

    $ip = provider_signup_request_ip();
    try {
        $pdo = db();

        $emailCountStmt = $pdo->prepare(<<<SQL
            SELECT COUNT(*) FROM dbo.ProviderSignupEmailChallenge
            WHERE ProviderEmail = :email
              AND CreatedAt >= DATEADD(HOUR, -1, SYSUTCDATETIME())
        SQL);
        $emailCountStmt->execute(['email' => $providerEmail]);
        if ((int) $emailCountStmt->fetchColumn() >= PROVIDER_SIGNUP_EMAIL_CHALLENGE_MAX_PER_EMAIL_HOUR) {
            return [
                'ok'    => false,
                'error' => 'Too many start attempts for this email. Please wait and check your inbox, or try again later.',
            ];
        }

        if ($ip !== '') {
            $ipCountStmt = $pdo->prepare(<<<SQL
                SELECT COUNT(*) FROM dbo.ProviderSignupEmailChallenge
                WHERE RequestIp = :ip
                  AND CreatedAt >= DATEADD(HOUR, -1, SYSUTCDATETIME())
            SQL);
            $ipCountStmt->execute(['ip' => $ip]);
            if ((int) $ipCountStmt->fetchColumn() >= PROVIDER_SIGNUP_EMAIL_CHALLENGE_MAX_PER_IP_HOUR) {
                return [
                    'ok'    => false,
                    'error' => 'Too many start attempts from this network. Please try again later.',
                ];
            }
        }

        $token = provider_signup_generate_token();
        $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupEmailChallenge (
                ChallengeToken, ProviderEmail, RequestIp, ExpiresAt
            )
            VALUES (
                :token,
                :email,
                :ip,
                DATEADD(MINUTE, :ttl, SYSUTCDATETIME())
            )
        SQL)->execute([
            'token' => $token,
            'email' => $providerEmail,
            'ip'    => $ip !== '' ? $ip : null,
            'ttl'   => PROVIDER_SIGNUP_EMAIL_CHALLENGE_TTL_MINUTES,
        ]);
    } catch (Throwable $e) {
        error_log('provider_signup_request_email_challenge: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to start the application right now. Please try again.'];
    }

    try {
        provider_signup_mail_email_challenge($providerEmail, $token);
    } catch (Throwable $e) {
        error_log('provider_signup_request_email_challenge mail: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to send the confirmation email. Please try again.'];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Consume emailed challenge token and create (or resume) the application.
 *
 * @return array{ok: bool, error: ?string, application: ?array}
 */
function provider_signup_confirm_email_challenge(string $challengeToken): array
{
    $challengeToken = trim($challengeToken);
    if ($challengeToken === '' || !preg_match('/^[a-f0-9]{64}$/', $challengeToken)) {
        return ['ok' => false, 'error' => 'This confirmation link is invalid.', 'application' => null];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT TOP 1 *
            FROM dbo.ProviderSignupEmailChallenge
            WHERE ChallengeToken = :token
        SQL);
        $stmt->execute(['token' => $challengeToken]);
        $challenge = $stmt->fetch();
        if ($challenge === false) {
            return ['ok' => false, 'error' => 'This confirmation link is invalid or has already been used.', 'application' => null];
        }

        if (!empty($challenge['ConsumedAt'])) {
            // Already used — if an app exists, resume it instead of erroring harshly.
            $email = provider_signup_normalize_email((string) ($challenge['ProviderEmail'] ?? ''));
            $existing = provider_signup_find_resumable_by_email($email);
            if ($existing !== null) {
                return ['ok' => true, 'error' => null, 'application' => $existing];
            }

            return ['ok' => false, 'error' => 'This confirmation link has already been used.', 'application' => null];
        }

        $expiresAt = strtotime((string) ($challenge['ExpiresAt'] ?? ''));
        if ($expiresAt !== false && $expiresAt < time()) {
            return ['ok' => false, 'error' => 'This confirmation link has expired. Please start again.', 'application' => null];
        }

        $email = provider_signup_normalize_email((string) ($challenge['ProviderEmail'] ?? ''));
        $created = provider_signup_create_application($email, false);
        if (!$created['ok'] || !is_array($created['application'])) {
            return [
                'ok'          => false,
                'error'       => $created['error'] ?? 'Unable to create provider application.',
                'application' => null,
            ];
        }

        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupEmailChallenge
            SET ConsumedAt = SYSUTCDATETIME()
            WHERE ChallengeID = :id AND ConsumedAt IS NULL
        SQL)->execute(['id' => (int) $challenge['ChallengeID']]);

        return ['ok' => true, 'error' => null, 'application' => $created['application']];
    } catch (Throwable $e) {
        error_log('provider_signup_confirm_email_challenge: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to confirm your email right now.', 'application' => null];
    }
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

    if (!provider_signup_provider_can_edit($application)) {
        return ['ok' => false, 'error' => 'This application can no longer be edited online.'];
    }

    $applicationId = (int) $application['ApplicationID'];
    $persist = provider_signup_persist_form($applicationId, $form, true);
    if (!$persist['ok']) {
        return $persist;
    }

    $fresh = provider_signup_get($applicationId);
    if ($fresh === null) {
        return ['ok' => false, 'error' => 'Unable to load application after save.'];
    }

    $checklist = provider_signup_submit_checklist(provider_signup_form_from_row($fresh), $applicationId);
    if (!$checklist['complete']) {
        return [
            'ok'      => false,
            'error'   => 'Please complete the following before submitting: ' . implode(', ', $checklist['missing']) . '.',
            'missing' => $checklist['missing'],
        ];
    }

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET Status = :status,
                SubmittedAt = COALESCE(SubmittedAt, SYSUTCDATETIME()),
                LastSavedAt = SYSUTCDATETIME()
            WHERE ApplicationID = :id
        SQL)->execute([
            'status' => PROVIDER_SIGNUP_STATUS_SUBMITTED,
            'id'     => $applicationId,
        ]);
    } catch (Throwable $e) {
        error_log('provider_signup_submit: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to submit the application.'];
    }

    try {
        provider_signup_add_review_log(
            $applicationId,
            null,
            'Submitted',
            'Application submitted for review by provider.'
        );
    } catch (Throwable $e) {
        error_log('provider_signup_submit review log: ' . $e->getMessage());
    }

    $submitted = provider_signup_get($applicationId) ?? $fresh;
    $documentWarnings = provider_signup_optional_documents_warnings(
        provider_signup_form_from_row($submitted),
        $applicationId
    );

    try {
        provider_signup_mail_application_submitted($submitted, $documentWarnings);
    } catch (Throwable $e) {
        error_log('provider_signup_submit mail: ' . $e->getMessage());
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @return array{ok: bool, error: ?string, company_id?: ?int, customer_id?: ?int, clinic_id?: ?string, already?: bool}
 */
function provider_signup_finalize_provision(int $applicationId, ?int $reviewerUserId, string $logComments): array
{
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if ((string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_PROVISIONED) {
        return ['ok' => true, 'error' => null, 'already' => true];
    }

    if ((string) ($application['Status'] ?? '') !== PROVIDER_SIGNUP_STATUS_APPROVED) {
        return ['ok' => false, 'error' => 'Application must be approved before creating the ACCS company.'];
    }

    $provision = provider_signup_provision($applicationId);
    if (!$provision['ok']) {
        try {
            $pdo = db();
            $pdo->prepare(<<<SQL
                UPDATE dbo.ProviderSignupApplication
                SET LastProvisionError = ?,
                    LastSavedAt = SYSUTCDATETIME()
                WHERE ApplicationID = ?
            SQL)->execute([
                provider_signup_nullable_string((string) ($provision['error'] ?? 'Provisioning failed.')),
                $applicationId,
            ]);
        } catch (Throwable) {
            /* keep original provisioning error */
        }

        provider_signup_add_review_log(
            $applicationId,
            $reviewerUserId,
            'ProvisionFailed',
            (string) ($provision['error'] ?? 'Provisioning failed.')
        );

        return $provision;
    }

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET Status = ?,
                SubmittedAt = COALESCE(SubmittedAt, SYSUTCDATETIME()),
                ProvisionedAt = SYSUTCDATETIME(),
                LastSavedAt = SYSUTCDATETIME(),
                AccsEnvironment = ?,
                AccsCompanyId = ?,
                AccsCustomerId = ?,
                AccsClinicId = ?,
                AccsStepClinicDone = 1,
                AccsStepClinicAt = SYSUTCDATETIME(),
                AccsStepAdminDone = 1,
                AccsStepAdminAt = SYSUTCDATETIME(),
                AccsConfigurationComplete = 0,
                AccsConfigurationCompletedAt = NULL,
                LastProvisionError = NULL
            WHERE ApplicationID = ?
        SQL)->execute([
            PROVIDER_SIGNUP_STATUS_PROVISIONED,
            provider_signup_accs_target_environment(),
            $provision['company_id'] ?? null,
            $provision['customer_id'] ?? null,
            provider_signup_nullable_string((string) ($provision['clinic_id'] ?? '')),
            $applicationId,
        ]);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to update application after provisioning.'];
    }

    provider_signup_add_review_log($applicationId, $reviewerUserId, 'Provisioned', $logComments);
    $updated = provider_signup_get($applicationId);
    if ($updated !== null) {
        provider_signup_mail_provisioned(
            $updated,
            isset($provision['temporary_password']) ? (string) $provision['temporary_password'] : null
        );

        $configResult = provider_signup_accs_complete_clinic_configuration($updated);
        if ($configResult['ok']) {
            $persist = provider_signup_persist_accs_config_result($applicationId, $configResult);
            if ($persist['ok'] && !empty($persist['configuration_complete'])) {
                provider_signup_add_review_log(
                    $applicationId,
                    $reviewerUserId,
                    'Comment',
                    'ACCS clinic configuration completed automatically after provision.'
                );
            } elseif (!$persist['ok']) {
                provider_signup_add_review_log(
                    $applicationId,
                    $reviewerUserId,
                    'Comment',
                    'ACCS clinic configuration automation saved with errors: '
                    . ($persist['error'] ?? 'Unable to persist configuration results.')
                );
            }
        } else {
            provider_signup_add_review_log(
                $applicationId,
                $reviewerUserId,
                'Comment',
                'ACCS clinic configuration automation pending: '
                . ($configResult['error'] ?? 'Unknown error')
            );
        }
    }

    return ['ok' => true, 'error' => null];
}

function provider_signup_ops_provision(int $applicationId, array $options = []): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_ops_can_provision($application)) {
        return ['ok' => false, 'error' => 'This application is not ready for ACCS company creation.'];
    }

    $warnings = provider_signup_ops_review_warnings($application, $applicationId, true);
    $overrideConfirmed = provider_signup_ops_review_override_confirmed($options);
    $gate = provider_signup_ops_require_review_override_or_fail($warnings, $overrideConfirmed, 'create the Clinic Store');
    if (!$gate['ok']) {
        return ['ok' => false, 'error' => $gate['error']];
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    $logComments = 'ACCS company created by operations reviewer.';
    if ($overrideConfirmed && $warnings !== []) {
        $logComments .= ' ' . provider_signup_ops_format_review_override_log($warnings);
    }

    return provider_signup_finalize_provision(
        $applicationId,
        $reviewerId > 0 ? $reviewerId : null,
        $logComments
    );
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
                ClinicType = :clinic_type,
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
            'clinic_type'         => provider_signup_is_valid_clinic_type((string) ($form['clinic_type'] ?? ''))
                ? trim((string) $form['clinic_type']) : null,
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

/**
 * Save ACH payout fields after submit (complete-documents mode).
 *
 * @param array<string, mixed> $form
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_save_documents(string $accessToken, array $form): array
{
    $application = provider_signup_get_by_token($accessToken);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_provider_can_complete_documents($application)) {
        return ['ok' => false, 'error' => 'Documents can no longer be updated for this application.'];
    }

    $applicationId = (int) $application['ApplicationID'];
    $routing = preg_replace('/\D+/', '', (string) ($form['ach_routing_number'] ?? '')) ?? '';
    $account = preg_replace('/\D+/', '', (string) ($form['ach_account_number'] ?? '')) ?? '';
    $type = (string) ($form['ach_account_type'] ?? '');
    $hasStoredAccount = trim((string) ($application['AchAccountNumberEncrypted'] ?? '')) !== '';

    $anyProvided = $routing !== '' || $account !== '' || $type !== '';
    if (!$anyProvided && !$hasStoredAccount) {
        return ['ok' => false, 'error' => 'Enter ACH routing #, account #, and account type to save payout details.'];
    }

    if ($routing !== '' && strlen($routing) !== 9) {
        return ['ok' => false, 'error' => 'ACH routing number must be 9 digits.'];
    }

    if ($type !== '' && !in_array($type, PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid ACH account type.'];
    }

    if ($account === '' && !$hasStoredAccount) {
        return ['ok' => false, 'error' => 'ACH account number is required.'];
    }

    // If updating any ACH field, require a complete set (stored account may satisfy account #).
    $effectiveRouting = $routing !== '' ? $routing : preg_replace('/\D+/', '', (string) ($application['AchRoutingNumber'] ?? '')) ?? '';
    $effectiveType = $type !== '' ? $type : (string) ($application['AchAccountType'] ?? '');
    if (strlen($effectiveRouting) !== 9) {
        return ['ok' => false, 'error' => 'ACH routing number must be 9 digits.'];
    }
    if (!in_array($effectiveType, PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid ACH account type.'];
    }

    $accountEncrypted = $account !== ''
        ? provider_signup_encrypt($account)
        : (string) ($application['AchAccountNumberEncrypted'] ?? '');

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET AchRoutingNumber = :ach_routing_number,
                AchAccountNumberEncrypted = :ach_account_encrypted,
                AchAccountType = :ach_account_type,
                LastSavedAt = SYSUTCDATETIME()
            WHERE ApplicationID = :id
        SQL)->execute([
            'ach_routing_number'    => $effectiveRouting,
            'ach_account_encrypted' => $accountEncrypted !== '' ? $accountEncrypted : null,
            'ach_account_type'      => $effectiveType,
            'id'                    => $applicationId,
        ]);
    } catch (Throwable $e) {
        error_log('provider_signup_save_documents: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save ACH details.'];
    }

    try {
        provider_signup_add_review_log(
            $applicationId,
            null,
            'DocumentsUpdated',
            'Provider updated ACH payout details.'
        );
    } catch (Throwable $e) {
        error_log('provider_signup_save_documents review log: ' . $e->getMessage());
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

    if (!provider_signup_provider_can_complete_documents($application)) {
        return ['ok' => false, 'error' => 'This application can no longer accept document uploads.'];
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

        $oldStmt = $pdo->prepare(<<<SQL
            SELECT AttachmentID, BlobPath
            FROM dbo.ProviderSignupAttachment
            WHERE ApplicationID = :id AND AttachmentKind = N'ResellerCertificate'
        SQL);
        $oldStmt->execute(['id' => $applicationId]);
        $oldRows = $oldStmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupAttachment (
                ApplicationID, FileName, ContentType, FileSizeBytes, FileData, BlobPath, IsEncrypted, AttachmentKind
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:application_id, :name, :type, :size, NULL, NULL, 0, N'ResellerCertificate')
        SQL);
        $stmt->bindValue(':application_id', $applicationId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', (int) ($file['size'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();

        $attachmentId = db_fetch_inserted_int($stmt, 'inserted_id');
        $stored = attachment_storage_save(
            'provider-signup',
            $applicationId,
            $attachmentId,
            $fileName,
            $contentType,
            $content,
            ['encrypt' => true]
        );
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.ProviderSignupAttachment WHERE AttachmentID = :id')
                ->execute(['id' => $attachmentId]);

            return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to save the reseller certificate.'];
        }

        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupAttachment
            SET BlobPath = :path, FileData = NULL, IsEncrypted = 1
            WHERE AttachmentID = :id
        SQL)->execute([
            'path' => $stored['blob_path'],
            'id'   => $attachmentId,
        ]);

        foreach ($oldRows as $oldRow) {
            $oldId = (int) ($oldRow['AttachmentID'] ?? 0);
            if ($oldId > 0 && $oldId !== $attachmentId) {
                attachment_storage_delete_row_blob($oldRow);
                $pdo->prepare('DELETE FROM dbo.ProviderSignupAttachment WHERE AttachmentID = :id')
                    ->execute(['id' => $oldId]);
            }
        }

        return ['ok' => true, 'error' => null, 'id' => $attachmentId];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save the reseller certificate.'];
    }
}

function provider_signup_list_attachments(int $applicationId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT AttachmentID, FileName, ContentType, FileSizeBytes, AttachmentKind, UploadDate, BlobPath, IsEncrypted
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
    $resolved = attachment_storage_resolve_content($attachment);

    return $resolved['ok'] ? (string) $resolved['content'] : '';
}

function provider_signup_list_applications(array $filters = []): array
{
    return provider_signup_list_applications_page($filters)['rows'];
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

/**
 * Create a clinic application from Operations (no public signup flow).
 *
 * @param array<string, mixed> $form
 * @return array{ok: bool, error: ?string, application_id: ?int}
 */
function provider_signup_ops_create_clinic(array $form, bool $markApproved = false): array
{
    provider_signup_require_update();

    $providerEmail = provider_signup_normalize_email((string) ($form['provider_email'] ?? ''));
    if ($providerEmail === '' || !filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid provider email is required.', 'application_id' => null];
    }

    $adminEmail = provider_signup_normalize_email((string) ($form['admin_email'] ?? ''));
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'A valid admin email is required.', 'application_id' => null];
    }

    $existing = provider_signup_find_resumable_by_email($providerEmail);
    if ($existing !== null) {
        $existingId = (int) ($existing['ApplicationID'] ?? 0);

        return [
            'ok'             => false,
            'error'          => 'A draft/returned application already exists for this provider email (#'
                . $existingId . '). Open that application instead of creating a duplicate.',
            'application_id' => $existingId > 0 ? $existingId : null,
        ];
    }

    // Validate required clinic fields before inserting a row.
    $probeMissing = [];
    foreach ([
        'company_name'       => 'Practice / company name',
        'company_legal_name' => 'Legal company name',
        'company_email'      => 'Company email',
        'company_phone'      => 'Company phone',
        'street_address'     => 'Street address',
        'city'               => 'City',
        'state_code'         => 'State',
        'postal_code'        => 'Postal code',
        'clinic_type'        => 'Clinic type',
        'admin_first_name'   => 'Admin first name',
        'admin_last_name'    => 'Admin last name',
        'npi_number'         => 'NPI #',
        'tax_id_type'        => 'Tax ID type',
        'tax_id'             => 'Tax ID (SSN or EIN)',
    ] as $field => $label) {
        if (trim((string) ($form[$field] ?? '')) === '') {
            $probeMissing[] = $label;
        }
    }
    if (!provider_signup_is_valid_clinic_type((string) ($form['clinic_type'] ?? ''))) {
        $probeMissing[] = 'Clinic type';
    }
    if (!in_array((string) ($form['tax_id_type'] ?? ''), PROVIDER_SIGNUP_TAX_ID_TYPES, true)) {
        $probeMissing[] = 'Tax ID type (SSN or EIN)';
    }
    $npi = preg_replace('/\D+/', '', (string) ($form['npi_number'] ?? '')) ?? '';
    if ($npi === '' || strlen($npi) !== 10) {
        $probeMissing[] = 'Valid 10-digit NPI #';
    }
    if ($probeMissing !== []) {
        return [
            'ok'             => false,
            'error'          => 'Missing or invalid fields: ' . implode(', ', array_values(array_unique($probeMissing))) . '.',
            'application_id' => null,
        ];
    }

    $created = provider_signup_create_application($providerEmail, false, false);
    if (!$created['ok'] || !is_array($created['application'] ?? null)) {
        return [
            'ok'             => false,
            'error'          => $created['error'] ?? 'Unable to create clinic application.',
            'application_id' => null,
        ];
    }

    $application = $created['application'];
    $applicationId = (int) ($application['ApplicationID'] ?? 0);
    if ($applicationId <= 0) {
        return ['ok' => false, 'error' => 'Unable to load the new clinic application.', 'application_id' => null];
    }

    // If create_application resumed an unexpected row, stop before overwriting.
    if (!empty($created['resumed'])) {
        return [
            'ok'             => false,
            'error'          => 'A resumable application already exists for this provider email (#'
                . $applicationId . '). Open that application instead.',
            'application_id' => $applicationId,
        ];
    }

    $form['provider_email'] = $providerEmail;
    $form['admin_email'] = $adminEmail;
    $persist = provider_signup_persist_form($applicationId, $form, false);
    if (!$persist['ok']) {
        return [
            'ok'             => false,
            'error'          => $persist['error'] ?? 'Unable to save clinic details.',
            'application_id' => $applicationId,
        ];
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    $opsEmail = provider_signup_normalize_email((string) (auth_user()['Email'] ?? $providerEmail));

    try {
        $pdo = db();
        $pdo->prepare(<<<SQL
            UPDATE dbo.ProviderSignupApplication
            SET PolicyAcknowledgedAt = SYSUTCDATETIME(),
                PolicyAcknowledgedByEmail = :email,
                PolicyVersion = :version,
                LastSavedAt = SYSUTCDATETIME()
            WHERE ApplicationID = :id
        SQL)->execute([
            'email'   => $opsEmail !== '' ? $opsEmail : $providerEmail,
            'version' => PROVIDER_SIGNUP_POLICY_VERSION,
            'id'      => $applicationId,
        ]);
    } catch (Throwable $e) {
        error_log('provider_signup_ops_create_clinic policy ack: ' . $e->getMessage());

        return [
            'ok'             => false,
            'error'          => 'Clinic was created but policy acknowledgement could not be recorded.',
            'application_id' => $applicationId,
        ];
    }

    provider_signup_add_review_log(
        $applicationId,
        $reviewerId > 0 ? $reviewerId : null,
        'Comment',
        'Clinic application created by Operations (backend create). Policy '
        . PROVIDER_SIGNUP_POLICY_VERSION . ' recorded on behalf of the clinic.'
    );

    if ($markApproved) {
        $approve = provider_signup_ops_approve(
            $applicationId,
            'Approved immediately after Operations backend clinic create.',
            ['review_override' => '1']
        );
        if (!$approve['ok']) {
            return [
                'ok'             => false,
                'error'          => 'Clinic was created, but approval failed: '
                    . ($approve['error'] ?? 'unknown error')
                    . ' Open the application to finish review.',
                'application_id' => $applicationId,
            ];
        }
    }

    return ['ok' => true, 'error' => null, 'application_id' => $applicationId];
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
    return provider_signup_ops_revert_status($applicationId, PROVIDER_SIGNUP_STATUS_RETURNED, $comments);
}

function provider_signup_ops_revert_status(int $applicationId, string $targetStatus, string $comments): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_ops_can_revert($application)) {
        return ['ok' => false, 'error' => 'This application status cannot be changed back to the provider.'];
    }

    if (!in_array($targetStatus, PROVIDER_SIGNUP_OPS_REVERT_TARGET_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid target status.'];
    }

    $comments = trim($comments);
    if ($targetStatus === PROVIDER_SIGNUP_STATUS_RETURNED && $comments === '') {
        return ['ok' => false, 'error' => 'Please explain what the provider needs to update.'];
    }

    $currentStatus = (string) ($application['Status'] ?? '');
    $clearApprovalState = in_array($currentStatus, [
        PROVIDER_SIGNUP_STATUS_APPROVED,
        PROVIDER_SIGNUP_STATUS_SUBMITTED,
        PROVIDER_SIGNUP_STATUS_PENDING_VALIDATION,
    ], true);

    try {
        $pdo = db();
        if ($clearApprovalState) {
            $pdo->prepare(<<<SQL
                UPDATE dbo.ProviderSignupApplication
                SET Status = ?,
                    LastSavedAt = SYSUTCDATETIME(),
                    LastProvisionError = NULL
                WHERE ApplicationID = ?
            SQL)->execute([$targetStatus, $applicationId]);
        } else {
            $pdo->prepare(<<<SQL
                UPDATE dbo.ProviderSignupApplication
                SET Status = ?,
                    LastSavedAt = SYSUTCDATETIME()
                WHERE ApplicationID = ?
            SQL)->execute([$targetStatus, $applicationId]);
        }
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to update application status.'];
    }

    $reviewerId = (int) (auth_user()['UserID'] ?? 0);
    $logAction = $targetStatus === PROVIDER_SIGNUP_STATUS_RETURNED ? 'Returned' : 'Reopened';
    $logComments = $comments !== ''
        ? $comments
        : ($targetStatus === PROVIDER_SIGNUP_STATUS_DRAFT
            ? 'Application reopened as draft for provider edits.'
            : 'Application returned to provider.');

    provider_signup_add_review_log($applicationId, $reviewerId, $logAction, $logComments);

    $updated = provider_signup_get($applicationId);
    if ($updated !== null) {
        if ($targetStatus === PROVIDER_SIGNUP_STATUS_RETURNED) {
            provider_signup_mail_returned($updated, $comments);
        } else {
            provider_signup_mail_reopened($updated, $comments);
        }
    }

    return ['ok' => true, 'error' => null, 'target_status' => $targetStatus];
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

function provider_signup_npi_validate_for_application(int $applicationId, string $npiNumber, array $application): array
{
    $result = provider_signup_npi_validate($npiNumber);
    $result['snapshot_id'] = provider_signup_npi_save_snapshot($applicationId, $npiNumber, $result, $application);

    return $result;
}

function provider_signup_ops_validate_npi(int $applicationId): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $result = provider_signup_npi_validate_for_application(
        $applicationId,
        (string) ($application['NpiNumber'] ?? ''),
        $application
    );
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

function provider_signup_ops_approve(int $applicationId, string $comments = '', array $options = []): array
{
    provider_signup_require_update();
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    if (!provider_signup_ops_can_approve($application)) {
        return ['ok' => false, 'error' => 'This application cannot be approved in its current status.'];
    }

    $warnings = provider_signup_ops_review_warnings($application, $applicationId, false);
    $overrideConfirmed = provider_signup_ops_review_override_confirmed($options);
    $gate = provider_signup_ops_require_review_override_or_fail($warnings, $overrideConfirmed, 'approve this application');
    if (!$gate['ok']) {
        return ['ok' => false, 'error' => $gate['error']];
    }

    $form = provider_signup_form_from_row($application);
    $checklist = provider_signup_submit_checklist($form, $applicationId);
    if (!$checklist['complete']) {
        return [
            'ok'    => false,
            'error' => 'Complete application data is required before approval: ' . implode(', ', $checklist['missing']) . '.',
        ];
    }

    $npiResult = provider_signup_npi_validate_for_application(
        $applicationId,
        (string) ($form['npi_number'] ?? ''),
        $application
    );
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
    $logComments = trim($comments);
    if ($overrideConfirmed && $warnings !== []) {
        $overrideLog = provider_signup_ops_format_review_override_log($warnings);
        $logComments = trim($logComments . ($logComments !== '' ? ' ' : '') . $overrideLog);
    }
    provider_signup_add_review_log($applicationId, $reviewerId, 'Approved', $logComments);

    return ['ok' => true, 'error' => null];
}

function provider_signup_provision(int $applicationId): array
{
    $application = provider_signup_get($applicationId);
    if ($application === null) {
        return ['ok' => false, 'error' => 'Application not found.'];
    }

    $result = provider_signup_accs_provision($application);
    if (!$result['ok']) {
        return [
            'ok'          => false,
            'error'       => $result['error'] ?? 'ACCS provisioning failed.',
            'company_id'  => null,
            'customer_id' => null,
            'clinic_id'   => null,
        ];
    }

    return [
        'ok'                 => true,
        'error'              => null,
        'company_id'         => $result['company_id'] ?? null,
        'customer_id'        => $result['customer_id'] ?? null,
        'clinic_id'          => $result['clinic_id'] ?? null,
        'temporary_password' => $result['temporary_password'] ?? null,
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

function provider_signup_accs_environment_label(?string $environment): string
{
    $environment = strtolower(trim((string) $environment));
    if ($environment === '') {
        return '—';
    }

    return match ($environment) {
        'production' => 'Production',
        'stage'      => 'Stage',
        'dev'        => 'Dev',
        default      => ucfirst($environment),
    };
}

function provider_signup_format_datetime(DateTimeInterface|string|null $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        // DB timestamps are UTC (SYSUTCDATETIME); display in US Central.
        $raw = $value instanceof DateTimeInterface
            ? $value->format('Y-m-d H:i:s')
            : (string) $value;
        $dt = new DateTimeImmutable($raw, new DateTimeZone('UTC'));

        return $dt->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y g:i A T');
    } catch (Throwable) {
        return is_scalar($value) ? (string) $value : '—';
    }
}
