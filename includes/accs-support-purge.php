<?php

require_once __DIR__ . '/provider-signup-accs.php';
require_once __DIR__ . '/provider-signup-accs-config.php';
require_once __DIR__ . '/provider-signup-accs-deprovision.php';
require_once __DIR__ . '/accs-support-case.php';
require_once __DIR__ . '/accs-support-clone.php';

const ACCS_SUPPORT_PURGE_STAGE_ENV = 'stage';

/**
 * Preview Stage teardown for a support case (ledger + live ACCS check).
 *
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   blocked: list<string>,
 *   case: ?array<string, mixed>,
 *   company: array<string, mixed>,
 *   catalog: array<string, mixed>,
 *   customer: array<string, mixed>,
 *   resources: list<array<string, mixed>>,
 *   price_count: int
 * }
 */
function accs_support_purge_preview(int $caseId): array
{
    $case = accs_support_case_get($caseId);
    if ($case === null) {
        return [
            'ok'         => false,
            'error'      => 'Support case not found.',
            'blocked'    => [],
            'case'       => null,
            'company'    => [],
            'catalog'    => [],
            'customer'   => [],
            'resources'  => [],
            'price_count'=> 0,
        ];
    }

    $stageCompanyId = (int) ($case['StageCompanyId'] ?? 0);
    $stageCatalogId = (int) ($case['StageSharedCatalogId'] ?? 0);
    $stageCustomerId = (int) ($case['StageCustomerId'] ?? 0);
    $stageCompanyName = trim((string) ($case['StageCompanyName'] ?? ''));

    $blocked = [];
    if ($stageCompanyId <= 0 && $stageCatalogId <= 0 && $stageCustomerId <= 0) {
        $blocked[] = 'This case has no Stage resources to purge.';
    }
    if ($stageCompanyName !== '' && !str_starts_with($stageCompanyName, ACCS_SUPPORT_CLONE_NAME_PREFIX)) {
        $blocked[] = 'Stage company name does not use the SUPPORT- prefix. Refuse purge unless the case ledger is corrected.';
    }
    if (in_array((string) ($case['Status'] ?? ''), ['purged'], true)) {
        $blocked[] = 'Case is already marked purged.';
    }

    return provider_signup_accs_with_environment(ACCS_SUPPORT_PURGE_STAGE_ENV, static function () use (
        $case,
        $stageCompanyId,
        $stageCatalogId,
        $stageCustomerId,
        $blocked
    ): array {
        $company = [
            'id'     => $stageCompanyId,
            'name'   => trim((string) ($case['StageCompanyName'] ?? '')),
            'exists' => false,
        ];
        $catalog = [
            'id'     => $stageCatalogId,
            'name'   => '',
            'exists' => false,
            'type'   => null,
            'customer_group_id' => (int) ($case['StageCustomerGroupId'] ?? 0),
        ];
        $customer = [
            'id'     => $stageCustomerId,
            'email'  => trim((string) ($case['ProdAdminEmail'] ?? '')),
            'exists' => false,
        ];
        $priceCount = 0;

        if ($stageCompanyId > 0) {
            $companyResult = provider_signup_accs_api_request('GET', '/company/' . $stageCompanyId);
            if (provider_signup_accs_deprovision_is_missing($companyResult)) {
                $company['exists'] = false;
            } elseif ($companyResult['ok'] && is_array($companyResult['data'] ?? null)) {
                $company['exists'] = true;
                $company['name'] = trim((string) ($companyResult['data']['company_name'] ?? $company['name']));
                if ($company['name'] !== '' && !str_starts_with($company['name'], ACCS_SUPPORT_CLONE_NAME_PREFIX)) {
                    $blocked[] = 'Live Stage company "' . $company['name'] . '" is not a SUPPORT- clone.';
                }
            } else {
                $blocked[] = provider_signup_accs_format_api_error($companyResult);
            }
        }

        if ($stageCatalogId > 0) {
            $catalogResult = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $stageCatalogId);
            if (provider_signup_accs_deprovision_is_missing($catalogResult)) {
                $catalog['exists'] = false;
            } elseif ($catalogResult['ok'] && is_array($catalogResult['data'] ?? null)) {
                $catalog['exists'] = true;
                $catalog['name'] = trim((string) ($catalogResult['data']['name'] ?? ''));
                $catalog['type'] = (int) ($catalogResult['data']['type'] ?? 0);
                $catalog['customer_group_id'] = (int) ($catalogResult['data']['customer_group_id'] ?? 0);
                if ($catalog['type'] === 1) {
                    $blocked[] = 'Shared catalog is the public catalog and cannot be deleted.';
                }
                $priceCount = count(provider_signup_accs_deprovision_catalog_skus($stageCatalogId));
            } else {
                $blocked[] = provider_signup_accs_format_api_error($catalogResult);
            }
        }

        if ($stageCustomerId > 0) {
            $customerResult = provider_signup_accs_api_request('GET', '/customers/' . $stageCustomerId);
            if (provider_signup_accs_deprovision_is_missing($customerResult)) {
                $customer['exists'] = false;
            } elseif ($customerResult['ok'] && is_array($customerResult['data'] ?? null)) {
                $customer['exists'] = true;
                $customer['email'] = strtolower(trim((string) ($customerResult['data']['email'] ?? $customer['email'])));
                $salesRepId = provider_signup_accs_sales_representative_id();
                if ($stageCustomerId === $salesRepId) {
                    $blocked[] = 'Stage customer is the Sales_Support representative and cannot be deleted.';
                }
            } else {
                $blocked[] = provider_signup_accs_format_api_error($customerResult);
            }
        }

        return [
            'ok'          => $blocked === [],
            'error'       => $blocked === [] ? null : implode(' ', $blocked),
            'blocked'     => $blocked,
            'case'        => $case,
            'company'     => $company,
            'catalog'     => $catalog,
            'customer'    => $customer,
            'resources'   => accs_support_case_active_resources((int) $case['CaseID']),
            'price_count' => $priceCount,
        ];
    });
}

/**
 * Purge Stage support resources for a case. Requires typed confirm of Stage company name.
 *
 * @return array{ok: bool, error: ?string, summary: string, actions: list<string>}
 */
function accs_support_purge_run(int $caseId, string $confirmCompanyName, bool $deleteCustomer = true): array
{
    $preview = accs_support_purge_preview($caseId);
    if (!($preview['ok'] ?? false)) {
        return [
            'ok'      => false,
            'error'   => $preview['error'] ?? 'Purge preview failed.',
            'summary' => '',
            'actions' => [],
        ];
    }

    $expected = trim((string) ($preview['company']['name'] ?? ''));
    if ($expected === '' || trim($confirmCompanyName) !== $expected) {
        return [
            'ok'      => false,
            'error'   => 'Confirmation does not match the Stage company name "' . $expected . '".',
            'summary' => '',
            'actions' => [],
        ];
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    accs_support_case_update($caseId, ['status' => 'purge_started']);
    accs_support_case_add_event($caseId, 'purge_started', 'Stage support purge started.', [
        'stage_company_id' => (int) ($preview['company']['id'] ?? 0),
    ]);

    return provider_signup_accs_with_environment(ACCS_SUPPORT_PURGE_STAGE_ENV, static function () use (
        $caseId,
        $preview,
        $deleteCustomer
    ): array {
        $actions = [];
        $companyId = (int) ($preview['company']['id'] ?? 0);
        $catalogId = (int) ($preview['catalog']['id'] ?? 0);
        $customerId = (int) ($preview['customer']['id'] ?? 0);
        $customerGroupId = (int) ($preview['catalog']['customer_group_id'] ?? 0);

        if ($catalogId > 0 && !empty($preview['catalog']['exists'])) {
            $prices = provider_signup_accs_deprovision_delete_catalog_prices($catalogId);
            if (!($prices['ok'] ?? false)) {
                $error = $prices['error'] ?? 'Unable to remove shared-catalog custom prices.';
                accs_support_case_update($caseId, ['status' => 'purge_failed']);
                accs_support_case_add_event($caseId, 'purge_failed', $error);
                return ['ok' => false, 'error' => $error, 'summary' => implode('; ', $actions), 'actions' => $actions];
            }
            if ((int) ($prices['price_count'] ?? 0) > 0) {
                $actions[] = 'removed ' . (int) $prices['price_count'] . ' catalog prices';
                accs_support_case_add_resource(
                    $caseId,
                    'deleted',
                    'tier_price_batch',
                    (string) $catalogId,
                    (string) ($preview['catalog']['name'] ?? ''),
                    null,
                    ['price_count' => (int) $prices['price_count']]
                );
            }

            $deletedCatalog = provider_signup_accs_api_request('DELETE', '/sharedCatalog/' . $catalogId);
            if (!($deletedCatalog['ok'] ?? false) && !provider_signup_accs_deprovision_is_missing($deletedCatalog)) {
                $error = provider_signup_accs_format_api_error($deletedCatalog);
                accs_support_case_update($caseId, ['status' => 'purge_failed']);
                accs_support_case_add_event($caseId, 'purge_failed', $error);
                return ['ok' => false, 'error' => $error, 'summary' => implode('; ', $actions), 'actions' => $actions];
            }
            $actions[] = 'deleted shared catalog ' . $catalogId;
            accs_support_case_add_resource(
                $caseId,
                'deleted',
                'shared_catalog',
                (string) $catalogId,
                (string) ($preview['catalog']['name'] ?? ''),
                null,
                null
            );
        }

        if ($customerGroupId > 0 && !provider_signup_accs_is_protected_customer_group_id($customerGroupId)) {
            $ruleSync = provider_signup_accs_sync_free_shipping_rule_group($customerGroupId, 'remove');
            if (!($ruleSync['ok'] ?? false)) {
                $error = $ruleSync['error'] ?? 'Unable to remove clinic group from free-shipping rule.';
                accs_support_case_update($caseId, ['status' => 'purge_failed']);
                accs_support_case_add_event($caseId, 'purge_failed', $error);
                return ['ok' => false, 'error' => $error, 'summary' => implode('; ', $actions), 'actions' => $actions];
            }
            if (($ruleSync['action'] ?? '') === 'removed') {
                $actions[] = 'removed customer group ' . $customerGroupId . ' from free-shipping rule';
            }
        }

        if ($companyId > 0 && !empty($preview['company']['exists'])) {
            $deletedCompany = provider_signup_accs_api_request('DELETE', '/company/' . $companyId);
            if (!($deletedCompany['ok'] ?? false) && !provider_signup_accs_deprovision_is_missing($deletedCompany)) {
                $error = provider_signup_accs_format_api_error($deletedCompany);
                accs_support_case_update($caseId, ['status' => 'purge_failed']);
                accs_support_case_add_event($caseId, 'purge_failed', $error);
                return ['ok' => false, 'error' => $error, 'summary' => implode('; ', $actions), 'actions' => $actions];
            }
            $actions[] = 'deleted company ' . $companyId;
            accs_support_case_add_resource(
                $caseId,
                'deleted',
                'company',
                (string) $companyId,
                (string) ($preview['company']['name'] ?? ''),
                null,
                null
            );
        }

        if ($deleteCustomer && $customerId > 0 && !empty($preview['customer']['exists'])) {
            $deletedCustomer = provider_signup_accs_api_request('DELETE', '/customers/' . $customerId);
            if (!($deletedCustomer['ok'] ?? false) && !provider_signup_accs_deprovision_is_missing($deletedCustomer)) {
                $error = provider_signup_accs_format_api_error($deletedCustomer);
                accs_support_case_update($caseId, ['status' => 'purge_failed']);
                accs_support_case_add_event($caseId, 'purge_failed', $error);
                return ['ok' => false, 'error' => $error, 'summary' => implode('; ', $actions), 'actions' => $actions];
            }
            $actions[] = 'deleted customer ' . $customerId;
            accs_support_case_add_resource(
                $caseId,
                'deleted',
                'customer',
                (string) $customerId,
                (string) ($preview['customer']['email'] ?? ''),
                null,
                null
            );
        }

        accs_support_case_update($caseId, [
            'status'    => 'purged',
            'purged_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $summary = $actions === [] ? 'Nothing left to delete on Stage.' : implode('; ', $actions);
        accs_support_case_add_event($caseId, 'purged', $summary, ['actions' => $actions]);
        accs_support_case_add_comment($caseId, 'Stage support account purged. ' . $summary);

        return [
            'ok'      => true,
            'error'   => null,
            'summary' => $summary,
            'actions' => $actions,
        ];
    });
}
