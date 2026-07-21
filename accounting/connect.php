<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_update();

$envParam = strtolower(trim((string) ($_GET['env'] ?? '')));
if ($envParam !== QBO_ENV_SANDBOX && $envParam !== QBO_ENV_PRODUCTION) {
    header('Location: /accounting/?notice=config', true, 302);
    exit;
}
$env = qbo_normalize_environment($envParam);
$error = qbo_config_error($env);
if ($error !== null) {
    header('Location: /accounting/?notice=config&env=' . rawurlencode($env), true, 302);
    exit;
}

header('Location: ' . qbo_authorize_url($env), true, 302);
exit;
