<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/po-payment-attachments.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

accounting_require_update();

$paymentId = (int) ($_GET['id'] ?? 0);
$payment = $paymentId > 0 ? po_payment_get($paymentId) : null;

if ($payment === null || empty($payment['SupplierInvoiceID'])) {
    header('Location: ' . accounting_path('/accounting/invoice-payments/'), true, 302);
    exit;
}

$activeSlug = $activeSlug ?? 'accounting';
$accountingSection = 'invoice-payments';
$error = null;
$notice = $_GET['notice'] ?? null;
$form = po_payment_to_form($payment);
$invoiceOptions = po_payment_invoice_options();
$attachments = po_payment_list_attachments($paymentId);
$approvalLog = payment_approval_list_log($paymentId, (int) $payment['SupplierInvoiceID']);
$paymentEditable = payment_approval_is_editable($payment);
$canSubmitPayment = accounting_can_update() && payment_approval_can_submit($payment);
$canResubmitPayment = accounting_can_update() && ($payment['PaymentStatus'] ?? '') === PAYMENT_APPROVAL_STATUS_SUBMITTED;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$paymentEditable) {
        $error = 'This payment cannot be edited while it is awaiting approval.';
    } else {
    $form = array_merge($form, po_payment_from_input($_POST));
    $result = po_payment_save($_POST, $paymentId);

    if ($result['ok']) {
        header('Location: ' . accounting_path('/accounting/invoice-payments/') . '?notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
    }
}

$pageTitle = 'Edit Invoice Payment | Accounting';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      render_list_page_header([
          'back_href'  => accounting_path('/accounting/invoice-payments/'),
          'back_label' => 'Back to Invoice Payments',
          'category'   => 'Finance',
          'title'      => 'Edit Invoice Payment',
          'lead'       => po_payment_reference_label($payment) . ' · ' . ($payment['SupplierName'] ?? '') . ' · ' . accounting_format_money($payment['PaymentAmount']),
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Payment recorded. Submit for approval when ready.</div>
      <?php elseif ($notice === 'submitted'): ?>
      <div class="admin-notice is-success" role="status">Payment submitted for approval.</div>
      <?php elseif ($notice === 'resubmitted'): ?>
      <div class="admin-notice is-success" role="status">Approval request resent to payment approvers.</div>
      <?php endif; ?>
      <?php if (!empty($_GET['mail_message'])): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? '' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) $_GET['mail_message']) ?></div>
      <?php endif; ?>

      <?php if (($payment['PaymentStatus'] ?? '') === PAYMENT_APPROVAL_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This payment is awaiting approval. <a href="/approvals/?type=Payment&status=pending">View approvals</a></div>
      <?php endif; ?>

      <?php if ($canSubmitPayment): ?>
      <div class="module-actions" style="margin-bottom: 16px;">
        <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/invoice-payments/status.php')) ?>" class="inline-form">
          <input type="hidden" name="payment_id" value="<?= $paymentId ?>" />
          <button type="submit" name="action" value="submit" class="btn-primary">Submit for Payment Approval</button>
        </form>
      </div>
      <?php elseif ($canResubmitPayment): ?>
      <div class="module-actions" style="margin-bottom: 16px;">
        <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/invoice-payments/status.php')) ?>" class="inline-form">
          <input type="hidden" name="payment_id" value="<?= $paymentId ?>" />
          <button type="submit" name="action" value="resubmit" class="btn-secondary">Resubmit to Approvers</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($paymentEditable): ?>
      <?php
        $isEdit = true;
        $invoiceOnly = true;
        $poOptions = [];
        $formAction = accounting_path('/accounting/invoice-payments/edit.php') . '?id=' . $paymentId;
        require dirname(__DIR__, 2) . '/includes/po-payment-form.php';
      ?>
      <?php else: ?>
      <section class="detail-card">
        <h2>Payment details</h2>
        <dl class="detail-grid">
          <div><dt>Status</dt><dd><span class="status-badge <?= po_payment_status_class((string) $payment['PaymentStatus']) ?>"><?= htmlspecialchars(po_payment_format_status($payment['PaymentStatus'])) ?></span></dd></div>
          <div><dt>Payment date</dt><dd><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></dd></div>
          <div><dt>Amount</dt><dd><?= htmlspecialchars(accounting_format_money($payment['PaymentAmount'])) ?></dd></div>
          <div><dt>Type</dt><dd><?= htmlspecialchars($payment['PaymentType']) ?></dd></div>
          <div><dt>Confirmation #</dt><dd><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></dd></div>
          <div><dt>Comments</dt><dd><?= htmlspecialchars($payment['PaymentComments'] ?? '—') ?></dd></div>
        </dl>
      </section>
      <?php endif; ?>

      <?php if ($approvalLog !== []): ?>
      <section class="detail-card">
        <h2>Approval history</h2>
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Approver</th><th>Result</th><th>Comments</th></tr></thead>
          <tbody>
            <?php foreach ($approvalLog as $entry): ?>
            <tr>
              <td><?= htmlspecialchars(admin_format_datetime($entry['LogDate'])) ?></td>
              <td><?= htmlspecialchars($entry['ApproverName']) ?></td>
              <td><?= htmlspecialchars($entry['ApproverResult']) ?></td>
              <td><?= htmlspecialchars($entry['ApproverComments'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <?php endif; ?>

      <?php
        $showUploadForm = accounting_can_update();
        $uploadNotice = $notice;
        $attachmentBasePath = accounting_path('/accounting/invoice-payments/attachment.php');
        $uploadActionPath = accounting_path('/accounting/invoice-payments/upload-attachment.php');
        require dirname(__DIR__, 2) . '/includes/po-payment-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
