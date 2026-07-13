<?php

require_once __DIR__ . '/adobe-commerce.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/provider-signup-crypto.php';

const PROVIDER_SIGNUP_ACCS_CUSTOMER_GROUP_ID_DEFAULT = 4;
const PROVIDER_SIGNUP_ACCS_CLINIC_TYPE_ATTRIBUTE = 'clinic-type';

/** @var ?string */
$providerSignupAccsRecaptchaToken = null;

function provider_signup_accs_set_recaptcha_token(?string $token): void
{
    global $providerSignupAccsRecaptchaToken;
    $token = trim((string) $token);
    $providerSignupAccsRecaptchaToken = $token !== '' ? $token : null;
}

function provider_signup_accs_recaptcha_token(): ?string
{
    global $providerSignupAccsRecaptchaToken;
    if ($providerSignupAccsRecaptchaToken !== null) {
        return $providerSignupAccsRecaptchaToken;
    }

    $envToken = trim((string) env('ADOBE_COMMERCE_RECAPTCHA_TOKEN', ''));

    return $envToken !== '' ? $envToken : null;
}

function provider_signup_accs_recaptcha_site_key(): string
{
    return trim((string) env('ADOBE_COMMERCE_RECAPTCHA_SITE_KEY', ''));
}

function provider_signup_accs_recaptcha_version(): string
{
    $version = strtolower(trim((string) env('ADOBE_COMMERCE_RECAPTCHA_VERSION', 'v2')));

    return $version === 'v3' ? 'v3' : 'v2';
}

function provider_signup_accs_recaptcha_required_for_request(string $method, string $path): bool
{
    if (strtoupper($method) !== 'POST') {
        return false;
    }

    $path = '/' . ltrim(strtolower($path), '/');

    return in_array($path, ['/customers', '/company', '/company/setcustomattributes'], true);
}

function provider_signup_accs_target_environment(): string
{
    return strtolower(trim((string) env('PROVIDER_SIGNUP_ACCS_ENVIRONMENT', 'stage')));
}

function provider_signup_accs_customer_group_id(): int
{
    $configured = (int) env('PROVIDER_SIGNUP_ACCS_USER_GROUP_ID', (string) PROVIDER_SIGNUP_ACCS_CUSTOMER_GROUP_ID_DEFAULT);

    return $configured > 0 ? $configured : PROVIDER_SIGNUP_ACCS_CUSTOMER_GROUP_ID_DEFAULT;
}

function provider_signup_accs_website_id(): int
{
    $configured = (int) env('PROVIDER_SIGNUP_ACCS_WEBSITE_ID', '1');

    return $configured > 0 ? $configured : 1;
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
    if (stripos($message, 'recaptcha') !== false) {
        $hint = ' ACCS requires a valid reCAPTCHA token for this API call.';
        if (provider_signup_accs_recaptcha_site_key() === '' && provider_signup_accs_recaptcha_token() === null) {
            $hint .= ' Add ADOBE_COMMERCE_RECAPTCHA_SITE_KEY to Azure app settings using the Google API Website Key from ACCS Stores > Security > Google reCAPTCHA (reCAPTCHA v2 Invisible).';
        }

        return $message . $hint;
    }

    $parameters = $result['data']['parameters'] ?? null;
    if (!is_array($parameters)) {
        return $message;
    }

    $fieldName = (string) ($parameters['fieldName'] ?? $parameters['field_name'] ?? '');
    $value = $parameters['value'] ?? null;
    $valueLabel = $value === null || $value === '' ? '(empty)' : (is_scalar($value) ? (string) $value : json_encode($value));

    if ($fieldName !== '' && str_contains($message, '%fieldName')) {
        return 'ACCS rejected ' . $fieldName . ' (' . $valueLabel . ').';
    }

    if ($fieldName !== '') {
        return $message . ' Field: ' . $fieldName . '. Value: ' . $valueLabel . '.';
    }

    return $message;
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

function provider_signup_accs_api_request(string $method, string $path, ?array $query = null, ?array $body = null): array
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
        CURLOPT_TIMEOUT        => 30,
    ];

    if ($body !== null) {
        $curlOptions[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
    }

    $recaptchaToken = provider_signup_accs_recaptcha_token();
    if ($recaptchaToken !== null && provider_signup_accs_recaptcha_required_for_request($method, $path)) {
        $curlOptions[CURLOPT_HTTPHEADER][] = 'X-ReCaptcha: ' . $recaptchaToken;
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

    $phone = trim((string) ($application['AdminPhone'] ?? ''));
    if ($phone !== '') {
        $customer['custom_attributes'] = [
            ['attribute_code' => 'phone_number', 'value' => $phone],
        ];
    }

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
        return [
            'ok'          => true,
            'error'       => null,
            'customer_id' => (int) $existing['customer']['id'],
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

    return [
        'company' => [
            'status'            => 1,
            'company_name'      => trim((string) ($application['CompanyName'] ?? '')),
            'legal_name'        => trim((string) ($application['CompanyLegalName'] ?? '')),
            'company_email'     => trim((string) ($application['CompanyEmail'] ?? '')),
            'vat_tax_id'        => $taxId !== '' ? $taxId : null,
            'reseller_id'       => $npi !== '' ? $npi : null,
            'comment'           => implode("\n", $commentParts),
            'street'            => $street !== '' ? [$street] : [''],
            'city'              => trim((string) ($application['City'] ?? '')),
            'country_id'        => $countryId,
            'region_id'         => $regionId,
            'postcode'          => trim((string) ($application['PostalCode'] ?? '')),
            'telephone'         => trim((string) ($application['CompanyPhone'] ?? '')),
            'customer_group_id' => $groupId,
            'super_user_id'     => $superUserId,
        ],
    ];
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

    $attribute = provider_signup_accs_set_clinic_type((int) $company['company_id'], $clinicType);
    if (!$attribute['ok']) {
        return ['ok' => false, 'error' => $attribute['error'] ?? 'Unable to set clinic-type on ACCS company.'];
    }

    return [
        'ok'                => true,
        'error'             => null,
        'company_id'        => (int) $company['company_id'],
        'customer_id'       => (int) $admin['customer_id'],
        'clinic_id'         => (string) $company['company_id'],
        'temporary_password'=> $admin['password'] ?? null,
        'admin_created'     => (bool) ($admin['created'] ?? false),
    ];
}
