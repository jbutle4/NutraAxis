#!/usr/bin/env php
<?php

/**
 * Create or refresh the Clinic_Template ACCS company and seed clinic roles.
 *
 * Role permissions are copied from the configured source company (default: dev Butler Health).
 * Stage Butler Health currently only has Default User; dev Butler Health has the full clinic role set.
 *
 * Usage:
 *   PROVIDER_SIGNUP_ACCS_ENVIRONMENT=production php scripts/provider-signup-bootstrap-clinic-template.php
 *   php scripts/provider-signup-bootstrap-clinic-template.php --super-user-id=398
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/provider-signup-accs.php';
require_once __DIR__ . '/../includes/provider-signup-accs-config.php';

$options = getopt('', ['super-user-id::']);
$superUserId = isset($options['super-user-id'])
    ? (int) $options['super-user-id']
    : provider_signup_accs_config_bootstrap_super_user_id();

if ($superUserId <= 0) {
  fwrite(STDERR, "Unable to resolve super user ID. Pass --super-user-id=<customer_id> or set PROVIDER_SIGNUP_ACCS_BOOTSTRAP_SUPER_USER_ID_STAGE / PROVIDER_SIGNUP_ACCS_BOOTSTRAP_SUPER_USER_ID.\n");
  exit(1);
}

$targetEnvironment = provider_signup_accs_target_environment();
$sourceEnvironment = provider_signup_accs_config_template_source_environment();
$sourceCompanyId = provider_signup_accs_config_template_source_company_id();

echo "Target environment: {$targetEnvironment}\n";
echo "Template company: " . provider_signup_accs_config_template_company_name() . "\n";
echo "Role source: {$sourceEnvironment} company #{$sourceCompanyId}\n";
echo "Super user ID: {$superUserId}\n\n";

$result = provider_signup_accs_config_bootstrap_clinic_template($superUserId);
if (!$result['ok']) {
  fwrite(STDERR, 'Bootstrap failed: ' . ($result['error'] ?? 'Unknown error') . "\n");
  if (!empty($result['company_id'])) {
    fwrite(STDERR, 'Template company ID: ' . (int) $result['company_id'] . "\n");
  }
  exit(1);
}

echo "Company {$result['company_action']}: ID " . (int) $result['company_id'] . "\n";
echo 'Roles: ' . (string) ($result['roles_summary'] ?? '') . "\n";
foreach ($result['role_actions'] as $action) {
  echo "  - {$action}\n";
}

echo "\nSet on App Service (optional if company name resolves automatically):\n";
echo '  PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_NAME=' . provider_signup_accs_config_template_company_name() . "\n";
echo '  PROVIDER_SIGNUP_ACCS_TEMPLATE_COMPANY_ID=' . (int) $result['company_id'] . "\n";
echo '  PROVIDER_SIGNUP_ACCS_MASTER_SHARED_CATALOG_ID=1' . "\n";

exit(0);
