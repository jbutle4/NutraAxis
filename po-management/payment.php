<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-payment.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $poId = (int) ($_GET['id'] ?? 0);
    header('Location: /po-management/payments.php' . ($poId > 0 ? '?id=' . $poId : ''), true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$returnTo = ($_POST['return_to'] ?? 'view') === 'edit' ? 'edit' : 'view';
$action = $_POST['payment_action'] ?? '';

if ($poId <= 0 || po_get_order($poId) === null) {
    header('Location: /po-management/', true, 302);
    exit;
}

$baseUrl = $returnTo === 'edit'
    ? '/po-management/edit.php?id=' . $poId
    : '/po-management/view.php?id=' . $poId;

if ($action === 'add') {
    po_payment_require_create();
    $result = po_payment_save($_POST);
    $param = $result['ok'] ? 'payment_notice=added' : 'payment_error=' . rawurlencode((string) $result['error']);
} elseif ($action === 'delete') {
    po_payment_require_delete();
    $result = po_payment_delete((int) ($_POST['payment_id'] ?? 0));
    $param = $result['ok'] ? 'payment_notice=deleted' : 'payment_error=' . rawurlencode((string) $result['error']);
} else {
    header('Location: ' . $baseUrl, true, 302);
    exit;
}

header('Location: ' . $baseUrl . '&' . $param, true, 302);
exit;
