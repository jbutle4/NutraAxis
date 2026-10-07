<?php

require_once __DIR__ . '/provider-signup-accs.php';
require_once __DIR__ . '/provider-signup-accs-config.php';
require_once __DIR__ . '/provider-signup-accs-deprovision.php';
require_once __DIR__ . '/accs-support-case.php';

const ACCS_SUPPORT_CLONE_STAGE_ENV = 'stage';
const ACCS_SUPPORT_CLONE_PROD_ENV = 'production';
const ACCS_SUPPORT_CLONE_NAME_PREFIX = 'SUPPORT-';

/**
 * @return array{ok: bool, error: ?string, companies: list<array<string, mixed>>}
 */
function accs_support_clone_search_prod_companies(string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '') {
        return ['ok' => false, 'error' => 'Enter a company ID or name to search Production.', 'companies' => []];
    }

    return provider_signup_accs_with_environment(ACCS_SUPPORT_CLONE_PROD_ENV, static function () use ($query, $limit): array {
        $limit = max(1, min(50, $limit));
        $filters = [];
        if (ctype_digit($query)) {
            $filters = [
                'searchCriteria[pageSize]'                                     => (string) $limit,
                'searchCriteria[currentPage]'                                  => '1',
                'searchCriteria[filterGroups][0][filters][0][field]'           => 'entity_id',
                'searchCriteria[filterGroups][0][filters][0][value]'          => $query,
                'searchCriteria[filterGroups][0][filters][0][conditionType]'  => 'eq',
            ];
        } else {
            $filters = [
                'searchCriteria[pageSize]'                                     => (string) $limit,
                'searchCriteria[currentPage]'                                  => '1',
                'searchCriteria[filterGroups][0][filters][0][field]'           => 'company_name',
                'searchCriteria[filterGroups][0][filters][0][value]'          => '%' . $query . '%',
                'searchCriteria[filterGroups][0][filters][0][conditionType]'  => 'like',
            ];
        }

        $result = provider_signup_accs_api_request('GET', '/company/', $filters);
        if (!$result['ok'] || !is_array($result['data'] ?? null)) {
            // Some tenants use /company without trailing slash / entity_id vs id.
            if (ctype_digit($query)) {
                $byId = provider_signup_accs_api_request('GET', '/company/' . (int) $query);
                if ($byId['ok'] && is_array($byId['data'] ?? null)) {
                    return [
                        'ok'        => true,
                        'error'     => null,
                        'companies' => [accs_support_clone_company_summary($byId['data'])],
                    ];
                }
            }

            return [
                'ok'        => false,
                'error'     => provider_signup_accs_format_api_error($result),
                'companies' => [],
            ];
        }

        $companies = [];
        foreach ($result['data']['items'] ?? [] as $item) {
            if (is_array($item)) {
                $companies[] = accs_support_clone_company_summary($item);
            }
        }

        if ($companies === [] && ctype_digit($query)) {
            $byId = provider_signup_accs_api_request('GET', '/company/' . (int) $query);
            if ($byId['ok'] && is_array($byId['data'] ?? null)) {
                $companies[] = accs_support_clone_company_summary($byId['data']);
            }
        }

        return ['ok' => true, 'error' => null, 'companies' => $companies];
    });
}

/**
 * @param array<string, mixed> $company
 * @return array<string, mixed>
 */
function accs_support_clone_company_summary(array $company): array
{
    return [
        'id'           => (int) ($company['id'] ?? $company['entity_id'] ?? 0),
        'company_name' => trim((string) ($company['company_name'] ?? '')),
        'legal_name'   => trim((string) ($company['legal_name'] ?? '')),
        'company_email'=> trim((string) ($company['company_email'] ?? '')),
        'status'       => (int) ($company['status'] ?? 0),
        'super_user_id'=> (int) ($company['super_user_id'] ?? 0),
        'customer_group_id' => (int) ($company['customer_group_id'] ?? 0),
    ];
}

/**
 * Load Prod company + admin + shared catalog preview for clone.
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   company: ?array<string, mixed>,
 *   admin: ?array<string, mixed>,
 *   catalog: ?array<string, mixed>,
 *   sku_count: int,
 *   category_count: int,
 *   price_count: int
 * }
 */
function accs_support_clone_preview_prod_company(int $prodCompanyId): array
{
    if ($prodCompanyId <= 0) {
        return [
            'ok'             => false,
            'error'          => 'Production company ID is required.',
            'company'        => null,
            'admin'          => null,
            'catalog'        => null,
            'sku_count'      => 0,
            'category_count' => 0,
            'price_count'    => 0,
        ];
    }

    return provider_signup_accs_with_environment(ACCS_SUPPORT_CLONE_PROD_ENV, static function () use ($prodCompanyId): array {
        $companyResult = provider_signup_accs_api_request('GET', '/company/' . $prodCompanyId);
        if (!$companyResult['ok'] || !is_array($companyResult['data'] ?? null)) {
            return [
                'ok'             => false,
                'error'          => provider_signup_accs_format_api_error($companyResult),
                'company'        => null,
                'admin'          => null,
                'catalog'        => null,
                'sku_count'      => 0,
                'category_count' => 0,
                'price_count'    => 0,
            ];
        }

        $company = $companyResult['data'];
        $summary = accs_support_clone_company_summary($company);
        $adminCustomerId = (int) ($summary['super_user_id'] ?? 0);
        $admin = null;
        $catalogId = 0;

        if ($adminCustomerId > 0) {
            $adminResult = provider_signup_accs_api_request('GET', '/customers/' . $adminCustomerId);
            if ($adminResult['ok'] && is_array($adminResult['data'] ?? null)) {
                $adminData = $adminResult['data'];
                $catalogId = (int) provider_signup_accs_config_customer_attribute_value(
                    $adminData,
                    PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE
                );
                $admin = [
                    'id'         => (int) ($adminData['id'] ?? 0),
                    'email'      => strtolower(trim((string) ($adminData['email'] ?? ''))),
                    'firstname'  => trim((string) ($adminData['firstname'] ?? '')),
                    'lastname'   => trim((string) ($adminData['lastname'] ?? '')),
                    'group_id'   => (int) ($adminData['group_id'] ?? 0),
                    'catalog_id' => $catalogId,
                ];
            }
        }

        $catalog = null;
        $skuCount = 0;
        $categoryCount = 0;
        $priceCount = 0;
        if ($catalogId > 0) {
            $catalogResult = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId);
            if ($catalogResult['ok'] && is_array($catalogResult['data'] ?? null)) {
                $catalogData = $catalogResult['data'];
                $skus = accs_support_clone_catalog_skus($catalogId);
                $categories = accs_support_clone_catalog_category_ids($catalogId);
                $skuCount = count($skus);
                $categoryCount = count($categories);
                $prices = accs_support_clone_read_tier_prices_for_catalog($catalogId, $skus);
                $priceCount = count($prices['prices'] ?? []);
                $catalog = [
                    'id'                => $catalogId,
                    'name'              => trim((string) ($catalogData['name'] ?? '')),
                    'type'              => (int) ($catalogData['type'] ?? 0),
                    'customer_group_id' => (int) ($catalogData['customer_group_id'] ?? 0),
                    'sku_count'         => $skuCount,
                    'category_count'    => $categoryCount,
                    'price_count'       => $priceCount,
                ];
            }
        }

        return [
            'ok'             => true,
            'error'          => null,
            'company'        => $summary + ['raw' => $company],
            'admin'          => $admin,
            'catalog'        => $catalog,
            'sku_count'      => $skuCount,
            'category_count' => $categoryCount,
            'price_count'    => $priceCount,
        ];
    });
}

/**
 * @return list<string>
 */
function accs_support_clone_catalog_skus(int $catalogId): array
{
    return provider_signup_accs_deprovision_catalog_skus($catalogId);
}

/**
 * @return list<int>
 */
function accs_support_clone_catalog_category_ids(int $catalogId): array
{
    $categories = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId . '/categories');
    if (!$categories['ok']) {
        return [];
    }

    $ids = [];
    foreach ($categories['data'] ?? [] as $categoryId) {
        $id = (int) $categoryId;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * Read Prod tier prices for catalog group from product payloads.
 *
 * @param list<string> $skus
 * @return array{ok: bool, error: ?string, prices: list<array<string, mixed>>, group_id: int, group_code: string}
 */
function accs_support_clone_read_tier_prices_for_catalog(int $catalogId, array $skus): array
{
    $catalog = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId);
    if (!$catalog['ok'] || !is_array($catalog['data'] ?? null)) {
        return [
            'ok'         => false,
            'error'      => provider_signup_accs_format_api_error($catalog),
            'prices'     => [],
            'group_id'   => 0,
            'group_code' => '',
        ];
    }

    $groupId = (int) ($catalog['data']['customer_group_id'] ?? 0);
    $groupCode = trim((string) ($catalog['data']['name'] ?? ''));
    if ($groupId > 0) {
        $group = provider_signup_accs_api_request('GET', '/customerGroups/' . $groupId);
        if ($group['ok'] && is_array($group['data'] ?? null)) {
            $fromGroup = trim((string) ($group['data']['code'] ?? ''));
            if ($fromGroup !== '') {
                $groupCode = $fromGroup;
            }
        }
    }

    $loaded = provider_signup_accs_config_load_products_by_sku($skus);
    if (!$loaded['ok']) {
        return [
            'ok'         => false,
            'error'      => $loaded['error'] ?? 'Unable to load products for tier prices.',
            'prices'     => [],
            'group_id'   => $groupId,
            'group_code' => $groupCode,
        ];
    }

    $prices = [];
    foreach ($skus as $sku) {
        $product = $loaded['products'][$sku] ?? null;
        if (!is_array($product)) {
            continue;
        }
        foreach ($product['tier_prices'] ?? [] as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $tierGroupId = (int) ($tier['customer_group_id'] ?? -1);
            if ($groupId > 0 && $tierGroupId !== $groupId) {
                continue;
            }
            $price = isset($tier['value']) ? (float) $tier['value'] : null;
            if ($price === null && isset($tier['extension_attributes']['percentage_value'])) {
                continue;
            }
            if ($price === null) {
                continue;
            }
            $prices[] = [
                'sku'        => $sku,
                'price'      => $price,
                'price_type' => 'fixed',
                'website_id' => (int) ($tier['website_id'] ?? 0),
                'quantity'   => max(1, (float) ($tier['qty'] ?? 1)),
            ];
        }
    }

    return [
        'ok'         => true,
        'error'      => null,
        'prices'     => $prices,
        'group_id'   => $groupId,
        'group_code' => $groupCode,
    ];
}

function accs_support_clone_stage_company_name(string $prodCompanyName, int $caseId): string
{
    $base = trim($prodCompanyName);
    if ($base === '') {
        $base = 'Clinic';
    }
    $name = ACCS_SUPPORT_CLONE_NAME_PREFIX . $base . '-' . $caseId;
    if (strlen($name) > 240) {
        $name = ACCS_SUPPORT_CLONE_NAME_PREFIX . substr($base, 0, 200) . '-' . $caseId;
    }

    return $name;
}

function accs_support_clone_stage_catalog_name(string $stageCompanyName): string
{
    return 'SC-' . $stageCompanyName;
}

/**
 * Clone Prod company + admin + Prod-mirrored shared catalog onto Stage.
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   case_id: int,
 *   stage_company_id: ?int,
 *   stage_customer_id: ?int,
 *   stage_catalog_id: ?int,
 *   summary: array<string, mixed>,
 *   temp_password: ?string
 * }
 */
function accs_support_clone_run(int $caseId, int $prodCompanyId): array
{
    if ($caseId <= 0 || $prodCompanyId <= 0) {
        return [
            'ok'                => false,
            'error'             => 'Case ID and Production company ID are required.',
            'case_id'           => $caseId,
            'stage_company_id'  => null,
            'stage_customer_id' => null,
            'stage_catalog_id'  => null,
            'summary'           => [],
            'temp_password'     => null,
        ];
    }

    $case = accs_support_case_get($caseId);
    if ($case === null) {
        return [
            'ok'                => false,
            'error'             => 'Support case not found.',
            'case_id'           => $caseId,
            'stage_company_id'  => null,
            'stage_customer_id' => null,
            'stage_catalog_id'  => null,
            'summary'           => [],
            'temp_password'     => null,
        ];
    }

    if (!empty($case['StageCompanyId']) && !in_array((string) $case['Status'], ['clone_failed', 'purged'], true)) {
        return [
            'ok'                => false,
            'error'             => 'This case already has a Stage company. Purge it first or open a new case.',
            'case_id'           => $caseId,
            'stage_company_id'  => (int) $case['StageCompanyId'],
            'stage_customer_id' => (int) ($case['StageCustomerId'] ?? 0) ?: null,
            'stage_catalog_id'  => (int) ($case['StageSharedCatalogId'] ?? 0) ?: null,
            'summary'           => [],
            'temp_password'     => null,
        ];
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    accs_support_case_update($caseId, ['status' => 'clone_started', 'prod_company_id' => $prodCompanyId]);
    accs_support_case_add_event($caseId, 'clone_started', 'Clone from Production started.', [
        'prod_company_id' => $prodCompanyId,
    ]);

    $preview = accs_support_clone_preview_prod_company($prodCompanyId);
    if (!$preview['ok'] || !is_array($preview['company'] ?? null)) {
        $error = $preview['error'] ?? 'Unable to load Production company.';
        accs_support_case_update($caseId, ['status' => 'clone_failed']);
        accs_support_case_add_event($caseId, 'clone_failed', $error);
        return [
            'ok'                => false,
            'error'             => $error,
            'case_id'           => $caseId,
            'stage_company_id'  => null,
            'stage_customer_id' => null,
            'stage_catalog_id'  => null,
            'summary'           => [],
            'temp_password'     => null,
        ];
    }

    $prodCompany = $preview['company'];
    $prodAdmin = is_array($preview['admin'] ?? null) ? $preview['admin'] : null;
    $prodCatalog = is_array($preview['catalog'] ?? null) ? $preview['catalog'] : null;
    $prodCompanyName = trim((string) ($prodCompany['company_name'] ?? ''));
    $stageCompanyName = accs_support_clone_stage_company_name($prodCompanyName, $caseId);

    accs_support_case_update($caseId, [
        'prod_company_name'       => $prodCompanyName,
        'prod_admin_email'        => $prodAdmin['email'] ?? null,
        'prod_admin_customer_id'  => $prodAdmin['id'] ?? null,
        'prod_shared_catalog_id'  => $prodCatalog['id'] ?? null,
        'stage_company_name'      => $stageCompanyName,
        'subject'                 => trim((string) ($case['Subject'] ?? '')) !== ''
            ? (string) $case['Subject']
            : 'Support clone: ' . $prodCompanyName,
    ]);

    // Snapshot Prod catalog membership + prices while still on Prod env context inside preview helpers.
    $prodSnapshot = provider_signup_accs_with_environment(ACCS_SUPPORT_CLONE_PROD_ENV, static function () use ($prodCatalog): array {
        $catalogId = (int) ($prodCatalog['id'] ?? 0);
        if ($catalogId <= 0) {
            return ['skus' => [], 'categories' => [], 'prices' => [], 'error' => 'Production company has no patient shared catalog to mirror.'];
        }
        $skus = accs_support_clone_catalog_skus($catalogId);
        $categories = accs_support_clone_catalog_category_ids($catalogId);
        $prices = accs_support_clone_read_tier_prices_for_catalog($catalogId, $skus);
        return [
            'skus'       => $skus,
            'categories' => $categories,
            'prices'     => $prices['prices'] ?? [],
            'error'      => $prices['ok'] ? null : ($prices['error'] ?? null),
        ];
    });

    if (!empty($prodSnapshot['error']) && ($prodSnapshot['skus'] ?? []) === []) {
        $error = (string) $prodSnapshot['error'];
        accs_support_case_update($caseId, ['status' => 'clone_failed']);
        accs_support_case_add_event($caseId, 'clone_failed', $error);
        return [
            'ok'                => false,
            'error'             => $error,
            'case_id'           => $caseId,
            'stage_company_id'  => null,
            'stage_customer_id' => null,
            'stage_catalog_id'  => null,
            'summary'           => [],
            'temp_password'     => null,
        ];
    }

    return provider_signup_accs_with_environment(ACCS_SUPPORT_CLONE_STAGE_ENV, static function () use (
        $caseId,
        $prodCompany,
        $prodAdmin,
        $prodCatalog,
        $prodSnapshot,
        $stageCompanyName,
        $prodCompanyId
    ): array {
        $configError = adobe_commerce_config_error();
        if ($configError !== null) {
            accs_support_case_update($caseId, ['status' => 'clone_failed']);
            accs_support_case_add_event($caseId, 'clone_failed', $configError);
            return [
                'ok'                => false,
                'error'             => $configError,
                'case_id'           => $caseId,
                'stage_company_id'  => null,
                'stage_customer_id' => null,
                'stage_catalog_id'  => null,
                'summary'           => [],
                'temp_password'     => null,
            ];
        }

        $groupId = provider_signup_accs_customer_group_id();
        $rawCompany = is_array($prodCompany['raw'] ?? null) ? $prodCompany['raw'] : $prodCompany;
        $adminEmail = strtolower(trim((string) ($prodAdmin['email'] ?? '')));
        $adminFirst = trim((string) ($prodAdmin['firstname'] ?? 'Support'));
        $adminLast = trim((string) ($prodAdmin['lastname'] ?? 'Clone'));
        if ($adminEmail === '') {
            $adminEmail = 'support+case' . $caseId . '@nutraaxis.invalid';
        }

        $application = [
            'ApplicationID'    => 0,
            'CompanyName'      => $stageCompanyName,
            'CompanyLegalName' => trim((string) ($rawCompany['legal_name'] ?? $stageCompanyName)),
            'CompanyEmail'     => trim((string) ($rawCompany['company_email'] ?? $adminEmail)),
            'CompanyPhone'     => trim((string) ($rawCompany['telephone'] ?? '')),
            'StreetAddress'    => is_array($rawCompany['street'] ?? null)
                ? trim((string) (($rawCompany['street'][0] ?? '')))
                : trim((string) ($rawCompany['street'] ?? '')),
            'City'             => trim((string) ($rawCompany['city'] ?? '')),
            'StateCode'        => '',
            'PostalCode'       => trim((string) ($rawCompany['postcode'] ?? '')),
            'CountryCode'      => trim((string) ($rawCompany['country_id'] ?? 'US')) ?: 'US',
            'AdminFirstName'   => $adminFirst !== '' ? $adminFirst : 'Support',
            'AdminLastName'    => $adminLast !== '' ? $adminLast : 'Clone',
            'AdminEmail'       => $adminEmail,
            'AdminPhone'       => '',
            'ClinicType'       => 'Other',
            'NpiNumber'        => trim((string) ($rawCompany['reseller_id'] ?? '')),
            'TaxIdEncrypted'   => null,
        ];

        // Prefer region_id from Prod company when present.
        $regionId = (int) ($rawCompany['region_id'] ?? 0);
        if ($regionId <= 0) {
            $region = $rawCompany['region'] ?? null;
            if (is_array($region)) {
                $regionId = (int) ($region['region_id'] ?? 0);
                if ($application['StateCode'] === '') {
                    $application['StateCode'] = trim((string) ($region['region_code'] ?? ''));
                }
            }
        }

        $tempPassword = null;
        $customerId = 0;
        $existingAdmin = provider_signup_accs_search_customer_by_email($adminEmail);
        if (!$existingAdmin['ok']) {
            $error = $existingAdmin['error'] ?? 'Unable to search Stage customers.';
            accs_support_case_update($caseId, ['status' => 'clone_failed']);
            accs_support_case_add_event($caseId, 'clone_failed', $error);
            return [
                'ok'                => false,
                'error'             => $error,
                'case_id'           => $caseId,
                'stage_company_id'  => null,
                'stage_customer_id' => null,
                'stage_catalog_id'  => null,
                'summary'           => [],
                'temp_password'     => null,
            ];
        }

        if (is_array($existingAdmin['customer'] ?? null) && !empty($existingAdmin['customer']['id'])) {
            $customerId = (int) $existingAdmin['customer']['id'];
            $sync = provider_signup_accs_sync_company_admin_customer($customerId, $groupId, $existingAdmin['customer']);
            if (!$sync['ok']) {
                $error = $sync['error'] ?? 'Unable to sync existing Stage admin customer.';
                accs_support_case_update($caseId, ['status' => 'clone_failed']);
                accs_support_case_add_event($caseId, 'clone_failed', $error);
                return [
                    'ok'                => false,
                    'error'             => $error,
                    'case_id'           => $caseId,
                    'stage_company_id'  => null,
                    'stage_customer_id' => null,
                    'stage_catalog_id'  => null,
                    'summary'           => [],
                    'temp_password'     => null,
                ];
            }
            accs_support_case_add_resource(
                $caseId,
                'created',
                'customer',
                (string) $customerId,
                $adminEmail,
                (string) ($prodAdmin['id'] ?? ''),
                ['reused_existing' => true]
            );
        } else {
            $created = provider_signup_accs_create_customer($application, $groupId);
            if (!$created['ok'] || empty($created['customer_id'])) {
                $error = $created['error'] ?? 'Unable to create Stage company admin.';
                accs_support_case_update($caseId, ['status' => 'clone_failed']);
                accs_support_case_add_event($caseId, 'clone_failed', $error);
                return [
                    'ok'                => false,
                    'error'             => $error,
                    'case_id'           => $caseId,
                    'stage_company_id'  => null,
                    'stage_customer_id' => null,
                    'stage_catalog_id'  => null,
                    'summary'           => [],
                    'temp_password'     => null,
                ];
            }
            $customerId = (int) $created['customer_id'];
            $tempPassword = is_string($created['password'] ?? null) ? (string) $created['password'] : null;
            accs_support_case_add_resource(
                $caseId,
                'created',
                'customer',
                (string) $customerId,
                $adminEmail,
                (string) ($prodAdmin['id'] ?? ''),
                ['created' => true]
            );
        }

        // Create company with explicit region when available.
        $companyPayload = provider_signup_accs_build_company_payload($application, $groupId, $customerId);
        $companyPayload['company']['company_name'] = $stageCompanyName;
        $companyPayload['company']['comment'] = 'NutraAxis ACCS support clone for case #' . $caseId
            . ' from Production company #' . $prodCompanyId . ' (' . trim((string) ($prodCompany['company_name'] ?? '')) . ').'
            . "\nPurpose=support-clone. Do not email the provider.";
        if ($regionId > 0) {
            $companyPayload['company']['region_id'] = $regionId;
        } elseif (empty($application['StateCode'])) {
            // Fallback: try TX if no region — better than hard fail for address-less companies.
            $fallbackRegion = provider_signup_accs_region_id_for_state('TX', $application['CountryCode']);
            if ($fallbackRegion !== null) {
                $companyPayload['company']['region_id'] = $fallbackRegion;
                $companyPayload['company']['country_id'] = $application['CountryCode'];
            }
        }

        $companyCreate = provider_signup_accs_api_request('POST', '/company/', null, $companyPayload);
        if (!$companyCreate['ok']) {
            $companyCreate = provider_signup_accs_api_request('POST', '/company', null, $companyPayload);
        }
        if (!$companyCreate['ok']) {
            $error = provider_signup_accs_format_api_error($companyCreate);
            accs_support_case_update($caseId, ['status' => 'clone_failed']);
            accs_support_case_add_event($caseId, 'clone_failed', $error);
            return [
                'ok'                => false,
                'error'             => $error,
                'case_id'           => $caseId,
                'stage_company_id'  => null,
                'stage_customer_id' => $customerId,
                'stage_catalog_id'  => null,
                'summary'           => [],
                'temp_password'     => $tempPassword,
            ];
        }

        $stageCompanyId = (int) ($companyCreate['data']['id'] ?? 0);
        if ($stageCompanyId <= 0) {
            $error = 'ACCS Stage did not return a company ID.';
            accs_support_case_update($caseId, ['status' => 'clone_failed']);
            accs_support_case_add_event($caseId, 'clone_failed', $error);
            return [
                'ok'                => false,
                'error'             => $error,
                'case_id'           => $caseId,
                'stage_company_id'  => null,
                'stage_customer_id' => $customerId,
                'stage_catalog_id'  => null,
                'summary'           => [],
                'temp_password'     => $tempPassword,
            ];
        }

        accs_support_case_add_resource(
            $caseId,
            'created',
            'company',
            (string) $stageCompanyId,
            $stageCompanyName,
            (string) $prodCompanyId,
            ['purpose' => 'support-clone']
        );

        provider_signup_accs_set_company_defaults(
            $stageCompanyId,
            $groupId,
            provider_signup_accs_sales_representative_id()
        );
        provider_signup_accs_set_admin_clinic_id($customerId, $stageCompanyId);

        $roles = provider_signup_accs_config_clone_roles($stageCompanyId);
        if ($roles['ok']) {
            foreach ($roles['actions'] ?? [] as $roleAction) {
                accs_support_case_add_resource(
                    $caseId,
                    'created',
                    'company_role',
                    null,
                    is_string($roleAction) ? $roleAction : null,
                    null,
                    ['detail' => $roleAction]
                );
            }
        }

        $catalogEnsure = provider_signup_accs_config_ensure_shared_catalog([
            'CompanyName'   => $stageCompanyName,
            'ApplicationID' => $caseId,
        ]);

        if (!$catalogEnsure['ok'] || empty($catalogEnsure['catalog_id'])) {
            $error = $catalogEnsure['error'] ?? 'Unable to create Stage shared catalog.';
            accs_support_case_update($caseId, [
                'status'            => 'clone_failed',
                'stage_company_id'  => $stageCompanyId,
                'stage_customer_id' => $customerId,
            ]);
            accs_support_case_add_event($caseId, 'clone_failed', $error);
            return [
                'ok'                => false,
                'error'             => $error,
                'case_id'           => $caseId,
                'stage_company_id'  => $stageCompanyId,
                'stage_customer_id' => $customerId,
                'stage_catalog_id'  => null,
                'summary'           => [],
                'temp_password'     => $tempPassword,
            ];
        }

        $stageCatalogId = (int) $catalogEnsure['catalog_id'];
        $stageCatalogMeta = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $stageCatalogId);
        $stageGroupId = 0;
        $stageGroupCode = trim((string) ($catalogEnsure['name'] ?? accs_support_clone_stage_catalog_name($stageCompanyName)));
        if ($stageCatalogMeta['ok'] && is_array($stageCatalogMeta['data'] ?? null)) {
            $stageGroupId = (int) ($stageCatalogMeta['data']['customer_group_id'] ?? 0);
            if ($stageGroupId > 0) {
                $group = provider_signup_accs_api_request('GET', '/customerGroups/' . $stageGroupId);
                if ($group['ok'] && is_array($group['data'] ?? null)) {
                    $code = trim((string) ($group['data']['code'] ?? ''));
                    if ($code !== '') {
                        $stageGroupCode = $code;
                    }
                }
            }
        }

        accs_support_case_add_resource(
            $caseId,
            'created',
            'shared_catalog',
            (string) $stageCatalogId,
            $stageGroupCode,
            (string) ($prodCatalog['id'] ?? ''),
            ['customer_group_id' => $stageGroupId]
        );

        provider_signup_accs_add_catalog_group_to_free_shipping_rule($stageCatalogId);
        provider_signup_accs_config_set_admin_patient_shared_catalog($customerId, $stageCatalogId);

        $prodSkus = array_values(array_unique(array_filter(array_map(
            static fn ($sku): string => trim((string) $sku),
            $prodSnapshot['skus'] ?? []
        ))));
        $prodCategories = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $prodSnapshot['categories'] ?? []
        ))));
        $prodPrices = is_array($prodSnapshot['prices'] ?? null) ? $prodSnapshot['prices'] : [];

        $stageProducts = provider_signup_accs_config_load_products_by_sku($prodSkus);
        $stageSkuSet = [];
        if ($stageProducts['ok']) {
            foreach (array_keys($stageProducts['products'] ?? []) as $sku) {
                $stageSkuSet[$sku] = true;
            }
        }
        $mirroredSkus = [];
        $skippedSkus = [];
        foreach ($prodSkus as $sku) {
            if (isset($stageSkuSet[$sku])) {
                $mirroredSkus[] = $sku;
            } else {
                $skippedSkus[] = $sku;
            }
        }

        if ($prodCategories !== []) {
            provider_signup_accs_config_api_request(
                'POST',
                '/sharedCatalog/' . $stageCatalogId . '/assignCategories',
                null,
                [
                    'categories' => array_map(
                        static fn (int $categoryId): array => ['id' => $categoryId],
                        $prodCategories
                    ),
                ]
            );
        }

        if ($mirroredSkus !== []) {
            $payloads = [
                ['products' => array_map(static fn (string $sku): array => ['sku' => $sku], $mirroredSkus)],
                ['products' => $mirroredSkus],
            ];
            foreach (array_chunk($mirroredSkus, 100) as $chunk) {
                $assigned = false;
                foreach ($payloads as $base) {
                    $body = $base;
                    if (isset($body['products']) && is_array($body['products']) && $body['products'] !== [] && is_array($body['products'][0] ?? null)) {
                        $body['products'] = array_map(static fn (string $sku): array => ['sku' => $sku], $chunk);
                    } else {
                        $body['products'] = $chunk;
                    }
                    $assign = provider_signup_accs_config_api_request(
                        'POST',
                        '/sharedCatalog/' . $stageCatalogId . '/assignProducts',
                        null,
                        $body
                    );
                    if ($assign['ok']) {
                        $assigned = true;
                        break;
                    }
                }
                if (!$assigned) {
                    // Continue; report in summary.
                }
            }
        }

        $mirroredPrices = [];
        foreach ($prodPrices as $priceRow) {
            if (!is_array($priceRow)) {
                continue;
            }
            $sku = trim((string) ($priceRow['sku'] ?? ''));
            if ($sku === '' || !isset($stageSkuSet[$sku])) {
                continue;
            }
            $mirroredPrices[] = [
                'price'          => (float) ($priceRow['price'] ?? 0),
                'price_type'     => 'fixed',
                'website_id'     => (int) ($priceRow['website_id'] ?? 0),
                'sku'            => $sku,
                'customer_group' => $stageGroupCode,
                'quantity'       => max(1, (float) ($priceRow['quantity'] ?? 1)),
            ];
        }

        $priceCount = 0;
        foreach (array_chunk($mirroredPrices, 50) as $chunk) {
            $priceResult = provider_signup_accs_config_api_request('POST', '/products/tier-prices', null, [
                'prices' => $chunk,
            ]);
            if ($priceResult['ok']) {
                $priceCount += count($chunk);
            }
        }
        if ($priceCount > 0) {
            accs_support_case_add_resource(
                $caseId,
                'created',
                'tier_price_batch',
                (string) $stageCatalogId,
                $stageGroupCode,
                (string) ($prodCatalog['id'] ?? ''),
                ['price_count' => $priceCount]
            );
        }

        $summary = [
            'prod_company_id'     => $prodCompanyId,
            'prod_catalog_id'     => (int) ($prodCatalog['id'] ?? 0),
            'stage_company_id'    => $stageCompanyId,
            'stage_catalog_id'    => $stageCatalogId,
            'stage_customer_id'   => $customerId,
            'admin_email'         => $adminEmail,
            'sku_prod_count'      => count($prodSkus),
            'sku_mirrored_count'  => count($mirroredSkus),
            'sku_skipped_count'   => count($skippedSkus),
            'sku_skipped'         => array_slice($skippedSkus, 0, 100),
            'category_count'      => count($prodCategories),
            'price_mirrored_count'=> $priceCount,
            'roles_ok'            => !empty($roles['ok']),
            'roles_summary'       => $roles['summary'] ?? null,
            'storefront_url'      => provider_signup_accs_storefront_url(ACCS_SUPPORT_CLONE_STAGE_ENV),
        ];

        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        accs_support_case_update($caseId, [
            'status'                  => 'cloned',
            'stage_company_id'        => $stageCompanyId,
            'stage_company_name'      => $stageCompanyName,
            'stage_customer_id'       => $customerId,
            'stage_shared_catalog_id' => $stageCatalogId,
            'stage_customer_group_id' => $stageGroupId,
            'clone_summary_json'      => $summaryJson !== false ? $summaryJson : null,
        ]);
        accs_support_case_add_event($caseId, 'clone_succeeded', 'Stage support clone completed.', $summary);

        if ($tempPassword !== null && $tempPassword !== '') {
            accs_support_case_add_comment(
                $caseId,
                "Stage admin credentials (operator only — do not email the provider):\n"
                . 'Email: ' . $adminEmail . "\n"
                . 'Temporary password: ' . $tempPassword . "\n"
                . 'Storefront: ' . ($summary['storefront_url'] ?? '')
            );
        } else {
            accs_support_case_add_comment(
                $caseId,
                "Stage admin reused existing customer {$adminEmail} (customer #{$customerId}). "
                . 'Reset the Stage password in ACCS admin if needed. Storefront: '
                . ($summary['storefront_url'] ?? '')
            );
        }

        return [
            'ok'                => true,
            'error'             => null,
            'case_id'           => $caseId,
            'stage_company_id'  => $stageCompanyId,
            'stage_customer_id' => $customerId,
            'stage_catalog_id'  => $stageCatalogId,
            'summary'           => $summary,
            'temp_password'     => $tempPassword,
        ];
    });
}
