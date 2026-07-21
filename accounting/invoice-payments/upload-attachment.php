<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/po-payment-attachments.php';

accounting_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . accounting_path('/accounting/invoice-payments/'), true, 302);
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$payment = po_payment_get($paymentId);
if ($payment === null || empty($payment['SupplierInvoiceID'])) {
    header('Location: ' . accounting_path('/accounting/invoice-payments/'), true, 302);
    exit;
}

$result = po_payment_save_attachment(
    $paymentId,
    $_FILES['attachment'] ?? [],
    (string) ($_POST['attachment_kind'] ?? 'Other')
);

if ($result['ok']) {
    header('Location: ' . accounting_path('/accounting/invoice-payments/edit.php') . '?id=' . $paymentId . '&notice=attachment', true, 302);
    exit;
}

header('Location: ' . accounting_path('/accounting/invoice-payments/edit.php') . '?id=' . $paymentId . '&error=' . urlencode($result['error']), true, 302);
exit;
