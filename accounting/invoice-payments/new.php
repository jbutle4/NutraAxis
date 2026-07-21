<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();

accounting_require_create();

header('Location: ' . accounting_path('/accounting/invoice-payments/') . '?notice=manual_disabled', true, 302);
exit;
