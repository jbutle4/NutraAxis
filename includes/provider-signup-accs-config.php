<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/provider-signup-accs.php';

const PROVIDER_SIGNUP_ACCS_CONFIG_API_TIMEOUT_SECONDS = 120;
const PROVIDER_SIGNUP_ACCS_CONFIG_SHARED_CATALOG_TAX_CLASS_ID = 3;
const PROVIDER_SIGNUP_ACCS_CONFIG_TEMPLATE_COMPANY_NAME_DEFAULT = 'Clinic_Template';
const PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE = 'patient_shared_catalog_id';

function provider_signup_accs_config_api_request_for_environment(
    string $environment,
    string $method,
    string $path,
    ?array $query = null,
    ?array $body = null
): array {
    $environment = strtolower(trim($environment));
    $previous = getenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT');
    $hadPrevious = $previous !== false;

    putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT=' . $environment);
    $_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT'] = $environment;
    adobe_commerce_reset_access_token_cache();

    try {
        return provider_signup_accs_config_api_request($method, $path, $query, $body);
    } finally {
        if ($hadPrevious) {
            putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT=' . $previous);
            $_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT'] = $previous;
        } else {
            putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT');
            unset($_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT']);
        }
    }
}

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
    return provider_signup_accs_setting_int('PROVIDER_SIGNUP_ACCS_MASTER_SHARED_CATALOG_ID', 1);
}

function provider_signup_accs_config_template_company_name(): string
{
    $configured = trim((string) env(
        'PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_NAME',
        PROVIDER_SIGNUP_ACCS_CONFIG_TEMPLATE_COMPANY_NAME_DEFAULT
    ));

    return $configured !== '' ? $configured : PROVIDER_SIGNUP_ACCS_CONFIG_TEMPLATE_COMPANY_NAME_DEFAULT;
}

function provider_signup_accs_config_template_source_environment(): string
{
    $configured = strtolower(trim((string) env('PROVIDER_SIGNUP_ACCS_TEMPLATE_SOURCE_ENVIRONMENT', 'dev')));

    return $configured !== '' ? $configured : 'dev';
}

function provider_signup_accs_config_template_source_company_id(): int
{
    $configured = (int) env('PROVIDER_SIGNUP_ACCS_TEMPLATE_SOURCE_COMPANY_ID', '5');

    return $configured > 0 ? $configured : 5;
}

function provider_signup_accs_config_find_company_id_by_name(string $companyName, ?string $environment = null): ?int
{
    $companyName = trim($companyName);
    if ($companyName === '') {
        return null;
    }

    $request = $environment === null
        ? static fn (string $method, string $path, ?array $query = null, ?array $body = null): array => provider_signup_accs_config_api_request($method, $path, $query, $body)
        : static fn (string $method, string $path, ?array $query = null, ?array $body = null): array => provider_signup_accs_config_api_request_for_environment($environment, $method, $path, $query, $body);

    $result = $request('GET', '/company', [
        'searchCriteria[filterGroups][0][filters][0][field]'          => 'company_name',
        'searchCriteria[filterGroups][0][filters][0][value]'          => $companyName,
        'searchCriteria[filterGroups][0][filters][0][conditionType]'    => 'eq',
        'searchCriteria[pageSize]'                                     => '5',
        'searchCriteria[currentPage]'                                  => '1',
    ]);

    if (!$result['ok'] || !is_array($result['data'] ?? null)) {
        return null;
    }

    foreach ($result['data']['items'] ?? [] as $company) {
        if (!is_array($company)) {
            continue;
        }
        if (strcasecmp(trim((string) ($company['company_name'] ?? '')), $companyName) === 0) {
            $companyId = (int) ($company['id'] ?? 0);

            return $companyId > 0 ? $companyId : null;
        }
    }

    return null;
}

function provider_signup_accs_config_company_has_name(int $companyId, string $companyName): bool
{
    if ($companyId <= 0 || trim($companyName) === '') {
        return false;
    }

    $result = provider_signup_accs_config_api_request('GET', '/company/' . $companyId);
    if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
        return false;
    }

    return strcasecmp(trim((string) ($result['data']['company_name'] ?? '')), trim($companyName)) === 0;
}

/**
 * Resolve Clinic_Template in the current ACCS tenant.
 * Never reuse a Production company ID (Azure TEMPLATE_COMPANY_ID=3) on Stage.
 */
function provider_signup_accs_config_template_company_id(): int
{
    $templateName = provider_signup_accs_config_template_company_name();
    $environment = provider_signup_accs_normalize_environment(provider_signup_accs_target_environment()) ?? 'stage';
    $fallback = PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID_BY_ENVIRONMENT[$environment] ?? 0;

    $explicit = provider_signup_accs_setting_int('PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID', 0, false);
    if ($explicit > 0 && provider_signup_accs_config_company_has_name($explicit, $templateName)) {
        return $explicit;
    }

    $lookedUp = provider_signup_accs_config_find_company_id_by_name($templateName);
    if ($lookedUp !== null && $lookedUp > 0) {
        return $lookedUp;
    }

    if ($explicit > 0) {
        return $explicit;
    }

    return $fallback;
}

/**
 * @return list<int>
 */
function provider_signup_accs_config_template_role_ids(): array
{
    // Role IDs are per tenant. Do not fall back to a shared Production list on Stage.
    $raw = trim((string) (provider_signup_accs_setting_value('PROVIDER_SIGNUP_ACCS_TEMPLATE_ROLE_IDS', false) ?? ''));
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
 * @param array<string, mixed> $application
 * @return array{ok: bool, error: ?string, customer_id: int}
 */
function provider_signup_accs_config_resolve_admin_customer_id(array $application, int $companyId): array
{
    $customerId = (int) ($application['AccsCustomerId'] ?? 0);
    if ($customerId > 0) {
        return ['ok' => true, 'error' => null, 'customer_id' => $customerId];
    }

    if ($companyId <= 0) {
        return [
            'ok'          => false,
            'error'       => 'ACCS company admin customer ID is required.',
            'customer_id' => 0,
        ];
    }

    $company = provider_signup_accs_config_api_request('GET', '/company/' . $companyId);
    if (!$company['ok'] || !is_array($company['data'] ?? null)) {
        return [
            'ok'          => false,
            'error'       => provider_signup_accs_format_api_error($company),
            'customer_id' => 0,
        ];
    }

    $customerId = (int) ($company['data']['super_user_id'] ?? 0);
    if ($customerId <= 0) {
        return [
            'ok'          => false,
            'error'       => 'ACCS company is missing a super user (company admin) ID.',
            'customer_id' => 0,
        ];
    }

    return ['ok' => true, 'error' => null, 'customer_id' => $customerId];
}

/**
 * @param array<string, mixed> $customer
 */
function provider_signup_accs_config_customer_attribute_value(array $customer, string $attributeCode): string
{
    foreach ($customer['custom_attributes'] ?? [] as $attribute) {
        if (!is_array($attribute)) {
            continue;
        }
        if ((string) ($attribute['attribute_code'] ?? '') === $attributeCode) {
            return trim((string) ($attribute['value'] ?? ''));
        }
    }

    return '';
}

/**
 * Set patient_shared_catalog_id on the company admin customer. Does not call assignCompanies.
 *
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_accs_config_set_admin_patient_shared_catalog(int $customerId, int $catalogId): array
{
    if ($customerId <= 0 || $catalogId <= 0) {
        return ['ok' => false, 'error' => 'Customer ID and shared catalog ID are required.'];
    }

    $current = provider_signup_accs_config_api_request('GET', '/customers/' . $customerId);
    if (!$current['ok'] || !is_array($current['data'] ?? null)) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($current)];
    }

    $customer = $current['data'];
    $catalogValue = (string) $catalogId;
    if (provider_signup_accs_config_customer_attribute_value($customer, PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE) === $catalogValue) {
        return ['ok' => true, 'error' => null];
    }

    $attributes = [];
    foreach ($customer['custom_attributes'] ?? [] as $attribute) {
        if (!is_array($attribute)) {
            continue;
        }
        $code = trim((string) ($attribute['attribute_code'] ?? ''));
        if ($code === '' || $code === PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE) {
            continue;
        }
        $attributes[] = [
            'attribute_code' => $code,
            'value'          => $attribute['value'] ?? '',
        ];
    }
    $attributes[] = [
        'attribute_code' => PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE,
        'value'          => $catalogValue,
    ];

    $result = provider_signup_accs_config_api_request('PUT', '/customers/' . $customerId, null, [
        'customer' => [
            'id'                 => $customerId,
            'email'              => (string) ($customer['email'] ?? ''),
            'firstname'          => (string) ($customer['firstname'] ?? ''),
            'lastname'           => (string) ($customer['lastname'] ?? ''),
            'website_id'         => (int) ($customer['website_id'] ?? provider_signup_accs_website_id()),
            'group_id'           => (int) ($customer['group_id'] ?? provider_signup_accs_customer_group_id()),
            'custom_attributes'  => $attributes,
        ],
    ]);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($result)];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Resolve MSRP used for shared-catalog custom pricing.
 * Prefer the product `msrp` attribute when set; otherwise Magento base `price`
 * (the value shown as MSRP in Set Pricing and Structure).
 *
 * @param array<string, mixed> $product
 */
function provider_signup_accs_config_product_msrp(array $product): ?float
{
    foreach ($product['custom_attributes'] ?? [] as $attribute) {
        if (!is_array($attribute)) {
            continue;
        }
        if (trim((string) ($attribute['attribute_code'] ?? '')) !== 'msrp') {
            continue;
        }
        $raw = $attribute['value'] ?? null;
        if ($raw === null || $raw === '') {
            break;
        }
        $msrp = round((float) $raw, 4);

        return $msrp > 0 ? $msrp : null;
    }

    if (!array_key_exists('price', $product) || $product['price'] === null || $product['price'] === '') {
        return null;
    }

    $price = round((float) $product['price'], 4);

    return $price > 0 ? $price : null;
}

/**
 * Magento product status: 1 = Enabled, 2 = Disabled.
 */
function provider_signup_accs_config_product_is_enabled(array $product): bool
{
    return (int) ($product['status'] ?? 0) === 1;
}

/**
 * Keep only Enabled product SKUs (skip Disabled products for shared catalogs).
 *
 * @param list<string> $skus
 * @return array{ok: bool, error: ?string, skus: list<string>, skipped: list<string>}
 */
function provider_signup_accs_config_filter_enabled_skus(array $skus): array
{
    $skus = array_values(array_unique(array_filter(array_map(
        static fn ($sku): string => trim((string) $sku),
        $skus
    ))));
    if ($skus === []) {
        return ['ok' => true, 'error' => null, 'skus' => [], 'skipped' => []];
    }

    $loaded = provider_signup_accs_config_load_products_by_sku($skus);
    if (!$loaded['ok']) {
        return [
            'ok'      => false,
            'error'   => $loaded['error'] ?? 'Unable to load products while filtering disabled SKUs.',
            'skus'    => [],
            'skipped' => [],
        ];
    }

    $enabled = [];
    $skipped = [];
    foreach ($skus as $sku) {
        $product = $loaded['products'][$sku] ?? null;
        if (!is_array($product)) {
            $skipped[] = $sku;
            continue;
        }
        if (provider_signup_accs_config_product_is_enabled($product)) {
            $enabled[] = $sku;
        } else {
            $skipped[] = $sku;
        }
    }

    return [
        'ok'      => true,
        'error'   => null,
        'skus'    => $enabled,
        'skipped' => $skipped,
    ];
}

/**
 * Load product records for the given SKUs.
 *
 * @param list<string> $skus
 * @return array{ok: bool, error: ?string, products: array<string, array<string, mixed>>}
 */
function provider_signup_accs_config_load_products_by_sku(array $skus): array
{
    $skus = array_values(array_unique(array_filter(array_map(
        static fn ($sku): string => trim((string) $sku),
        $skus
    ))));
    if ($skus === []) {
        return ['ok' => true, 'error' => null, 'products' => []];
    }

    $products = [];
    foreach (array_chunk($skus, 50) as $chunk) {
        $result = provider_signup_accs_config_api_request('GET', '/products', [
            'searchCriteria[pageSize]' => (string) count($chunk),
            'searchCriteria[currentPage]' => '1',
            'searchCriteria[filterGroups][0][filters][0][field]' => 'sku',
            'searchCriteria[filterGroups][0][filters][0][value]' => implode(',', $chunk),
            'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'in',
        ]);
        if (!$result['ok'] || !is_array($result['data'] ?? null)) {
            return [
                'ok'       => false,
                'error'    => provider_signup_accs_format_api_error($result),
                'products' => [],
            ];
        }

        foreach ($result['data']['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku !== '') {
                $products[$sku] = $item;
            }
        }
    }

    return ['ok' => true, 'error' => null, 'products' => $products];
}

/**
 * Set shared-catalog custom prices (tier prices for the catalog customer group) to each product MSRP.
 *
 * @param list<string> $skus
 * @return array{ok: bool, error: ?string, price_count: int}
 */
function provider_signup_accs_config_set_catalog_prices_to_msrp(int $catalogId, array $skus): array
{
    if ($catalogId <= 0) {
        return ['ok' => false, 'error' => 'Shared catalog ID is required.', 'price_count' => 0];
    }

    $skus = array_values(array_unique(array_filter(array_map(
        static fn ($sku): string => trim((string) $sku),
        $skus
    ))));
    if ($skus === []) {
        return ['ok' => true, 'error' => null, 'price_count' => 0];
    }

    $catalog = provider_signup_accs_config_api_request('GET', '/sharedCatalog/' . $catalogId);
    if (!$catalog['ok'] || !is_array($catalog['data'] ?? null)) {
        return [
            'ok'          => false,
            'error'       => provider_signup_accs_format_api_error($catalog),
            'price_count' => 0,
        ];
    }

    $groupId = (int) ($catalog['data']['customer_group_id'] ?? 0);
    $groupCode = trim((string) ($catalog['data']['name'] ?? ''));
    if ($groupId > 0) {
        $group = provider_signup_accs_config_api_request('GET', '/customerGroups/' . $groupId);
        if ($group['ok'] && is_array($group['data'] ?? null)) {
            $fromGroup = trim((string) ($group['data']['code'] ?? ''));
            if ($fromGroup !== '') {
                $groupCode = $fromGroup;
            }
        }
    }
    if ($groupCode === '') {
        return [
            'ok'          => false,
            'error'       => 'Unable to resolve shared catalog customer group for custom pricing.',
            'price_count' => 0,
        ];
    }

    $loaded = provider_signup_accs_config_load_products_by_sku($skus);
    if (!$loaded['ok']) {
        return [
            'ok'          => false,
            'error'       => $loaded['error'] ?? 'Unable to load products for MSRP pricing.',
            'price_count' => 0,
        ];
    }

    $prices = [];
    foreach ($skus as $sku) {
        $product = $loaded['products'][$sku] ?? null;
        if (!is_array($product)) {
            return [
                'ok'          => false,
                'error'       => 'Product SKU "' . $sku . '" was not found while setting shared catalog MSRP prices.',
                'price_count' => 0,
            ];
        }
        $msrp = provider_signup_accs_config_product_msrp($product);
        if ($msrp === null) {
            return [
                'ok'          => false,
                'error'       => 'Product SKU "' . $sku . '" has no MSRP/base price for shared catalog custom pricing.',
                'price_count' => 0,
            ];
        }
        $prices[] = [
            'price'          => $msrp,
            'price_type'     => 'fixed',
            'website_id'     => 0,
            'sku'            => $sku,
            'customer_group' => $groupCode,
            'quantity'       => 1,
        ];
    }

    foreach (array_chunk($prices, 50) as $chunk) {
        $result = provider_signup_accs_config_api_request('POST', '/products/tier-prices', null, [
            'prices' => $chunk,
        ]);
        if (!$result['ok']) {
            return [
                'ok'          => false,
                'error'       => provider_signup_accs_format_api_error($result),
                'price_count' => 0,
            ];
        }
    }

    return [
        'ok'          => true,
        'error'       => null,
        'price_count' => count($prices),
    ];
}

/**
 * @return array{ok: bool, error: ?string, category_count: int, product_count: int}
 */
function provider_signup_accs_config_assign_catalog_contents(int $catalogId, int $customerId): array
{
    if ($catalogId <= 0 || $customerId <= 0) {
        return ['ok' => false, 'error' => 'Catalog ID and company admin customer ID are required.', 'category_count' => 0, 'product_count' => 0];
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
        $enabledFilter = provider_signup_accs_config_filter_enabled_skus($skus);
        if (!$enabledFilter['ok']) {
            return [
                'ok'             => false,
                'error'          => $enabledFilter['error'] ?? 'Unable to filter disabled products before shared catalog assignment.',
                'category_count' => count($categoryIds),
                'product_count'  => 0,
            ];
        }
        $skus = $enabledFilter['skus'];
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

        $msrpPrices = provider_signup_accs_config_set_catalog_prices_to_msrp($catalogId, $skus);
        if (!$msrpPrices['ok']) {
            return [
                'ok'             => false,
                'error'          => $msrpPrices['error'] ?? 'Unable to set shared catalog custom prices to MSRP.',
                'category_count' => count($categoryIds),
                'product_count'  => count($skus),
            ];
        }
    }

    $adminCatalog = provider_signup_accs_config_set_admin_patient_shared_catalog($customerId, $catalogId);
    if (!$adminCatalog['ok']) {
        return [
            'ok'             => false,
            'error'          => $adminCatalog['error'] ?? 'Unable to set patient_shared_catalog_id on the company admin.',
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
                'error'   => 'Clinic role template company is not configured. Run scripts/provider-signup-bootstrap-clinic-template.php or set PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID.',
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
 * @return array{ok: bool, error: ?string, category_count: int, product_count: int, patient_catalog_assigned: bool}
 */
function provider_signup_accs_config_verify_catalog_assignment(int $catalogId, int $customerId): array
{
    $categoryCount = 0;
    $productCount = 0;
    $patientCatalogAssigned = false;

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

    if ($customerId > 0) {
        $customer = provider_signup_accs_config_api_request('GET', '/customers/' . $customerId);
        if ($customer['ok'] && is_array($customer['data'] ?? null)) {
            $patientCatalogAssigned = provider_signup_accs_config_customer_attribute_value(
                $customer['data'],
                PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE
            ) === (string) $catalogId;
        }
    }

    if ($categoryCount <= 0 || $productCount <= 0 || !$patientCatalogAssigned) {
        return [
            'ok'                       => false,
            'error'                    => 'Shared catalog assignment is incomplete (categories, products, or admin patient_shared_catalog_id).',
            'category_count'           => $categoryCount,
            'product_count'            => $productCount,
            'patient_catalog_assigned' => $patientCatalogAssigned,
        ];
    }

    return [
        'ok'                       => true,
        'error'                    => null,
        'category_count'           => $categoryCount,
        'product_count'            => $productCount,
        'patient_catalog_assigned' => true,
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

    $admin = provider_signup_accs_config_resolve_admin_customer_id($application, $companyId);
    if (!$admin['ok'] || (int) ($admin['customer_id'] ?? 0) <= 0) {
        return [
            'ok'                     => false,
            'error'                  => $admin['error'] ?? 'ACCS company admin customer ID is required before clinic configuration can run.',
            'company_id'             => $companyId,
            'shared_catalog_id'      => null,
            'category_count'         => null,
            'product_count'          => null,
            'roles_summary'          => null,
            'configuration_complete' => false,
            'steps'                  => [],
        ];
    }
    $customerId = (int) $admin['customer_id'];

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

        $verify = provider_signup_accs_config_verify_catalog_assignment($sharedCatalogId, $customerId);
        if (!$verify['ok']) {
            $assign = provider_signup_accs_config_assign_catalog_contents($sharedCatalogId, $customerId);
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

/**
 * @return list<array{resource_id: mixed, permission: mixed}>
 */
function provider_signup_accs_config_role_permissions_from_role_data(array $roleData): array
{
    $permissions = [];
    foreach ($roleData['permissions'] ?? [] as $permission) {
        if (!is_array($permission) || empty($permission['resource_id'])) {
            continue;
        }
        $permissions[] = [
            'resource_id' => $permission['resource_id'],
            'permission'  => $permission['permission'] ?? 'allow',
        ];
    }

    return $permissions;
}

/**
 * @param array<string, array<string, mixed>> $existingByName
 * @return array{ok: bool, error: ?string, role_name: ?string, action: ?string, role_id: ?int}
 */
function provider_signup_accs_config_upsert_company_role(
    int $companyId,
    string $roleName,
    array $permissions,
    array $existingByName
): array {
    if (isset($existingByName[$roleName])) {
        $current = $existingByName[$roleName];
        $roleId = (int) ($current['id'] ?? 0);
        if ($roleId <= 0) {
            return ['ok' => false, 'error' => 'Existing role is missing an ID.', 'role_name' => $roleName, 'action' => null, 'role_id' => null];
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
                'role_id'   => null,
            ];
        }

        return ['ok' => true, 'error' => null, 'role_name' => $roleName, 'action' => 'updated', 'role_id' => $roleId];
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
            'role_id'   => null,
        ];
    }

    return [
        'ok'        => true,
        'error'     => null,
        'role_name' => $roleName,
        'action'    => 'created',
        'role_id'   => (int) ($create['data']['id'] ?? 0),
    ];
}

/**
 * @return array{ok: bool, error: ?string, company_id: ?int, action: ?string}
 */
function provider_signup_accs_config_bootstrap_clinic_template_company(int $superUserId): array
{
    $companyName = provider_signup_accs_config_template_company_name();
    $existingId = provider_signup_accs_config_find_company_id_by_name($companyName);
    if ($existingId !== null) {
        return ['ok' => true, 'error' => null, 'company_id' => $existingId, 'action' => 'existing'];
    }

    if ($superUserId <= 0) {
        return ['ok' => false, 'error' => 'A valid ACCS super user ID is required to create the clinic template company.', 'company_id' => null, 'action' => null];
    }

    $groupId = provider_signup_accs_customer_group_id();
    $salesRepresentativeId = provider_signup_accs_sales_representative_id();
    $result = provider_signup_accs_config_api_request('POST', '/company', null, [
        'company' => [
            'status'                  => 1,
            'company_name'            => $companyName,
            'legal_name'              => $companyName,
            'company_email'           => 'clinic-template@nutraaxislabs.com',
            'comment'                 => 'NutraAxis clinic role template company. Do not assign to live clinics.',
            'street'                  => ['123 Template Street'],
            'city'                    => 'Frisco',
            'country_id'              => 'US',
            'region_id'               => provider_signup_accs_region_id_for_state('TX', 'US'),
            'postcode'                => '75035',
            'telephone'               => '2145550100',
            'customer_group_id'       => $groupId,
            'sales_representative_id' => $salesRepresentativeId,
            'super_user_id'           => $superUserId,
        ],
    ]);

    if (!$result['ok']) {
        return [
            'ok'         => false,
            'error'      => provider_signup_accs_format_api_error($result),
            'company_id' => null,
            'action'     => null,
        ];
    }

    $companyId = (int) ($result['data']['id'] ?? 0);
    if ($companyId <= 0) {
        return ['ok' => false, 'error' => 'ACCS did not return a clinic template company ID.', 'company_id' => null, 'action' => null];
    }

    return ['ok' => true, 'error' => null, 'company_id' => $companyId, 'action' => 'created'];
}

/**
 * @return array{ok: bool, error: ?string, roles: array<string, list<array{resource_id: mixed, permission: mixed}>>}
 */
function provider_signup_accs_config_load_template_role_definitions(
    ?string $sourceEnvironment = null,
    ?int $sourceCompanyId = null
): array {
    $sourceEnvironment = $sourceEnvironment ?? provider_signup_accs_config_template_source_environment();
    $sourceCompanyId = $sourceCompanyId ?? provider_signup_accs_config_template_source_company_id();
    if ($sourceCompanyId <= 0) {
        return ['ok' => false, 'error' => 'Source company ID is required.', 'roles' => []];
    }

    $sourceRoles = provider_signup_accs_config_api_request_for_environment($sourceEnvironment, 'GET', '/company/role', [
        'searchCriteria[pageSize]'                                     => '50',
        'searchCriteria[currentPage]'                                  => '1',
        'searchCriteria[filterGroups][0][filters][0][field]'           => 'company_id',
        'searchCriteria[filterGroups][0][filters][0][value]'          => (string) $sourceCompanyId,
        'searchCriteria[filterGroups][0][filters][0][conditionType]'    => 'eq',
    ]);
    if (!$sourceRoles['ok'] || !is_array($sourceRoles['data'] ?? null)) {
        return [
            'ok'    => false,
            'error' => provider_signup_accs_format_api_error($sourceRoles),
            'roles' => [],
        ];
    }

    $definitions = [];
    foreach ($sourceRoles['data']['items'] ?? [] as $role) {
        if (!is_array($role)) {
            continue;
        }
        $roleName = trim((string) ($role['role_name'] ?? ''));
        if ($roleName === '') {
            continue;
        }

        $sourceRoleId = (int) ($role['id'] ?? 0);
        $sourceDetail = $sourceRoleId > 0
            ? provider_signup_accs_config_api_request_for_environment($sourceEnvironment, 'GET', '/company/role/' . $sourceRoleId)
            : ['ok' => false, 'error' => 'Missing source role ID.', 'data' => null];
        if (!$sourceDetail['ok'] || !is_array($sourceDetail['data'] ?? null)) {
            return [
                'ok'    => false,
                'error' => provider_signup_accs_format_api_error($sourceDetail),
                'roles' => [],
            ];
        }

        $definitions[$roleName] = provider_signup_accs_config_role_permissions_from_role_data($sourceDetail['data']);
    }

    return ['ok' => true, 'error' => null, 'roles' => $definitions];
}

/**
 * @param array<string, list<array{resource_id: mixed, permission: mixed}>> $roleDefinitions
 * @return array{ok: bool, error: ?string, summary: ?string, actions: list<string>, company_id: ?int}
 */
function provider_signup_accs_config_apply_template_role_definitions(int $targetCompanyId, array $roleDefinitions): array
{
    if ($targetCompanyId <= 0) {
        return ['ok' => false, 'error' => 'Target company ID is required.', 'summary' => null, 'actions' => [], 'company_id' => null];
    }

    $requiredNames = provider_signup_accs_config_required_role_names();
    $existing = provider_signup_accs_config_list_company_roles($targetCompanyId);
    if (!$existing['ok']) {
        return ['ok' => false, 'error' => $existing['error'], 'summary' => null, 'actions' => [], 'company_id' => $targetCompanyId];
    }

    $existingByName = [];
    foreach ($existing['roles'] as $role) {
        $roleName = trim((string) ($role['role_name'] ?? ''));
        if ($roleName !== '') {
            $existingByName[$roleName] = $role;
        }
    }

    $actions = [];
    foreach ($requiredNames as $roleName) {
        if (!isset($roleDefinitions[$roleName])) {
            return [
                'ok'         => false,
                'error'      => 'Source company is missing required role "' . $roleName . '".',
                'summary'    => null,
                'actions'    => $actions,
                'company_id' => $targetCompanyId,
            ];
        }

        $upsert = provider_signup_accs_config_upsert_company_role(
            $targetCompanyId,
            $roleName,
            $roleDefinitions[$roleName],
            $existingByName
        );
        if (!$upsert['ok']) {
            return [
                'ok'         => false,
                'error'      => $upsert['error'],
                'summary'    => null,
                'actions'    => $actions,
                'company_id' => $targetCompanyId,
            ];
        }

        $actions[] = ($upsert['action'] ?? 'updated') . ':' . $roleName;
        if (!empty($upsert['role_name'])) {
            $existingByName[$upsert['role_name']] = ['role_name' => $upsert['role_name'], 'id' => $upsert['role_id'] ?? null];
        }
    }

    $finalRoles = provider_signup_accs_config_list_company_roles($targetCompanyId);
    if (!$finalRoles['ok']) {
        return ['ok' => false, 'error' => $finalRoles['error'], 'summary' => null, 'actions' => $actions, 'company_id' => $targetCompanyId];
    }

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
            'ok'         => false,
            'error'      => 'Clinic template company is missing required roles: ' . implode(', ', $missing) . '.',
            'summary'    => null,
            'actions'    => $actions,
            'company_id' => $targetCompanyId,
        ];
    }

    return [
        'ok'         => true,
        'error'      => null,
        'summary'    => implode(', ', $finalNames),
        'actions'    => $actions,
        'company_id' => $targetCompanyId,
    ];
}

/**
 * @return array{ok: bool, error: ?string, summary: ?string, actions: list<string>, company_id: ?int}
 */
function provider_signup_accs_config_seed_clinic_template_roles(
    int $targetCompanyId,
    ?string $sourceEnvironment = null,
    ?int $sourceCompanyId = null
): array {
    $definitions = provider_signup_accs_config_load_template_role_definitions($sourceEnvironment, $sourceCompanyId);
    if (!$definitions['ok']) {
        return [
            'ok'         => false,
            'error'      => $definitions['error'] ?? 'Unable to load template role definitions.',
            'summary'    => null,
            'actions'    => [],
            'company_id' => $targetCompanyId,
        ];
    }

    return provider_signup_accs_config_apply_template_role_definitions($targetCompanyId, $definitions['roles']);
}

/**
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   company_id: ?int,
 *   company_action: ?string,
 *   roles_summary: ?string,
 *   role_actions: list<string>
 * }
 */
function provider_signup_accs_config_bootstrap_clinic_template(int $superUserId): array
{
    $definitions = provider_signup_accs_config_load_template_role_definitions();
    if (!$definitions['ok']) {
        return [
            'ok'             => false,
            'error'          => $definitions['error'] ?? 'Unable to load clinic template role definitions.',
            'company_id'     => null,
            'company_action' => null,
            'roles_summary'  => null,
            'role_actions'   => [],
        ];
    }

    $company = provider_signup_accs_config_bootstrap_clinic_template_company($superUserId);
    if (!$company['ok'] || empty($company['company_id'])) {
        return [
            'ok'             => false,
            'error'          => $company['error'] ?? 'Unable to create clinic template company.',
            'company_id'     => null,
            'company_action' => null,
            'roles_summary'  => null,
            'role_actions'   => [],
        ];
    }

    $roles = provider_signup_accs_config_apply_template_role_definitions((int) $company['company_id'], $definitions['roles']);
    if (!$roles['ok']) {
        return [
            'ok'             => false,
            'error'          => $roles['error'] ?? 'Unable to seed clinic template roles.',
            'company_id'     => (int) $company['company_id'],
            'company_action' => $company['action'] ?? null,
            'roles_summary'  => null,
            'role_actions'   => $roles['actions'] ?? [],
        ];
    }

    return [
        'ok'             => true,
        'error'          => null,
        'company_id'     => (int) $company['company_id'],
        'company_action' => $company['action'] ?? null,
        'roles_summary'  => $roles['summary'] ?? null,
        'role_actions'   => $roles['actions'] ?? [],
    ];
}
