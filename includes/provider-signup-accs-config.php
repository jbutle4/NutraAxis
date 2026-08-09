<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/provider-signup-accs.php';

const PROVIDER_SIGNUP_ACCS_CONFIG_API_TIMEOUT_SECONDS = 120;
const PROVIDER_SIGNUP_ACCS_CONFIG_SHARED_CATALOG_TAX_CLASS_ID = 3;

function provider_signup_accs_config_api_request(
    string $method,
    string $path,
    ?array $query = null,
    ?array $body = null
): array {
    return provider_signup_accs_api_request(
        $method,
        $path,
        $query,
        $body,
        PROVIDER_SIGNUP_ACCS_CONFIG_API_TIMEOUT_SECONDS
    );
}

function provider_signup_accs_config_master_catalog_id(): int
{
    $configured = (int) env('PROVIDER_SIGNUP_ACCS_MASTER_SHARED_CATALOG_ID', '1');

    return $configured > 0 ? $configured : 1;
}

function provider_signup_accs_config_template_company_id(): int
{
    return max(0, (int) env('PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID', '0'));
}

/**
 * @return list<int>
 */
function provider_signup_accs_config_template_role_ids(): array
{
    $raw = trim((string) env('PROVIDER_SIGNUP_ACCS_TEMPLATE_ROLE_IDS', ''));
    if ($raw === '') {
        return [];
    }

    $ids = [];
    foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
        $id = (int) $part;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @return list<string>
 */
function provider_signup_accs_config_required_role_names(): array
{
    $raw = trim((string) env(
        'PROVIDER_SIGNUP_ACCS_REQUIRED_ROLE_NAMES',
        'Default User,Owner,Company_Admin,Provider,Affiliated Patients'
    ));
    if ($raw === '') {
        return [];
    }

    $names = [];
    foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
        $name = trim($part);
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function provider_signup_accs_config_shared_catalog_name(array $application): string
{
    $companyName = trim((string) ($application['CompanyName'] ?? ''));
    if ($companyName === '') {
        $companyName = 'Clinic ' . (int) ($application['ApplicationID'] ?? 0);
    }

    return 'SC-' . $companyName;
}

/**
 * @return array{ok: bool, error: ?string, roles: list<array<string, mixed>>}
 */
function provider_signup_accs_config_list_company_roles(int $companyId): array
{
    if ($companyId <= 0) {
        return ['ok' => false, 'error' => 'Company ID is required.', 'roles' => []];
    }

    $result = provider_signup_accs_config_api_request('GET', '/company/role', [
        'searchCriteria[pageSize]'                                     => '50',
        'searchCriteria[currentPage]'                                  => '1',
        'searchCriteria[filterGroups][0][filters][0][field]'           => 'company_id',
        'searchCriteria[filterGroups][0][filters][0][value]'          => (string) $companyId,
        'searchCriteria[filterGroups][0][filters][0][conditionType]'    => 'eq',
    ]);

    if (!$result['ok'] || !is_array($result['data'] ?? null)) {
        return [
            'ok'    => false,
            'error' => provider_signup_accs_format_api_error($result),
            'roles' => [],
        ];
    }

    $roles = [];
    foreach ($result['data']['items'] ?? [] as $role) {
        if (is_array($role)) {
            $roles[] = $role;
        }
    }

    return ['ok' => true, 'error' => null, 'roles' => $roles];
}

/**
 * @return array{ok: bool, error: ?string, catalog_id: ?int, action: ?string, name: ?string}
 */
function provider_signup_accs_config_ensure_shared_catalog(array $application, ?int $existingCatalogId = null): array
{
    $catalogName = provider_signup_accs_config_shared_catalog_name($application);
    $companyName = trim((string) ($application['CompanyName'] ?? 'Provider clinic'));

    if ($existingCatalogId !== null && $existingCatalogId > 0) {
        $current = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $existingCatalogId);
        if ($current['ok'] && is_array($current['data'] ?? null)) {
            return [
                'ok'         => true,
                'error'      => null,
                'catalog_id' => $existingCatalogId,
                'action'     => 'existing',
                'name'       => (string) ($current['data']['name'] ?? $catalogName),
            ];
        }
    }

    $list = provider_signup_accs_config_api_request('GET', '/sharedCatalog', [
        'searchCriteria[pageSize]'    => '50',
        'searchCriteria[currentPage]' => '1',
    ]);
    if (!$list['ok'] || !is_array($list['data'] ?? null)) {
        return [
            'ok'         => false,
            'error'      => provider_signup_accs_format_api_error($list),
            'catalog_id' => null,
            'action'     => null,
            'name'       => null,
        ];
    }

    foreach ($list['data']['items'] ?? [] as $catalog) {
        if (!is_array($catalog)) {
            continue;
        }
        if ((string) ($catalog['name'] ?? '') === $catalogName) {
            return [
                'ok'         => true,
                'error'      => null,
                'catalog_id' => (int) ($catalog['id'] ?? 0),
                'action'     => 'existing',
                'name'       => $catalogName,
            ];
        }
    }

    $create = provider_signup_accs_config_api_request('POST', '/sharedCatalog', null, [
        'sharedCatalog' => [
            'name'         => $catalogName,
            'description'  => 'Shared catalog for ' . $companyName,
            'type'         => 0,
            'store_id'     => 0,
            'tax_class_id' => PROVIDER_SIGNUP_ACCS_CONFIG_SHARED_CATALOG_TAX_CLASS_ID,
        ],
    ]);
    if (!$create['ok']) {
        return [
            'ok'         => false,
            'error'      => provider_signup_accs_format_api_error($create),
            'catalog_id' => null,
            'action'     => null,
            'name'       => null,
        ];
    }

    $catalogId = 0;
    $data = $create['data'] ?? null;
    if (is_int($data)) {
        $catalogId = $data;
    } elseif (is_array($data) && !empty($data['id'])) {
        $catalogId = (int) $data['id'];
    }

    if ($catalogId <= 0) {
        return [
            'ok'         => false,
            'error'      => 'ACCS did not return a shared catalog ID.',
            'catalog_id' => null,
            'action'     => null,
            'name'       => null,
        ];
    }

    return [
        'ok'         => true,
        'error'      => null,
        'catalog_id' => $catalogId,
        'action'     => 'created',
        'name'       => $catalogName,
    ];
}

/**
 * @return array{ok: bool, error: ?string, category_count: int, product_count: int}
 */
function provider_signup_accs_config_assign_catalog_contents(int $catalogId, int $companyId): array
{
    if ($catalogId <= 0 || $companyId <= 0) {
        return ['ok' => false, 'error' => 'Catalog ID and company ID are required.', 'category_count' => 0, 'product_count' => 0];
    }

    $masterCatalogId = provider_signup_accs_config_master_catalog_id();

    $categories = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $masterCatalogId . '/categories');
    if (!$categories['ok']) {
        return [
            'ok'             => false,
            'error'          => provider_signup_accs_format_api_error($categories),
            'category_count' => 0,
            'product_count'  => 0,
        ];
    }

    $categoryIds = [];
    foreach ($categories['data'] ?? [] as $categoryId) {
        $id = (int) $categoryId;
        if ($id > 0) {
            $categoryIds[] = $id;
        }
    }

    if ($categoryIds !== []) {
        $assignCategories = provider_signup_accs_config_api_request(
            'POST',
            '/sharedCatalog/' . $catalogId . '/assignCategories',
            null,
            [
                'categories' => array_map(
                    static fn (int $categoryId): array => ['id' => $categoryId],
                    $categoryIds
                ),
            ]
        );
        if (!$assignCategories['ok']) {
            return [
                'ok'             => false,
                'error'          => provider_signup_accs_format_api_error($assignCategories),
                'category_count' => 0,
                'product_count'  => 0,
            ];
        }
    }

    $products = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $masterCatalogId . '/products');
    if (!$products['ok']) {
        return [
            'ok'             => false,
            'error'          => provider_signup_accs_format_api_error($products),
            'category_count' => count($categoryIds),
            'product_count'  => 0,
        ];
    }

    $skus = [];
    $productData = $products['data'] ?? null;
    if (is_array($productData) && array_is_list($productData)) {
        foreach ($productData as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
    } elseif (is_array($productData)) {
        foreach ($productData['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
    }

    if ($skus !== []) {
        $productPayloads = [
            ['products' => array_map(static fn (string $sku): array => ['sku' => $sku], $skus)],
            ['products' => $skus],
        ];
        $assigned = false;
        $lastError = 'Unable to assign products to shared catalog.';
        foreach ($productPayloads as $payload) {
            $assignProducts = provider_signup_accs_config_api_request(
                'POST',
                '/sharedCatalog/' . $catalogId . '/assignProducts',
                null,
                $payload
            );
            if ($assignProducts['ok']) {
                $assigned = true;
                break;
            }
            $lastError = provider_signup_accs_format_api_error($assignProducts);
        }

        if (!$assigned) {
            return [
                'ok'             => false,
                'error'          => $lastError,
                'category_count' => count($categoryIds),
                'product_count'  => 0,
            ];
        }
    }

    $companyPayloads = [
        ['companies' => [$companyId]],
        ['companies' => [(string) $companyId]],
    ];
    $companyAssigned = false;
    $lastCompanyError = 'Unable to assign company to shared catalog.';
    foreach ($companyPayloads as $payload) {
        $assignCompany = provider_signup_accs_config_api_request(
            'POST',
            '/sharedCatalog/' . $catalogId . '/assignCompanies',
            null,
            $payload
        );
        if ($assignCompany['ok']) {
            $companyAssigned = true;
            break;
        }
        $lastCompanyError = provider_signup_accs_format_api_error($assignCompany);
    }

    if (!$companyAssigned) {
        return [
            'ok'             => false,
            'error'          => $lastCompanyError,
            'category_count' => count($categoryIds),
            'product_count'  => count($skus),
        ];
    }

    return [
        'ok'             => true,
        'error'          => null,
        'category_count' => count($categoryIds),
        'product_count'  => count($skus),
    ];
}

/**
 * @param array<string, array<string, mixed>> $existingByName
 * @return array{ok: bool, error: ?string, role_name: ?string, action: ?string}
 */
function provider_signup_accs_config_clone_role(int $sourceRoleId, int $companyId, array $existingByName): array
{
    $source = provider_signup_accs_config_api_request('GET', '/company/role/' . $sourceRoleId);
    if (!$source['ok'] || !is_array($source['data'] ?? null)) {
        return [
            'ok'        => false,
            'error'     => provider_signup_accs_format_api_error($source),
            'role_name' => null,
            'action'    => null,
        ];
    }

    $roleName = trim((string) ($source['data']['role_name'] ?? ''));
    if ($roleName === '') {
        return ['ok' => false, 'error' => 'Template role is missing a name.', 'role_name' => null, 'action' => null];
    }

    $permissions = [];
    foreach ($source['data']['permissions'] ?? [] as $permission) {
        if (!is_array($permission) || empty($permission['resource_id'])) {
            continue;
        }
        $permissions[] = [
            'resource_id' => $permission['resource_id'],
            'permission'  => $permission['permission'] ?? 'allow',
        ];
    }

    if (isset($existingByName[$roleName])) {
        $current = $existingByName[$roleName];
        $roleId = (int) ($current['id'] ?? 0);
        if ($roleId <= 0) {
            return ['ok' => false, 'error' => 'Existing role is missing an ID.', 'role_name' => $roleName, 'action' => null];
        }

        $update = provider_signup_accs_config_api_request('PUT', '/company/role/' . $roleId, null, [
            'role' => [
                'id'          => $roleId,
                'role_name'   => $roleName,
                'company_id'  => $companyId,
                'permissions' => $permissions,
            ],
        ]);
        if (!$update['ok']) {
            return [
                'ok'        => false,
                'error'     => provider_signup_accs_format_api_error($update),
                'role_name' => $roleName,
                'action'    => null,
            ];
        }

        return ['ok' => true, 'error' => null, 'role_name' => $roleName, 'action' => 'updated'];
    }

    $create = provider_signup_accs_config_api_request('POST', '/company/role', null, [
        'role' => [
            'role_name'   => $roleName,
            'company_id'  => $companyId,
            'permissions' => $permissions,
        ],
    ]);
    if (!$create['ok'] || !is_array($create['data'] ?? null)) {
        return [
            'ok'        => false,
            'error'     => provider_signup_accs_format_api_error($create),
            'role_name' => $roleName,
            'action'    => null,
        ];
    }

    return ['ok' => true, 'error' => null, 'role_name' => $roleName, 'action' => 'created'];
}

/**
 * @return array{ok: bool, error: ?string, summary: ?string, actions: list<string>}
 */
function provider_signup_accs_config_clone_roles(int $companyId): array
{
    if ($companyId <= 0) {
        return ['ok' => false, 'error' => 'Company ID is required.', 'summary' => null, 'actions' => []];
    }

    $templateRoleIds = provider_signup_accs_config_template_role_ids();
    if ($templateRoleIds === []) {
        $templateCompanyId = provider_signup_accs_config_template_company_id();
        if ($templateCompanyId <= 0) {
            return [
                'ok'      => false,
                'error'   => 'Set PROVIDER_SIGNUP_ACCS_TEMPLATE_ROLE_IDS or PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID.',
                'summary' => null,
                'actions' => [],
            ];
        }

        $templateRoles = provider_signup_accs_config_list_company_roles($templateCompanyId);
        if (!$templateRoles['ok']) {
            return ['ok' => false, 'error' => $templateRoles['error'], 'summary' => null, 'actions' => []];
        }

        foreach ($templateRoles['roles'] as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            if ($roleId > 0) {
                $templateRoleIds[] = $roleId;
            }
        }
    }

    if ($templateRoleIds === []) {
        return ['ok' => false, 'error' => 'No template roles are configured.', 'summary' => null, 'actions' => []];
    }

    $existing = provider_signup_accs_config_list_company_roles($companyId);
    if (!$existing['ok']) {
        return ['ok' => false, 'error' => $existing['error'], 'summary' => null, 'actions' => []];
    }

    $existingByName = [];
    foreach ($existing['roles'] as $role) {
        $roleName = trim((string) ($role['role_name'] ?? ''));
        if ($roleName !== '') {
            $existingByName[$roleName] = $role;
        }
    }

    $actions = [];
    foreach ($templateRoleIds as $templateRoleId) {
        $cloned = provider_signup_accs_config_clone_role($templateRoleId, $companyId, $existingByName);
        if (!$cloned['ok']) {
            return ['ok' => false, 'error' => $cloned['error'], 'summary' => null, 'actions' => $actions];
        }

        $actions[] = ($cloned['action'] ?? 'updated') . ':' . ($cloned['role_name'] ?? '');
        if (!empty($cloned['role_name'])) {
            $existingByName[$cloned['role_name']] = ['role_name' => $cloned['role_name']];
        }
    }

    $finalRoles = provider_signup_accs_config_list_company_roles($companyId);
    if (!$finalRoles['ok']) {
        return ['ok' => false, 'error' => $finalRoles['error'], 'summary' => null, 'actions' => $actions];
    }

    $requiredNames = provider_signup_accs_config_required_role_names();
    $finalNames = [];
    foreach ($finalRoles['roles'] as $role) {
        $roleName = trim((string) ($role['role_name'] ?? ''));
        if ($roleName !== '') {
            $finalNames[] = $roleName;
        }
    }

    $missing = array_values(array_diff($requiredNames, $finalNames));
    if ($missing !== []) {
        return [
            'ok'      => false,
            'error'   => 'Missing required roles after clone: ' . implode(', ', $missing) . '.',
            'summary' => null,
            'actions' => $actions,
        ];
    }

    return [
        'ok'      => true,
        'error'   => null,
        'summary' => implode(', ', $finalNames),
        'actions' => $actions,
    ];
}

/**
 * @return array{ok: bool, error: ?string, category_count: int, product_count: int, company_assigned: bool}
 */
function provider_signup_accs_config_verify_catalog_assignment(int $catalogId, int $companyId): array
{
    $categoryCount = 0;
    $productCount = 0;
    $companyAssigned = false;

    $categories = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $catalogId . '/categories');
    if ($categories['ok'] && is_array($categories['data'] ?? null)) {
        $categoryCount = count($categories['data']);
    }

    $products = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $catalogId . '/products');
    if ($products['ok']) {
        $productData = $products['data'] ?? null;
        if (is_array($productData) && array_is_list($productData)) {
            $productCount = count($productData);
        } elseif (is_array($productData)) {
            $productCount = count($productData['items'] ?? []);
        }
    }

    $companies = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $catalogId . '/companies');
    if ($companies['ok']) {
        $companyData = $companies['data'] ?? null;
        if (is_array($companyData) && array_is_list($companyData)) {
            foreach ($companyData as $assignedCompanyId) {
                if ((int) $assignedCompanyId === $companyId) {
                    $companyAssigned = true;
                    break;
                }
            }
        } elseif (is_array($companyData)) {
            foreach ($companyData['items'] ?? [] as $item) {
                $assignedId = is_array($item)
                    ? (int) ($item['id'] ?? $item['company_id'] ?? 0)
                    : (int) $item;
                if ($assignedId === $companyId) {
                    $companyAssigned = true;
                    break;
                }
            }
        }
    }

    if ($categoryCount <= 0 || $productCount <= 0 || !$companyAssigned) {
        return [
            'ok'               => false,
            'error'            => 'Shared catalog assignment is incomplete (categories, products, or company link).',
            'category_count'   => $categoryCount,
            'product_count'    => $productCount,
            'company_assigned' => $companyAssigned,
        ];
    }

    return [
        'ok'               => true,
        'error'            => null,
        'category_count'   => $categoryCount,
        'product_count'    => $productCount,
        'company_assigned' => true,
    ];
}

function provider_signup_accs_config_application_step_done(array $application, string $step): bool
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

/**
 * @param array<string, mixed> $application
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   company_id: ?int,
 *   shared_catalog_id: ?int,
 *   category_count: ?int,
 *   product_count: ?int,
 *   roles_summary: ?string,
 *   configuration_complete: bool,
 *   steps: array<string, array{done: bool, action: ?string}>
 * }
 */
function provider_signup_accs_complete_clinic_configuration(array $application): array
{
    $configError = adobe_commerce_config_error();
    if ($configError !== null) {
        return [
            'ok'                     => false,
            'error'                  => $configError,
            'company_id'             => null,
            'shared_catalog_id'      => null,
            'category_count'         => null,
            'product_count'          => null,
            'roles_summary'          => null,
            'configuration_complete' => false,
            'steps'                  => [],
        ];
    }

    $companyId = (int) ($application['AccsCompanyId'] ?? 0);
    if ($companyId <= 0) {
        return [
            'ok'                     => false,
            'error'                  => 'ACCS company ID is required before clinic configuration can run.',
            'company_id'             => null,
            'shared_catalog_id'      => null,
            'category_count'         => null,
            'product_count'          => null,
            'roles_summary'          => null,
            'configuration_complete' => false,
            'steps'                  => [],
        ];
    }

    $steps = [
        'shared_catalog' => ['done' => false, 'action' => null],
        'catalog_assign' => ['done' => false, 'action' => null],
        'roles'          => ['done' => false, 'action' => null],
    ];

    $sharedCatalogId = (int) ($application['AccsSharedCatalogId'] ?? 0);
    if (!provider_signup_accs_config_application_step_done($application, 'shared_catalog')) {
        $catalog = provider_signup_accs_config_ensure_shared_catalog(
            $application,
            $sharedCatalogId > 0 ? $sharedCatalogId : null
        );
        if (!$catalog['ok'] || empty($catalog['catalog_id'])) {
            return [
                'ok'                     => false,
                'error'                  => $catalog['error'] ?? 'Unable to create or locate shared catalog.',
                'company_id'             => $companyId,
                'shared_catalog_id'      => null,
                'category_count'         => null,
                'product_count'          => null,
                'roles_summary'          => null,
                'configuration_complete' => false,
                'steps'                  => $steps,
            ];
        }

        $sharedCatalogId = (int) $catalog['catalog_id'];
        $steps['shared_catalog'] = ['done' => true, 'action' => (string) ($catalog['action'] ?? 'existing')];
    } else {
        $sharedCatalogId = $sharedCatalogId > 0 ? $sharedCatalogId : (int) ($application['AccsSharedCatalogId'] ?? 0);
        $steps['shared_catalog'] = ['done' => true, 'action' => 'skipped'];
    }

    $categoryCount = (int) ($application['AccsCatalogCategoryCount'] ?? 0);
    $productCount = (int) ($application['AccsCatalogProductCount'] ?? 0);
    if (!provider_signup_accs_config_application_step_done($application, 'catalog_assign')) {
        if ($sharedCatalogId <= 0) {
            return [
                'ok'                     => false,
                'error'                  => 'Shared catalog ID is required before assigning categories and products.',
                'company_id'             => $companyId,
                'shared_catalog_id'      => null,
                'category_count'         => null,
                'product_count'          => null,
                'roles_summary'          => null,
                'configuration_complete' => false,
                'steps'                  => $steps,
            ];
        }

        $verify = provider_signup_accs_config_verify_catalog_assignment($sharedCatalogId, $companyId);
        if (!$verify['ok']) {
            $assign = provider_signup_accs_config_assign_catalog_contents($sharedCatalogId, $companyId);
            if (!$assign['ok']) {
                return [
                    'ok'                     => false,
                    'error'                  => $assign['error'] ?? 'Unable to assign shared catalog contents.',
                    'company_id'             => $companyId,
                    'shared_catalog_id'      => $sharedCatalogId,
                    'category_count'         => null,
                    'product_count'          => null,
                    'roles_summary'          => null,
                    'configuration_complete' => false,
                    'steps'                  => $steps,
                ];
            }

            $categoryCount = (int) $assign['category_count'];
            $productCount = (int) $assign['product_count'];
        } else {
            $categoryCount = (int) $verify['category_count'];
            $productCount = (int) $verify['product_count'];
        }

        $steps['catalog_assign'] = ['done' => true, 'action' => 'assigned'];
    } else {
        $steps['catalog_assign'] = ['done' => true, 'action' => 'skipped'];
    }

    $rolesSummary = trim((string) ($application['AccsRolesSummary'] ?? ''));
    if (!provider_signup_accs_config_application_step_done($application, 'roles')) {
        $roles = provider_signup_accs_config_clone_roles($companyId);
        if (!$roles['ok']) {
            return [
                'ok'                     => false,
                'error'                  => $roles['error'] ?? 'Unable to clone company roles.',
                'company_id'             => $companyId,
                'shared_catalog_id'      => $sharedCatalogId,
                'category_count'         => $categoryCount,
                'product_count'        => $productCount,
                'roles_summary'          => null,
                'configuration_complete' => false,
                'steps'                  => $steps,
            ];
        }

        $rolesSummary = (string) ($roles['summary'] ?? '');
        $steps['roles'] = ['done' => true, 'action' => 'cloned'];
    } else {
        $steps['roles'] = ['done' => true, 'action' => 'skipped'];
    }

    $configurationComplete = provider_signup_accs_config_application_step_done($application, 'clinic')
        && provider_signup_accs_config_application_step_done($application, 'admin')
        && $steps['shared_catalog']['done']
        && $steps['catalog_assign']['done']
        && $steps['roles']['done'];

    return [
        'ok'                     => true,
        'error'                  => null,
        'company_id'             => $companyId,
        'shared_catalog_id'      => $sharedCatalogId > 0 ? $sharedCatalogId : null,
        'category_count'         => $categoryCount > 0 ? $categoryCount : null,
        'product_count'          => $productCount > 0 ? $productCount : null,
        'roles_summary'          => $rolesSummary !== '' ? $rolesSummary : null,
        'configuration_complete' => $configurationComplete,
        'steps'                  => $steps,
    ];
}
