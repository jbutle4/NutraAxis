<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
accounting_bind_qbo_environment();
require dirname(__DIR__, 2) . '/includes/admin.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments.php';
require dirname(__DIR__, 2) . '/includes/qbo-insert-approval.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

supplier_invoice_require_update();

$invoiceId = (int) ($_GET['id'] ?? 0);
$invoice = $invoiceId > 0 ? supplier_invoice_get($invoiceId) : null;

if ($invoice === null) {
    header('Location: ' . accounting_path('/accounting/supplier-invoices/'), true, 302);
    exit;
}

$activeSlug = $activeSlug ?? 'accounting';
$accountingSection = 'invoices';
$error = null;
$isLocked = supplier_invoice_is_locked($invoice);
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
$lines = supplier_invoice_get_lines($invoiceId);
$form = supplier_invoice_to_form($invoice, $lines);
$suppliers = supplier_invoice_list_suppliers();
$poOptions = supplier_invoice_po_options((int) $invoice['SupplierID']);
$attachments = supplier_invoice_list_attachments($invoiceId);
$notice = $_GET['notice'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $form = array_merge($form, supplier_invoice_from_input($_POST), ['lines' => $_POST['lines'] ?? []]);
    $result = supplier_invoice_save($_POST, $invoiceId);

    if ($result['ok']) {
        header('Location: ' . accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit ' . supplier_invoice_reference($invoice) . ' | Accounting';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      render_list_page_header([
          'back_href'  => accounting_path('/accounting/supplier-invoices/view.php') . '?id=' . $invoiceId,
          'back_label' => 'Back to Invoice',
          'category'   => 'Finance',
          'title'      => 'Edit Invoice',
          'lead'       => supplier_invoice_reference($invoice) . ' · ' . $invoice['SupplierName'],
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($isLocked): ?>
      <div class="admin-notice is-detail" role="status">This invoice is <?= htmlspecialchars(strtolower((string) $invoice['SyncStatus'])) ?> and cannot be edited.</div>
      <?php elseif (supplier_invoice_posted_is_reopenable($invoice)): ?>
      <div class="admin-notice" role="status">This invoice was payment-approved<?= payment_approval_is_stub_mode() ? ' in test mode' : '' ?> and can be edited before resubmitting for payment approval from the invoice view page. Use Submit for QBO Insert there if you only need accounting posting recovery.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php elseif (!empty($_GET['error'])): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $_GET['error']) ?></div>
      <?php endif; ?>

      <?php if (($invoice['SyncStatus'] ?? '') === 'Sent Back for Comment' && $latestSendBack !== null): ?>
      <div class="admin-notice is-detail" role="status">
        <strong>Approver comments</strong> from <?= htmlspecialchars($latestSendBack['ApproverName']) ?>
        on <?= htmlspecialchars(admin_format_datetime($latestSendBack['LogDate'])) ?>:
        <p style="margin: 8px 0 0;"><?= nl2br(htmlspecialchars(supplier_invoice_format_log_comments($latestSendBack['ApproverComments'] ?? null))) ?></p>
      </div>
      <?php elseif (($invoice['SyncStatus'] ?? '') === 'Sent Back for Comment'): ?>
      <div class="admin-notice" role="status">This invoice was sent back for comment. Update the invoice and submit for approval again from the invoice view page.</div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = accounting_path('/accounting/supplier-invoices/edit.php') . '?id=' . $invoiceId;
        require dirname(__DIR__, 2) . '/includes/supplier-invoice-form.php';
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/supplier-invoice-approval-history.php'; ?>

      <?php if (supplier_invoice_may_delete($invoice)): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/delete.php')) ?>" style="margin-top: 24px;" onsubmit="return confirm('Delete this supplier invoice?');">
        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>" />
        <button type="submit" class="btn-secondary">Delete Invoice</button>
      </form>
      <?php endif; ?>

      <?php
        $showUploadForm = supplier_invoice_can_update();
        $uploadReturnPath = accounting_path('/accounting/supplier-invoices/edit.php') . '?id=' . $invoiceId;
        require dirname(__DIR__, 2) . '/includes/supplier-invoice-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
