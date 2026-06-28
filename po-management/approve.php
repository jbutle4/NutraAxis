<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';
require dirname(__DIR__) . '/includes/po-approval.php';

$poId = (int) ($_GET['id'] ?? 0);
$rawToken = trim($_GET['token'] ?? '');
$tokenContext = $rawToken !== '' ? po_approval_token_resolve($rawToken, $poId) : null;
$prefillAction = trim($_GET['action'] ?? '');
$isTokenAccess = $tokenContext !== null;

if ($rawToken !== '' && $tokenContext === null) {
    http_response_code(403);
    $pageTitle = 'Invalid Approval Link';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Approval link invalid or expired</h1><p class="page-lead">This link may have already been used or has expired. Sign in to review the purchase order from the approval queue.</p><div class="module-actions"><a class="btn-secondary" href="/login/">Sign in</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if (!$isTokenAccess) {
    po_require_approval_read();
}

$order = po_get_order($poId);

if ($order === null) {
    http_response_code(404);
    $pageTitle = 'PO Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Purchase order not found</h1><div class="module-actions"><a class="btn-secondary" href="/approvals/?type=PO&status=pending">Back to Approvals</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$activeSlug = 'po-management';
$activePoSection = 'approvals';
$lines = po_get_lines($poId);
$attachments = po_list_attachments($poId);
$approvalLog = po_list_approval_log($poId);
$error = $_GET['error'] ?? null;
$notice = $_GET['notice'] ?? null;
$canAct = $order['POStatus'] === PO_STATUS_SUBMITTED && (
    ($isTokenAccess && $tokenContext['can_act'])
    || (!$isTokenAccess && po_can_take_approval_action())
);
$approverLabel = $isTokenAccess
    ? (string) ($tokenContext['user']['UserName'] ?? 'Approver')
    : (string) (auth_user()['UserName'] ?? 'Approver');

$pageTitle = 'Review ' . $order['PONumber'] . ' | PO Approval';
$pageDescription = 'Review purchase order details and take approval action.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $approveLead = '<span class="status-badge ' . po_status_class($order['POStatus']) . '">' . htmlspecialchars($order['POStatus']) . '</span> · ' . htmlspecialchars($order['SupplierName']);
      if ($isTokenAccess) {
          $approveLead .= ' · Acting as ' . htmlspecialchars($approverLabel);
      }
      render_list_page_header([
          'back_href'  => '/approvals/?type=PO&status=pending',
          'back_label' => 'Back to Approvals',
          'category'   => 'PO Approval',
          'title'      => $order['PONumber'],
          'lead'       => $approveLead,
          'lead_html'  => true,
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php if ($notice === 'actioned'): ?>
      <div class="admin-notice is-success" role="status">Your approval action was recorded successfully.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($canAct): ?>
      <?php if ($prefillAction !== '' && isset(PO_APPROVAL_ACTIONS[$prefillAction])): ?>
      <div class="admin-notice" role="status">
        You opened the <strong><?= htmlspecialchars(PO_APPROVAL_ACTIONS[$prefillAction]['label']) ?></strong> link from email.
        Confirm your choice below or choose a different action.
      </div>
      <?php endif; ?>
      <div class="account-card approval-actions-card">
        <h2>Approver actions</h2>
        <p class="account-card-lead">Choose an action for this purchase order. PO users will be notified by email.</p>
        <form class="admin-form" method="post" action="/po-management/approval-action.php">
          <input type="hidden" name="po_id" value="<?= $poId ?>" />
          <?php if ($isTokenAccess): ?>
          <input type="hidden" name="approval_token" value="<?= htmlspecialchars($rawToken) ?>" />
          <?php endif; ?>
          <div class="form-group">
            <label for="comments">Comments</label>
            <textarea class="form-input" id="comments" name="comments" rows="4" placeholder="Required when sending back with comments."></textarea>
          </div>
          <div class="module-actions approval-action-buttons">
            <?php foreach (PO_APPROVAL_ACTIONS as $key => $action): ?>
            <button
              class="<?= $key === 'approve' ? 'btn-primary' : ($key === 'cancel' ? 'btn-text btn-text-danger' : 'btn-secondary') ?><?= $prefillAction === $key ? ' is-highlighted' : '' ?>"
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
