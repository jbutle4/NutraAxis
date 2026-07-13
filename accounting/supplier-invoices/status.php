<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

accounting_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/supplier-invoices/', true, 302);
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;
$usePaymentApproval = $invoice !== null && payment_approval_invoice_is_standalone($invoice);

if ($usePaymentApproval) {
    $result = match ($action) {
        'submit'   => payment_approval_invoice_submit($invoiceId),
        'resubmit' => payment_approval_invoice_resubmit($invoiceId),
        default    => ['ok' => false, 'error' => 'Invalid status action.'],
    };
    $formatNotify = 'payment_approval_format_notify_message';
} else {
    $result = match ($action) {
        'submit'   => qbo_insert_submit_for_approval($invoiceId),
        'resubmit' => qbo_insert_resubmit_for_approval($invoiceId),
        default    => ['ok' => false, 'error' => 'Invalid status action.'],
    };
    $formatNotify = 'qbo_insert_format_notify_message';
}

if ($result['ok']) {
    $notice = $action === 'resubmit' ? 'resubmitted' : 'submitted';
    $params = ['id' => $invoiceId, 'notice' => $notice];
    if (!empty($result['notify']) && is_array($result['notify'])) {
        $mailMessage = $formatNotify($result['notify']);
        if ($mailMessage !== '') {
            $params['mail_message'] = $mailMessage;
        }
        if (($result['notify']['skipped_reason'] ?? null) !== null || ($result['notify']['failed'] ?? []) !== []) {
            $params['mail_warning'] = '1';
        }
    }
    header('Location: /accounting/supplier-invoices/view.php?' . http_build_query($params), true, 302);
    exit;
}

$pageTitle = 'Submit Invoice | Accounting';
require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/accounting/supplier-invoices/view.php?id=<?= $invoiceId ?>">Back to Invoice</a>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
