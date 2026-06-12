<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';
require dirname(__DIR__) . '/includes/po-approval.php';

po_require_approval_read();

$poId = (int) ($_GET['id'] ?? 0);
$order = po_get_order($poId);

if ($order === null) {
    http_response_code(404);
    $pageTitle = 'PO Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Purchase order not found</h1><div class="module-actions"><a class="btn-secondary" href="/po-management/approvals.php">Back to Approvals</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$activeSlug = 'po-management';
$activePoSection = 'approvals';
$lines = po_get_lines($poId);
$attachments = po_list_attachments($poId);
$approvalLog = po_list_approval_log($poId);
$canAct = po_can_take_approval_action() && $order['POStatus'] === PO_STATUS_SUBMITTED;
$error = $_GET['error'] ?? null;

$pageTitle = 'Review ' . $order['PONumber'] . ' | PO Approval';
$pageDescription = 'Review purchase order details and take approval action.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-management/approvals.php">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Approval Queue
      </a>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">PO Approval</div>
          <h1><?= htmlspecialchars($order['PONumber']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= po_status_class($order['POStatus']) ?>"><?= htmlspecialchars($order['POStatus']) ?></span>
            · <?= htmlspecialchars($order['SupplierName']) ?>
          </p>
        </div>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($canAct): ?>
      <div class="account-card approval-actions-card">
        <h2>Approver actions</h2>
        <p class="account-card-lead">Choose an action for this purchase order. PO users will be notified by email.</p>
        <form class="admin-form" method="post" action="/po-management/approval-action.php">
          <input type="hidden" name="po_id" value="<?= $poId ?>" />
          <div class="form-group">
            <label for="comments">Comments</label>
            <textarea class="form-input" id="comments" name="comments" rows="4" placeholder="Required when sending back with comments."></textarea>
          </div>
          <div class="module-actions approval-action-buttons">
            <?php foreach (PO_APPROVAL_ACTIONS as $key => $action): ?>
            <button
              class="<?= $key === 'approve' ? 'btn-primary' : ($key === 'cancel' ? 'btn-text btn-text-danger' : 'btn-secondary') ?>"
              type="submit"
              name="action"
              value="<?= htmlspecialchars($key) ?>"
              <?php if ($key === 'reject'): ?>onclick="return confirm('Reject this purchase order?');"<?php endif; ?>
              <?php if ($key === 'cancel'): ?>onclick="return confirm('Record that you viewed this PO without taking further action?');"<?php endif; ?>
            ><?= htmlspecialchars($action['label']) ?></button>
            <?php endforeach; ?>
          </div>
        </form>
      </div>
      <?php elseif ($order['POStatus'] !== PO_STATUS_SUBMITTED): ?>
      <div class="admin-notice" role="status">This purchase order is no longer awaiting approval.</div>
      <?php else: ?>
      <div class="admin-notice" role="status">You have read-only access to this approval review.</div>
      <?php endif; ?>

      <?php
      $showUploadForm = false;
      require dirname(__DIR__) . '/includes/po-detail.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
