#!/usr/bin/env php
<?php

/**
 * Copy Clinic_Template company roles from Production into Stage ACCS.
 *
 * Creates the Clinic_Template company in Stage when missing, then clones the
 * required clinic roles from Production (or another source tenant).
 *
 * Usage:
 *   php scripts/provider-signup-copy-clinic-template-to-stage.php
 *   php scripts/provider-signup-copy-clinic-template-to-stage.php --super-user-id=263
 *   php scripts/provider-signup-copy-clinic-template-to-stage.php --source-environment=production --source-company-id=3
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/provider-signup-accs.php';
require_once __DIR__ . '/../includes/provider-signup-accs-config.php';

$options = getopt('', [
    'super-user-id::',
    'source-environment::',
    'source-company-id::',
    'target-environment::',
]);

$targetEnvironment = strtolower(trim((string) ($options['target-environment'] ?? 'stage')));
$sourceEnvironment = strtolower(trim((string) ($options['source-environment'] ?? 'production')));
$sourceCompanyId = isset($options['source-company-id'])
    ? (int) $options['source-company-id']
    : 0;
$superUserId = isset($options['super-user-id'])
    ? (int) $options['super-user-id']
    : provider_signup_accs_config_bootstrap_super_user_id();

if ($superUserId <= 0) {
    fwrite(STDERR, "Pass --super-user-id=<stage_customer_id> or set PROVIDER_SIGNUP_ACCS_BOOTSTRAP_SUPER_USER_ID_STAGE.\n");
    exit(1);
}

if ($sourceCompanyId <= 0) {
    $sourceCompanyId = provider_signup_accs_config_find_company_id_by_name(
        provider_signup_accs_config_template_company_name(),
        $sourceEnvironment
    ) ?? 0;
}

if ($sourceCompanyId <= 0) {
    fwrite(STDERR, "Unable to resolve source Clinic_Template company in {$sourceEnvironment}.\n");
    exit(1);
}

echo "Source: {$sourceEnvironment} company #{$sourceCompanyId}\n";
echo 'Target: ' . $targetEnvironment . ' template company ' . provider_signup_accs_config_template_company_name() . "\n";
echo "Super user ID: {$superUserId}\n\n";

$definitions = provider_signup_accs_config_load_template_role_definitions($sourceEnvironment, $sourceCompanyId);
if (!$definitions['ok']) {
    fwrite(STDERR, 'Failed to load source roles: ' . ($definitions['error'] ?? 'Unknown error') . "\n");
    exit(1);
}

echo 'Source roles: ' . implode(', ', array_keys($definitions['roles'])) . "\n";

$previous = getenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT');
$hadPrevious = $previous !== false;
putenv('PROVIDER_SIGNUP_ACCS_ENVIRONMENT=' . $targetEnvironment);
$_ENV['PROVIDER_SIGNUP_ACCS_ENVIRONMENT'] = $targetEnvironment;
adobe_commerce_reset_access_token_cache();

try {
    $company = provider_signup_accs_config_bootstrap_clinic_template_company($superUserId);
    if (!$company['ok'] || empty($company['company_id'])) {
        fwrite(STDERR, 'Target company bootstrap failed: ' . ($company['error'] ?? 'Unknown error') . "\n");
        exit(1);
    }

    echo 'Target company ' . ($company['action'] ?? 'ready') . ': ID ' . (int) $company['company_id'] . "\n";

    $roles = provider_signup_accs_config_apply_template_role_definitions(
        (int) $company['company_id'],
        $definitions['roles']
    );
    if (!$roles['ok']) {
        fwrite(STDERR, 'Role copy failed: ' . ($roles['error'] ?? 'Unknown error') . "\n");
        exit(1);
    }

    echo 'Target roles: ' . (string) ($roles['summary'] ?? '') . "\n";
    foreach ($roles['actions'] ?? [] as $action) {
        echo "  - {$action}\n";
    }

    echo "\nOptional App Service settings for {$targetEnvironment}:\n";
    echo '  PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID=' . (int) $company['company_id'] . "\n";
    $superUserEnvKey = 'PROVIDER_SIGNUP_ACCS_BOOTSTRAP_SUPER_USER_ID_'
        . strtoupper(str_replace('-', '_', $targetEnvironment));
    echo '  ' . $superUserEnvKey . '=' . $superUserId . "\n";
    if ($targetEnvironment === 'dev') {
        echo "  PROVIDER_SIGNUP_ACCS_SALES_REPRESENTATIVE_ID=1  # Dev tenant uses sales rep #1, not production's #12\n";
    }
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

exit(0);
