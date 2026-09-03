<?php

/**
 * Backfill Clinic ID on provisioned provider signup applications.
 *
 * Standard: AccsClinicId = AccsCompanyId and ACCS company admin custom attribute clinic_id
 * matches the current company ID (required for storefront doctor dropdown).
 *
 * Usage:
 *   php scripts/repair-provider-signup-clinic-id.php --dry-run
 *   php scripts/repair-provider-signup-clinic-id.php
 *   php scripts/repair-provider-signup-clinic-id.php --id=28
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/provider-signup.php';
require_once __DIR__ . '/../includes/provider-signup-accs.php';

$options = getopt('', ['dry-run', 'id:']);
$dryRun = array_key_exists('dry-run', $options);
$onlyId = isset($options['id']) ? (int) $options['id'] : 0;

$pdo = db();
$sql = <<<SQL
    SELECT ApplicationID, CompanyName, Status, AccsEnvironment, AccsCompanyId, AccsCustomerId, AccsClinicId
    FROM dbo.ProviderSignupApplication
    WHERE AccsCompanyId IS NOT NULL
      AND AccsCustomerId IS NOT NULL
SQL;
$params = [];
if ($onlyId > 0) {
    $sql .= ' AND ApplicationID = :application_id';
    $params['application_id'] = $onlyId;
}
$sql .= ' ORDER BY ApplicationID';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    echo "No provisioned applications found.\n";
    exit(0);
}

echo ($dryRun ? '[DRY RUN] ' : '') . 'Repairing Clinic ID for ' . count($rows) . " application(s)...\n\n";

$updatedDb = 0;
$updatedAccs = 0;
$skippedAccs = 0;
$failed = 0;

foreach ($rows as $row) {
    $applicationId = (int) ($row['ApplicationID'] ?? 0);
    $companyId = (int) ($row['AccsCompanyId'] ?? 0);
    $customerId = (int) ($row['AccsCustomerId'] ?? 0);
    $expectedClinicId = (string) $companyId;
    $currentClinicId = trim((string) ($row['AccsClinicId'] ?? ''));
    $environment = provider_signup_accs_normalize_environment((string) ($row['AccsEnvironment'] ?? ''))
        ?? provider_signup_accs_target_environment();
    $label = sprintf(
        '#%d %s env=%s company=%d customer=%d',
        $applicationId,
        (string) ($row['CompanyName'] ?? ''),
        $environment,
        $companyId,
        $customerId
    );

    if ($currentClinicId !== $expectedClinicId) {
        echo "$label\n  DB AccsClinicId: " . ($currentClinicId !== '' ? $currentClinicId : '(null)') . " -> $expectedClinicId\n";
        if (!$dryRun) {
            $update = $pdo->prepare(<<<SQL
                UPDATE dbo.ProviderSignupApplication
                SET AccsClinicId = :accs_clinic_id,
                    LastSavedAt = SYSUTCDATETIME()
                WHERE ApplicationID = :application_id
            SQL);
            $update->execute([
                'accs_clinic_id'  => $expectedClinicId,
                'application_id'  => $applicationId,
            ]);
        }
        $updatedDb++;
    }

    $accsResult = provider_signup_accs_with_environment(
        $environment,
        static function () use ($customerId, $companyId, $dryRun, $label): array {
            $current = provider_signup_accs_api_request('GET', '/customers/' . $customerId);
            if (!($current['ok'] ?? false) || !is_array($current['data'] ?? null)) {
                return [
                    'ok'      => false,
                    'error'   => provider_signup_accs_format_api_error($current),
                    'updated' => false,
                    'current' => '',
                ];
            }

            $customer = $current['data'];
            $existing = provider_signup_accs_customer_attribute_value($customer, PROVIDER_SIGNUP_ACCS_CLINIC_ID_ATTRIBUTE);
            if ($existing === (string) $companyId) {
                return ['ok' => true, 'error' => null, 'updated' => false, 'current' => $existing];
            }

            echo "$label\n  ACCS clinic_id: " . ($existing !== '' ? $existing : '(missing)') . " -> $companyId\n";
            if ($dryRun) {
                return ['ok' => true, 'error' => null, 'updated' => true, 'current' => $existing];
            }

            return provider_signup_accs_set_admin_clinic_id($customerId, $companyId, $customer) + [
                'current' => $existing,
            ];
        }
    );

    if (!($accsResult['ok'] ?? false)) {
        echo "  ERROR: " . ($accsResult['error'] ?? 'Unable to set ACCS clinic_id.') . "\n";
        $failed++;
        continue;
    }

    if (!empty($accsResult['updated'])) {
        $updatedAccs++;
    } else {
        $skippedAccs++;
    }
}

echo "\nSummary:\n";
echo "  DB rows updated: $updatedDb\n";
echo "  ACCS admins updated: $updatedAccs\n";
echo "  ACCS admins already correct: $skippedAccs\n";
echo "  Failures: $failed\n";

exit($failed > 0 ? 1 : 0);
