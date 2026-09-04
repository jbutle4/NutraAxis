<?php

require_once __DIR__ . '/provider-signup-accs.php';
require_once __DIR__ . '/provider-signup-accs-config.php';

/**
 * Live ACCS preview of what Remove Clinic Store would delete.
 *
 * @param array<string, mixed> $application
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   environment: string,
 *   blocked: list<string>,
 *   company: array<string, mixed>,
 *   catalog: array<string, mixed>,
 *   users: list<array<string, mixed>>,
 *   price_count: int,
 *   customer_group_id: ?int
 * }
 */
function provider_signup_accs_deprovision_preview(array $application): array
{
    $environment = provider_signup_application_accs_environment($application);
    $companyId = (int) ($application['AccsCompanyId'] ?? 0);
    $customerId = (int) ($application['AccsCustomerId'] ?? 0);
    $catalogId = (int) ($application['AccsSharedCatalogId'] ?? 0);

    $preview = [
        'ok'                => true,
        'error'             => null,
        'environment'       => $environment,
        'blocked'           => [],
        'company'           => [
            'id'     => $companyId > 0 ? $companyId : null,
            'name'   => null,
            'exists' => false,
        ],
        'catalog'           => [
            'id'     => $catalogId > 0 ? $catalogId : null,
            'name'   => null,
            'type'   => null,
            'exists' => false,
        ],
        'users'             => [],
        'price_count'       => 0,
        'customer_group_id' => null,
    ];

    $configError = adobe_commerce_config_error();
    if ($configError !== null) {
        return array_merge($preview, ['ok' => false, 'error' => $configError]);
    }

    $refs = provider_signup_deprovision_other_refs($application);
    $preview['blocked'] = array_merge($preview['blocked'], $refs);

    $templateCompanyId = provider_signup_accs_config_template_company_id();
    $masterCatalogId = provider_signup_accs_config_master_catalog_id();

    if ($companyId > 0 && $companyId === $templateCompanyId) {
        $preview['blocked'][] = 'Company ID ' . $companyId . ' is the Clinic_Template company and cannot be deleted.';
    }

    if ($catalogId > 0 && $catalogId === $masterCatalogId) {
        $preview['blocked'][] = 'Shared catalog ID ' . $catalogId . ' is the master catalog and cannot be deleted.';
    }

    if ($companyId > 0) {
        $company = provider_signup_accs_api_request('GET', '/company/' . $companyId);
        if (provider_signup_accs_deprovision_is_missing($company)) {
            $preview['company']['exists'] = false;
        } elseif (!($company['ok'] ?? false)) {
            return array_merge($preview, [
                'ok'    => false,
                'error' => provider_signup_accs_format_api_error($company),
            ]);
        } else {
            $preview['company']['exists'] = true;
            $preview['company']['name'] = trim((string) ($company['data']['company_name'] ?? ''));
            $preview['company']['email'] = trim((string) ($company['data']['company_email'] ?? ''));
            $preview['company']['super_user_id'] = (int) ($company['data']['super_user_id'] ?? 0);
            $users = provider_signup_accs_deprovision_company_users($companyId, $customerId, (int) ($company['data']['super_user_id'] ?? 0));
            if (!($users['ok'] ?? false)) {
                return array_merge($preview, [
                    'ok'    => false,
                    'error' => $users['error'] ?? 'Unable to list company users.',
                ]);
            }
            $preview['users'] = $users['users'];
        }
    }

    if ($catalogId > 0) {
        $catalog = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId);
        if (provider_signup_accs_deprovision_is_missing($catalog)) {
            $preview['catalog']['exists'] = false;
        } elseif (!($catalog['ok'] ?? false)) {
            return array_merge($preview, [
                'ok'    => false,
                'error' => provider_signup_accs_format_api_error($catalog),
            ]);
        } else {
            $type = (int) ($catalog['data']['type'] ?? 0);
            $preview['catalog']['exists'] = true;
            $preview['catalog']['name'] = trim((string) ($catalog['data']['name'] ?? ''));
            $preview['catalog']['type'] = $type;
            $preview['catalog']['customer_group_id'] = (int) ($catalog['data']['customer_group_id'] ?? 0);
            $preview['customer_group_id'] = $preview['catalog']['customer_group_id'] ?: null;
            if ($type === 1) {
                $preview['blocked'][] = 'Shared catalog "' . $preview['catalog']['name'] . '" is the public catalog and cannot be deleted.';
            }
            $skus = provider_signup_accs_deprovision_catalog_skus($catalogId);
            $preview['price_count'] = count($skus);
        }
    }

    if ($preview['users'] === [] && $customerId > 0) {
        $customer = provider_signup_accs_api_request('GET', '/customers/' . $customerId);
        if (($customer['ok'] ?? false) && is_array($customer['data'] ?? null)) {
            $preview['users'][] = provider_signup_accs_deprovision_user_row($customer['data'], $companyId);
        }
    }

    $salesRepId = provider_signup_accs_sales_representative_id();
    foreach ($preview['users'] as $index => $user) {
        $userId = (int) ($user['id'] ?? 0);
        $reasons = [];
        if ($userId === $salesRepId) {
            $reasons[] = 'Sales_Support admin';
        }
        if (!empty($user['other_application_id'])) {
            $reasons[] = 'admin on application #' . (int) $user['other_application_id'];
        }
        $preview['users'][$index]['can_delete'] = $reasons === [];
        $preview['users'][$index]['keep_reason'] = $reasons === [] ? null : implode('; ', $reasons);
    }

    return $preview;
}

/**
 * Tear down ACCS company, custom shared catalog, catalog prices, and optionally users.
 *
 * @param array<string, mixed> $application
 * @param array{delete_customers?: bool} $options
 * @return array{ok: bool, error: ?string, summary: string, actions: list<string>}
 */
function provider_signup_accs_deprovision(array $application, array $options = []): array
{
    $preview = provider_signup_accs_deprovision_preview($application);
    if (!($preview['ok'] ?? false)) {
        return [
            'ok'      => false,
            'error'   => $preview['error'] ?? 'Unable to preview ACCS teardown.',
            'summary' => '',
            'actions' => [],
        ];
    }
    if ($preview['blocked'] !== []) {
        return [
            'ok'      => false,
            'error'   => implode(' ', $preview['blocked']),
            'summary' => '',
            'actions' => [],
        ];
    }

    $actions = [];
    $deleteCustomers = !empty($options['delete_customers']);
    $companyId = (int) ($preview['company']['id'] ?? 0);
    $catalogId = (int) ($preview['catalog']['id'] ?? 0);
    $customerGroupId = (int) ($preview['customer_group_id'] ?? 0);

    if ($catalogId > 0 && !empty($preview['catalog']['exists'])) {
        $prices = provider_signup_accs_deprovision_delete_catalog_prices($catalogId);
        if (!($prices['ok'] ?? false)) {
            return [
                'ok'      => false,
                'error'   => $prices['error'] ?? 'Unable to remove shared-catalog custom prices.',
                'summary' => implode('; ', $actions),
                'actions' => $actions,
            ];
        }
        if ((int) ($prices['price_count'] ?? 0) > 0) {
            $actions[] = 'removed ' . (int) $prices['price_count'] . ' catalog prices';
        }

        $deletedCatalog = provider_signup_accs_api_request('DELETE', '/sharedCatalog/' . $catalogId);
        if (!($deletedCatalog['ok'] ?? false) && !provider_signup_accs_deprovision_is_missing($deletedCatalog)) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($deletedCatalog),
                'summary' => implode('; ', $actions),
                'actions' => $actions,
            ];
        }
        $actions[] = 'deleted shared catalog ' . $catalogId;
    } elseif ($catalogId > 0) {
        $actions[] = 'shared catalog ' . $catalogId . ' already absent';
    }

    if ($companyId > 0 && !empty($preview['company']['exists'])) {
        $deletedCompany = provider_signup_accs_api_request('DELETE', '/company/' . $companyId);
        if (!($deletedCompany['ok'] ?? false) && !provider_signup_accs_deprovision_is_missing($deletedCompany)) {
            return [
                'ok'      => false,
                'error'   => provider_signup_accs_format_api_error($deletedCompany),
                'summary' => implode('; ', $actions),
                'actions' => $actions,
            ];
        }
        $actions[] = 'deleted company ' . $companyId;
    } elseif ($companyId > 0) {
        $actions[] = 'company ' . $companyId . ' already absent';
    }

    if ($deleteCustomers) {
        foreach ($preview['users'] as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId <= 0 || empty($user['can_delete'])) {
                if (!empty($user['keep_reason'])) {
                    $actions[] = 'kept customer ' . $userId . ' (' . $user['keep_reason'] . ')';
                }
                continue;
            }
            $deletedUser = provider_signup_accs_api_request('DELETE', '/customers/' . $userId);
            if (!($deletedUser['ok'] ?? false) && !provider_signup_accs_deprovision_is_missing($deletedUser)) {
                return [
                    'ok'      => false,
                    'error'   => provider_signup_accs_format_api_error($deletedUser),
                    'summary' => implode('; ', $actions),
                    'actions' => $actions,
                ];
            }
            $actions[] = 'deleted customer ' . $userId;
        }
    } elseif ($preview['users'] !== []) {
        $actions[] = 'left ' . count($preview['users']) . ' ACCS customer account(s) in place';
    }

    if ($customerGroupId > 4) {
        $deletedGroup = provider_signup_accs_api_request('DELETE', '/customerGroups/' . $customerGroupId);
        if ($deletedGroup['ok'] ?? false) {
            $actions[] = 'deleted customer group ' . $customerGroupId;
        } elseif (provider_signup_accs_deprovision_is_missing($deletedGroup)) {
            $actions[] = 'customer group ' . $customerGroupId . ' already absent';
        }
    }

    if ($actions === []) {
        $actions[] = 'no ACCS objects remained to delete';
    }

    return [
        'ok'      => true,
        'error'   => null,
        'summary' => implode('; ', $actions),
        'actions' => $actions,
    ];
}

function provider_signup_accs_deprovision_is_missing(array $result): bool
{
    $status = (int) ($result['status'] ?? 0);
    $error = strtolower((string) ($result['error'] ?? ''));

    return $status === 404
        || str_contains($error, 'no such entity')
        || str_contains($error, 'requested resource doesn\'t exist');
}

/**
 * @return list<string>
 */
function provider_signup_deprovision_other_refs(array $application): array
{
    $applicationId = (int) ($application['ApplicationID'] ?? 0);
    $environment = provider_signup_application_accs_environment($application);
    $companyId = (int) ($application['AccsCompanyId'] ?? 0);
    $customerId = (int) ($application['AccsCustomerId'] ?? 0);
    $catalogId = (int) ($application['AccsSharedCatalogId'] ?? 0);
    $blocked = [];

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT ApplicationID, CompanyName, AccsCompanyId, AccsCustomerId, AccsSharedCatalogId
            FROM dbo.ProviderSignupApplication
            WHERE ApplicationID <> :id
              AND Status = :status
              AND AccsEnvironment = :environment
              AND (
                    (:company_id_present = 1 AND AccsCompanyId = :company_id)
                 OR (:customer_id_present = 1 AND AccsCustomerId = :customer_id)
                 OR (:catalog_id_present = 1 AND AccsSharedCatalogId = :catalog_id)
              )
            ORDER BY ApplicationID
        SQL);
        $stmt->execute([
            'id'                  => $applicationId,
            'status'              => PROVIDER_SIGNUP_STATUS_PROVISIONED,
            'environment'         => $environment,
            'company_id_present'  => $companyId > 0 ? 1 : 0,
            'company_id'          => $companyId,
            'customer_id_present' => $customerId > 0 ? 1 : 0,
            'customer_id'         => $customerId,
            'catalog_id_present'  => $catalogId > 0 ? 1 : 0,
            'catalog_id'          => $catalogId,
        ]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $otherId = (int) ($row['ApplicationID'] ?? 0);
            $name = trim((string) ($row['CompanyName'] ?? 'application'));
            if ($companyId > 0 && (int) ($row['AccsCompanyId'] ?? 0) === $companyId) {
                $blocked[] = 'Company ID ' . $companyId . ' is also linked to #' . $otherId . ' (' . $name . ').';
            }
            if ($catalogId > 0 && (int) ($row['AccsSharedCatalogId'] ?? 0) === $catalogId) {
                $blocked[] = 'Shared catalog ID ' . $catalogId . ' is also linked to #' . $otherId . ' (' . $name . ').';
            }
        }
    } catch (Throwable $e) {
        $blocked[] = 'Unable to check other provisioned applications for shared ACCS IDs.';
        error_log('provider_signup_deprovision_other_refs: ' . $e->getMessage());
    }

    return array_values(array_unique($blocked));
}

/**
 * @return array{ok: bool, error: ?string, users: list<array<string, mixed>>}
 */
function provider_signup_accs_deprovision_company_users(int $companyId, int $storedCustomerId, int $superUserId): array
{
    $ids = [];
    foreach ([$storedCustomerId, $superUserId] as $id) {
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    $hierarchy = provider_signup_accs_api_request('GET', '/hierarchy/' . $companyId);
    if ($hierarchy['ok'] ?? false) {
        foreach ($hierarchy['data'] ?? [] as $node) {
            if (!is_array($node)) {
                continue;
            }
            if (trim((string) ($node['entity_type'] ?? '')) !== 'customer') {
                continue;
            }
            $id = (int) ($node['entity_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
    } elseif (!provider_signup_accs_deprovision_is_missing($hierarchy)) {
        return [
            'ok'    => false,
            'error' => provider_signup_accs_format_api_error($hierarchy),
            'users' => [],
        ];
    }

    $users = [];
    foreach (array_keys($ids) as $id) {
        $customer = provider_signup_accs_api_request('GET', '/customers/' . $id);
        if (provider_signup_accs_deprovision_is_missing($customer)) {
            continue;
        }
        if (!($customer['ok'] ?? false) || !is_array($customer['data'] ?? null)) {
            return [
                'ok'    => false,
                'error' => provider_signup_accs_format_api_error($customer),
                'users' => [],
            ];
        }
        $users[] = provider_signup_accs_deprovision_user_row($customer['data'], $companyId);
    }

    return ['ok' => true, 'error' => null, 'users' => $users];
}

/**
 * @param array<string, mixed> $customer
 * @return array<string, mixed>
 */
function provider_signup_accs_deprovision_user_row(array $customer, int $companyId): array
{
    $id = (int) ($customer['id'] ?? 0);
    $companyAttrs = is_array($customer['extension_attributes']['company_attributes'] ?? null)
        ? $customer['extension_attributes']['company_attributes']
        : [];

    return [
        'id'                   => $id,
        'email'                => trim((string) ($customer['email'] ?? '')),
        'name'                 => trim((string) ($customer['firstname'] ?? '') . ' ' . (string) ($customer['lastname'] ?? '')),
        'group_id'             => (int) ($customer['group_id'] ?? 0),
        'company_id'           => (int) ($companyAttrs['company_id'] ?? 0),
        'is_company_admin'     => !empty($companyAttrs['is_default']),
        'other_application_id' => provider_signup_deprovision_other_customer_application($id, $companyId),
        'can_delete'           => true,
        'keep_reason'          => null,
    ];
}

function provider_signup_deprovision_other_customer_application(int $customerId, int $currentCompanyId): ?int
{
    if ($customerId <= 0) {
        return null;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT TOP 1 ApplicationID
            FROM dbo.ProviderSignupApplication
            WHERE AccsCustomerId = :customer_id
              AND Status = :status
              AND (AccsCompanyId IS NULL OR AccsCompanyId <> :company_id)
            ORDER BY ApplicationID
        SQL);
        $stmt->execute([
            'customer_id' => $customerId,
            'status'      => PROVIDER_SIGNUP_STATUS_PROVISIONED,
            'company_id'  => $currentCompanyId,
        ]);
        $otherId = (int) $stmt->fetchColumn();

        return $otherId > 0 ? $otherId : null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return list<string>
 */
function provider_signup_accs_deprovision_catalog_skus(int $catalogId): array
{
    $products = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId . '/products');
    if (!($products['ok'] ?? false)) {
        return [];
    }

    $skus = [];
    $data = $products['data'] ?? null;
    if (is_array($data) && array_is_list($data)) {
        foreach ($data as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
    } elseif (is_array($data)) {
        foreach ($data['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }
    }

    return array_values(array_unique($skus));
}

/**
 * @return array{ok: bool, error: ?string, price_count: int}
 */
function provider_signup_accs_deprovision_delete_catalog_prices(int $catalogId): array
{
    $catalog = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId);
    if (provider_signup_accs_deprovision_is_missing($catalog)) {
        return ['ok' => true, 'error' => null, 'price_count' => 0];
    }
    if (!($catalog['ok'] ?? false) || !is_array($catalog['data'] ?? null)) {
        return [
            'ok'          => false,
            'error'       => provider_signup_accs_format_api_error($catalog),
            'price_count' => 0,
        ];
    }

    $groupCode = trim((string) ($catalog['data']['name'] ?? ''));
    $groupId = (int) ($catalog['data']['customer_group_id'] ?? 0);
    if ($groupId > 0) {
        $group = provider_signup_accs_api_request('GET', '/customerGroups/' . $groupId);
        if (($group['ok'] ?? false) && is_array($group['data'] ?? null)) {
            $fromGroup = trim((string) ($group['data']['code'] ?? ''));
            if ($fromGroup !== '') {
                $groupCode = $fromGroup;
            }
        }
    }
    if ($groupCode === '') {
        return ['ok' => true, 'error' => null, 'price_count' => 0];
    }

    $skus = provider_signup_accs_deprovision_catalog_skus($catalogId);
    if ($skus === []) {
        return ['ok' => true, 'error' => null, 'price_count' => 0];
    }

    $prices = [];
    foreach ($skus as $sku) {
        $prices[] = [
            'website_id'     => 0,
            'sku'            => $sku,
            'customer_group' => $groupCode,
            'quantity'       => 1,
        ];
    }

    $deleted = 0;
    foreach (array_chunk($prices, 50) as $chunk) {
        $result = provider_signup_accs_api_request('POST', '/products/tier-prices-delete', null, [
            'prices' => $chunk,
        ]);
        if (!($result['ok'] ?? false)) {
            return [
                'ok'          => false,
                'error'       => provider_signup_accs_format_api_error($result),
                'price_count' => $deleted,
            ];
        }
        $deleted += count($chunk);
    }

    return ['ok' => true, 'error' => null, 'price_count' => $deleted];
}
