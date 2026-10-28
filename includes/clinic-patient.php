<?php

require_once __DIR__ . '/provider-signup-accs.php';
require_once __DIR__ . '/provider-signup-accs-config.php';

const CLINIC_PATIENT_PRACTITIONER_ID_ATTRIBUTE = 'practitioner_id';
const CLINIC_PATIENT_EXCLUDED_COMPANY_NAMES = [
    'Clinic_Template',
    'Clinic Template',
];

function clinic_patient_normalize_environment(?string $environment): ?string
{
    $environment = provider_signup_accs_normalize_environment($environment ?? '');
    if ($environment === 'production' || $environment === 'stage') {
        return $environment;
    }

    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function clinic_patient_list_companies(string $environment): array
{
    return provider_signup_accs_with_environment($environment, static function () {
        $companies = [];
        $page = 1;
        while ($page <= 40) {
            $result = provider_signup_accs_api_request('GET', '/company/', [
                'searchCriteria[pageSize]'    => '50',
                'searchCriteria[currentPage]' => (string) $page,
            ]);
            if (!$result['ok']) {
                return [];
            }
            $items = $result['data']['items'] ?? [];
            if (!is_array($items) || $items === []) {
                break;
            }
            foreach ($items as $company) {
                if (!is_array($company)) {
                    continue;
                }
                $name = trim((string) ($company['company_name'] ?? ''));
                if ($name === '' || in_array($name, CLINIC_PATIENT_EXCLUDED_COMPANY_NAMES, true)) {
                    continue;
                }
                $companies[] = $company;
            }
            $page++;
        }
        usort($companies, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['company_name'] ?? ''), (string) ($b['company_name'] ?? ''));
        });

        return $companies;
    });
}

/**
 * @return array{ok: bool, error: ?string, catalog_id: int, catalog_name: string, group_id: int, group_code: string}
 */
function clinic_patient_resolve_catalog(string $environment, int $companyId): array
{
    $empty = [
        'ok'           => false,
        'error'        => 'Unable to resolve the clinic shared catalog.',
        'catalog_id'   => 0,
        'catalog_name' => '',
        'group_id'     => 0,
        'group_code'   => '',
    ];
    if ($companyId <= 0) {
        return $empty;
    }

    return provider_signup_accs_with_environment($environment, static function () use ($companyId, $empty) {
        $company = provider_signup_accs_api_request('GET', '/company/' . $companyId);
        if (!$company['ok'] || !is_array($company['data'] ?? null)) {
            return array_merge($empty, ['error' => provider_signup_accs_format_api_error($company)]);
        }
        $companyName = trim((string) ($company['data']['company_name'] ?? ''));
        $adminId = (int) ($company['data']['super_user_id'] ?? 0);
        $catalogId = 0;
        if ($adminId > 0) {
            $admin = provider_signup_accs_api_request('GET', '/customers/' . $adminId);
            if ($admin['ok'] && is_array($admin['data'] ?? null)) {
                $catalogId = (int) provider_signup_accs_customer_attribute_value(
                    $admin['data'],
                    PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE
                );
            }
        }

        if ($catalogId <= 0) {
            $list = provider_signup_accs_config_api_request('GET', '/sharedCatalog', [
                'searchCriteria[pageSize]'    => '100',
                'searchCriteria[currentPage]' => '1',
            ]);
            $needles = array_filter([
                'SC-' . $companyName,
                'SC - ' . $companyName,
                'SC-' . str_replace(' ', '', $companyName),
                'SC - ' . str_replace(' ', '', $companyName),
            ]);
            foreach (($list['data']['items'] ?? []) as $catalog) {
                if (!is_array($catalog)) {
                    continue;
                }
                $name = trim((string) ($catalog['name'] ?? ''));
                foreach ($needles as $needle) {
                    if (strcasecmp($name, $needle) === 0) {
                        $catalogId = (int) ($catalog['id'] ?? 0);
                        break 2;
                    }
                }
            }
        }

        if ($catalogId <= 0) {
            return array_merge($empty, [
                'error' => 'No SC-{clinic} shared catalog is linked to this company. Complete clinic catalog setup first.',
            ]);
        }

        $catalog = provider_signup_accs_api_request('GET', '/sharedCatalog/' . $catalogId);
        if (!$catalog['ok'] || !is_array($catalog['data'] ?? null)) {
            return array_merge($empty, ['error' => provider_signup_accs_format_api_error($catalog)]);
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
        if ($groupId <= 0) {
            return array_merge($empty, ['error' => 'Shared catalog is missing a customer group.']);
        }

        return [
            'ok'           => true,
            'error'        => null,
            'catalog_id'   => $catalogId,
            'catalog_name' => trim((string) ($catalog['data']['name'] ?? '')),
            'group_id'     => $groupId,
            'group_code'   => $groupCode,
        ];
    });
}

/**
 * Doctors for a clinic: company users with Clinic Doctor = Yes, else clinic_id matches.
 *
 * @return list<array{id: int, name: string, email: string}>
 */
function clinic_patient_list_practitioners(string $environment, int $companyId): array
{
    if ($companyId <= 0) {
        return [];
    }

    return provider_signup_accs_with_environment($environment, static function () use ($companyId) {
        $byId = [];
        $hierarchy = provider_signup_accs_api_request('GET', '/hierarchy/' . $companyId);
        $ids = [];
        if ($hierarchy['ok']) {
            foreach ($hierarchy['data'] ?? [] as $node) {
                if (!is_array($node) || ($node['entity_type'] ?? '') !== 'customer') {
                    continue;
                }
                $id = (int) ($node['entity_id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $company = provider_signup_accs_api_request('GET', '/company/' . $companyId);
        $adminId = (int) ($company['data']['super_user_id'] ?? 0);
        if ($adminId > 0) {
            $ids[] = $adminId;
        }

        foreach (array_unique($ids) as $id) {
            $result = provider_signup_accs_api_request('GET', '/customers/' . $id);
            if (!$result['ok'] || !is_array($result['data'] ?? null)) {
                continue;
            }
            $customer = $result['data'];
            $isDoctor = provider_signup_accs_customer_attribute_value($customer, PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE);
            if ($isDoctor !== '1' && $id !== $adminId) {
                continue;
            }
            $byId[$id] = clinic_patient_practitioner_row($customer);
        }

        if ($byId === []) {
            $page = 1;
            while ($page <= 10) {
                $search = provider_signup_accs_api_request('GET', '/customers/search', [
                    'searchCriteria[pageSize]'                                     => '50',
                    'searchCriteria[currentPage]'                                  => (string) $page,
                    'searchCriteria[filterGroups][0][filters][0][field]'          => 'clinic_id',
                    'searchCriteria[filterGroups][0][filters][0][value]'          => (string) $companyId,
                    'searchCriteria[filterGroups][0][filters][0][conditionType]'   => 'eq',
                ]);
                if (!$search['ok']) {
                    break;
                }
                $items = $search['data']['items'] ?? [];
                if (!is_array($items) || $items === []) {
                    break;
                }
                foreach ($items as $customer) {
                    if (!is_array($customer)) {
                        continue;
                    }
                    if (provider_signup_accs_customer_attribute_value($customer, PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE) !== '1') {
                        continue;
                    }
                    $id = (int) ($customer['id'] ?? 0);
                    if ($id > 0) {
                        $byId[$id] = clinic_patient_practitioner_row($customer);
                    }
                }
                $page++;
            }
        }

        $rows = array_values($byId);
        usort($rows, static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    });
}

/**
 * @param array<string, mixed> $customer
 * @return array{id: int, name: string, email: string}
 */
function clinic_patient_practitioner_row(array $customer): array
{
    $name = trim((string) ($customer['firstname'] ?? '') . ' ' . (string) ($customer['lastname'] ?? ''));

    return [
        'id'    => (int) ($customer['id'] ?? 0),
        'name'  => $name !== '' ? $name : 'Customer #' . (int) ($customer['id'] ?? 0),
        'email' => (string) ($customer['email'] ?? ''),
    ];
}

/**
 * Create an individual (non-company) patient on the clinic shared-catalog group.
 *
 * @param array{firstname?: string, lastname?: string, email?: string, phone?: string} $input
 * @return array{ok: bool, error: ?string, customer_id: ?int, password: ?string, catalog_id: ?int, group_id: ?int, group_code: ?string}
 */
function clinic_patient_create(string $environment, int $companyId, int $practitionerId, array $input): array
{
    $fail = static function (string $error) {
        return [
            'ok'          => false,
            'error'       => $error,
            'customer_id' => null,
            'password'    => null,
            'catalog_id'  => null,
            'group_id'    => null,
            'group_code'  => null,
        ];
    };

    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $firstName = trim((string) ($input['firstname'] ?? ''));
    $lastName = trim((string) ($input['lastname'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    if ($email === '' || $firstName === '' || $lastName === '') {
        return $fail('First name, last name, and email are required.');
    }
    if ($companyId <= 0) {
        return $fail('Select a clinic.');
    }
    if ($practitionerId <= 0) {
        return $fail('Select the practitioner this patient should be attributed to.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $fail('Enter a valid email address.');
    }

    $catalog = clinic_patient_resolve_catalog($environment, $companyId);
    if (!$catalog['ok']) {
        return $fail((string) $catalog['error']);
    }

    $practitioners = clinic_patient_list_practitioners($environment, $companyId);
    $practitionerOk = false;
    foreach ($practitioners as $row) {
        if ((int) $row['id'] === $practitionerId) {
            $practitionerOk = true;
            break;
        }
    }
    if (!$practitionerOk) {
        return $fail('The selected practitioner is not a doctor on this clinic.');
    }

    return provider_signup_accs_with_environment($environment, static function () use (
        $fail,
        $email,
        $firstName,
        $lastName,
        $phone,
        $companyId,
        $practitionerId,
        $catalog
    ) {
        $existing = provider_signup_accs_search_customer_by_email($email);
        if (!$existing['ok']) {
            return $fail($existing['error'] ?? 'Unable to search ACCS customers.');
        }
        if (is_array($existing['customer'])) {
            $existingId = (int) ($existing['customer']['id'] ?? 0);

            return $fail('A customer with that email already exists (ACCS ID ' . $existingId . ').');
        }

        $customAttributes = [
            [
                'attribute_code' => PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE,
                'value'          => '0',
            ],
            [
                'attribute_code' => PROVIDER_SIGNUP_ACCS_CLINIC_ID_ATTRIBUTE,
                'value'          => (string) $companyId,
            ],
            [
                'attribute_code' => CLINIC_PATIENT_PRACTITIONER_ID_ATTRIBUTE,
                'value'          => (string) $practitionerId,
            ],
            [
                'attribute_code' => PROVIDER_SIGNUP_ACCS_PATIENT_SHARED_CATALOG_ATTRIBUTE,
                'value'          => (string) $catalog['catalog_id'],
            ],
        ];
        if ($phone !== '') {
            $customAttributes[] = [
                'attribute_code' => 'phone_number',
                'value'          => $phone,
            ];
        }

        $password = provider_signup_accs_generate_password();
        $result = provider_signup_accs_api_request('POST', '/customers', null, [
            'customer' => [
                'email'             => $email,
                'firstname'         => $firstName,
                'lastname'          => $lastName,
                'group_id'          => (int) $catalog['group_id'],
                'website_id'        => provider_signup_accs_website_id(),
                'custom_attributes' => $customAttributes,
            ],
            'password' => $password,
        ]);
        if (!$result['ok']) {
            return $fail(provider_signup_accs_format_api_error($result));
        }
        $customerId = (int) ($result['data']['id'] ?? 0);
        if ($customerId <= 0) {
            return $fail('ACCS did not return a customer ID.');
        }

        return [
            'ok'          => true,
            'error'       => null,
            'customer_id' => $customerId,
            'password'    => $password,
            'catalog_id'  => (int) $catalog['catalog_id'],
            'group_id'    => (int) $catalog['group_id'],
            'group_code'  => (string) $catalog['group_code'],
        ];
    });
}
