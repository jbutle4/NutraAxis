<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/admin.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

$invoiceId = (int) ($_GET['id'] ?? 0);
$rawToken = trim($_GET['token'] ?? '');
$prefillAction = trim($_GET['action'] ?? '');
$invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;

$tokenContext = null;
$tokenKind = null;
if ($rawToken !== '') {
    $tokenContext = payment_approval_invoice_token_resolve($rawToken, $invoiceId);
    if ($tokenContext !== null) {
        $tokenKind = 'Payment';
    } else {
        $tokenContext = qbo_insert_approval_token_resolve($rawToken, $invoiceId);
        if ($tokenContext !== null) {
            $tokenKind = 'QBOInsert';
        }
    }
}
$isTokenAccess = $tokenContext !== null;
$isQboRecovery = $tokenKind === 'QBOInsert'
    || ($tokenKind === null && $invoice !== null && qbo_insert_is_recovery_pending($invoice));

if ($rawToken !== '' && $tokenContext === null) {
    http_response_code(403);
    $pageTitle = 'Invalid Approval Link';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Approval link invalid or expired</h1><p class="page-lead">Sign in or open the approval queue.</p><div class="module-actions"><a class="btn-secondary" href="/login/">Sign in</a></div></div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

if (!$isTokenAccess) {
    if ($isQboRecovery) {
        qbo_insert_require_read();
    } else {
        payment_approval_require_read();
    }
}

if ($invoice === null) {
    header('Location: /approvals/?type=' . ($isQboRecovery ? 'QBOInsert' : 'Payment') . '&status=pending', true, 302);
    exit;
}

$activeSlug = 'accounting';
$accountingSection = 'approvals';
$lines = supplier_invoice_get_lines($invoiceId);
$attachments = supplier_invoice_list_attachments($invoiceId);
$paymentLog = payment_approval_invoice_list_log($invoiceId);
$qboLog = qbo_insert_list_approval_log($invoiceId);
$approvalLog = array_merge($paymentLog, $qboLog);
usort($approvalLog, static function (array $a, array $b): int {
    $dateA = $a['LogDate'] ?? '';
    $dateB = $b['LogDate'] ?? '';
    if ($dateA instanceof DateTimeInterface) {
        $dateA = $dateA->format('Y-m-d H:i:s.u');
    }
    if ($dateB instanceof DateTimeInterface) {
        $dateB = $dateB->format('Y-m-d H:i:s.u');
    }

    return strcmp((string) $dateB, (string) $dateA);
});
$error = $_GET['error'] ?? null;
$notice = $_GET['notice'] ?? null;
$canAct = $invoice['SyncStatus'] === QBO_INSERT_STATUS_SUBMITTED && (
    ($isTokenAccess && $tokenContext['can_act'])
    || (!$isTokenAccess && ($isQboRecovery ? qbo_insert_can_take_action() : payment_approval_can_take_action()))
);
$approvalActions = $isQboRecovery ? qbo_insert_approval_actions() : payment_approval_invoice_actions();
$queueType = $isQboRecovery ? 'QBOInsert' : 'Payment';
$categoryLabel = $isQboRecovery ? 'QBO Insert Approval' : 'Payment Approval';

$pageTitle = 'Review ' . supplier_invoice_reference($invoice) . ' | ' . ($isQboRecovery ? 'QBO Approval' : 'Payment Approval');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      $siApproveLead = '<span class="status-badge ' . supplier_invoice_status_class((string) $invoice['SyncStatus']) . '">' . htmlspecialchars($invoice['SyncStatus']) . '</span> · ' . htmlspecialchars($invoice['SupplierName']);
      render_list_page_header([
          'back_href'  => '/approvals/?type=' . $queueType . '&status=pending',
          'back_label' => 'Back to Approvals',
          'category'   => $categoryLabel,
          'title'      => supplier_invoice_reference($invoice),
          'lead'       => $siApproveLead,
          'lead_html'  => true,
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($notice === 'actioned'): ?>
      <div class="admin-notice is-success" role="status">Your approval action was recorded successfully.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($prefillAction === 'send_back'): ?>
      <div class="admin-notice" role="status">Add comments below, then click Send Back with Comments.</div>
      <?php endif; ?>

      <?php if ($canAct): ?>
      <div class="account-card approval-actions-card">
        <h2>Approver actions</h2>
        <?php if ($isQboRecovery): ?>
          <?php if (qbo_insert_is_stub_mode()): ?>
          <div class="admin-notice" role="status">Test mode is on: approving records the QBO Insert recovery decision and sends email only. QuickBooks is not updated. Set <code>QBO_INSERT_STUB=0</code> when ready to post bills.</div>
          <?php endif; ?>
          <p class="account-card-lead">This is accounting posting recovery after payment approval. Approving will create a Bill in QuickBooks Online.</p>
        <?php else: ?>
          <?php if (payment_approval_is_stub_mode()): ?>
          <div class="admin-notice" role="status">Test mode is on: approving records the payment decision and marks the invoice Posted without creating a QuickBooks bill. Set <code>QBO_INSERT_STUB=0</code> to auto-post bills on approve.</div>
          <p class="account-card-lead">Review the invoice and choose an action. The requestor will be notified by email.</p>
          <?php else: ?>
          <p class="account-card-lead">Approving authorizes this invoice for payment and creates a Bill in QuickBooks Online when one is missing. Payment activity is updated from the QBO integration.</p>
          <?php endif; ?>
        <?php endif; ?>
        <form class="admin-form" method="post" action="/accounting/supplier-invoices/approval-action.php">
          <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
          <?php if ($isTokenAccess): ?>
          <input type="hidden" name="approval_token" value="<?= htmlspecialchars($rawToken) ?>" />
          <?php endif; ?>
          <div class="form-group">
            <label for="comments">Comments</label>
            <textarea class="form-input" id="comments" name="comments" rows="4"></textarea>
          </div>
          <div class="module-actions approval-action-buttons">
            <?php foreach ($approvalActions as $key => $action): ?>
            <button class="<?= $key === 'approve' ? 'btn-primary' : ($key === 'cancel' ? 'btn-text btn-text-danger' : 'btn-secondary') ?>" type="submit" name="action" value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($action['label']) ?></button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <?php elseif ($invoice['SyncStatus'] !== QBO_INSERT_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This invoice is no longer awaiting approval.</div>
      <?php endif; ?>

      <section class="detail-card">
        <h2>Invoice summary</h2>
        <dl class="detail-grid">
          <div><dt>Total</dt><dd><?= htmlspecialchars(accounting_format_money($invoice['TotalAmt'])) ?></dd></div>
          <div><dt>Invoice date</dt><dd><?= htmlspecialchars(accounting_format_date($invoice['TxnDate'])) ?></dd></div>
          <div><dt>Due date</dt><dd><?= htmlspecialchars(accounting_format_date($invoice['DueDate'])) ?></dd></div>
          <div><dt>Memo</dt><dd><?= htmlspecialchars($invoice['Memo'] ?? '—') ?></dd></div>
        </dl>
      </section>

      <section class="detail-card">
        <h2>Line items</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Description</th><th>Amount</th><th>Detail</th></tr></thead>
            <tbody>
              <?php foreach ($lines as $line): ?>
              <tr>
                <td><?= (int) $line['LineNumber'] ?></td>
                <td><?= htmlspecialchars($line['Description'] ?? '—') ?></td>
                <td><?= htmlspecialchars(accounting_format_money($line['Amount'])) ?></td>
                <td><?= htmlspecialchars(SUPPLIER_INVOICE_DETAIL_TYPES[$line['DetailType']] ?? $line['DetailType']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php if ($attachments !== []): ?>
      <section class="detail-card">
        <h2>Attachments</h2>
        <ul>
          <?php foreach ($attachments as $attachment): ?>
          <li><a href="/accounting/supplier-invoices/attachment.php?id=<?= (int) $attachment['AttachmentID'] ?>"><?= htmlspecialchars($attachment['FileName']) ?></a></li>
          <?php endforeach; ?>
        </ul>
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
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
