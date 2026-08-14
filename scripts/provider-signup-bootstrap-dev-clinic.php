#!/usr/bin/env php
<?php

/**
 * Bootstrap a full test clinic in DEV ACCS (provision + clinic configuration).
 *
 * Does not require Azure SQL — uses ACCS API only.
 *
 * Usage:
 *   php scripts/provider-signup-bootstrap-dev-clinic.php
 *   php scripts/provider-signup-bootstrap-dev-clinic.php --company-name="My Dev Clinic"
 *   php scripts/provider-signup-bootstrap-dev-clinic.php --company-id=8 --company-name="Dev Boot 0814"
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/provider-signup.php';
require_once __DIR__ . '/../includes/provider-signup-accs.php';
require_once __DIR__ . '/../includes/provider-signup-accs-config.php';

$options = getopt('', ['company-name::', 'company-id::', 'dry-run']);
$dryRun = array_key_exists('dry-run', $options);
$resumeCompanyId = isset($options['company-id']) ? (int) $options['company-id'] : 0;
$stamp = gmdate('ymd-His');
$companyName = trim((string) ($options['company-name'] ?? 'Dev Boot ' . $stamp));
$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyName) ?? 'dev-clinic');
$slug = trim($slug, '-') ?: 'dev-clinic';
$adminEmail = 'dev-bootstrap+' . $stamp . '@nutraaxislabs.com';

putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT=dev');
$_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT'] = 'dev';
// Dev tenant uses different IDs than Production/Stage defaults in .env.
putenv('PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID=1');
$_ENV['PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID'] = '1';
putenv('PROVIDER_SIGNUP_ACCS_BOOTSTRAP_SUPER_USER_ID_DEV=30');
$_ENV['PROVIDER_SIGNUP_ACCS_BOOTSTRAP_SUPER_USER_ID_DEV'] = '30';
putenv('PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID=7');
$_ENV['PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID'] = '7';
adobe_commerce_reset_access_token_cache();

$configError = adobe_commerce_config_error();
if ($configError !== null) {
    fwrite(STDERR, "ACCS config error: {$configError}\n");
    exit(1);
}

$apiBase = provider_signup_accs_api_base_url();
$tenant = preg_match('#/([^/]+)/V1$#', $apiBase, $matches) ? $matches[1] : adobe_commerce_tenant_id();
$environment = provider_signup_accs_target_environment();
$templateId = provider_signup_accs_config_template_company_id();
$superUserId = provider_signup_accs_config_bootstrap_super_user_id();
$salesRepId = provider_signup_accs_sales_representative_id();

echo "DEV clinic bootstrap\n";
echo "  Tenant: {$tenant}\n";
echo "  Environment: {$environment}\n";
echo "  Clinic_Template company: #{$templateId}\n";
echo "  Super user: #{$superUserId}\n";
echo "  Sales rep: #{$salesRepId}\n";
echo "  Company name: {$companyName}\n";
echo "  Admin email: {$adminEmail}\n\n";

if ($dryRun) {
    echo "Dry run — exiting before API calls.\n";
    exit(0);
}

$application = [
    'ApplicationID'     => 0,
    'AccsEnvironment'   => 'dev',
    'ClinicType'        => 'Functional Medicine',
    'CompanyName'       => $companyName,
    'CompanyLegalName'  => $companyName . ' LLC',
    'CompanyEmail'      => $adminEmail,
    'CompanyPhone'      => '555-0100',
    'StreetAddress'     => '100 Test Clinic Way',
    'City'              => 'Austin',
    'StateCode'         => 'TX',
    'CountryCode'       => 'US',
    'PostalCode'        => '78701',
    'NpiNumber'         => '1234567890',
    'AdminFirstName'    => 'Dev',
    'AdminLastName'     => 'Bootstrap',
    'AdminEmail'        => $adminEmail,
    'AdminPhone'        => '555-0101',
];

$companyId = 0;
$customerId = 0;
$tempPassword = '';

if ($resumeCompanyId > 0) {
    echo "Step 1: Resume configuration for existing company #{$resumeCompanyId}…\n";
    $existing = provider_signup_accs_api_request('GET', '/company/' . $resumeCompanyId);
    if (!$existing['ok'] || !is_array($existing['data'] ?? null)) {
        fwrite(STDERR, 'Unable to load company #' . $resumeCompanyId . ': ' . ($existing['error'] ?? 'Unknown error') . "\n");
        exit(1);
    }

    $companyId = $resumeCompanyId;
    $customerId = (int) ($existing['data']['super_user_id'] ?? 0);
    $application['AccsCompanyId'] = $companyId;
    $application['CompanyName'] = trim((string) ($existing['data']['company_name'] ?? $companyName));
    if (!empty($existing['data']['company_email'])) {
        $application['CompanyEmail'] = (string) $existing['data']['company_email'];
        $adminEmail = $application['CompanyEmail'];
    }
    echo "  OK company \"{$application['CompanyName']}\" (admin customer #{$customerId})\n\n";
} else {
    echo "Step 1: ACCS provision (company + admin)…\n";
    $provision = provider_signup_accs_provision($application);
    if (!$provision['ok']) {
        fwrite(STDERR, 'Provision failed: ' . ($provision['error'] ?? 'Unknown error') . "\n");
        exit(1);
    }

    $companyId = (int) ($provision['company_id'] ?? 0);
    $customerId = (int) ($provision['customer_id'] ?? 0);
    $tempPassword = (string) ($provision['temporary_password'] ?? '');

    echo "  OK company #{$companyId}, admin customer #{$customerId}\n";
    if ($tempPassword !== '') {
        echo "  Temporary admin password: {$tempPassword}\n";
    }

    $application['AccsCompanyId'] = $companyId;
    $application['AccsCustomerId'] = $customerId;
    echo "\n";
}

echo "Step 2: ACCS clinic configuration (catalog, assign, roles)…\n";
$config = provider_signup_accs_complete_clinic_configuration($application);
if (!$config['ok']) {
    fwrite(STDERR, 'Configuration failed: ' . ($config['error'] ?? 'Unknown error') . "\n");
    fwrite(STDERR, "Company #{$companyId} was created — re-run: php scripts/provider-signup-bootstrap-dev-clinic.php --company-id={$companyId} --company-name=\"{$companyName}\"\n");
    exit(1);
}

$catalogId = (int) ($config['shared_catalog_id'] ?? 0);
$categoryCount = (int) ($config['category_count'] ?? 0);
$productCount = (int) ($config['product_count'] ?? 0);
$rolesSummary = (string) ($config['roles_summary'] ?? '');
$complete = !empty($config['configuration_complete']);

echo "  OK shared catalog #{$catalogId}\n";
echo "  Categories: {$categoryCount}, products: {$productCount}\n";
if ($rolesSummary !== '') {
    echo "  Roles: {$rolesSummary}\n";
}
echo '  Configuration complete: ' . ($complete ? 'yes' : 'no') . "\n";

if ($catalogId > 0 && $companyId > 0) {
    echo "\nStep 3: Verify catalog assignment…\n";
    $verify = provider_signup_accs_config_verify_catalog_assignment($catalogId, $companyId);
    if (!$verify['ok']) {
        fwrite(STDERR, 'Verification failed: ' . ($verify['error'] ?? 'Unknown error') . "\n");
        exit(1);
    }
    echo "  OK company linked, {$verify['category_count']} categories, {$verify['product_count']} products\n";
}

echo "\nBootstrap complete.\n";
echo json_encode([
    'environment'       => $environment,
    'tenant'            => $tenant,
    'company_id'        => $companyId,
    'customer_id'       => $customerId,
    'shared_catalog_id' => $catalogId,
    'admin_email'       => $adminEmail,
    'admin_password'    => $tempPassword !== '' ? $tempPassword : null,
    'configuration_complete' => $complete,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

exit(0);
