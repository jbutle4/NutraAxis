<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';

inventory_transfers_require_read();

$transferId = (int) ($_GET['id'] ?? 0);
$transfer = $transferId > 0 ? inventory_transfers_get($transferId) : null;
if ($transfer === null) {
    header('Location: /inventory-transfers/', true, 302);
    exit;
}

$activeSlug = 'inventory-transfers';
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$userId = auth_user()['UserID'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inventory_transfers_can_update()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'ship') {
        $result = inventory_transfers_ship($transferId, $userId !== null ? (int) $userId : null);
        header(
            'Location: /inventory-transfers/view.php?id=' . $transferId . '&'
                . http_build_query($result['ok']
                    ? ['notice' => 'shipped']
                    : ['error' => $result['error'] ?? 'Ship failed.']),
            true,
            302
        );
        exit;
    }
    if ($action === 'receive') {
        $result = inventory_transfers_receive($transferId, $userId !== null ? (int) $userId : null);
        header(
            'Location: /inventory-transfers/view.php?id=' . $transferId . '&'
                . http_build_query($result['ok']
                    ? ['notice' => 'received']
                    : ['error' => $result['error'] ?? 'Receive failed.']),
            true,
            302
        );
        exit;
    }
}

$transfer = inventory_transfers_get($transferId);
$pageTitle = 'Transfer #' . $transferId . ' | Inventory Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/inventory-transfers/',
          'back_label' => 'Back to Transfers',
          'category'   => 'Inventory',
          'title'      => 'Transfer #' . $transferId,
          'lead'       => htmlspecialchars((string) $transfer['SKUCode']) . ' · '
              . htmlspecialchars((string) $transfer['FromFacilityCode']) . ' → '
              . htmlspecialchars((string) $transfer['ToFacilityCode']),
          'lead_html'  => true,
      ]);
      ob_start();
      if (inventory_transfers_can_update() && ($transfer['TransferStatus'] ?? '') === 'Pending'): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Ship this transfer and update IMS quantities?');">
        <input type="hidden" name="action" value="ship" />
        <button type="submit" class="btn-primary">Ship transfer</button>
      </form>
      <?php elseif (inventory_transfers_can_update() && ($transfer['TransferStatus'] ?? '') === 'InTransit'): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Receive this transfer into the destination facility?');">
        <input type="hidden" name="action" value="receive" />
        <button type="submit" class="btn-primary">Receive transfer</button>
      </form>
      <?php endif;
      render_list_page_toolbar(trim(ob_get_clean()) ?: null);
      ?>

      <?php if ($notice === 'created' || $notice === 'shipped' || $notice === 'received'): ?>
      <div class="admin-notice is-success" role="status">Transfer updated.</div>
      <?php endif; ?>
      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <dl class="detail-grid">
        <div><dt>Status</dt><dd><?= htmlspecialchars((string) $transfer['TransferStatus']) ?></dd></div>
        <div><dt>SKU</dt><dd><?= htmlspecialchars((string) $transfer['SKUCode']) ?></dd></div>
        <div><dt>From</dt><dd><?= htmlspecialchars((string) $transfer['FromFacilityCode']) ?> (<?= htmlspecialchars((string) $transfer['FromStatusBucket']) ?>)</dd></div>
        <div><dt>To</dt><dd><?= htmlspecialchars((string) $transfer['ToFacilityCode']) ?> (<?= htmlspecialchars((string) $transfer['ToStatusBucket']) ?>)</dd></div>
        <div><dt>Requested</dt><dd><?= htmlspecialchars(inventory_ledger_format_quantity($transfer['QtyRequested'] ?? null)) ?></dd></div>
        <div><dt>Shipped</dt><dd><?= htmlspecialchars(inventory_ledger_format_quantity($transfer['QtyShipped'] ?? null)) ?></dd></div>
        <div><dt>Received</dt><dd><?= htmlspecialchars(inventory_ledger_format_quantity($transfer['QtyReceived'] ?? null)) ?></dd></div>
        <div><dt>Notes</dt><dd><?= htmlspecialchars((string) ($transfer['Notes'] ?? '—')) ?></dd></div>
      </dl>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
