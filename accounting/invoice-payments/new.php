<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';

accounting_require_create();

header('Location: /accounting/invoice-payments/?notice=manual_disabled', true, 302);
exit;
