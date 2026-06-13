<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-payments/', true, 302);
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$result = po_payment_delete($paymentId);

if ($result['ok']) {
    header('Location: /po-payments/?notice=deleted', true, 302);
    exit;
}

header('Location: /po-payments/edit.php?id=' . $paymentId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
