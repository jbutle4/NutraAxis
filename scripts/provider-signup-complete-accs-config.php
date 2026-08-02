#!/usr/bin/env php
<?php

/**
 * Batch-complete ACCS clinic configuration for provisioned provider signups.
 *
 * Usage:
 *   php scripts/provider-signup-complete-accs-config.php
 *   php scripts/provider-signup-complete-accs-config.php --id=20
 *   php scripts/provider-signup-complete-accs-config.php --limit=25
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/provider-signup.php';

$options = getopt('', ['id::', 'limit::']);
$applicationId = isset($options['id']) ? (int) $options['id'] : 0;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 100;

if ($applicationId > 0) {
    $application = provider_signup_get($applicationId);
    $applications = $application !== null ? [$application] : [];
} else {
    $applications = provider_signup_list_applications_needing_accs_config($limit);
}

if ($applications === []) {
    echo "No applications need ACCS clinic configuration.\n";
    exit(0);
}

$success = 0;
$failed = 0;

foreach ($applications as $application) {
    $id = (int) ($application['ApplicationID'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    if ((string) ($application['Status'] ?? '') !== PROVIDER_SIGNUP_STATUS_PROVISIONED) {
        echo "#{$id}: skipped (not provisioned)\n";
        continue;
    }

    if (provider_signup_config_steps_complete($application)) {
        echo "#{$id}: already complete\n";
        continue;
    }

    echo "#{$id}: running ACCS clinic configuration…\n";
    $result = provider_signup_accs_complete_clinic_configuration($application);
    if (!$result['ok']) {
        $failed++;
        echo "  FAIL: " . ($result['error'] ?? 'Unknown error') . "\n";
        continue;
    }

    $persist = provider_signup_persist_accs_config_result($id, $result);
    if (!$persist['ok']) {
        $failed++;
        echo "  FAIL: " . ($persist['error'] ?? 'Unable to persist results') . "\n";
        continue;
    }

    $success++;
    $catalogId = (int) ($result['shared_catalog_id'] ?? 0);
    $categoryCount = (int) ($result['category_count'] ?? 0);
    $productCount = (int) ($result['product_count'] ?? 0);
    echo "  OK: catalog {$catalogId}, {$categoryCount} categories, {$productCount} products";
    if (!empty($result['roles_summary'])) {
        echo ', roles: ' . (string) $result['roles_summary'];
    }
    echo !empty($persist['configuration_complete']) ? " (complete)\n" : " (partial)\n";
}

echo "\nDone. {$success} succeeded, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
