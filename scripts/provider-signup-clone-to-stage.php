#!/usr/bin/env php
<?php

/**
 * Clone a provisioned Production application into a new Stage-tagged row and
 * provision ACCS Stage (company, admin, shared catalog, products, roles).
 *
 * Does NOT modify the source application or Production ACCS.
 * Does NOT send the provisioned welcome email.
 *
 * Usage:
 *   php scripts/provider-signup-clone-to-stage.php --from-id=28
 *   php scripts/provider-signup-clone-to-stage.php --from-id=28 --dry-run
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/data-profile.php';
require_once __DIR__ . '/../includes/provider-signup.php';
require_once __DIR__ . '/../includes/provider-signup-accs.php';
require_once __DIR__ . '/../includes/provider-signup-accs-config.php';

$options = getopt('', ['from-id:', 'dry-run']);
$fromId = isset($options['from-id']) ? (int) $options['from-id'] : 0;
$dryRun = array_key_exists('dry-run', $options);

if ($fromId <= 0) {
    fwrite(STDERR, "Usage: php scripts/provider-signup-clone-to-stage.php --from-id=28 [--dry-run]\n");
    exit(1);
}

$source = provider_signup_get($fromId);
if ($source === null) {
    fwrite(STDERR, "Source application #{$fromId} not found.\n");
    exit(1);
}

echo "Source #{$fromId}\n";
echo '  Status: ' . ($source['Status'] ?? '') . "\n";
echo '  Company: ' . ($source['CompanyName'] ?? '') . "\n";
echo '  Provider/Admin: ' . ($source['ProviderEmail'] ?? '') . ' / ' . ($source['AdminEmail'] ?? '') . "\n";
echo '  AccsEnvironment: ' . ($source['AccsEnvironment'] ?? '') . "\n";
echo '  AccsCompanyId: ' . ($source['AccsCompanyId'] ?? '') . "\n";
echo '  AccsCustomerId: ' . ($source['AccsCustomerId'] ?? '') . "\n";
echo '  AccsSharedCatalogId: ' . ($source['AccsSharedCatalogId'] ?? '') . "\n";

if ($dryRun) {
    echo "\nDry run only — no clone or Stage provision.\n";
    exit(0);
}

// Prefer UAT/stage Adobe resolution for any helpers that key off data profile.
if (function_exists('data_profile_set')) {
    data_profile_set('uat');
}

$pdo = db();
$token = provider_signup_generate_token();

$stmt = $pdo->prepare(<<<SQL
    INSERT INTO dbo.ProviderSignupApplication (
        AccessToken,
        Status,
        ProviderEmail,
        CompanyName,
        CompanyLegalName,
        CompanyEmail,
        CompanyPhone,
        StreetAddress,
        City,
        StateCode,
        PostalCode,
        CountryCode,
        ClinicType,
        AdminFirstName,
        AdminLastName,
        AdminEmail,
        AdminPhone,
        NpiNumber,
        TaxIdType,
        TaxIdEncrypted,
        AchRoutingNumber,
        AchAccountNumberEncrypted,
        AchAccountType,
        AccsEnvironment,
        PolicyAcknowledgedAt,
        PolicyAcknowledgedByEmail,
        PolicyVersion,
        SubmittedAt,
        LastSavedAt
    )
    OUTPUT INSERTED.ApplicationID AS inserted_id
    VALUES (
        :token,
        :status,
        :provider_email,
        :company_name,
        :company_legal_name,
        :company_email,
        :company_phone,
        :street_address,
        :city,
        :state_code,
        :postal_code,
        :country_code,
        :clinic_type,
        :admin_first_name,
        :admin_last_name,
        :admin_email,
        :admin_phone,
        :npi_number,
        :tax_id_type,
        :tax_id_encrypted,
        :ach_routing_number,
        :ach_account_encrypted,
        :ach_account_type,
        :accs_environment,
        :policy_ack_at,
        :policy_ack_email,
        :policy_version,
        SYSUTCDATETIME(),
        SYSUTCDATETIME()
    )
SQL);

$stmt->execute([
    'token'               => $token,
    'status'              => PROVIDER_SIGNUP_STATUS_APPROVED,
    'provider_email'      => $source['ProviderEmail'] ?? null,
    'company_name'        => $source['CompanyName'] ?? null,
    'company_legal_name'  => $source['CompanyLegalName'] ?? null,
    'company_email'       => $source['CompanyEmail'] ?? null,
    'company_phone'       => $source['CompanyPhone'] ?? null,
    'street_address'      => $source['StreetAddress'] ?? null,
    'city'                => $source['City'] ?? null,
    'state_code'          => $source['StateCode'] ?? null,
    'postal_code'         => $source['PostalCode'] ?? null,
    'country_code'        => $source['CountryCode'] ?? 'US',
    'clinic_type'         => $source['ClinicType'] ?? null,
    'admin_first_name'    => $source['AdminFirstName'] ?? null,
    'admin_last_name'     => $source['AdminLastName'] ?? null,
    'admin_email'         => $source['AdminEmail'] ?? null,
    'admin_phone'         => $source['AdminPhone'] ?? null,
    'npi_number'          => $source['NpiNumber'] ?? null,
    'tax_id_type'         => $source['TaxIdType'] ?? null,
    'tax_id_encrypted'    => $source['TaxIdEncrypted'] ?? null,
    'ach_routing_number'  => $source['AchRoutingNumber'] ?? null,
    'ach_account_encrypted' => $source['AchAccountNumberEncrypted'] ?? null,
    'ach_account_type'    => $source['AchAccountType'] ?? null,
    'accs_environment'    => 'stage',
    'policy_ack_at'       => $source['PolicyAcknowledgedAt'] ?? null,
    'policy_ack_email'    => $source['PolicyAcknowledgedByEmail'] ?? null,
    'policy_version'      => $source['PolicyVersion'] ?? null,
]);

$newId = db_fetch_inserted_int($stmt, 'inserted_id');
if ($newId <= 0) {
    fwrite(STDERR, "Failed to insert Stage clone application.\n");
    exit(1);
}

echo "\nCreated Stage clone application #{$newId} (AccsEnvironment=stage, Status=Approved)\n";

provider_signup_add_review_log(
    $newId,
    null,
    'Comment',
    'Stage ACCS validation clone created from application #' . $fromId
    . ' (Production ACCS left untouched on source).'
);

$clone = provider_signup_get($newId);
if ($clone === null) {
    fwrite(STDERR, "Unable to reload clone #{$newId}.\n");
    exit(1);
}

echo "Provisioning ACCS Stage company + admin…\n";
$provision = provider_signup_accs_with_environment(
    'stage',
    static fn (): array => provider_signup_accs_provision($clone)
);

if (!$provision['ok']) {
    $error = provider_signup_accs_format_provision_error(
        (string) ($provision['error'] ?? 'Provisioning failed.')
    );
    $pdo->prepare(<<<SQL
        UPDATE dbo.ProviderSignupApplication
        SET LastProvisionError = ?, LastSavedAt = SYSUTCDATETIME()
        WHERE ApplicationID = ?
    SQL)->execute([$error, $newId]);
    provider_signup_add_review_log($newId, null, 'ProvisionFailed', $error);
    fwrite(STDERR, "Stage provision FAILED: {$error}\n");
    fwrite(STDERR, "Clone application #{$newId} remains Approved for retry.\n");
    exit(1);
}

$pdo->prepare(<<<SQL
    UPDATE dbo.ProviderSignupApplication
    SET Status = ?,
        ProvisionedAt = SYSUTCDATETIME(),
        LastSavedAt = SYSUTCDATETIME(),
        AccsEnvironment = N'stage',
        AccsCompanyId = ?,
        AccsCustomerId = ?,
        AccsClinicId = ?,
        AccsStepClinicDone = 1,
        AccsStepClinicAt = SYSUTCDATETIME(),
        AccsStepAdminDone = 1,
        AccsStepAdminAt = SYSUTCDATETIME(),
        AccsConfigurationComplete = 0,
        AccsConfigurationCompletedAt = NULL,
        LastProvisionError = NULL
    WHERE ApplicationID = ?
SQL)->execute([
    PROVIDER_SIGNUP_STATUS_PROVISIONED,
    $provision['company_id'] ?? null,
    $provision['customer_id'] ?? null,
    provider_signup_nullable_string((string) ($provision['clinic_id'] ?? '')),
    $newId,
]);

provider_signup_add_review_log(
    $newId,
    null,
    'Provisioned',
    'ACCS Stage company created for validation clone (no welcome email sent).'
);

echo '  Stage company_id=' . ($provision['company_id'] ?? '')
    . ' customer_id=' . ($provision['customer_id'] ?? '')
    . ' admin_created=' . (!empty($provision['admin_created']) ? 'yes' : 'no')
    . "\n";

$updated = provider_signup_get($newId);
if ($updated === null) {
    fwrite(STDERR, "Provisioned but unable to reload #{$newId} for configuration.\n");
    exit(1);
}

echo "Completing Stage shared catalog / products / roles…\n";
$configResult = provider_signup_accs_with_environment(
    'stage',
    static fn (): array => provider_signup_accs_complete_clinic_configuration($updated)
);

if (!$configResult['ok']) {
    $error = (string) ($configResult['error'] ?? 'Unknown configuration error');
    provider_signup_add_review_log(
        $newId,
        null,
        'Comment',
        'ACCS Stage clinic configuration pending: ' . $error
    );
    fwrite(STDERR, "Stage configuration FAILED: {$error}\n");
    fwrite(STDERR, "Application #{$newId} is Provisioned; retry with complete-accs-config.\n");
    exit(1);
}

$persist = provider_signup_persist_accs_config_result($newId, $configResult);
if (!$persist['ok']) {
    fwrite(STDERR, 'Persist config failed: ' . ($persist['error'] ?? 'unknown') . "\n");
    exit(1);
}

provider_signup_add_review_log(
    $newId,
    null,
    'Comment',
    'ACCS Stage clinic configuration completed'
    . (!empty($persist['configuration_complete']) ? ' (all steps complete).' : ' (partial).')
);

$final = provider_signup_get($newId);
$sourceCheck = provider_signup_get($fromId);

echo "\nDone.\n";
echo "Clone #{$newId}:\n";
echo '  Status=' . ($final['Status'] ?? '') . "\n";
echo '  AccsEnvironment=' . ($final['AccsEnvironment'] ?? '') . "\n";
echo '  AccsCompanyId=' . ($final['AccsCompanyId'] ?? '') . "\n";
echo '  AccsCustomerId=' . ($final['AccsCustomerId'] ?? '') . "\n";
echo '  AccsSharedCatalogId=' . ($final['AccsSharedCatalogId'] ?? '') . "\n";
echo '  AccsConfigurationComplete=' . (!empty($final['AccsConfigurationComplete']) ? '1' : '0') . "\n";
echo "Source #{$fromId} (must remain Production):\n";
echo '  AccsEnvironment=' . ($sourceCheck['AccsEnvironment'] ?? '') . "\n";
echo '  AccsCompanyId=' . ($sourceCheck['AccsCompanyId'] ?? '') . "\n";
echo '  AccsCustomerId=' . ($sourceCheck['AccsCustomerId'] ?? '') . "\n";
echo '  AccsSharedCatalogId=' . ($sourceCheck['AccsSharedCatalogId'] ?? '') . "\n";

$viewUrl = 'https://operations.nutraaxislabs.com/operations-dashboard/signup-review/view.php?id=' . $newId;
echo "\nOps view: {$viewUrl}\n";
exit(0);
