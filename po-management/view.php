<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';
require dirname(__DIR__) . '/includes/po-approval.php';
require dirname(__DIR__) . '/includes/po-production.php';
require dirname(__DIR__) . '/includes/po-payment.php';
require dirname(__DIR__) . '/includes/po-receiving.php';
require dirname(__DIR__) . '/includes/supplier-invoice.php';
require dirname(__DIR__) . '/includes/po-qbo.php';

po_require_read();

$pageContainerClass = 'page-inner--wide';

$poId = (int) ($_GET['id'] ?? 0);
$order = po_get_order($poId);

if ($order === null) {
    http_response_code(404);
    $pageTitle = 'PO Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Purchase order not found</h1><div class="module-actions"><a class="btn-secondary" href="/po-management/">Back to Purchase Orders</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$activeSlug = 'po-management';
$activePoSection = 'list';
$lines = po_get_lines($poId);
$productionByLine = po_get_production_status_map($poId);
$canEditProduction = po_can_edit_production_status($order);
$attachments = po_list_attachments($poId);
$approvalLog = po_list_approval_log($poId);
$notice = $_GET['notice'] ?? null;
$warning = isset($_GET['warning']) ? (string) $_GET['warning'] : null;
$mailMessage = isset($_GET['mail_message']) ? (string) $_GET['mail_message'] : null;
$mailWarning = !empty($_GET['mail_warning']);
$approverEmails = $order['POStatus'] === PO_STATUS_SUBMITTED
    ? array_keys(po_recipient_emails_for_approvers())
    : [];
$canUpdate = po_can_update();
$canDelete = po_can_delete();
$canApprove = po_can_read_approval_queue();
$canSubmitForApproval = po_can_submit_for_approval($order);
$needsReapproval = po_requires_reapproval($order);
$poPayments = po_payment_list_for_po($poId);
$poReceipts = por_list(['po_id' => $poId]);
$poPaymentTotal = po_payment_total_for_po($poId);
$paymentNotice = $_GET['payment_notice'] ?? null;
$paymentError = $_GET['payment_error'] ?? null;
$notesError = isset($_GET['notes_error']) ? (string) $_GET['notes_error'] : null;
$canAddNotesAndAttachments = po_can_add_notes_and_attachments($order);
$poQboSyncBlockers = po_qbo_can_sync($order) ? po_qbo_sync_blockers($order, $lines) : [];
$canSyncQbo = po_qbo_can_sync($order);
require dirname(__DIR__) . '/includes/po-lifecycle.php';
$poLifecycleSteps = po_lifecycle_timeline($poId, $order);

$pageTitle = $order['PONumber'] . ' | PO Management';
$pageDescription = 'View purchase order details.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide page-no-sticky-top">
      <?php
      ob_start();
      ?>
          <a class="btn-secondary" href="/po-management/print.php?id=<?= $poId ?>" target="_blank" rel="noopener">Printable View</a>
          <?php if ($canApprove && $order['POStatus'] === PO_STATUS_SUBMITTED): ?>
          <a class="btn-primary" href="/po-management/approve.php?id=<?= $poId ?>">Review for Approval</a>
          <?php endif; ?>
          <?php if (po_can_edit_order($order)): ?>
          <a class="btn-secondary" href="/po-management/edit.php?id=<?= $poId ?>">Edit</a>
          <?php if ($canSubmitForApproval): ?>
          <form method="post" action="/po-management/status.php" class="inline-form">
            <input type="hidden" name="po_id" value="<?= $poId ?>" />
            <input type="hidden" name="action" value="submit" />
            <button type="submit" class="btn-primary"><?= $needsReapproval ? 'Resubmit for Approval' : 'Submit for Approval' ?></button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
          <?php if ($canUpdate && $order['POStatus'] === PO_STATUS_SUBMITTED): ?>
          <form method="post" action="/po-management/status.php" class="inline-form">
            <input type="hidden" name="po_id" value="<?= $poId ?>" />
            <input type="hidden" name="action" value="resubmit" />
            <button type="submit" class="btn-secondary">Resend Approval Notification</button>
          </form>
          <?php endif; ?>
          <?php if ($canSyncQbo): ?>
          <form method="post" action="/po-management/sync-qbo.php" class="inline-form" onsubmit="return confirm('Sync this purchase order to QuickBooks?');">
            <input type="hidden" name="po_id" value="<?= $poId ?>" />
            <button type="submit" class="btn-secondary"<?= $poQboSyncBlockers !== [] ? ' title="' . htmlspecialchars(implode(' ', $poQboSyncBlockers)) . '"' : '' ?>>Sync to QuickBooks</button>
          </form>
          <?php elseif ($canUpdate && !qbo_is_connected() && po_is_post_approval_edit($order)): ?>
          <a class="btn-secondary" href="/accounting/">Connect QuickBooks</a>
          <?php endif; ?>
          <?php if (por_can_create()): ?>
          <a class="btn-secondary" href="/po-receiving/new.php?po_id=<?= $poId ?>">New PO Receipt</a>
          <?php endif; ?>
          <?php if (supplier_invoice_can_create() && po_is_post_approval_edit($order)): ?>
          <a class="btn-secondary" href="/accounting/supplier-invoices/new.php?po_id=<?= $poId ?>">New Payment Request</a>
          <?php endif; ?>
          <?php if ($canDelete): ?>
          <?= table_action_delete_form(
              '/po-management/delete.php',
              ['po_id' => $poId],
              po_delete_confirm_message((string) $order['PONumber']),
              'Delete PO'
          ) ?>
          <?php endif; ?>
      <?php
      $listToolbar = trim(ob_get_clean());
      render_list_page_header([
          'back_href'  => '/po-management/',
          'back_label' => 'Back to Purchase Orders',
          'category'   => 'Procurement',
          'title'      => $order['PONumber'],
          'lead'       => '<span class="status-badge ' . po_view_status_class($order['POStatus']) . '">' . htmlspecialchars(po_view_status_label($order['POStatus'])) . '</span> · ' . htmlspecialchars($order['SupplierName']),
          'lead_html'  => true,
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php if ($notice === 'created' || $notice === 'imported'): ?>
      <div class="admin-notice is-success" role="status">Purchase order created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Purchase order updated successfully.</div>
      <?php elseif ($notice === 'submitted'): ?>
      <div class="admin-notice <?= $mailWarning ? 'is-error is-detail' : 'is-success' ?>" role="status">
        <?= htmlspecialchars($mailMessage ?? 'Purchase order submitted for approval. Approvers have been notified.') ?>
      </div>
      <?php elseif ($notice === 'resubmitted'): ?>
      <div class="admin-notice <?= $mailWarning ? 'is-error is-detail' : 'is-success' ?>" role="status">
        <?= htmlspecialchars($mailMessage ?? 'Approval notification resent to approvers.') ?>
      </div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php elseif ($notice === 'notes_updated'): ?>
      <div class="admin-notice is-success" role="status">Notes saved successfully.</div>
      <?php elseif ($notice === 'production_updated'): ?>
      <div class="admin-notice is-success" role="status">Production status updated successfully.</div>
      <?php elseif ($notice === 'qbo_synced'): ?>
      <div class="admin-notice is-success" role="status">Purchase order synced to QuickBooks successfully.</div>
      <?php endif; ?>

      <?php if ($warning !== null && $warning !== ''): ?>
      <div class="admin-notice is-warning" role="status"><?= htmlspecialchars($warning) ?></div>
      <?php endif; ?>
      <?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars(qbo_humanize_error((string) $_GET['error'])) ?></div>
      <?php endif; ?>

      <?php if ($poQboSyncBlockers !== []): ?>
      <div class="admin-notice is-warning" role="status">
        <strong>QuickBooks PO sync is not ready.</strong>
        <ul class="notice-list">
          <?php foreach ($poQboSyncBlockers as $blocker): ?>
          <li><?= htmlspecialchars($blocker) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php elseif ($canSyncQbo && !empty($order['QBO_POID'])): ?>
      <div class="admin-notice" role="status">Linked to QuickBooks purchase order <strong><?= htmlspecialchars((string) $order['QBO_POID']) ?></strong>.</div>
      <?php endif; ?>

      <?php if (!empty($_GET['delete_error'])): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $_GET['delete_error']) ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['reapproval']) && $_GET['reapproval'] === '1'): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        Total due changed after approval. This purchase order must be resubmitted for approval before it can be sent to QuickBooks.
      </div>
      <?php endif; ?>

      <?php if ($needsReapproval && $order['POStatus'] === PO_STATUS_APPROVED): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        Total due changed after approval. Use <strong>Resubmit for Approval</strong> before this PO can be sent to QuickBooks.
        <?php if (!empty($order['ApprovedTotalDue'])): ?>
        Approved total: <strong><?= htmlspecialchars(po_format_money((float) $order['ApprovedTotalDue'])) ?></strong>
        · Current total: <strong><?= htmlspecialchars(po_format_money((float) $order['TotalDue'])) ?></strong>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (in_array($order['POStatus'], [PO_STATUS_APPROVED, PO_STATUS_ACCOUNTING, PO_STATUS_PAID], true)): ?>
      <div class="admin-notice" role="status">
        PO approval is for the purchase order only. After approval, the PO is sent to QuickBooks.
        Payment approval requires a supplier invoice and is handled on <a href="/po-payments/">PO Payments</a>.
      </div>
      <?php endif; ?>

      <?php if ($canUpdate && $order['POStatus'] === PO_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">
        This purchase order is awaiting PO approver review under <a href="/approvals/?type=PO&status=pending">Approvals</a>.
        This step does not submit a payment request.
        <?php if ($approverEmails !== []): ?>
        Approval emails are sent to designated PO approvers: <strong><?= htmlspecialchars(implode(', ', $approverEmails)) ?></strong>.
        <?php else: ?>
        <strong>No PO approvers are configured.</strong> Assign a role with PO Approval Update access in Site Admin → Roles.
        <?php endif; ?>
        Use <strong>Resend Approval Notification</strong> if approvers did not receive the email.
      </div>
      <?php endif; ?>

      <?php if ($warning !== null && $warning !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        Purchase order was saved, but the import file could not be attached. <?= htmlspecialchars($warning) ?>
      </div>
      <?php endif; ?>

      <?php if ($notesError !== null && $notesError !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($notesError) ?></div>
      <?php endif; ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <?php require dirname(__DIR__) . '/includes/po-lifecycle-bar.php'; ?>

      <?php
      $showUploadForm = $canAddNotesAndAttachments;
      $canEditNotes = $canAddNotesAndAttachments;
      require dirname(__DIR__) . '/includes/po-detail.php';
      ?>

      <?php require dirname(__DIR__) . '/includes/po-payment-detail.php'; ?>

      <?php require dirname(__DIR__) . '/includes/po-receipts-detail.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
