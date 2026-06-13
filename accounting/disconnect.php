<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_update();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    qbo_disconnect();
}

header('Location: /accounting/?notice=disconnected', true, 302);
exit;
