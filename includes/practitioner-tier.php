<?php

require_once __DIR__ . '/provider-signup-accs.php';

const PRACTITIONER_TIER_PERMISSION_COLUMN = 'ProviderAccountReview';
const PRACTITIONER_TIER_ENVIRONMENTS = ['production', 'stage'];
const PRACTITIONER_TIER_EXCLUDED_COMPANY_NAMES = [
    'Clinic_Template',
    'Clinic Template',
];

function practitioner_tier_can_read(): bool
{
    return permission_can_read(auth_permission_value(PRACTITIONER_TIER_PERMISSION_COLUMN));
}

function practitioner_tier_can_update(): bool
{
    return permission_can_update(auth_permission_value(PRACTITIONER_TIER_PERMISSION_COLUMN));
}

function practitioner_tier_require_read(): void
{
    auth_require_login();
    if (practitioner_tier_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view practitioner tiers.');
}

function practitioner_tier_require_update(): void
{
    practitioner_tier_require_read();
    if (practitioner_tier_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update practitioner tiers.');
}

function practitioner_tier_normalize_environment(?string $environment): ?string
{
    $environment = strtolower(trim((string) $environment));
    return in_array($environment, PRACTITIONER_TIER_ENVIRONMENTS, true) ? $environment : null;
}

function practitioner_tier_environment_from_request(?string $fallback = 'production'): string
{
    $fromGet = practitioner_tier_normalize_environment($_GET['env'] ?? null);
    if ($fromGet !== null) {
        return $fromGet;
    }
    $fromPost = practitioner_tier_normalize_environment($_POST['env'] ?? null);
    if ($fromPost !== null) {
        return $fromPost;
    }

    return practitioner_tier_normalize_environment($fallback) ?? 'production';
}

function practitioner_tier_environment_label(string $environment): string
{
    return match (practitioner_tier_normalize_environment($environment) ?? '') {
        'production' => 'Production',
        'stage'      => 'Stage',
        default      => ucfirst($environment),
    };
}

function practitioner_tier_sql_source_environment(string $environment): string
{
    return practitioner_tier_normalize_environment($environment) ?? 'production';
}

/**
 * @return list<array<string, mixed>>
 */
function practitioner_tier_list_thresholds(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT TierCode, DisplayName, MinRevenue, MaxRevenue, StageGroupId, ProdGroupId, SortOrder, IsActive, UpdatedAt
        FROM dbo.PractitionerTierThreshold
        WHERE IsActive = 1
        ORDER BY SortOrder ASC
    SQL);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string, mixed> $threshold
 */
function practitioner_tier_group_id_for_environment(array $threshold, string $environment): int
{
    $environment = practitioner_tier_sql_source_environment($environment);

    return $environment === 'stage'
        ? (int) ($threshold['StageGroupId'] ?? 0)
        : (int) ($threshold['ProdGroupId'] ?? 0);
}

/**
 * @return array{ok: bool, error: ?string}
 */
function practitioner_tier_update_threshold(
    string $tierCode,
    float $minRevenue,
    ?float $maxRevenue,
    int $stageGroupId,
    int $prodGroupId
): array {
    $tierCode = trim($tierCode);
    if ($tierCode === '' || $stageGroupId <= 0 || $prodGroupId <= 0) {
        return ['ok' => false, 'error' => 'Tier code and group IDs are required.'];
    }
    if ($maxRevenue !== null && $maxRevenue < $minRevenue) {
        return ['ok' => false, 'error' => 'Max revenue must be greater than or equal to min revenue.'];
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.PractitionerTierThreshold
            SET MinRevenue = :min_revenue,
                MaxRevenue = :max_revenue,
                StageGroupId = :stage_group_id,
                ProdGroupId = :prod_group_id,
                UpdatedAt = SYSUTCDATETIME()
            WHERE TierCode = :tier_code
        SQL);
        $stmt->bindValue(':min_revenue', $minRevenue);
        if ($maxRevenue === null) {
            $stmt->bindValue(':max_revenue', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':max_revenue', $maxRevenue);
        }
        $stmt->bindValue(':stage_group_id', $stageGroupId, PDO::PARAM_INT);
        $stmt->bindValue(':prod_group_id', $prodGroupId, PDO::PARAM_INT);
        $stmt->bindValue(':tier_code', $tierCode);
        $stmt->execute();

        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'error' => 'Threshold row not found.'];
        }

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('practitioner_tier_update_threshold: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to update threshold.'];
    }
}

/**
 * @return array<string, mixed>|null
 */
function practitioner_tier_resolve_for_revenue(float $revenue, string $environment): ?array
{
    foreach (practitioner_tier_list_thresholds() as $row) {
        $min = (float) ($row['MinRevenue'] ?? 0);
        $max = $row['MaxRevenue'];
        $maxVal = $max === null || $max === '' ? null : (float) $max;
        if ($revenue + 0.00001 < $min) {
            continue;
        }
        if ($maxVal !== null && $revenue - 0.00001 > $maxVal) {
            continue;
        }
        $row['ResolvedGroupId'] = practitioner_tier_group_id_for_environment($row, $environment);

        return $row;
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function practitioner_tier_threshold_by_code(string $tierCode): ?array
{
    foreach (practitioner_tier_list_thresholds() as $row) {
        if ((string) ($row['TierCode'] ?? '') === $tierCode) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function practitioner_tier_threshold_by_group_id(int $groupId, string $environment): ?array
{
    foreach (practitioner_tier_list_thresholds() as $row) {
        if (practitioner_tier_group_id_for_environment($row, $environment) === $groupId) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function practitioner_tier_get_override(int $companyId, string $environment): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT OverrideID, AccsCompanyId, SourceEnvironment, LockedGroupId, LockedTierCode,
               IsLocked, Notes, LockedBy, LockedAt, UpdatedAt
        FROM dbo.PractitionerTierOverride
        WHERE AccsCompanyId = :company_id
          AND SourceEnvironment = :env
    SQL);
    $stmt->execute([
        ':company_id' => $companyId,
        ':env'        => practitioner_tier_sql_source_environment($environment),
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @return array{ok: bool, error: ?string}
 */
function practitioner_tier_set_override(
    int $companyId,
    string $environment,
    bool $isLocked,
    ?int $lockedGroupId,
    ?string $lockedTierCode,
    ?string $notes,
    ?string $actor
): array {
    if ($companyId <= 0) {
        return ['ok' => false, 'error' => 'Company ID is required.'];
    }
    $environment = practitioner_tier_sql_source_environment($environment);

    try {
        $pdo = db();
        $existing = practitioner_tier_get_override($companyId, $environment);
        $locked = $isLocked ? 1 : 0;

        if (is_array($existing)) {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.PractitionerTierOverride
                SET LockedGroupId = :locked_group_id,
                    LockedTierCode = :locked_tier_code,
                    IsLocked = :is_locked,
                    Notes = :notes,
                    LockedBy = CASE WHEN :lock_actor_flag = 1 THEN :locked_by ELSE LockedBy END,
                    LockedAt = CASE WHEN :lock_time_flag = 1 THEN SYSUTCDATETIME() ELSE LockedAt END,
                    UpdatedAt = SYSUTCDATETIME()
                WHERE AccsCompanyId = :company_id
                  AND SourceEnvironment = :env
            SQL);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':env', $environment);
            $stmt->bindValue(':locked_group_id', $lockedGroupId, $lockedGroupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':locked_tier_code', $lockedTierCode);
            $stmt->bindValue(':is_locked', $locked, PDO::PARAM_INT);
            $stmt->bindValue(':notes', $notes);
            $stmt->bindValue(':lock_actor_flag', $locked, PDO::PARAM_INT);
            $stmt->bindValue(':lock_time_flag', $locked, PDO::PARAM_INT);
            $stmt->bindValue(':locked_by', $actor);
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.PractitionerTierOverride (
                    AccsCompanyId, SourceEnvironment, LockedGroupId, LockedTierCode,
                    IsLocked, Notes, LockedBy, LockedAt
                ) VALUES (
                    :company_id, :env, :locked_group_id, :locked_tier_code,
                    :is_locked, :notes, :locked_by,
                    CASE WHEN :lock_time_flag = 1 THEN SYSUTCDATETIME() ELSE NULL END
                )
            SQL);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':env', $environment);
            $stmt->bindValue(':locked_group_id', $lockedGroupId, $lockedGroupId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':locked_tier_code', $lockedTierCode);
            $stmt->bindValue(':is_locked', $locked, PDO::PARAM_INT);
            $stmt->bindValue(':notes', $notes);
            $stmt->bindValue(':locked_by', $actor);
            $stmt->bindValue(':lock_time_flag', $locked, PDO::PARAM_INT);
            $stmt->execute();
        }

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('practitioner_tier_set_override: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save override.'];
    }
}

/**
 * Calendar YTD bounds in America/Chicago, returned as UTC-naive datetimes for SQL comparison
 * against AccsSalesOrderHeader.OrderCreatedAt (ACCS store timezone is America/Chicago).
 *
 * @return array{start: string, end: string, year: int}
 */
function practitioner_tier_ytd_bounds(?DateTimeImmutable $now = null): array
{
    $tz = new DateTimeZone('America/Chicago');
    $now = $now?->setTimezone($tz) ?? new DateTimeImmutable('now', $tz);
    $year = (int) $now->format('Y');
    $start = new DateTimeImmutable($year . '-01-01 00:00:00', $tz);

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end'   => $now->format('Y-m-d H:i:s'),
        'year'  => $year,
    ];
}

/**
 * @param list<int> $customerIds
 * @return array{total: float, by_customer: array<int, float>}
 */
function practitioner_tier_sum_order_revenue(array $customerIds, string $environment): array
{
    $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds), static fn ($id) => $id > 0)));
    if ($customerIds === []) {
        return ['total' => 0.0, 'by_customer' => []];
    }

    $bounds = practitioner_tier_ytd_bounds();
    $env = practitioner_tier_sql_source_environment($environment);
    $pdo = db();

    $placeholders = [];
    $params = [
        ':env'   => $env,
        ':start' => $bounds['start'],
        ':end'   => $bounds['end'],
    ];
    foreach ($customerIds as $i => $id) {
        $key = ':c' . $i;
        $placeholders[] = $key;
        $params[$key] = $id;
    }

    $inList = implode(', ', $placeholders);
    $sql = <<<SQL
        SELECT CustomerId, CAST(SUM(ISNULL(GrandTotal, 0)) AS DECIMAL(18,2)) AS Revenue
        FROM dbo.AccsSalesOrderHeader
        WHERE SourceEnvironment = :env
          AND CustomerId IN ($inList)
          AND OrderCreatedAt >= :start
          AND OrderCreatedAt <= :end
          AND LOWER(ISNULL(OrderStatus, N'')) NOT IN (N'canceled', N'cancelled', N'fraud', N'paypal_canceled_reversal')
        GROUP BY CustomerId
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $byCustomer = [];
    $total = 0.0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $cid = (int) ($row['CustomerId'] ?? 0);
        $rev = (float) ($row['Revenue'] ?? 0);
        if ($cid > 0) {
            $byCustomer[$cid] = $rev;
            $total += $rev;
        }
    }

    return ['total' => round($total, 2), 'by_customer' => $byCustomer];
}

/**
 * @return array{ok: bool, error: ?string, companies: list<array<string, mixed>>}
 */
function practitioner_tier_list_companies(string $environment): array
{
    return provider_signup_accs_with_environment($environment, static function () {
        $companies = [];
        $page = 1;
        $pageSize = 50;

        while ($page <= 40) {
            $result = provider_signup_accs_api_request('GET', '/company/', [
                'searchCriteria[pageSize]'    => (string) $pageSize,
                'searchCriteria[currentPage]' => (string) $page,
            ]);
            if (!$result['ok'] || !is_array($result['data'] ?? null)) {
                return [
                    'ok'        => false,
                    'error'     => provider_signup_accs_format_api_error($result),
                    'companies' => [],
                ];
            }

            $items = $result['data']['items'] ?? [];
            if (!is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['company_name'] ?? ''));
                if ($name !== '' && in_array($name, PRACTITIONER_TIER_EXCLUDED_COMPANY_NAMES, true)) {
                    continue;
                }
                $companies[] = $item;
            }

            if (count($items) < $pageSize) {
                break;
            }
            $page++;
        }

        return ['ok' => true, 'error' => null, 'companies' => $companies];
    });
}

/**
 * @return array{ok: bool, error: ?string, company: ?array<string, mixed>}
 */
function practitioner_tier_get_company(int $companyId, string $environment): array
{
    if ($companyId <= 0) {
        return ['ok' => false, 'error' => 'Company ID is required.', 'company' => null];
    }

    return provider_signup_accs_with_environment($environment, static function () use ($companyId) {
        $result = provider_signup_accs_api_request('GET', '/company/' . $companyId);
        if (!$result['ok'] || !is_array($result['data'] ?? null)) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($result),
                'company' => null,
            ];
        }

        return ['ok' => true, 'error' => null, 'company' => $result['data']];
    });
}

/**
 * @return array{ok: bool, error: ?string, customers: list<array<string, mixed>>}
 */
function practitioner_tier_list_company_hierarchy_customers(int $companyId, string $environment): array
{
    return provider_signup_accs_with_environment($environment, static function () use ($companyId) {
        $hierarchy = provider_signup_accs_api_request('GET', '/hierarchy/' . $companyId);
        if (!$hierarchy['ok']) {
            return [
                'ok'        => false,
                'error'     => provider_signup_accs_format_api_error($hierarchy),
                'customers' => [],
            ];
        }

        $rows = is_array($hierarchy['data']) ? $hierarchy['data'] : [];
        $customers = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['entity_type'] ?? '') !== 'customer') {
                continue;
            }
            $customerId = (int) ($row['entity_id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }
            $customerResult = provider_signup_accs_api_request('GET', '/customers/' . $customerId);
            if ($customerResult['ok'] && is_array($customerResult['data'] ?? null)) {
                $customers[] = $customerResult['data'];
            }
        }

        return ['ok' => true, 'error' => null, 'customers' => $customers];
    });
}

/**
 * Patients stamped with clinic_id = company (may include company admin too).
 *
 * @return array{ok: bool, error: ?string, customers: list<array<string, mixed>>}
 */
function practitioner_tier_list_clinic_id_customers(int $companyId, string $environment): array
{
    return provider_signup_accs_with_environment($environment, static function () use ($companyId) {
        $customers = [];
        $page = 1;
        $pageSize = 50;

        while ($page <= 40) {
            $result = provider_signup_accs_api_request('GET', '/customers/search', [
                'searchCriteria[filterGroups][0][filters][0][field]'          => 'clinic_id',
                'searchCriteria[filterGroups][0][filters][0][value]'          => (string) $companyId,
                'searchCriteria[filterGroups][0][filters][0][condition_type]' => 'eq',
                'searchCriteria[pageSize]'                                     => (string) $pageSize,
                'searchCriteria[currentPage]'                                  => (string) $page,
            ]);
            if (!$result['ok'] || !is_array($result['data'] ?? null)) {
                return [
                    'ok'        => false,
                    'error'     => provider_signup_accs_format_api_error($result),
                    'customers' => [],
                ];
            }

            $items = $result['data']['items'] ?? [];
            if (!is_array($items) || $items === []) {
                break;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $customers[] = $item;
                }
            }
            if (count($items) < $pageSize) {
                break;
            }
            $page++;
        }

        return ['ok' => true, 'error' => null, 'customers' => $customers];
    });
}

/**
 * @param array<string, mixed> $customer
 */
function practitioner_tier_customer_company_id(array $customer): int
{
    $attrs = $customer['extension_attributes']['company_attributes'] ?? null;

    return is_array($attrs) ? (int) ($attrs['company_id'] ?? 0) : 0;
}

/**
 * @param list<array<string, mixed>> $providers
 * @param list<array<string, mixed>> $clinicCustomers
 * @return array{
 *   provider_ids: list<int>,
 *   patient_ids: list<int>,
 *   all_ids: list<int>,
 *   provider_revenue: float,
 *   patient_revenue: float,
 *   total_revenue: float
 * }
 */
function practitioner_tier_compute_revenue_split(
    array $providers,
    array $clinicCustomers,
    string $environment
): array {
    $providerIds = [];
    foreach ($providers as $customer) {
        $id = (int) ($customer['id'] ?? 0);
        if ($id > 0) {
            $providerIds[$id] = $id;
        }
    }

    $patientIds = [];
    foreach ($clinicCustomers as $customer) {
        $id = (int) ($customer['id'] ?? 0);
        if ($id <= 0 || isset($providerIds[$id])) {
            continue;
        }
        $patientIds[$id] = $id;
    }

    $providerSum = practitioner_tier_sum_order_revenue(array_values($providerIds), $environment);
    $patientSum = practitioner_tier_sum_order_revenue(array_values($patientIds), $environment);

    return [
        'provider_ids'     => array_values($providerIds),
        'patient_ids'      => array_values($patientIds),
        'all_ids'          => array_values($providerIds + $patientIds),
        'provider_revenue' => $providerSum['total'],
        'patient_revenue'  => $patientSum['total'],
        'total_revenue'    => round($providerSum['total'] + $patientSum['total'], 2),
    ];
}

/**
 * @return array{ok: bool, error: ?string, updated: bool}
 */
function practitioner_tier_set_customer_group(int $customerId, int $groupId, string $environment): array
{
    if ($customerId <= 0 || $groupId <= 0) {
        return ['ok' => false, 'error' => 'Customer ID and group ID are required.', 'updated' => false];
    }

    return provider_signup_accs_with_environment($environment, static function () use ($customerId, $groupId) {
        $current = provider_signup_accs_api_request('GET', '/customers/' . $customerId);
        if (!$current['ok'] || !is_array($current['data'] ?? null)) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($current),
                'updated' => false,
            ];
        }

        $customer = $current['data'];
        if ((int) ($customer['group_id'] ?? 0) === $groupId) {
            return ['ok' => true, 'error' => null, 'updated' => false];
        }

        $result = provider_signup_accs_api_request('PUT', '/customers/' . $customerId, null, [
            'customer' => [
                'id'         => $customerId,
                'email'      => (string) ($customer['email'] ?? ''),
                'firstname'  => (string) ($customer['firstname'] ?? ''),
                'lastname'   => (string) ($customer['lastname'] ?? ''),
                'website_id' => (int) ($customer['website_id'] ?? provider_signup_accs_website_id()),
                'group_id'   => $groupId,
            ],
        ]);

        if (!$result['ok']) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($result),
                'updated' => false,
            ];
        }

        return ['ok' => true, 'error' => null, 'updated' => true];
    });
}

/**
 * @return array{ok: bool, error: ?string, updated: bool}
 */
function practitioner_tier_set_company_group(int $companyId, int $groupId, string $environment): array
{
    if ($companyId <= 0 || $groupId <= 0) {
        return ['ok' => false, 'error' => 'Company ID and group ID are required.', 'updated' => false];
    }

    return provider_signup_accs_with_environment($environment, static function () use ($companyId, $groupId) {
        $current = provider_signup_accs_api_request('GET', '/company/' . $companyId);
        if (!$current['ok'] || !is_array($current['data'] ?? null)) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($current),
                'updated' => false,
            ];
        }

        $company = $current['data'];
        if ((int) ($company['customer_group_id'] ?? 0) === $groupId) {
            return ['ok' => true, 'error' => null, 'updated' => false];
        }

        $company['customer_group_id'] = $groupId;
        $result = provider_signup_accs_api_request('PUT', '/company/' . $companyId, null, $company);
        if (!$result['ok']) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($result),
                'updated' => false,
            ];
        }

        return ['ok' => true, 'error' => null, 'updated' => true];
    });
}

/**
 * Apply a tier to company + provider users (not patients).
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   company_updated: bool,
 *   providers_updated: int,
 *   providers_failed: int,
 *   from_group_id: ?int,
 *   to_group_id: int,
 *   details: list<string>
 * }
 */
function practitioner_tier_apply_to_company(
    int $companyId,
    string $environment,
    int $targetGroupId
): array {
    $companyResult = practitioner_tier_get_company($companyId, $environment);
    if (!$companyResult['ok'] || !is_array($companyResult['company'])) {
        return [
            'ok'                => false,
            'error'             => $companyResult['error'] ?? 'Company not found.',
            'company_updated'   => false,
            'providers_updated' => 0,
            'providers_failed'  => 0,
            'from_group_id'     => null,
            'to_group_id'       => $targetGroupId,
            'details'           => [],
        ];
    }

    $company = $companyResult['company'];
    $fromGroupId = (int) ($company['customer_group_id'] ?? 0);
    $details = [];

    $companyUpdate = practitioner_tier_set_company_group($companyId, $targetGroupId, $environment);
    if (!$companyUpdate['ok']) {
        return [
            'ok'                => false,
            'error'             => $companyUpdate['error'] ?? 'Unable to update company group.',
            'company_updated'   => false,
            'providers_updated' => 0,
            'providers_failed'  => 0,
            'from_group_id'     => $fromGroupId,
            'to_group_id'       => $targetGroupId,
            'details'           => [],
        ];
    }
    if ($companyUpdate['updated']) {
        $details[] = 'Company group updated.';
    }

    $providersResult = practitioner_tier_list_company_hierarchy_customers($companyId, $environment);
    if (!$providersResult['ok']) {
        return [
            'ok'                => false,
            'error'             => $providersResult['error'] ?? 'Unable to list company users.',
            'company_updated'   => (bool) $companyUpdate['updated'],
            'providers_updated' => 0,
            'providers_failed'  => 0,
            'from_group_id'     => $fromGroupId,
            'to_group_id'       => $targetGroupId,
            'details'           => $details,
        ];
    }

    $providersUpdated = 0;
    $providersFailed = 0;
    foreach ($providersResult['customers'] as $customer) {
        $customerId = (int) ($customer['id'] ?? 0);
        if ($customerId <= 0) {
            continue;
        }
        $update = practitioner_tier_set_customer_group($customerId, $targetGroupId, $environment);
        if (!$update['ok']) {
            $providersFailed++;
            $details[] = 'Failed customer ' . $customerId . ': ' . ($update['error'] ?? 'error');
            continue;
        }
        if ($update['updated']) {
            $providersUpdated++;
        }
    }

    return [
        'ok'                => $providersFailed === 0,
        'error'             => $providersFailed > 0 ? 'One or more provider updates failed.' : null,
        'company_updated'   => (bool) $companyUpdate['updated'],
        'providers_updated' => $providersUpdated,
        'providers_failed'  => $providersFailed,
        'from_group_id'     => $fromGroupId,
        'to_group_id'       => $targetGroupId,
        'details'           => $details,
    ];
}

/**
 * Build list/detail snapshot for one company.
 *
 * @return array<string, mixed>
 */
function practitioner_tier_company_snapshot(array $company, string $environment): array
{
    $companyId = (int) ($company['id'] ?? 0);
    $providersResult = practitioner_tier_list_company_hierarchy_customers($companyId, $environment);
    $clinicResult = practitioner_tier_list_clinic_id_customers($companyId, $environment);
    $providers = $providersResult['ok'] ? $providersResult['customers'] : [];
    $clinicCustomers = $clinicResult['ok'] ? $clinicResult['customers'] : [];

    $revenue = practitioner_tier_compute_revenue_split($providers, $clinicCustomers, $environment);
    $suggested = practitioner_tier_resolve_for_revenue($revenue['total_revenue'], $environment);
    $currentGroupId = (int) ($company['customer_group_id'] ?? 0);
    $currentTier = practitioner_tier_threshold_by_group_id($currentGroupId, $environment);
    $override = practitioner_tier_get_override($companyId, $environment);

    return [
        'company_id'         => $companyId,
        'company_name'       => (string) ($company['company_name'] ?? ''),
        'company_status'     => (int) ($company['status'] ?? 0),
        'current_group_id'   => $currentGroupId,
        'current_tier'       => $currentTier,
        'suggested_tier'     => $suggested,
        'provider_revenue'   => $revenue['provider_revenue'],
        'patient_revenue'    => $revenue['patient_revenue'],
        'total_revenue'      => $revenue['total_revenue'],
        'provider_count'     => count($revenue['provider_ids']),
        'patient_count'      => count($revenue['patient_ids']),
        'providers'          => $providers,
        'override'           => $override,
        'providers_error'    => $providersResult['ok'] ? null : ($providersResult['error'] ?? 'error'),
        'clinic_error'       => $clinicResult['ok'] ? null : ($clinicResult['error'] ?? 'error'),
        'ytd'                => practitioner_tier_ytd_bounds(),
    ];
}

/**
 * @return array{ok: bool, error: ?string, run_log_id: ?int, scanned: int, updated: int, skipped: int, failed: int}
 */
function practitioner_tier_run_environment(
    string $environment,
    string $triggerType,
    ?string $actor = null,
    ?int $onlyCompanyId = null,
    bool $respectOverrides = true
): array {
    $environment = practitioner_tier_sql_source_environment($environment);
    $pdo = db();

    $insert = $pdo->prepare(<<<SQL
        INSERT INTO dbo.PractitionerTierRunLog (SourceEnvironment, TriggerType, TriggeredBy, Status)
        OUTPUT INSERTED.RunLogID
        VALUES (:env, :trigger_type, :triggered_by, N'running')
    SQL);
    $insert->execute([
        ':env'           => $environment,
        ':trigger_type'  => $triggerType,
        ':triggered_by'  => $actor,
    ]);
    $runLogId = (int) $insert->fetchColumn();

    $scanned = 0;
    $updated = 0;
    $skipped = 0;
    $failed = 0;

    $companiesResult = practitioner_tier_list_companies($environment);
    if (!$companiesResult['ok']) {
        $pdo->prepare(<<<SQL
            UPDATE dbo.PractitionerTierRunLog
            SET FinishedAt = SYSUTCDATETIME(), Status = N'failed', Message = :message
            WHERE RunLogID = :id
        SQL)->execute([
            ':message' => $companiesResult['error'] ?? 'Unable to list companies.',
            ':id'      => $runLogId,
        ]);

        return [
            'ok'         => false,
            'error'      => $companiesResult['error'] ?? 'Unable to list companies.',
            'run_log_id' => $runLogId,
            'scanned'    => 0,
            'updated'    => 0,
            'skipped'    => 0,
            'failed'     => 0,
        ];
    }

    $detailStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.PractitionerTierRunDetail (
            RunLogID, AccsCompanyId, CompanyName, YtdRevenue, ProviderRevenue, PatientRevenue,
            FromGroupId, ToGroupId, SuggestedTierCode, ActionStatus, SkipReason, DetailMessage
        ) VALUES (
            :run_log_id, :company_id, :company_name, :ytd, :provider_rev, :patient_rev,
            :from_group, :to_group, :tier_code, :action_status, :skip_reason, :detail_message
        )
    SQL);

    foreach ($companiesResult['companies'] as $company) {
        $companyId = (int) ($company['id'] ?? 0);
        if ($companyId <= 0) {
            continue;
        }
        if ($onlyCompanyId !== null && $companyId !== $onlyCompanyId) {
            continue;
        }

        $scanned++;
        $snapshot = practitioner_tier_company_snapshot($company, $environment);
        $suggested = $snapshot['suggested_tier'];
        $toGroupId = is_array($suggested) ? (int) ($suggested['ResolvedGroupId'] ?? 0) : 0;
        $tierCode = is_array($suggested) ? (string) ($suggested['TierCode'] ?? '') : null;
        $fromGroupId = (int) $snapshot['current_group_id'];
        $override = $snapshot['override'];

        $actionStatus = 'skipped';
        $skipReason = null;
        $detailMessage = null;

        if ($respectOverrides && is_array($override) && (int) ($override['IsLocked'] ?? 0) === 1) {
            $skipped++;
            $skipReason = 'override';
            $detailMessage = 'Manual override lock is set.';
            $toGroupId = (int) ($override['LockedGroupId'] ?? $fromGroupId);
            $tierCode = (string) ($override['LockedTierCode'] ?? $tierCode);
        } elseif ($toGroupId <= 0) {
            $failed++;
            $actionStatus = 'failed';
            $detailMessage = 'Unable to resolve tier for revenue.';
        } elseif ($fromGroupId === $toGroupId) {
            // Still ensure providers match company group.
            $apply = practitioner_tier_apply_to_company($companyId, $environment, $toGroupId);
            if (!$apply['ok']) {
                $failed++;
                $actionStatus = 'failed';
                $detailMessage = $apply['error'] ?? 'Apply failed.';
            } elseif ($apply['company_updated'] || $apply['providers_updated'] > 0) {
                $updated++;
                $actionStatus = 'updated';
                $detailMessage = 'Aligned providers to current company tier.';
            } else {
                $skipped++;
                $skipReason = 'already_current';
                $detailMessage = 'Company and providers already on suggested tier.';
            }
        } else {
            $apply = practitioner_tier_apply_to_company($companyId, $environment, $toGroupId);
            if (!$apply['ok']) {
                $failed++;
                $actionStatus = 'failed';
                $detailMessage = $apply['error'] ?? 'Apply failed.';
            } else {
                $updated++;
                $actionStatus = 'updated';
                $detailMessage = 'Reclassified to ' . ($tierCode ?? (string) $toGroupId) . '.';
            }
        }

        $detailStmt->execute([
            ':run_log_id'      => $runLogId,
            ':company_id'      => $companyId,
            ':company_name'    => (string) ($snapshot['company_name'] ?? ''),
            ':ytd'             => $snapshot['total_revenue'],
            ':provider_rev'    => $snapshot['provider_revenue'],
            ':patient_rev'     => $snapshot['patient_revenue'],
            ':from_group'      => $fromGroupId,
            ':to_group'        => $toGroupId > 0 ? $toGroupId : null,
            ':tier_code'       => $tierCode,
            ':action_status'   => $actionStatus,
            ':skip_reason'     => $skipReason,
            ':detail_message'  => $detailMessage,
        ]);
    }

    $status = 'success';
    if ($failed > 0 && $updated > 0) {
        $status = 'partial';
    } elseif ($failed > 0 && $updated === 0) {
        $status = 'failed';
    }

    $pdo->prepare(<<<SQL
        UPDATE dbo.PractitionerTierRunLog
        SET FinishedAt = SYSUTCDATETIME(),
            Status = :status,
            CompaniesScanned = :scanned,
            CompaniesUpdated = :updated,
            CompaniesSkipped = :skipped,
            CompaniesFailed = :failed,
            Message = :message
        WHERE RunLogID = :id
    SQL)->execute([
        ':status'  => $status,
        ':scanned' => $scanned,
        ':updated' => $updated,
        ':skipped' => $skipped,
        ':failed'  => $failed,
        ':message' => sprintf(
            'Scanned %d — updated %d, skipped %d, failed %d.',
            $scanned,
            $updated,
            $skipped,
            $failed
        ),
        ':id'      => $runLogId,
    ]);

    return [
        'ok'         => $status !== 'failed',
        'error'      => $status === 'failed' ? 'Run completed with failures.' : null,
        'run_log_id' => $runLogId,
        'scanned'    => $scanned,
        'updated'    => $updated,
        'skipped'    => $skipped,
        'failed'     => $failed,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function practitioner_tier_list_runs(string $environment, int $limit = 25): array
{
    $pdo = db();
    $limit = max(1, min(100, $limit));
    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP ($limit)
            RunLogID, SourceEnvironment, TriggerType, TriggeredBy, StartedAt, FinishedAt,
            Status, CompaniesScanned, CompaniesUpdated, CompaniesSkipped, CompaniesFailed, Message
        FROM dbo.PractitionerTierRunLog
        WHERE SourceEnvironment = :env
        ORDER BY StartedAt DESC
    SQL);
    $stmt->execute([
        ':env' => practitioner_tier_sql_source_environment($environment),
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string, mixed>>
 */
function practitioner_tier_list_run_details(int $runLogId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.PractitionerTierRunDetail
        WHERE RunLogID = :id
        ORDER BY AccsCompanyId ASC
    SQL);
    $stmt->execute([':id' => $runLogId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function practitioner_tier_format_money(float $amount): string
{
    return '$' . number_format($amount, 2);
}
