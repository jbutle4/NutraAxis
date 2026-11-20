<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';
require dirname(__DIR__) . '/includes/inventory-transfers-jazz.php';

inventory_transfers_require_update();
inventory_ims_bind_page_environments();

$transferId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$transfer = $transferId > 0 ? inventory_transfers_get($transferId) : null;
if ($transfer === null) {
    header('Location: ' . inventory_ims_page_path('/inventory-transfers/'), true, 302);
    exit;
}

$activeSlug = $activeSlug ?? 'inventory-transfers';
$viewPath = inventory_ims_page_path('/inventory-transfers/view.php?id=' . $transferId);
$editPath = inventory_ims_page_path('/inventory-transfers/edit.php?id=' . $transferId);
$userId = auth_user()['UserID'] ?? null;
$error = null;
$notice = $_GET['notice'] ?? null;

$jazzSendRequired = inventory_transfers_jazz_send_required($transfer);
$jazzSendSatisfied = inventory_transfers_jazz_send_satisfied($transfer);
$isPharmacyReplen = inventory_transfers_jazz_is_pharmacy_replenishment($transfer);
$jazzBatchPeers = $isPharmacyReplen ? inventory_transfers_jazz_batch_peers($transfer) : [$transfer];
$jazzBatchLineCount = count($jazzBatchPeers);
$canEdit = in_array((string) ($transfer['TransferStatus'] ?? ''), ['Pending', 'InTransit'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'send_jazz') {
        $result = inventory_transfers_send_to_jazz($transferId, $userId !== null ? (int) $userId : null);
        $query = [];
        if (!empty($result['ok'])) {
            $query['notice'] = !empty($result['skipped']) ? 'jazz_skipped' : 'jazz_sent';
            if (!empty($result['warning'])) {
                $query['jazz_warning'] = (string) $result['warning'];
            }
            header('Location: ' . $viewPath . '&' . http_build_query($query), true, 302);
        } else {
            $query['error'] = $result['error'] ?? 'Send to Jazz failed.';
            header('Location: ' . $viewPath . '&' . http_build_query($query), true, 302);
        }
        exit;
    }

    if ($action === 'save' && $canEdit) {
        $result = inventory_transfers_update($transferId, [
            'qty_requested'       => $_POST['qty_requested'] ?? null,
            'notes'               => $_POST['notes'] ?? '',
            'jazz_ship_by_date'   => $_POST['jazz_ship_by_date'] ?? '',
            'transfer_status'     => $_POST['transfer_status'] ?? null,
        ], $userId !== null ? (int) $userId : null);
        if ($result['ok']) {
            header('Location: ' . $editPath . '&notice=updated', true, 302);
            exit;
        }
        $error = $result['error'] ?? 'Unable to save transfer.';
    }
}

$transfer = inventory_transfers_get($transferId);
$shipByValue = '';
if (!empty($transfer['JazzShipByDate'])) {
    try {
        $shipByValue = (new DateTimeImmutable((string) $transfer['JazzShipByDate']))->format('Y-m-d');
    } catch (Throwable) {
        $shipByValue = '';
    }
}

$pageTitle = 'Edit Transfer #' . $transferId . ' | Inventory Management';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => inventory_ims_page_path('/inventory-transfers/'),
          'back_label' => 'Back to Transfers',
          'category'   => 'Inventory',
          'title'      => 'Edit transfer #' . $transferId,
          'lead'       => htmlspecialchars((string) $transfer['SKUCode']) . ' · '
              . htmlspecialchars((string) $transfer['FromFacilityCode']) . ' → '
              . htmlspecialchars((string) $transfer['ToFacilityCode']),
          'lead_html'  => true,
      ]); ?>

      <?php if ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Transfer saved.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!$canEdit): ?>
      <div class="admin-notice is-warning" role="status">This transfer can no longer be edited (status is <?= htmlspecialchars((string) $transfer['TransferStatus']) ?>).</div>
      <p><a class="btn-secondary" href="<?= htmlspecialchars($viewPath) ?>">View transfer</a></p>
      <?php else: ?>
      <form method="post" class="admin-form" action="<?= htmlspecialchars($editPath) ?>">
        <input type="hidden" name="action" value="save" />
        <input type="hidden" name="id" value="<?= $transferId ?>" />
        <div class="form-grid">
          <div class="form-group">
            <label for="transfer_status">Status</label>
            <select class="form-input" id="transfer_status" name="transfer_status">
              <?php
              $current = (string) ($transfer['TransferStatus'] ?? 'Pending');
              foreach (['Pending', 'InTransit', 'Cancelled'] as $statusOption):
                  if ($statusOption === 'Cancelled' && $current !== 'Pending') {
                      continue;
                  }
                  if ($statusOption === 'InTransit' && $current === 'Pending') {
                      continue;
                  }
                  if ($statusOption === 'Pending' && $current === 'InTransit') {
                      continue;
                  }
              ?>
              <option value="<?= htmlspecialchars($statusOption) ?>"<?= $current === $statusOption ? ' selected' : '' ?>><?= htmlspecialchars($statusOption) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint">Only Pending → Cancelled is allowed from this screen. Use Ship / Receive on the view page for workflow steps.</p>
            <?php if ($isPharmacyReplen): ?>
            <p class="form-hint">Jazz batch: <strong><?= htmlspecialchars(inventory_transfers_jazz_batch_order_number($transfer)) ?></strong> includes <?= (int) $jazzBatchLineCount ?> pending line<?= $jazzBatchLineCount === 1 ? '' : 's' ?> (same pharmacy + ship-by date).</p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="qty_requested">Quantity requested</label>
            <input class="form-input" id="qty_requested" name="qty_requested" type="number" min="0.0001" step="0.0001" required
              value="<?= htmlspecialchars(inventory_ledger_format_quantity($transfer['QtyRequested'] ?? null)) ?>"
              <?= $current !== 'Pending' ? 'readonly' : '' ?> />
          </div>
          <?php if (inventory_transfer_has_ship_by_column() && $jazzSendRequired): ?>
          <div class="form-group">
            <label for="jazz_ship_by_date">Jazz ship-by date</label>
            <input class="form-input" id="jazz_ship_by_date" name="jazz_ship_by_date" type="date" value="<?= htmlspecialchars($shipByValue) ?>" />
          </div>
          <?php endif; ?>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="4"><?= htmlspecialchars((string) ($transfer['Notes'] ?? '')) ?></textarea>
          </div>
        </div>
        <?php
        render_form_actions(capture_form_actions(static function () use ($viewPath): void {
            echo '<button type="submit" class="btn-primary">Save changes</button>';
            echo '<a class="btn-secondary" href="' . htmlspecialchars($viewPath) . '">Cancel</a>';
        }));
        ?>
      </form>

      <?php if (
          $jazzSendRequired
          && ($transfer['TransferStatus'] ?? '') === 'Pending'
          && !$jazzSendSatisfied
      ): ?>
      <section class="admin-section">
        <h2 class="admin-section-title">Jazz OMS</h2>
        <p class="page-lead">
          <?php if ($isPharmacyReplen): ?>
          Posts a <strong>production</strong> Jazz <code>order/import</code> (<?= htmlspecialchars(inventory_transfers_jazz_order_source_code()) ?>,
          allocation <?= htmlspecialchars(inventory_transfers_jazz_allocation_facility_code($transfer)) ?>,
          ship code <?= htmlspecialchars(inventory_transfers_jazz_replenishment_ship_code()) ?>).
          <?php else: ?>
          Sends transfer data to Jazz (order and/or ASN per environment).
          <?php endif; ?>
        </p>
        <form method="post" class="inline-form" onsubmit="return confirm('Send batch order <?= htmlspecialchars(inventory_transfers_jazz_batch_order_number($transfer)) ?> with <?= (int) $jazzBatchLineCount ?> line(s) to Jazz?');">
          <input type="hidden" name="action" value="send_jazz" />
          <input type="hidden" name="id" value="<?= $transferId ?>" />
          <button type="submit" class="btn-primary">Send to Jazz (<?= (int) $jazzBatchLineCount ?> line<?= $jazzBatchLineCount === 1 ? '' : 's' ?>)</button>
        </form>
      </section>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>