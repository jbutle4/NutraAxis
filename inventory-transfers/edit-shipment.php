<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';

inventory_transfers_require_update();
inventory_ims_bind_page_environments();

$shipmentId = (int) ($_GET['shipment_id'] ?? $_POST['shipment_id'] ?? 0);
$shipment = $shipmentId > 0 ? inventory_transfers_shipment_get($shipmentId) : null;
if ($shipment === null) {
    header('Location: ' . inventory_ims_page_path('/inventory-transfers/'), true, 302);
    exit;
}

$lines = inventory_transfers_shipment_lines($shipmentId);
$leadLine = $lines[0] ?? null;
$viewPath = inventory_ims_page_path('/inventory-transfers/shipment.php?shipment_id=' . $shipmentId);
$editPath = inventory_ims_page_path('/inventory-transfers/edit-shipment.php?shipment_id=' . $shipmentId);
$error = null;
$notice = $_GET['notice'] ?? null;
$userId = auth_user()['UserID'] ?? null;

$canEdit = in_array((string) ($shipment['TransferStatus'] ?? ''), ['Pending', 'InTransit'], true);
$isPharmacyReplen = $leadLine !== null
    && inventory_transfers_jazz_is_pharmacy_replenishment($leadLine);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action = (string) ($_POST['action'] ?? '')) {
    if ($action === 'save' && $canEdit) {
        $result = inventory_transfers_shipment_update($shipmentId, [
            'notes'             => $_POST['notes'] ?? '',
            'jazz_ship_by_date' => $_POST['jazz_ship_by_date'] ?? '',
            'transfer_status'   => $_POST['transfer_status'] ?? null,
        ], $userId !== null ? (int) $userId : null);
        if ($result['ok']) {
            header('Location: ' . $viewPath . '&notice=updated', true, 302);
            exit;
        }
        $error = $result['error'] ?? 'Unable to save shipment.';
    }
}

$shipment = inventory_transfers_shipment_get($shipmentId) ?? $shipment;
$shipByRaw = $shipment['JazzShipByDate'] ?? ($leadLine['JazzShipByDate'] ?? null);
$shipByValue = inventory_transfers_normalize_ship_by_date($shipByRaw) ?? '';

$pageTitle = 'Edit shipment #' . $shipmentId . ' | Inventory Management';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $viewPath,
          'back_label' => 'Back to shipment',
          'category'   => 'Inventory',
          'title'      => 'Edit shipment #' . $shipmentId,
          'lead'       => htmlspecialchars((string) ($shipment['FromFacilityCode'] ?? ''))
              . ' → ' . htmlspecialchars((string) ($shipment['ToFacilityCode'] ?? ''))
              . ' · ' . count($lines) . ' line items',
          'lead_html'  => true,
      ]); ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!$canEdit): ?>
      <div class="admin-notice is-warning" role="status">This shipment can no longer be edited.</div>
      <?php else: ?>
      <form method="post" class="admin-form" action="<?= htmlspecialchars($editPath) ?>">
        <input type="hidden" name="shipment_id" value="<?= $shipmentId ?>" />
        <input type="hidden" name="action" value="save" />
        <div class="form-grid">
          <?php if ($isPharmacyReplen): ?>
          <div class="form-group">
            <label for="jazz_ship_by_date">Jazz ship-by date</label>
            <input class="form-input" type="date" id="jazz_ship_by_date" name="jazz_ship_by_date" required value="<?= htmlspecialchars($shipByValue) ?>" />
            <p class="form-hint">Applies to all <?= count($lines) ?> lines in this shipment (one Jazz order).</p>
          </div>
          <?php endif; ?>
          <div class="form-group">
            <label for="transfer_status">Status</label>
            <select class="form-input" id="transfer_status" name="transfer_status">
              <?php
              $cur = (string) ($shipment['TransferStatus'] ?? 'Pending');
              foreach (['Pending' => 'Pending', 'Cancelled' => 'Cancelled (Pending only)'] as $val => $label):
                  if ($val === 'Cancelled' && $cur !== 'Pending') {
                      continue;
                  }
                  ?>
              <option value="<?= htmlspecialchars($val) ?>"<?= $cur === $val ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($shipment['Notes'] ?? '')) ?></textarea>
          </div>
        </div>
        <?php
        render_form_actions(capture_form_actions(static function () use ($viewPath): void {
            ?>
            <button type="submit" class="btn-primary">Save shipment</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($viewPath) ?>">Cancel</a>
            <?php
        }));
        ?>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>