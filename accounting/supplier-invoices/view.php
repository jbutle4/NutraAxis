<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
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
    header('Location: /accounting/supplier-invoices/', true, 302);
    exit;
}

$activeSlug = 'accounting';
$accountingSection = 'invoices';
$lines = supplier_invoice_get_lines($invoiceId);
$attachments = supplier_invoice_list_attachments($invoiceId);
$isLocked = supplier_invoice_is_locked($invoice);
$isEditable = supplier_invoice_is_editable($invoice);
$isStandalone = empty($invoice['POID']);
$approvalLog = $isStandalone ? payment_approval_invoice_list_log($invoiceId) : qbo_insert_list_approval_log($invoiceId);
$latestSendBack = supplier_invoice_latest_send_back($approvalLog);
$canSubmitForApproval = supplier_invoice_can_update() && (
    ($isStandalone && payment_approval_invoice_can_submit($invoice))
    || (!$isStandalone && qbo_insert_can_submit($invoice))
);
$canResubmitApproval = supplier_invoice_can_update() && $invoice['SyncStatus'] === QBO_INSERT_STATUS_SUBMITTED;
$paidTotal = empty($invoice['POID']) ? po_payment_total_for_invoice($invoiceId) : 0.0;
$invoicePayments = empty($invoice['POID']) ? po_payment_list(['supplier_invoice_id' => $invoiceId]) : [];
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
          <a class="btn-primary" href="/accounting/supplier-invoices/edit.php?id=<?= $invoiceId ?>">Edit Invoice</a>
          <?php endif; ?>
          <?php if ($canSubmitForApproval): ?>
          <form method="post" action="/accounting/supplier-invoices/status.php" class="inline-form">
            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
            <button type="submit" name="action" value="submit" class="btn-primary">Submit for Approval</button>
          </form>
          <?php endif; ?>
          <?php if ($canResubmitApproval): ?>
          <form method="post" action="/accounting/supplier-invoices/status.php" class="inline-form">
            <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
            <button type="submit" name="action" value="resubmit" class="btn-secondary">Resubmit to Approvers</button>
          </form>
          <?php endif; ?>
      <?php
      $listToolbar = trim(ob_get_clean());
      render_list_page_header([
          'back_href'  => '/accounting/supplier-invoices/',
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
      <div class="admin-notice is-success" role="status">Invoice submitted for approval.</div>
      <?php elseif ($notice === 'resubmitted'): ?>
      <div class="admin-notice is-success" role="status">Approval request resent to approvers.</div>
      <?php endif; ?>
      <?php if (!empty($_GET['mail_message'])): ?>
      <div class="admin-notice<?= !empty($_GET['mail_warning']) ? '' : ' is-success' ?>" role="status"><?= htmlspecialchars((string) $_GET['mail_message']) ?></div>
      <?php endif; ?>

      <?php if ($isStandalone && payment_approval_is_stub_mode()): ?>
      <div class="admin-notice" role="status">Payment approval test mode is active — approving records the decision and sends email only. QuickBooks is not updated. Set <code>QBO_INSERT_STUB=0</code> when ready to post bills.</div>
      <?php elseif (!$isStandalone && qbo_insert_is_stub_mode()): ?>
      <div class="admin-notice" role="status">QBO insert test mode is active — submit sends approval email; approve does not post to QuickBooks.</div>
      <?php endif; ?>

      <?php if ($isStandalone && ($invoice['SyncStatus'] ?? '') === QBO_INSERT_STATUS_SENT_BACK && $latestSendBack !== null): ?>
      <div class="admin-notice is-detail" role="status">
        <strong>Approver comments</strong> from <?= htmlspecialchars($latestSendBack['ApproverName']) ?>
        on <?= htmlspecialchars(admin_format_datetime($latestSendBack['LogDate'])) ?>:
        <p style="margin: 8px 0 0;"><?= nl2br(htmlspecialchars(supplier_invoice_format_log_comments($latestSendBack['ApproverComments'] ?? null))) ?></p>
      </div>
      <div class="admin-notice" role="status">Update the invoice and submit for approval again when ready.</div>
      <?php elseif ($isStandalone && ($invoice['SyncStatus'] ?? '') === QBO_INSERT_STATUS_SENT_BACK): ?>
      <div class="admin-notice" role="status">This invoice was sent back for comment. Update the invoice and submit for approval again.</div>
      <?php elseif ($isStandalone && $invoice['SyncStatus'] === QBO_INSERT_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This invoice is awaiting payment approval. <a href="/approvals/?type=Payment&status=pending">View approvals</a></div>
      <?php elseif ($isStandalone): ?>
      <div class="admin-notice" role="status">Submit this invoice for payment approver review. When approved, the bill is posted to QuickBooks. Payment activity is updated from the QBO integration.</div>
      <?php elseif ($invoice['SyncStatus'] === QBO_INSERT_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This invoice is in the QBO insert approval queue. <a href="/approvals/?type=QBOInsert&status=pending">View approvals</a></div>
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
        <h2>Payments</h2>
        <p class="page-lead">Payment activity is synced from QuickBooks. Manual payment entry is not used for standalone invoices.</p>
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
              <tr><td colspan="6">No payments recorded for this invoice.</td></tr>
              <?php else: ?>
              <?php foreach ($invoicePayments as $payment): ?>
              <tr>
                <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
                <td><?= htmlspecialchars(accounting_format_money($payment['PaymentAmount'])) ?></td>
                <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
                <td><span class="status-badge <?= po_payment_status_class((string) ($payment['PaymentStatus'] ?? '')) ?>"><?= htmlspecialchars(po_payment_format_status($payment['PaymentStatus'] ?? null)) ?></span></td>
                <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
                <td><a class="btn-text" href="/accounting/invoice-payments/edit.php?id=<?= (int) $payment['PaymentID'] ?>">View</a></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php else: ?>
      <section class="detail-card">
        <h2>Payments</h2>
        <p class="page-lead">This invoice is linked to a purchase order. Record payments against the PO in PO Payments.</p>
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
