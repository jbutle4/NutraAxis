<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/supplier-invoice.php';

supplier_invoice_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/supplier-invoices/', true, 302);
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$result = supplier_invoice_delete($invoiceId);

if ($result['ok']) {
    header('Location: /accounting/supplier-invoices/?notice=deleted', true, 302);
    exit;
}

header('Location: /accounting/supplier-invoices/edit.php?id=' . $invoiceId . '&error=' . urlencode($result['error']), true, 302);
exit;
