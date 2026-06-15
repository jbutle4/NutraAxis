#!/usr/bin/env php
<?php

/**
 * Print Adobe Commerce env var names and whether each is configured (values hidden).
 *
 * Usage: php scripts/adobe-commerce-env-check.php
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/adobe-commerce-settings.php';
require_once __DIR__ . '/../includes/adobe-commerce.php';

echo "Adobe Commerce environment variables (canonical names)\n";
echo str_repeat('=', 60) . "\n\n";

foreach (ADOBE_COMMERCE_RUNTIME_ENV_KEYS as $key) {
    $value = env($key);
    $status = ($value !== null && $value !== '') ? 'set' : 'missing';
    printf("  %-40s %s\n", $key, $status);
}

echo "\nActive configuration\n";
echo str_repeat('-', 60) . "\n";
printf("  %-40s %s\n", 'environment', adobe_commerce_environment());
printf("  %-40s %s\n", 'tenant_id', adobe_commerce_tenant_id());
printf("  %-40s %s\n", 'api_host', adobe_commerce_api_host());
printf("  %-40s %s\n", 'client_id', adobe_commerce_client_id() !== '' ? 'set' : 'missing');
printf("  %-40s %s\n", 'client_secret', adobe_commerce_client_secret() !== '' ? 'set' : 'missing');

$configError = adobe_commerce_config_error();
echo "\nAPI ready: " . ($configError === null ? 'yes' : 'no') . "\n";
if ($configError !== null) {
    echo "  $configError\n";
}

echo "\nSee includes/adobe-commerce-settings.php for descriptions.\n";
