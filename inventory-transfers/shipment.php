<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-rework.php';

inventory_transfers_require_read();
inventory_ims_bind_page_environments();

$shipmentId = (int) ($_GET['shipment_id'] ?? 0);
$shipment = $shipmentId > 0 ? inventory_transfers_shipment_get($shipmentId) : null;
if ($shipment === null) {
    header('Location: ' . inventory_ims_page_path('/inventory-transfers/'), true, 302);
    exit;
}

$lines = inventory_transfers_shipment_lines($shipmentId);
$leadLineId = inventory_transfers_shipment_lead_line_id($shipmentId) ?? 0;
$leadLine = $leadLineId > 0 ? inventory_transfers_get($leadLineId) : null;

$activeSlug = $activeSlug ?? 'inventory-transfers';
$viewPath = inventory_ims_page_path('/inventory-transfers/shipment.php?shipment_id=' . $shipmentId);
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$userId = auth_user()['UserID'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inventory_transfers_can_update()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'send_jazz' && $leadLineId > 0) {
        $result = inventory_transfers_send_to_jazz($leadLineId, $userId !== null ? (int) $userId : null);
        $query = [];
        if (!empty($result['ok'])) {
            $query['notice'] = !empty($result['skipped']) && ($result['reason'] ?? '') === 'already_sent'
                ? 'jazz_already_sent'
                : (!empty($result['skipped']) ? 'jazz_skipped' : 'jazz_sent');
            if (!empty($result['warning'])) {
                $query['jazz_warning'] = (string) $result['warning'];
            }
        } else {
            $query['error'] = $result['error'] ?? 'Send to Jazz failed.';
        }
        header('Location: ' . $viewPath . '&' . http_build_query($query), true, 302);
        exit;
    }
    if ($action === 'ship') {
        $result = inventory_transfers_ship_shipment($shipmentId, $userId !== null ? (int) $userId : null);
        header(
            'Location: ' . $viewPath . '&'
                . http_build_query($result['ok'] ? ['notice' => 'shipped'] : ['error' => $result['error'] ?? 'Ship failed.']),
            true,
            302
        );
        exit;
    }
    if ($action === 'receive') {
        $result = inventory_transfers_receive_shipment($shipmentId, $userId !== null ? (int) $userId : null);
        header(
            'Location: ' . $viewPath . '&'
                . http_build_query($result['ok'] ? ['notice' => 'received'] : ['error' => $result['error'] ?? 'Receive failed.']),
            true,
            302
        );
        exit;
    }
}

$shipment = inventory_transfers_shipment_get($shipmentId) ?? $shipment;
$lines = inventory_transfers_shipment_lines($shipmentId);
$leadLine = $leadLineId > 0 ? inventory_transfers_get($leadLineId) : null;

$jazzContext = $leadLine ?? array_merge($shipment, [
    'FromFacilityCode' => $shipment['FromFacilityCode'] ?? '',
    'ToFacilityCode'   => $shipment['ToFacilityCode'] ?? '',
    'JazzShipByDate'   => $shipment['JazzShipByDate'] ?? null,
    'JazzSendStatus'   => $shipment['JazzSendStatus'] ?? null,
]);
inventory_transfers_jazz_sync_batch_if_sent($jazzContext);
if ($leadLineId > 0) {
    $leadLine = inventory_transfers_get($leadLineId) ?? $leadLine;
    $jazzContext = $leadLine;
}
$shipment = inventory_transfers_shipment_get($shipmentId) ?? $shipment;

$jazzSendRequired = inventory_transfers_jazz_send_required($jazzContext);
$jazzSendSatisfied = inventory_transfers_jazz_send_satisfied($jazzContext);
$jazzBatchLineCount = count($lines);
$jazzSendStatus = trim((string) ($shipment['JazzSendStatus'] ?? ($leadLine['JazzSendStatus'] ?? '')));
$jazzOrderLabel = $leadLine !== null
    ? inventory_transfers_jazz_shipment_number_for_transfer($leadLine)
    : (string) ($shipment['JazzShipmentNumber'] ?? ('SHP-' . $shipmentId));

$pageTitle = 'Transfer shipment #' . $shipmentId . ' | Inventory Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => inventory_ims_page_path('/inventory-transfers/'),
          'back_label' => 'Back to Transfers',
          'category'   => 'Inventory',
          'title'      => 'Transfer #' . $shipmentId,
          'lead'       => htmlspecialchars((string) ($shipment['FromFacilityCode'] ?? ''))
              . ' → ' . htmlspecialchars((string) ($shipment['ToFacilityCode'] ?? ''))
              . ' · ' . (int) $jazzBatchLineCount . ' line item' . ($jazzBatchLineCount === 1 ? '' : 's'),
          'lead_html'  => true,
      ]);
      ob_start();
      if (
          inventory_transfers_can_update()
          && ($shipment['TransferStatus'] ?? '') === 'Pending'
          && $jazzSendRequired
          && !$jazzSendSatisfied
          && $leadLineId > 0
      ): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Send this shipment (<?= (int) $jazzBatchLineCount ?> lines) to Jazz as one order?');">
        <input type="hidden" name="action" value="send_jazz" />
        <button type="submit" class="btn-primary">Send to Jazz (<?= (int) $jazzBatchLineCount ?> lines)</button>
      </form>
      <?php endif;
      if (
          inventory_transfers_can_update()
          && ($shipment['TransferStatus'] ?? '') === 'Pending'
          && (!$jazzSendRequired || $jazzSendSatisfied)
      ): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Ship all lines and update IMS?');">
        <input type="hidden" name="action" value="ship" />
        <button type="submit" class="btn-primary">Ship shipment</button>
      </form>
      <?php elseif (inventory_transfers_can_update() && ($shipment['TransferStatus'] ?? '') === 'InTransit'): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('Receive all in-transit lines?');">
        <input type="hidden" name="action" value="receive" />
        <button type="submit" class="btn-primary">Receive shipment</button>
      </form>
      <?php endif;
      render_list_page_toolbar(trim(ob_get_clean()) ?: null);
      ?>

      <?php if ($notice === 'batch_created'): ?>
      <div class="admin-notice is-success" role="status">Shipment created. Send to Jazz posts one order for all lines below.</div>
      <?php elseif ($notice === 'shipped' || $notice === 'received'): ?>
      <div class="admin-notice is-success" role="status">Shipment updated.</div>
      <?php elseif ($notice === 'jazz_sent'): ?>
      <div class="admin-notice is-success" role="status">Shipment sent to Jazz OMS (one order).</div>
      <?php elseif ($notice === 'jazz_already_sent'): ?>
      <div class="admin-notice is-success" role="status">This shipment was already sent to Jazz.</div>
      <?php endif; ?>
      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <dl class="detail-grid detail-list-inline">
        <div><dt>Status</dt><dd><?= htmlspecialchars((string) ($shipment['TransferStatus'] ?? '')) ?></dd></div>
        <div><dt>From</dt><dd><?= htmlspecialchars((string) ($shipment['FromFacilityCode'] ?? '')) ?></dd></div>
        <div><dt>To</dt><dd><?= htmlspecialchars((string) ($shipment['ToFacilityCode'] ?? '')) ?></dd></div>
        <div><dt>Jazz order</dt><dd><?= htmlspecialchars($jazzOrderLabel) ?></dd></div>
        <div><dt>Jazz send</dt><dd><?= $jazzSendStatus !== '' ? htmlspecialchars($jazzSendStatus) : 'Pending' ?></dd></div>
        <div><dt>Jazz ship-by</dt><dd><?php
            $shipByDisplay = '—';
            if (!empty($shipment['JazzShipByDate'])) {
                try {
                    $shipByDisplay = (new DateTimeImmutable((string) $shipment['JazzShipByDate']))->format('Y-m-d');
                } catch (Throwable) {
                    $shipByDisplay = (string) $shipment['JazzShipByDate'];
                }
            }
            echo htmlspecialchars($shipByDisplay);
        ?></dd></div>
        <div><dt>Notes</dt><dd><?= htmlspecialchars((string) ($shipment['Notes'] ?? '—')) ?></dd></div>
      </dl>

      <div class="po-lines-section">
        <h2 class="form-section-title">Line items</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Line status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lines as $line): ?>
              <tr>
                <td><?= (int) ($line['LineNumber'] ?? $line['TransferID']) ?></td>
                <td><?= htmlspecialchars((string) $line['SKUCode']) ?></td>
                <td><?= htmlspecialchars(inventory_ledger_format_quantity($line['QtyRequested'] ?? null)) ?></td>
                <td><?= htmlspecialchars((string) ($line['TransferStatus'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>