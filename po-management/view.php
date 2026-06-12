<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';
require dirname(__DIR__) . '/includes/po-approval.php';
require dirname(__DIR__) . '/includes/po-production.php';
require dirname(__DIR__) . '/includes/po-payment.php';
require dirname(__DIR__) . '/includes/po-receiving.php';

po_require_read();

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
$canApprove = po_can_read_approval_queue();
$poPayments = po_payment_list_for_po($poId);
$poReceipts = por_list(['po_id' => $poId]);
$poPaymentTotal = po_payment_total_for_po($poId);
$paymentNotice = $_GET['payment_notice'] ?? null;
$paymentError = $_GET['payment_error'] ?? null;
$notesError = isset($_GET['notes_error']) ? (string) $_GET['notes_error'] : null;
$canAddNotesAndAttachments = po_can_add_notes_and_attachments($order);

$pageTitle = $order['PONumber'] . ' | PO Management';
$pageDescription = 'View purchase order details.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Purchase Orders
      </a>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Procurement</div>
          <h1><?= htmlspecialchars($order['PONumber']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= po_status_class($order['POStatus']) ?>"><?= htmlspecialchars($order['POStatus']) ?></span>
            · <?= htmlspecialchars($order['SupplierName']) ?>
          </p>
        </div>
        <div class="admin-actions">
          <a class="btn-secondary" href="/po-management/print.php?id=<?= $poId ?>" target="_blank" rel="noopener">Printable View</a>
          <?php if ($canApprove && $order['POStatus'] === PO_STATUS_SUBMITTED): ?>
          <a class="btn-primary" href="/po-management/approve.php?id=<?= $poId ?>">Review for Approval</a>
          <?php endif; ?>
          <?php if (po_can_edit_order($order)): ?>
          <a class="btn-secondary" href="/po-management/edit.php?id=<?= $poId ?>">Edit</a>
          <?php if (in_array($order['POStatus'], PO_EDITABLE_STATUSES, true)): ?>
          <form method="post" action="/po-management/status.php" class="inline-form">
            <input type="hidden" name="po_id" value="<?= $poId ?>" />
            <input type="hidden" name="action" value="submit" />
            <button type="submit" class="btn-primary">Submit for Approval</button>
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
          <?php if ($canUpdate && $order['POStatus'] === PO_STATUS_APPROVED): ?>
          <form method="post" action="/po-management/status.php" class="inline-form">
            <input type="hidden" name="po_id" value="<?= $poId ?>" />
            <input type="hidden" name="action" value="accounting" />
            <button type="submit" class="btn-primary">Submit to Accounting</button>
          </form>
          <?php endif; ?>
          <?php if ($canUpdate && $order['POStatus'] === PO_STATUS_ACCOUNTING): ?>
          <form method="post" action="/po-management/status.php" class="inline-form">
            <input type="hidden" name="po_id" value="<?= $poId ?>" />
            <input type="hidden" name="action" value="paid" />
            <button type="submit" class="btn-primary">Mark as Paid</button>
          </form>
          <?php endif; ?>
          <?php if (por_can_create()): ?>
          <a class="btn-secondary" href="/po-receiving/new.php?po_id=<?= $poId ?>">New PO Receipt</a>
          <?php endif; ?>
        </div>
      </div>

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
      <?php elseif ($notice === 'accounting'): ?>
      <div class="admin-notice is-success" role="status">Purchase order submitted to accounting for payment.</div>
      <?php elseif ($notice === 'paid'): ?>
      <div class="admin-notice is-success" role="status">Purchase order marked as paid.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php elseif ($notice === 'notes_updated'): ?>
      <div class="admin-notice is-success" role="status">Notes saved successfully.</div>
      <?php elseif ($notice === 'production_updated'): ?>
      <div class="admin-notice is-success" role="status">Production status updated successfully.</div>
      <?php endif; ?>

      <?php if ($canUpdate && $order['POStatus'] === PO_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">
        This purchase order is in the approval queue. Approvers can review it under <a href="/po-management/approvals.php">Approvals</a>.
        <?php if ($approverEmails !== []): ?>
        Approval emails are sent to: <strong><?= htmlspecialchars(implode(', ', $approverEmails)) ?></strong> (subscribed to PO Approval Request alerts).
        <?php else: ?>
        <strong>No approver email addresses are configured.</strong> Subscribe users to PO Approval Request alerts in Site Admin → Users.
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
