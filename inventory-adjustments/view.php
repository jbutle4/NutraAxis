<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-adjustments.php';

inventory_adjustments_require_read();

$adjustmentId = (int) ($_GET['id'] ?? 0);
$adjustment = $adjustmentId > 0 ? inventory_adjustments_get($adjustmentId) : null;
if ($adjustment === null) {
    header('Location: /inventory-adjustments/', true, 302);
    exit;
}

$activeSlug = 'inventory-adjustments';
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$userId = auth_user()['UserID'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inventory_adjustments_can_update()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'approve') {
        $result = inventory_adjustments_approve($adjustmentId, $userId !== null ? (int) $userId : null);
        if ($result['ok'] && isset($result['qbo_ok']) && $result['qbo_ok'] === false) {
            header(
                'Location: /inventory-adjustments/view.php?id=' . $adjustmentId . '&'
                    . http_build_query([
                        'notice' => 'approved',
                        'error' => 'IMS posted, but QBO failed: ' . ($result['qbo_error'] ?? 'unknown error'),
                    ]),
                true,
                302
            );
            exit;
        }
        header(
            'Location: /inventory-adjustments/view.php?id=' . $adjustmentId . '&'
                . http_build_query($result['ok']
                    ? ['notice' => 'approved']
                    : ['error' => $result['error'] ?? 'Approve failed.']),
            true,
            302
        );
        exit;
    }
    if ($action === 'reject') {
        $result = inventory_adjustments_reject($adjustmentId, $userId !== null ? (int) $userId : null);
        header(
            'Location: /inventory-adjustments/view.php?id=' . $adjustmentId . '&'
                . http_build_query($result['ok']
                    ? ['notice' => 'rejected']
                    : ['error' => $result['error'] ?? 'Reject failed.']),
            true,
            302
        );
        exit;
    }
    if ($action === 'retry_qbo') {
        $result = inventory_adjustments_post_qbo($adjustmentId);
        header(
            'Location: /inventory-adjustments/view.php?id=' . $adjustmentId . '&'
                . http_build_query($result['ok']
                    ? ['notice' => 'approved']
                    : ['error' => $result['error'] ?? 'QBO retry failed.']),
            true,
            302
        );
        exit;
    }
}

$adjustment = inventory_adjustments_get($adjustmentId);
$docNumber = inventory_adjustments_doc_number($adjustmentId);
$qboStatus = qbo_inventory_sync_log_status($docNumber);
$pageTitle = 'Adjustment #' . $adjustmentId . ' | Inventory Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/inventory-adjustments/',
          'back_label' => 'Back to Adjustments',
          'category'   => 'Inventory',
          'title'      => 'Adjustment #' . $adjustmentId,
          'lead'       => htmlspecialchars((string) $adjustment['SKUCode']) . ' · '
              . htmlspecialchars((string) $adjustment['FacilityCode']) . ' · '
              . htmlspecialchars(inventory_ledger_format_quantity($adjustment['QtyAdjusted'] ?? null)),
          'lead_html'  => true,
      ]);
      ob_start();
      $status = (string) ($adjustment['AdjStatus'] ?? '');
      if (inventory_adjustments_can_update() && $status === 'Pending'): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Approve and post this adjustment to IMS and QuickBooks?');">
        <input type="hidden" name="action" value="approve" />
        <button type="submit" class="btn-primary">Approve &amp; post</button>
      </form>
      <form method="post" class="inline-form" onsubmit="return confirm('Reject this pending adjustment?');">
        <input type="hidden" name="action" value="reject" />
        <button type="submit" class="btn-secondary">Reject</button>
      </form>
      <?php elseif (inventory_adjustments_can_update() && $status === 'Approved' && $qboStatus !== 'Synced'): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Retry QuickBooks InventoryAdjustment for this approved adjustment?');">
        <input type="hidden" name="action" value="retry_qbo" />
        <button type="submit" class="btn-primary">Retry QBO post</button>
      </form>
      <?php endif;
      render_list_page_toolbar(trim(ob_get_clean()) ?: null);
      ?>

      <?php if ($notice === 'created' || $notice === 'approved' || $notice === 'rejected'): ?>
      <div class="admin-notice is-success" role="status">Adjustment updated.</div>
      <?php endif; ?>
      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <dl class="detail-grid">
        <div><dt>Status</dt><dd><?= htmlspecialchars($status) ?></dd></div>
        <div><dt>SKU</dt><dd><?= htmlspecialchars((string) $adjustment['SKUCode']) ?></dd></div>
        <div><dt>Facility</dt><dd><?= htmlspecialchars((string) $adjustment['FacilityCode']) ?></dd></div>
        <div><dt>Bucket</dt><dd><?= htmlspecialchars((string) $adjustment['StatusBucket']) ?></dd></div>
        <div><dt>Qty change</dt><dd><?= htmlspecialchars(inventory_ledger_format_quantity($adjustment['QtyAdjusted'] ?? null)) ?></dd></div>
        <div><dt>Qty before → after</dt><dd><?= htmlspecialchars(inventory_ledger_format_quantity($adjustment['QtyBefore'] ?? null)) ?> → <?= htmlspecialchars(inventory_ledger_format_quantity($adjustment['QtyAfter'] ?? null)) ?></dd></div>
        <div><dt>Reason</dt><dd><?= htmlspecialchars((string) ($adjustment['ReasonCode'] ?? '—')) ?> <?= htmlspecialchars((string) ($adjustment['ReasonDescription'] ?? '')) ?></dd></div>
        <div><dt>IMS transaction</dt><dd><?= $adjustment['TransactionID'] ? (int) $adjustment['TransactionID'] : '—' ?></dd></div>
        <div><dt>QBO DocNumber</dt><dd><?= htmlspecialchars($docNumber) ?></dd></div>
        <div><dt>QBO sync</dt><dd><?= htmlspecialchars($qboStatus ?? '—') ?></dd></div>
        <div><dt>Approved at</dt><dd><?= htmlspecialchars((string) ($adjustment['ApprovedAt'] ?? '—')) ?></dd></div>
        <div><dt>Notes</dt><dd><?= htmlspecialchars((string) ($adjustment['Notes'] ?? '—')) ?></dd></div>
      </dl>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
