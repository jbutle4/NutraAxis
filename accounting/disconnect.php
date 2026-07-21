<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_update();

$envRaw = strtolower(trim((string) ($_POST['env'] ?? $_GET['env'] ?? '')));
if ($envRaw !== QBO_ENV_SANDBOX && $envRaw !== QBO_ENV_PRODUCTION) {
    header('Location: /accounting/?notice=config', true, 302);
    exit;
}
$env = qbo_normalize_environment($envRaw);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    qbo_disconnect($env);
}

header('Location: /accounting/?notice=disconnected&env=' . rawurlencode($env), true, 302);
exit;
