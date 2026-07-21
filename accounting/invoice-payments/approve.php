<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';

$paymentId = (int) ($_GET['id'] ?? 0);
$rawToken = trim($_GET['token'] ?? '');
$tokenContext = $rawToken !== '' ? payment_approval_token_resolve($rawToken, $paymentId) : null;
$isTokenAccess = $tokenContext !== null;

if ($rawToken !== '' && $tokenContext === null) {
    http_response_code(403);
    $pageTitle = 'Invalid Approval Link';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Approval link invalid or expired</h1><div class="module-actions"><a class="btn-secondary" href="/login/">Sign in</a></div></div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

if (!$isTokenAccess) {
    payment_approval_require_read();
}

$payment = $paymentId > 0 ? po_payment_get($paymentId) : null;
if (!payment_approval_is_invoice_payment($payment)) {
    header('Location: /approvals/?type=Payment&status=pending', true, 302);
    exit;
}

$invoice = po_payment_get_invoice((int) $payment['SupplierInvoiceID']);
$approvalLog = payment_approval_list_log($paymentId, (int) $payment['SupplierInvoiceID']);
$error = $_GET['error'] ?? null;
$notice = $_GET['notice'] ?? null;
$canAct = $payment['PaymentStatus'] === PAYMENT_APPROVAL_STATUS_SUBMITTED && (
    ($isTokenAccess && $tokenContext['can_act'])
    || (!$isTokenAccess && payment_approval_can_take_action())
);

$activeSlug = $activeSlug ?? 'accounting';
$accountingSection = 'approvals';
$pageTitle = 'Review Payment | Accounting';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      $payApproveLead = '<span class="status-badge ' . po_payment_status_class((string) $payment['PaymentStatus']) . '">' . htmlspecialchars(po_payment_format_status($payment['PaymentStatus'])) . '</span> · ' . htmlspecialchars($payment['SupplierName'] ?? '') . ' · ' . accounting_format_money($payment['PaymentAmount']);
      render_list_page_header([
          'back_href'  => '/approvals/?type=Payment&status=pending',
          'back_label' => 'Back to Approvals',
          'category'   => 'Payment Approval',
          'title'      => 'Payment #' . $paymentId,
          'lead'       => $payApproveLead,
          'lead_html'  => true,
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (payment_approval_is_stub_mode()): ?>
      <div class="admin-notice" role="status">Test mode is on: approving records the decision and sends email only. QuickBooks is not updated. Set <code>QBO_INSERT_STUB=0</code> when ready to post bills and payments.</div>
      <?php endif; ?>

      <?php if ($canAct): ?>
      <div class="account-card approval-actions-card">
        <h2>Approver actions</h2>
        <?php if (payment_approval_is_stub_mode()): ?>
        <p class="account-card-lead">Review the payment request and choose an action. The requestor will be notified by email.</p>
        <?php else: ?>
        <p class="account-card-lead">Approving will post the supplier bill (if needed) and bill payment to QuickBooks Online.</p>
        <?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars(accounting_path('/accounting/invoice-payments/approval-action.php')) ?>">
          <input type="hidden" name="payment_id" value="<?= $paymentId ?>" />
          <?php if ($isTokenAccess): ?>
          <input type="hidden" name="approval_token" value="<?= htmlspecialchars($rawToken) ?>" />
          <?php endif; ?>
          <div class="form-group">
            <label for="comments">Comments</label>
            <textarea class="form-input" id="comments" name="comments" rows="4"></textarea>
          </div>
          <div class="module-actions approval-action-buttons">
            <?php foreach (payment_approval_actions() as $key => $action): ?>
            <button class="<?= $key === 'approve' ? 'btn-primary' : ($key === 'cancel' ? 'btn-text btn-text-danger' : 'btn-secondary') ?>" type="submit" name="action" value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($action['label']) ?></button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <section class="detail-card">
        <h2>Payment details</h2>
        <dl class="detail-grid">
          <div><dt>Invoice</dt><dd><?= htmlspecialchars($invoice ? supplier_invoice_reference($invoice) : '—') ?></dd></div>
          <div><dt>Payment date</dt><dd><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></dd></div>
          <div><dt>Type</dt><dd><?= htmlspecialchars($payment['PaymentType']) ?></dd></div>
          <div><dt>Confirmation #</dt><dd><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></dd></div>
          <div><dt>Comments</dt><dd><?= htmlspecialchars($payment['PaymentComments'] ?? '—') ?></dd></div>
        </dl>
      </section>

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
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
