<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/admin.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments.php';
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

supplier_invoice_require_read();

$invoiceId = (int) ($_GET['id'] ?? 0);
$invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;

if ($invoice === null) {
    header('Location: ' . accounting_path('/accounting/supplier-invoices/'), true, 302);
    exit;
}

$activeSlug = $activeSlug ?? 'accounting';
$accountingSection = 'invoices';
$lines = supplier_invoice_get_lines($invoiceId);
$attachments = supplier_invoice_list_attachments($invoiceId);
$isLocked = supplier_invoice_is_locked($invoice);
$isEditable = supplier_invoice_is_editable($invoice);
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
$latestSendBack = supplier_invoice_latest_send_back($paymentLog);
$isQboRecoveryPending = qbo_insert_is_recovery_pending($invoice);
$canSubmitForApproval = supplier_invoice_can_update() && payment_approval_invoice_can_submit($invoice);
$canResubmitApproval = supplier_invoice_can_update()
    && $invoice['SyncStatus'] === QBO_INSERT_STATUS_SUBMITTED
    && !$isQboRecoveryPending;
$canSubmitForQbo = supplier_invoice_can_update() && qbo_insert_can_manual_submit($invoice)
    && $invoice['SyncStatus'] !== QBO_INSERT_STATUS_SUBMITTED;
$canResubmitQbo = supplier_invoice_can_update() && $isQboRecoveryPending;
$postedIsReopenable = supplier_invoice_posted_is_reopenable($invoice);
$paidTotal = po_payment_total_for_invoice($invoiceId);
$invoicePayments = po_payment_list(['supplier_invoice_id' => $invoiceId]);
$notice = $_GET['notice'] ?? null;

$pageTitle = supplier_invoice_reference($invoice) . ' | Supplier Invoices';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      ob_start();
      ?>
          <?php if (supplier_invoice_can_update() && $isEditable): ?>
          <a class="btn-primary" href="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/edit.php') . '?id=' . $invoiceId) ?>">Edit Invoice</a>
          <?php endif; ?>
          <?php if (po_payment_can_create()): ?>
          <a class="btn-secondary" href="/po-payments/new.php?supplier_invoice_id=<?= $invoiceId ?><?= !empty($invoice['POID']) ? '&po_id=' . (int) $invoice['POID'] : '' ?>">New Payment Request</a>
          <?php endif; ?>
          <?php if ($canSubmitForApproval): ?>
          <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/status.php')) ?>" class="inline-form" onsubmit="return confirm(<?= htmlspecialchars(json_encode($postedIsReopenable ? 'Submit this invoice for payment approval again? Payment approvers will receive a new approval email.' : 'Submit this invoice for payment approval? Payment approvers will be notified by email.'), ENT_QUOTES) ?>);">
            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
            <button type="submit" name="action" value="submit" class="btn-primary"><?= $postedIsReopenable ? 'Resubmit for Approval' : 'Submit for Approval' ?></button>
          </form>
          <?php endif; ?>
          <?php if ($canResubmitApproval): ?>
          <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/status.php')) ?>" class="inline-form" onsubmit="return confirm('Resend the payment approval email to approvers?');">
            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
            <button type="submit" name="action" value="resubmit" class="btn-secondary">Resubmit to Approvers</button>
          </form>
          <?php endif; ?>
          <?php if ($canSubmitForQbo): ?>
          <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/status.php')) ?>" class="inline-form" onsubmit="return confirm('Submit this invoice for QBO Insert recovery? Accounting (QBO Insert approvers) will be notified to post the bill.');">
            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
            <button type="submit" name="action" value="submit_qbo" class="btn-secondary">Submit for QBO Insert</button>
          </form>
          <?php endif; ?>
          <?php if ($canResubmitQbo): ?>
          <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/status.php')) ?>" class="inline-form" onsubmit="return confirm('Resend the QBO Insert recovery email to accounting?');">
            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
            <button type="submit" name="action" value="resubmit_qbo" class="btn-secondary">Resubmit for QBO Insert</button>
          </form>
          <?php endif; ?>
      <?php
      $listToolbar = trim(ob_get_clean());
      render_list_page_header([
          'back_href'  => accounting_path('/accounting/supplier-invoices/'),
          'back_label' => 'Back to Supplier Invoices',
          'category'   => 'Finance',
          'title'      => supplier_invoice_reference($invoice),
          'lead'       => $invoice['SupplierName'] . ' · ' . accounting_format_money($invoice['TotalAmt']),
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Invoice created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Invoice updated successfully.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php elseif ($notice === 'submitted'): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? ' is-error is-detail' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) ($_GET['mail_message'] ?? 'Invoice submitted for payment approval.')) ?></div>
      <?php elseif ($notice === 'resubmitted'): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? ' is-error is-detail' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) ($_GET['mail_message'] ?? 'Payment approval request resent to approvers.')) ?></div>
      <?php elseif ($notice === 'submitted_qbo'): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? ' is-error is-detail' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) ($_GET['mail_message'] ?? 'Invoice submitted for QBO Insert recovery.')) ?></div>
      <?php elseif ($notice === 'resubmitted_qbo'): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? ' is-error is-detail' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) ($_GET['mail_message'] ?? 'QBO Insert recovery request resent to accounting.')) ?></div>
      <?php endif; ?>
      <?php if (!empty($_GET['mail_message']) && !in_array($notice, ['submitted', 'resubmitted', 'submitted_qbo', 'resubmitted_qbo'], true)): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? ' is-error is-detail' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) $_GET['mail_message']) ?></div>
      <?php endif; ?>

      <?php if (payment_approval_is_stub_mode()): ?>
      <div class="admin-notice" role="status">Payment approval test mode is active — approving records the payment decision and marks the invoice Posted without creating a QuickBooks bill. Use <strong>Submit for QBO Insert</strong> for accounting posting recovery, or set <code>QBO_INSERT_STUB=0</code> to auto-post bills on approve.</div>
      <?php endif; ?>

      <?php if (($invoice['SyncStatus'] ?? '') === QBO_INSERT_STATUS_SENT_BACK && $latestSendBack !== null): ?>
      <div class="admin-notice is-detail" role="status">
        <strong>Approver comments</strong> from <?= htmlspecialchars($latestSendBack['ApproverName']) ?>
        on <?= htmlspecialchars(admin_format_datetime($latestSendBack['LogDate'])) ?>:
        <p style="margin: 8px 0 0;"><?= nl2br(htmlspecialchars(supplier_invoice_format_log_comments($latestSendBack['ApproverComments'] ?? null))) ?></p>
      </div>
      <div class="admin-notice" role="status">Update the invoice and submit for payment approval again when ready.</div>
      <?php elseif (($invoice['SyncStatus'] ?? '') === QBO_INSERT_STATUS_SENT_BACK): ?>
      <div class="admin-notice" role="status">This invoice was sent back for comment. Update the invoice and submit for payment approval again.</div>
      <?php elseif ($isQboRecoveryPending): ?>
      <div class="admin-notice" role="status">This invoice is awaiting QBO Insert recovery (accounting posting). <a href="/approvals/?type=QBOInsert&status=pending">View approvals</a></div>
      <?php elseif ($invoice['SyncStatus'] === QBO_INSERT_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This invoice is awaiting payment approval. <a href="/approvals/?type=Payment&status=pending">View approvals</a></div>
      <?php elseif (($invoice['SyncStatus'] ?? '') === QBO_INSERT_STATUS_FAILED && payment_approval_invoice_has_approved($invoiceId)): ?>
      <div class="admin-notice is-error is-detail" role="status">
        Payment was approved, but QuickBooks bill creation failed<?= trim((string) ($invoice['LastSyncError'] ?? '')) !== '' ? ': ' . htmlspecialchars((string) $invoice['LastSyncError']) : '.' ?>
        Use <strong>Submit for QBO Insert</strong> to send this to accounting for posting recovery.
      </div>
      <?php elseif ($postedIsReopenable): ?>
      <div class="admin-notice" role="status">
        <?php if (payment_approval_is_stub_mode()): ?>
        This invoice was payment-approved in test mode and was not posted to QuickBooks.
        <?php else: ?>
        This invoice is marked Posted but has no QuickBooks bill ID.
        <?php endif; ?>
        Edit and resubmit for payment approval if needed, or use <strong>Submit for QBO Insert</strong> for accounting posting recovery.
      </div>
      <?php elseif ($canSubmitForApproval): ?>
      <div class="admin-notice" role="status">Submit this invoice for payment approval. When approved, the bill is posted to QuickBooks automatically (or use Submit for QBO Insert if posting fails). Cash payment is requested separately via New Payment Request.</div>
      <?php endif; ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <section class="detail-card">
        <h2>Invoice summary</h2>
        <dl class="detail-grid">
          <div><dt>Supplier</dt><dd><?= htmlspecialchars($invoice['SupplierName']) ?></dd></div>
          <div><dt>Invoice date</dt><dd><?= htmlspecialchars(accounting_format_date($invoice['TxnDate'])) ?></dd></div>
          <div><dt>Due date</dt><dd><?= htmlspecialchars(accounting_format_date($invoice['DueDate'])) ?></dd></div>
          <div><dt>Total</dt><dd><?= htmlspecialchars(accounting_format_money($invoice['TotalAmt'])) ?></dd></div>
          <div><dt>Sync status</dt><dd><span class="status-badge <?= supplier_invoice_status_class((string) $invoice['SyncStatus']) ?>"><?= htmlspecialchars($invoice['SyncStatus']) ?></span></dd></div>
          <div><dt>QBO Bill ID</dt><dd><?= htmlspecialchars($invoice['QBO_BillId'] ?? '—') ?></dd></div>
          <div><dt>Linked PO</dt><dd><?php if (!empty($invoice['POID'])): ?><a class="btn-text" href="/po-management/view.php?id=<?= (int) $invoice['POID'] ?>"><?= htmlspecialchars($invoice['PONumber']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
          <div><dt>Memo</dt><dd><?= htmlspecialchars($invoice['Memo'] ?? '—') ?></dd></div>
        </dl>
      </section>

      <section class="detail-card">
        <h2>Line items</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Detail type</th>
                <th>Account / item</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($lines === []): ?>
              <tr><td colspan="5">No line items.</td></tr>
              <?php else: ?>
              <?php foreach ($lines as $line): ?>
              <tr>
                <td><?= (int) $line['LineNumber'] ?></td>
                <td><?= htmlspecialchars($line['Description'] ?? '—') ?></td>
                <td><?= htmlspecialchars(accounting_format_money($line['Amount'])) ?></td>
                <td><?= htmlspecialchars(SUPPLIER_INVOICE_DETAIL_TYPES[$line['DetailType']] ?? $line['DetailType']) ?></td>
                <td><?php
                  if ($line['DetailType'] === 'ItemBasedExpenseLineDetail') {
                      echo htmlspecialchars(trim(($line['ItemRefName'] ?? '') . ' (' . ($line['ItemRefValue'] ?? '') . ')'));
                  } else {
                      echo htmlspecialchars(trim(($line['AccountRefName'] ?? '') . ' (' . ($line['AccountRefValue'] ?? '') . ')'));
                  }
                ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php if (empty($invoice['POID'])): ?>
      <section class="detail-card">
        <h2>Payment requests</h2>
        <p class="page-lead">Payment requests for this standalone supplier invoice.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Status</th>
                <th>Confirmation #</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($invoicePayments === []): ?>
              <tr><td colspan="6">No payment requests for this invoice.</td></tr>
              <?php else: ?>
              <?php foreach ($invoicePayments as $payment): ?>
              <tr>
                <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
                <td><?= htmlspecialchars(accounting_format_money($payment['PaymentAmount'])) ?></td>
                <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
                <td><span class="status-badge <?= po_payment_status_class((string) ($payment['PaymentStatus'] ?? '')) ?>"><?= htmlspecialchars(po_payment_format_status($payment['PaymentStatus'] ?? null)) ?></span></td>
                <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
                <td><a class="btn-text" href="/po-payments/edit.php?id=<?= (int) $payment['PaymentID'] ?>">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php else: ?>
      <section class="detail-card">
        <h2>Payment requests</h2>
        <p class="page-lead">
          This invoice is linked to PO <a href="/po-management/view.php?id=<?= (int) $invoice['POID'] ?>"><?= htmlspecialchars($invoice['PONumber'] ?? '') ?></a>.
          <?php if ($invoicePayments !== []): ?>
          <?= count($invoicePayments) === 1 ? '1 payment request' : count($invoicePayments) . ' payment requests' ?> on this invoice
          · Total requested: <strong><?= htmlspecialchars(accounting_format_money($paidTotal)) ?></strong>
          <?php endif; ?>
        </p>
        <?php if ($invoicePayments !== []): ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Status</th>
                <th>Confirmation #</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoicePayments as $payment): ?>
              <tr>
                <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
                <td><?= htmlspecialchars(accounting_format_money($payment['PaymentAmount'])) ?></td>
                <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
                <td><span class="status-badge <?= po_payment_status_class((string) ($payment['PaymentStatus'] ?? '')) ?>"><?= htmlspecialchars(po_payment_format_status($payment['PaymentStatus'] ?? null)) ?></span></td>
                <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
                <td><a class="btn-text" href="/po-payments/edit.php?id=<?= (int) $payment['PaymentID'] ?>">View</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php
        $showUploadForm = supplier_invoice_can_update() && $isEditable;
        $uploadNotice = $notice;
        require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments-section.php';
      ?>

      <?php if ($approvalLog !== []): ?>
      <?php require dirname(__DIR__, 2) . '/includes/supplier-invoice-approval-history.php'; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
