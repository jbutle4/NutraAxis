<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-ap.php';

accounting_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . accounting_path('/accounting/supplier-invoices/'), true, 302);
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if ($action === 'start_asn') {
    $invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;
    if ($invoice === null) {
        header('Location: ' . accounting_path('/accounting/supplier-invoices/'), true, 302);
        exit;
    }
    supplier_invoice_mark_asn_draft_started($invoiceId);
    $href = supplier_invoice_asn_create_href($invoice);
    header('Location: ' . ($href ?? accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId), true, 302);
    exit;
}

$result = match ($action) {
    'submit', 'submit_qbo'     => qbo_insert_submit_for_approval($invoiceId),
    'resubmit', 'resubmit_qbo' => qbo_insert_resubmit_for_approval($invoiceId),
    default                    => ['ok' => false, 'error' => 'Invalid status action.'],
};
$notice = in_array($action, ['resubmit', 'resubmit_qbo'], true) ? 'resubmitted_qbo' : 'submitted_qbo';

if ($result['ok']) {
    $params = ['id' => $invoiceId, 'notice' => $notice];
    if (!empty($result['notify']) && is_array($result['notify'])) {
        $mailMessage = qbo_insert_format_notify_message($result['notify']);
        if ($mailMessage !== '') {
            $params['mail_message'] = $mailMessage;
        }
        if (($result['notify']['skipped_reason'] ?? null) !== null || ($result['notify']['failed'] ?? []) !== []) {
            $params['mail_warning'] = '1';
        }
    }
    header('Location: ' . accounting_path('/accounting/supplier-invoices/view.php') . '?' . http_build_query($params), true, 302);
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
        <a class="btn-secondary" href="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId) ?>">Back to Invoice</a>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
