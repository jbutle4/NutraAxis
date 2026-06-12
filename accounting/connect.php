<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_update();

$error = qbo_config_error();
if ($error !== null) {
    header('Location: /accounting/?notice=config', true, 302);
    exit;
}

header('Location: ' . qbo_authorize_url(), true, 302);
exit;
