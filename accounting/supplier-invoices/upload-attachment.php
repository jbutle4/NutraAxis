<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments.php';

supplier_invoice_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . accounting_path('/accounting/supplier-invoices/'), true, 302);
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$result = supplier_invoice_save_attachment(
    $invoiceId,
    $_FILES['attachment'] ?? [],
    (string) ($_POST['attachment_kind'] ?? 'InvoicePDF')
);
$isAjax = !empty($_POST['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$returnTo = trim((string) ($_POST['return_to'] ?? ''));
$successRedirect = accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId . '&notice=attachment';
$returnToAllowed = false;
foreach (['/accounting/supplier-invoices/', '/accounting/supplier-invoices-uat/'] as $prefix) {
    if ($returnTo !== '' && str_starts_with($returnTo, $prefix)) {
        $returnToAllowed = true;
        break;
    }
}
if ($returnToAllowed) {
    $successRedirect = $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'notice=attachment';
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if ($result['ok']) {
        echo json_encode([
            'ok'       => true,
            'error'    => null,
            'redirect' => $successRedirect,
        ], JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => $result['error'] ?? 'Unable to upload attachment.',
        ], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($result['ok']) {
    header('Location: ' . $successRedirect, true, 302);
    exit;
}

header('Location: ' . accounting_path('/accounting/supplier-invoices/edit.php') . '?id=' . $invoiceId . '&error=' . urlencode($result['error']), true, 302);
exit;
