<?php

require_once __DIR__ . '/adobe-commerce.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/provider-signup-crypto.php';

const PROVIDER_SIGNUP_ACCS_CUSTOMER_GROUP_ID_DEFAULT = 4;
const PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID_DEFAULT = 12; // Production Sales_Support
const PROVIDER_SIGNUP_ACCS_CLINIC_TYPE_ATTRIBUTE = 'clinic-type';
const PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE = 'clinic_doctor';
const PROVIDER_SIGNUP_ACCS_CLINIC_ID_ATTRIBUTE = 'clinic_id';
/** @deprecated Legacy cookie — expired on public pages; no longer read for routing. */
const PROVIDER_SIGNUP_ACCS_ENV_COOKIE = 'provider_signup_accs_env';

/**
 * ACCS Admin user IDs for Sales_Support (company.sales_representative_id).
 * IDs are per tenant — do not reuse Production 12 on Stage.
 *
 * @var array<string, int>
 */
const PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID_BY_ENVIRONMENT = [
    'production' => 12,
    'stage'      => 18,
    'dev'        => 1,
];

/**
 * Clinic_Template company IDs (full clinic roles). Per tenant.
 *
 * @var array<string, int>
 */
const PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID_BY_ENVIRONMENT = [
    'production' => 3,
    'stage'      => 9,
    'dev'        => 7,
];

function provider_signup_accs_allowed_environments(): array
{
    return ['stage', 'dev', 'production'];
}

function provider_signup_accs_normalize_environment(?string $raw): ?string
{
    $raw = strtolower(trim((string) $raw));
    if ($raw === 'uat') {
        return 'stage';
    }

    return in_array($raw, provider_signup_accs_allowed_environments(), true) ? $raw : null;
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

/**
 * Resolve ACCS target from the current request only (query or POST). No cookies.
 */
function provider_signup_accs_environment_from_request(): ?string
{
    $env = provider_signup_accs_normalize_environment((string) ($_GET['accs_env'] ?? $_POST['accs_env'] ?? ''));
    if ($env !== null) {
        return $env;
    }

    $uatFlag = strtolower(trim((string) ($_GET['uat'] ?? $_POST['uat'] ?? '')));
    if (in_array($uatFlag, ['1', 'true', 'yes'], true)) {
        return 'stage';
    }

    return null;
}

/**
 * Public signup entry URL. Pass stage/dev for UAT applications from staging storefronts.
 */
function provider_signup_accs_application_start_url(?string $accsEnvironment = null): string
{
    $env = provider_signup_accs_normalize_environment($accsEnvironment ?? '');
    if ($env === null) {
        return '/provider-signup/application.php';
    }

    return '/provider-signup/application.php?accs_env=' . rawurlencode($env);
}

/**
 * Drop leftover Stage/Dev cookies from older builds so they cannot sticky-tag links.
 */
function provider_signup_accs_discard_legacy_environment_cookie(): void
{
    if (!isset($_COOKIE[PROVIDER_SIGNUP_ACCS_ENV_COOKIE])) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(PROVIDER_SIGNUP_ACCS_ENV_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/provider-signup',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[PROVIDER_SIGNUP_ACCS_ENV_COOKIE]);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_application_accs_environment(array $application): string
{
    $stored = provider_signup_accs_normalize_environment((string) ($application['AccsEnvironment'] ?? ''));
    if ($stored !== null) {
        return $stored;
    }

    return provider_signup_accs_target_environment();
}

/**
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function provider_signup_accs_with_environment(string $environment, callable $callback)
{
    $environment = provider_signup_accs_normalize_environment($environment) ?? provider_signup_accs_target_environment();
    $previous = getenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT');
    $hadPrevious = $previous !== false;

    putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT=' . $environment);
    $_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT'] = $environment;
    adobe_commerce_reset_access_token_cache();

    try {
        return $callback();
    } finally {
        if ($hadPrevious) {
            putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT=' . $previous);
            $_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT'] = $previous;
        } else {
            putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT');
            unset($_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT']);
        }
        adobe_commerce_reset_access_token_cache();
    }
}

function provider_signup_accs_format_provision_error(string $error): string
{
    if (stripos($error, 'recaptcha') === false) {
        return $error;
    }

    return $error
        . ' This is returned by Adobe Commerce customer registration protection (not the Operations portal). '
        . 'Disable Google reCAPTCHA on customer create in ACCS admin for server-side provisioning, or provision using an admin email that already exists in ACCS.';
}

function provider_signup_accs_target_environment(): string
{
    $runtime = env_runtime_value('PROVIDER_SIGNUP_ACCS_ENVIRONMENT');
    if ($runtime !== null && $runtime !== '') {
        return strtolower(trim($runtime));
    }

    return strtolower(trim((string) env('PROVIDER_SIGNUP_ACCS_ENVIRONMENT', 'stage')));
}

function provider_signup_accs_environment_setting_suffix(?string $environment = null): string
{
    $environment = provider_signup_accs_normalize_environment($environment)
        ?? provider_signup_accs_normalize_environment(provider_signup_accs_target_environment())
        ?? 'stage';

    return match ($environment) {
        'production' => 'PRODUCTION',
        'stage'      => 'STAGE',
        'dev'        => 'DEV',
        default      => strtoupper($environment),
    };
}

/**
 * Read an env-specific setting first (KEY_STAGE / KEY_PRODUCTION / KEY_DEV),
 * then the shared KEY when $allowShared is true.
 */
function provider_signup_accs_setting_value(string $baseKey, bool $allowShared = true): ?string
{
    $envKey = $baseKey . '_' . provider_signup_accs_environment_setting_suffix();
    $keys = $allowShared ? [$envKey, $baseKey] : [$envKey];

    foreach ($keys as $key) {
        $runtime = env_runtime_value($key);
        if ($runtime !== null && $runtime !== '') {
            return $runtime;
        }

        $configured = env($key, null);
        if ($configured !== null && $configured !== '') {
            return $configured;
        }
    }

    return null;
}

function provider_signup_accs_setting_int(string $baseKey, int $fallback, bool $allowShared = true): int
{
    $configured = (int) (provider_signup_accs_setting_value($baseKey, $allowShared) ?? 0);

    return $configured > 0 ? $configured : $fallback;
}

function provider_signup_accs_customer_group_id(): int
{
    return provider_signup_accs_setting_int(
        'PROVIDER_SIGNUP_ACCS_USER_GROUP_ID',
        PROVIDER_SIGNUP_ACCS_CUSTOMER_GROUP_ID_DEFAULT
    );
}

function provider_signup_accs_sales_representative_id(): int
{
    $environment = provider_signup_accs_normalize_environment(provider_signup_accs_target_environment()) ?? 'stage';
    $fallback = PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID_BY_ENVIRONMENT[$environment]
        ?? PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID_DEFAULT;

    // Shared PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID is Production-oriented.
    // Do not apply it to Stage/Dev — those tenants have different Admin user IDs.
    return provider_signup_accs_setting_int(
        'PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID',
        $fallback,
        false
    );
}

function provider_signup_accs_website_id(): int
{
    return provider_signup_accs_setting_int('PROVIDER_SIGNUP_ACCS_WEBSITE_ID', 1);
}

function provider_signup_accs_generate_password(): string
{
    $configured = trim((string) env('PROVIDER_SIGNUP_ACCS_DEFAULT_PASSWORD', ''));
    if ($configured !== '') {
        return $configured;
    }

    return bin2hex(random_bytes(12)) . 'Aa1!';
}

function provider_signup_accs_api_base_url(): string
{
    $target = provider_signup_accs_target_environment();
    if (!array_key_exists($target, ADOBE_COMMERCE_ENVIRONMENTS)) {
        $target = 'stage';
    }

    $config = ADOBE_COMMERCE_ENVIRONMENTS[$target];
    $tenant = adobe_commerce_tenant_for_environment($target);
    if ($tenant === '') {
        $tenant = (string) $config['tenant'];
    }

    return 'https://' . $config['api_host'] . '/' . $tenant . '/V1';
}

function provider_signup_accs_format_api_error(array $result): string
{
    $message = (string) ($result['error'] ?? 'Adobe Commerce request failed.');
    $parameters = $result['data']['parameters'] ?? null;
    if (!is_array($parameters)) {
        return $message;
    }

    $fieldName = (string) ($parameters['fieldName'] ?? $parameters['field_name'] ?? '');
    $value = $parameters['value'] ?? null;
    $valueLabel = $value === null || $value === '' ? '(empty)' : (is_scalar($value) ? (string) $value : json_encode($value));

    if ($fieldName !== '' && str_contains($message, '%fieldName') && str_contains($message, 'is not supported')) {
        return 'ACCS does not support ' . $fieldName . ' in this environment.';
    }

    if ($fieldName !== '' && str_contains($message, '%fieldName')) {
        return 'ACCS rejected ' . $fieldName . ' (' . $valueLabel . ').';
    }

    if ($fieldName !== '') {
        return $message . ' Field: ' . $fieldName . '. Value: ' . $valueLabel . '.';
    }

    return $message;
}

/**
 * Whether the current ACCS tenant supports company address-book extension attributes.
 * Stage has Magento_CompanyAddressStorefrontCompatibility*; Production currently does not.
 */
function provider_signup_accs_supports_company_address_book(): bool
{
    static $cache = [];

    $environment = provider_signup_accs_normalize_environment(provider_signup_accs_target_environment()) ?? 'stage';
    if (array_key_exists($environment, $cache)) {
        return $cache[$environment];
    }

    $modules = provider_signup_accs_api_request('GET', '/modules');
    if (!($modules['ok'] ?? false) || !is_array($modules['data'] ?? null)) {
        // Fail closed on Production (known unsupported); allow Stage/Dev when module probe fails.
        return $cache[$environment] = ($environment !== 'production');
    }

    foreach ($modules['data'] as $module) {
        $name = is_string($module) ? $module : (string) ($module['name'] ?? '');
        if (
            $name === 'Magento_CompanyAddressStorefrontCompatibility'
            || $name === 'Magento_CompanyAddressStorefrontCompatibilityRest'
            || str_contains($name, 'CompanyAddressStorefrontCompatibility')
        ) {
            return $cache[$environment] = true;
        }
    }

    return $cache[$environment] = false;
}

/**
 * @return array{is_company_address_book_enabled?: bool, is_custom_shipping_address_allowed?: bool}
 */
function provider_signup_accs_company_address_book_extension_attributes(): array
{
    if (!provider_signup_accs_supports_company_address_book()) {
        return [];
    }

    return [
        'is_company_address_book_enabled'    => true,
        'is_custom_shipping_address_allowed' => true,
    ];
}

function provider_signup_accs_region_id_for_state(string $stateCode, string $countryId = 'US'): ?int
{
    $stateCode = strtoupper(trim($stateCode));
    $countryId = strtoupper(trim($countryId)) ?: 'US';
    if ($stateCode === '') {
        return null;
    }

    static $cache = [];

    if (!isset($cache[$countryId])) {
        $cache[$countryId] = [];
        $result = provider_signup_accs_api_request('GET', '/directory/countries/' . rawurlencode($countryId));
        if ($result['ok']) {
            foreach ($result['data']['available_regions'] ?? [] as $region) {
                if (!is_array($region)) {
                    continue;
                }

                $code = strtoupper(trim((string) ($region['code'] ?? '')));
                $id = (int) ($region['id'] ?? 0);
                if ($code !== '' && $id > 0) {
                    $cache[$countryId][$code] = $id;
                }
            }
        }
    }

    return $cache[$countryId][$stateCode] ?? null;
}

function provider_signup_accs_api_request(
    string $method,
    string $path,
    ?array $query = null,
    ?array $body = null,
    int $timeoutSeconds = 30
): array
{
    $tokenResult = adobe_commerce_get_token();
    if (!$tokenResult['ok']) {
        return ['ok' => false, 'error' => $tokenResult['error'], 'data' => null, 'status' => 0];
    }

    $path = '/' . ltrim($path, '/');
    $url = provider_signup_accs_api_base_url() . $path;
    if ($query !== null && $query !== []) {
        $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Adobe Commerce.', 'data' => null, 'status' => 0];
    }

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $tokenResult['token'],
            'x-api-key: ' . adobe_commerce_client_id(),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => max(5, $timeoutSeconds),
    ];

    if ($body !== null) {
        $curlOptions[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
    }

    curl_setopt_array($ch, $curlOptions);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach Adobe Commerce.', 'data' => null, 'status' => $status];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Adobe Commerce returned an unexpected response.', 'data' => null, 'status' => $status];
    }

    if ($status >= 400) {
        $message = $data['message'] ?? $data['error'] ?? ('Adobe Commerce request failed (HTTP ' . $status . ').');
        $error = is_string($message) ? $message : 'Adobe Commerce request failed.';

        return [
            'ok'     => false,
            'error'  => $error,
            'data'   => $data,
            'status' => $status,
        ];
    }

    return ['ok' => true, 'error' => null, 'data' => $data, 'status' => $status];
}

function provider_signup_accs_search_customer_by_email(string $email): array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return ['ok' => false, 'error' => 'Admin email is required.', 'customer' => null];
    }

    $result = provider_signup_accs_api_request('GET', '/customers/search', [
        'searchCriteria[filter_groups][0][filters][0][field]'          => 'email',
        'searchCriteria[filter_groups][0][filters][0][value]'          => $email,
        'searchCriteria[filter_groups][0][filters][0][condition_type]' => 'eq',
        'searchCriteria[pageSize]'                                     => '1',
        'searchCriteria[currentPage]'                                  => '1',
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'] ?? 'Unable to search ACCS customers.', 'customer' => null];
    }

    $items = $result['data']['items'] ?? [];
    $customer = is_array($items) && $items !== [] && is_array($items[0]) ? $items[0] : null;

    return ['ok' => true, 'error' => null, 'customer' => $customer];
}

function provider_signup_accs_create_customer(array $application, int $groupId): array
{
    $email = strtolower(trim((string) ($application['AdminEmail'] ?? '')));
    $firstName = trim((string) ($application['AdminFirstName'] ?? ''));
    $lastName = trim((string) ($application['AdminLastName'] ?? ''));

    if ($email === '' || $firstName === '' || $lastName === '') {
        return [
            'ok'          => false,
            'error'       => 'Admin first name, last name, and email are required for ACCS provisioning.',
            'customer_id' => null,
            'password'    => null,
        ];
    }

    $customer = [
        'email'      => $email,
        'firstname'  => $firstName,
        'lastname'   => $lastName,
        'group_id'   => $groupId,
        'website_id' => provider_signup_accs_website_id(),
    ];

    $customAttributes = [
        [
            'attribute_code' => PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE,
            'value'          => '1',
        ],
    ];
    $phone = trim((string) ($application['AdminPhone'] ?? ''));
    if ($phone !== '') {
        $customAttributes[] = [
            'attribute_code' => 'phone_number',
            'value'          => $phone,
        ];
    }
    $customer['custom_attributes'] = $customAttributes;

    $password = provider_signup_accs_generate_password();
    $result = provider_signup_accs_api_request('POST', '/customers', null, [
        'customer' => $customer,
        'password' => $password,
    ]);

    if (!$result['ok']) {
        return [
            'ok'          => false,
            'error'       => provider_signup_accs_format_api_error($result),
            'customer_id' => null,
            'password'    => null,
        ];
    }

    $customerId = (int) ($result['data']['id'] ?? 0);
    if ($customerId <= 0) {
        return [
            'ok'          => false,
            'error'       => 'ACCS did not return a customer ID for the company admin.',
            'customer_id' => null,
            'password'    => null,
        ];
    }

    return [
        'ok'          => true,
        'error'       => null,
        'customer_id' => $customerId,
        'password'    => $password,
    ];
}

function provider_signup_accs_ensure_company_admin(array $application, int $groupId): array
{
    $email = strtolower(trim((string) ($application['AdminEmail'] ?? '')));
    $existing = provider_signup_accs_search_customer_by_email($email);
    if (!$existing['ok']) {
        return [
            'ok'          => false,
            'error'       => $existing['error'],
            'customer_id' => null,
            'created'     => false,
            'password'    => null,
        ];
    }

    if (is_array($existing['customer']) && !empty($existing['customer']['id'])) {
        $customerId = (int) $existing['customer']['id'];
        $adminSync = provider_signup_accs_sync_company_admin_customer($customerId, $groupId, $existing['customer']);
        if (!$adminSync['ok']) {
            return [
                'ok'          => false,
                'error'       => $adminSync['error'] ?? 'Unable to sync Practitioner group / Clinic Doctor on admin user.',
                'customer_id' => null,
                'created'     => false,
                'password'    => null,
            ];
        }

        return [
            'ok'          => true,
            'error'       => null,
            'customer_id' => $customerId,
            'created'     => false,
            'password'    => null,
        ];
    }

    $created = provider_signup_accs_create_customer($application, $groupId);
    if (!$created['ok']) {
        return [
            'ok'          => false,
            'error'       => $created['error'],
            'customer_id' => null,
            'created'     => false,
            'password'    => null,
        ];
    }

    return [
        'ok'          => true,
        'error'       => null,
        'customer_id' => (int) $created['customer_id'],
        'created'     => true,
        'password'    => $created['password'] ?? null,
    ];
}

/**
 * Read a customer custom attribute value.
 *
 * @param array<string, mixed> $customer
 */
function provider_signup_accs_customer_attribute_value(array $customer, string $attributeCode): string
{
    foreach ($customer['custom_attributes'] ?? [] as $attribute) {
        if (!is_array($attribute)) {
            continue;
        }
        if (trim((string) ($attribute['attribute_code'] ?? '')) === $attributeCode) {
            return trim((string) ($attribute['value'] ?? ''));
        }
    }

    return '';
}

/**
 * Preserve existing custom attributes, replacing selected codes.
 *
 * @param array<string, mixed> $customer
 * @param array<string, string> $overrides attribute_code => value
 * @return list<array{attribute_code: string, value: string}>
 */
function provider_signup_accs_merge_customer_attributes(array $customer, array $overrides): array
{
    $attributes = [];
    foreach ($customer['custom_attributes'] ?? [] as $attribute) {
        if (!is_array($attribute)) {
            continue;
        }
        $code = trim((string) ($attribute['attribute_code'] ?? ''));
        if ($code === '' || array_key_exists($code, $overrides)) {
            continue;
        }
        $attributes[] = [
            'attribute_code' => $code,
            'value'          => (string) ($attribute['value'] ?? ''),
        ];
    }

    foreach ($overrides as $code => $value) {
        $code = trim((string) $code);
        if ($code === '') {
            continue;
        }
        $attributes[] = [
            'attribute_code' => $code,
            'value'          => (string) $value,
        ];
    }

    return $attributes;
}

/**
 * Ensure an existing ACCS customer is on the Practitioner group.
 *
 * @param array<string, mixed> $customer
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_accs_set_customer_group(int $customerId, int $groupId, array $customer): array
{
    return provider_signup_accs_sync_company_admin_customer($customerId, $groupId, $customer);
}

/**
 * Ensure company admin has Practitioner group and Clinic Doctor = Yes.
 *
 * @param array<string, mixed> $customer
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_accs_sync_company_admin_customer(int $customerId, int $groupId, array $customer): array
{
    if ($customerId <= 0 || $groupId <= 0) {
        return ['ok' => false, 'error' => 'Customer ID and group ID are required.'];
    }

    $needsGroup = (int) ($customer['group_id'] ?? 0) !== $groupId;
    $needsClinicDoctor = provider_signup_accs_customer_attribute_value(
        $customer,
        PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE
    ) !== '1';

    if (!$needsGroup && !$needsClinicDoctor) {
        return ['ok' => true, 'error' => null];
    }

    $payloadCustomer = [
        'id'         => $customerId,
        'email'      => (string) ($customer['email'] ?? ''),
        'firstname'  => (string) ($customer['firstname'] ?? ''),
        'lastname'   => (string) ($customer['lastname'] ?? ''),
        'website_id' => (int) ($customer['website_id'] ?? provider_signup_accs_website_id()),
        'group_id'   => $groupId,
        'custom_attributes' => provider_signup_accs_merge_customer_attributes($customer, [
            PROVIDER_SIGNUP_ACCS_CLINIC_DOCTOR_ATTRIBUTE => '1',
        ]),
    ];

    $result = provider_signup_accs_api_request('PUT', '/customers/' . $customerId, null, [
        'customer' => $payloadCustomer,
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($result)];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * Set Clinic ID on the company admin customer (ACCS custom attribute clinic_id).
 * Must match the current ACCS company ID so storefront doctor dropdowns resolve.
 *
 * @param array<string, mixed>|null $customer
 * @return array{ok: bool, error: ?string, updated: bool}
 */
function provider_signup_accs_set_admin_clinic_id(int $customerId, int $companyId, ?array $customer = null): array
{
    if ($customerId <= 0 || $companyId <= 0) {
        return ['ok' => false, 'error' => 'Customer ID and company ID are required.', 'updated' => false];
    }

    if ($customer === null) {
        $current = provider_signup_accs_api_request('GET', '/customers/' . $customerId);
        if (!$current['ok'] || !is_array($current['data'] ?? null)) {
            return ['ok' => false, 'error' => provider_signup_accs_format_api_error($current), 'updated' => false];
        }
        $customer = $current['data'];
    }

    $clinicIdValue = (string) $companyId;
    if (provider_signup_accs_customer_attribute_value($customer, PROVIDER_SIGNUP_ACCS_CLINIC_ID_ATTRIBUTE) === $clinicIdValue) {
        return ['ok' => true, 'error' => null, 'updated' => false];
    }

    $result = provider_signup_accs_api_request('PUT', '/customers/' . $customerId, null, [
        'customer' => [
            'id'                => $customerId,
            'email'             => (string) ($customer['email'] ?? ''),
            'firstname'         => (string) ($customer['firstname'] ?? ''),
            'lastname'          => (string) ($customer['lastname'] ?? ''),
            'website_id'        => (int) ($customer['website_id'] ?? provider_signup_accs_website_id()),
            'group_id'          => (int) ($customer['group_id'] ?? provider_signup_accs_customer_group_id()),
            'custom_attributes' => provider_signup_accs_merge_customer_attributes($customer, [
                PROVIDER_SIGNUP_ACCS_CLINIC_ID_ATTRIBUTE => $clinicIdValue,
            ]),
        ],
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($result), 'updated' => false];
    }

    return ['ok' => true, 'error' => null, 'updated' => true];
}

/**
 * Force company Practitioner group, Sales_Support, and Advanced Settings flags after create/update.
 *
 * @return array{ok: bool, error: ?string}
 */
function provider_signup_accs_set_company_defaults(int $companyId, int $groupId, int $salesRepresentativeId): array
{
    if ($companyId <= 0 || $groupId <= 0 || $salesRepresentativeId <= 0) {
        return ['ok' => false, 'error' => 'Company ID, group ID, and sales representative ID are required.'];
    }

    $current = provider_signup_accs_api_request('GET', '/company/' . $companyId);
    if (!$current['ok'] || !is_array($current['data'] ?? null)) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($current)];
    }

    $company = $current['data'];
    $needsUpdate = false;

    if ((int) ($company['customer_group_id'] ?? 0) !== $groupId) {
        $company['customer_group_id'] = $groupId;
        $needsUpdate = true;
    }

    if ((int) ($company['sales_representative_id'] ?? 0) !== $salesRepresentativeId) {
        $company['sales_representative_id'] = $salesRepresentativeId;
        $needsUpdate = true;
    }

    $addressBookFlags = provider_signup_accs_company_address_book_extension_attributes();
    if ($addressBookFlags !== []) {
        $extension = is_array($company['extension_attributes'] ?? null)
            ? $company['extension_attributes']
            : [];
        foreach ($addressBookFlags as $flag => $value) {
            if (empty($extension[$flag])) {
                $extension[$flag] = $value;
                $needsUpdate = true;
            }
        }
        $company['extension_attributes'] = $extension;
    }

    if (!$needsUpdate) {
        return ['ok' => true, 'error' => null];
    }

    $result = provider_signup_accs_api_request('PUT', '/company/' . $companyId, null, [
        'company' => $company,
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($result)];
    }

    return ['ok' => true, 'error' => null];
}

function provider_signup_accs_build_company_payload(array $application, int $groupId, int $superUserId): array
{
    $street = trim((string) ($application['StreetAddress'] ?? ''));
    $taxId = provider_signup_decrypt($application['TaxIdEncrypted'] ?? null);
    $npi = trim((string) ($application['NpiNumber'] ?? ''));
    $state = trim((string) ($application['StateCode'] ?? ''));
    $countryId = trim((string) ($application['CountryCode'] ?? 'US')) ?: 'US';
    $regionId = provider_signup_accs_region_id_for_state($state, $countryId);

    $commentParts = array_filter([
        'NutraAxis provider signup application #' . (int) ($application['ApplicationID'] ?? 0),
        trim((string) ($application['ClinicType'] ?? '')) !== ''
            ? 'Clinic type: ' . (string) $application['ClinicType']
            : null,
        'State reseller certificate on file in Operations portal.',
    ]);

    $payload = [
        'company' => [
            'status'                   => 1,
            'company_name'             => trim((string) ($application['CompanyName'] ?? '')),
            'legal_name'               => trim((string) ($application['CompanyLegalName'] ?? '')),
            'company_email'            => trim((string) ($application['CompanyEmail'] ?? '')),
            'vat_tax_id'               => $taxId !== '' ? $taxId : null,
            'reseller_id'              => $npi !== '' ? $npi : null,
            'comment'                  => implode("\n", $commentParts),
            'street'                   => $street !== '' ? [$street] : [''],
            'city'                     => trim((string) ($application['City'] ?? '')),
            'country_id'               => $countryId,
            'region_id'                => $regionId,
            'postcode'                 => trim((string) ($application['PostalCode'] ?? '')),
            'telephone'                => trim((string) ($application['CompanyPhone'] ?? '')),
            'customer_group_id'        => $groupId,
            'sales_representative_id'  => provider_signup_accs_sales_representative_id(),
            'super_user_id'            => $superUserId,
        ],
    ];

    $addressBookFlags = provider_signup_accs_company_address_book_extension_attributes();
    if ($addressBookFlags !== []) {
        $payload['company']['extension_attributes'] = $addressBookFlags;
    }

    return $payload;
}

function provider_signup_accs_create_company(array $application, int $groupId, int $superUserId): array
{
    $state = trim((string) ($application['StateCode'] ?? ''));
    $countryId = trim((string) ($application['CountryCode'] ?? 'US')) ?: 'US';
    $regionId = provider_signup_accs_region_id_for_state($state, $countryId);
    if ($regionId === null) {
        return [
            'ok'         => false,
            'error'      => 'Unable to map state "' . $state . '" to an ACCS region ID.',
            'company_id' => null,
        ];
    }

    $payload = provider_signup_accs_build_company_payload($application, $groupId, $superUserId);
    $result = provider_signup_accs_api_request('POST', '/company', null, $payload);
    if (!$result['ok']) {
        return [
            'ok'         => false,
            'error'      => provider_signup_accs_format_api_error($result),
            'company_id' => null,
        ];
    }

    $companyId = (int) ($result['data']['id'] ?? 0);
    if ($companyId <= 0) {
        return ['ok' => false, 'error' => 'ACCS did not return a company ID.', 'company_id' => null];
    }

    return ['ok' => true, 'error' => null, 'company_id' => $companyId];
}

function provider_signup_accs_set_clinic_type(int $companyId, string $clinicType): array
{
    $clinicType = trim($clinicType);
    if ($clinicType === '') {
        return ['ok' => false, 'error' => 'Clinic type is required for ACCS provisioning.'];
    }

    $result = provider_signup_accs_api_request('POST', '/company/setCustomAttributes', null, [
        'company_id'        => (string) $companyId,
        'custom_attributes' => [
            [
                'attribute_code' => PROVIDER_SIGNUP_ACCS_CLINIC_TYPE_ATTRIBUTE,
                'value'          => $clinicType,
            ],
        ],
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => provider_signup_accs_format_api_error($result)];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @param array<string, mixed> $application
 * @return array{
 *   ok: bool,
 *   error: ?string,
 *   company_id?: ?int,
 *   customer_id?: ?int,
 *   clinic_id?: ?string,
 *   temporary_password?: ?string,
 *   admin_created?: bool
 * }
 */
function provider_signup_accs_provision(array $application): array
{
    $configError = adobe_commerce_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError];
    }

    $clinicType = trim((string) ($application['ClinicType'] ?? ''));
    if ($clinicType === '' || !provider_signup_is_valid_clinic_type($clinicType)) {
        return [
            'ok'    => false,
            'error' => 'A valid clinic type is required before ACCS provisioning. Edit the application and select a clinic type.',
        ];
    }

    $groupId = provider_signup_accs_customer_group_id();
    $admin = provider_signup_accs_ensure_company_admin($application, $groupId);
    if (!$admin['ok'] || empty($admin['customer_id'])) {
        return ['ok' => false, 'error' => $admin['error'] ?? 'Unable to create or locate the ACCS company admin.'];
    }

    $company = provider_signup_accs_create_company($application, $groupId, (int) $admin['customer_id']);
    if (!$company['ok'] || empty($company['company_id'])) {
        return ['ok' => false, 'error' => $company['error'] ?? 'Unable to create ACCS company.'];
    }

    $companyId = (int) $company['company_id'];
    $companyDefaults = provider_signup_accs_set_company_defaults(
        $companyId,
        $groupId,
        provider_signup_accs_sales_representative_id()
    );
    if (!$companyDefaults['ok']) {
        return [
            'ok'    => false,
            'error' => $companyDefaults['error'] ?? 'Unable to set Practitioner group / Sales_Support on ACCS company.',
        ];
    }

    $attribute = provider_signup_accs_set_clinic_type($companyId, $clinicType);
    if (!$attribute['ok']) {
        return ['ok' => false, 'error' => $attribute['error'] ?? 'Unable to set clinic-type on ACCS company.'];
    }

    $adminClinicId = provider_signup_accs_set_admin_clinic_id((int) $admin['customer_id'], $companyId);
    if (!$adminClinicId['ok']) {
        return [
            'ok'    => false,
            'error' => $adminClinicId['error'] ?? 'Unable to set Clinic ID on ACCS company admin.',
        ];
    }

    return [
        'ok'                => true,
        'error'             => null,
        'company_id'        => $companyId,
        'customer_id'       => (int) $admin['customer_id'],
        'clinic_id'         => (string) $companyId,
        'temporary_password'=> $admin['password'] ?? null,
        'admin_created'     => (bool) ($admin['created'] ?? false),
    ];
}
