<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';

inventory_transfers_require_read();

$activeSlug = $activeSlug ?? 'inventory-transfers';
$statusFilter = trim($_GET['status'] ?? '');
$useShipments = inventory_transfers_use_shipment_model();
$rows = $useShipments
    ? inventory_transfers_list_shipments(['status' => $statusFilter])
    : inventory_transfers_list(['status' => $statusFilter]);
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$hubBack = app_module_hub_back_link($activeSlug);

$pageTitle = 'Facility Transfers | Inventory Management';
$pageDescription = 'Move inventory between Cart.com and spoke facilities.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => 'Facility Transfers',
          'lead'       => 'Hub-and-spoke from Cart.com; pharmacies can return to Cart or rebalance overstock to another pharmacy (Ocala, Dallas, Melbourne).',
          'permission' => permission_label(inventory_transfers_permission_value()),
      ]);
      if (inventory_transfers_can_update()) {
          render_list_page_toolbar('<a class="btn-primary" href="' . htmlspecialchars(inventory_ims_page_path('/inventory-transfers/new.php')) . '">New transfer</a>');
      }
      ?>

      <?php if ($notice === 'batch_created'): ?>
      <div class="admin-notice is-success" role="status"><?php
          $n = max(1, (int) ($_GET['n'] ?? 1));
          echo $n === 1
              ? 'Created 1 transfer shipment (Pending).'
              : 'Created 1 transfer shipment with ' . $n . ' lines (Pending).';
      ?></div>
      <?php elseif ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Transfer request created.</div>
      <?php elseif ($notice === 'shipped'): ?>
      <div class="admin-notice is-success" role="status">Transfer shipped and IMS quantities updated.</div>
      <?php elseif ($notice === 'received'): ?>
      <div class="admin-notice is-success" role="status">Transfer received into the destination facility.</div>
      <?php endif; ?>
      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-filter-bar" method="get" action="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/')) ?>">
        <div class="form-field">
          <label for="status">Status</label>
          <select class="form-input" id="status" name="status">
            <option value="">All</option>
            <?php foreach (['Pending', 'InTransit', 'Received', 'Cancelled'] as $status): ?>
            <option value="<?= $status ?>"<?= $statusFilter === $status ? ' selected' : '' ?>><?= $status ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-secondary">Filter</button>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th><?= $useShipments ? 'Shipment' : 'ID' ?></th>
              <?php if ($useShipments): ?>
              <th>Lines</th>
              <?php else: ?>
              <th>SKU</th>
              <?php endif; ?>
              <th>From</th>
              <th>To</th>
              <th>Qty</th>
              <th>Status</th>
              <th>Jazz</th>
              <th>Ship by</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="10">No transfers found.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row):
                $miniTransfer = [
                    'FromFacilityCode' => $row['FromFacilityCode'] ?? '',
                    'ToFacilityCode'   => $row['ToFacilityCode'] ?? '',
                ];
                $jazzRequired = inventory_transfers_jazz_send_required($miniTransfer);
                $jazzLabel = '—';
                if ($jazzRequired) {
                    $jazzLabel = trim((string) ($row['JazzSendStatus'] ?? '')) !== ''
                        ? (string) $row['JazzSendStatus']
                        : 'Pending';
                    $orderRef = trim((string) ($row['JazzShipmentNumber'] ?? ''));
                    if ($orderRef === '' && inventory_transfers_jazz_is_pharmacy_replenishment($miniTransfer)) {
                        $orderRef = inventory_transfers_jazz_batch_order_number(array_merge($miniTransfer, [
                            'JazzShipByDate' => $row['JazzShipByDate'] ?? null,
                        ]));
                    }
                    if ($orderRef !== '') {
                        $jazzLabel .= ' · ' . $orderRef;
                    }
                }
                $shipBy = inventory_transfers_format_ship_by_display($row['JazzShipByDate'] ?? null);
                if ($shipBy === '—' && $useShipments && !empty($row['ShipmentID'])) {
                    $lineRows = inventory_transfers_shipment_lines((int) $row['ShipmentID']);
                    if ($lineRows !== [] && !empty($lineRows[0]['JazzShipByDate'])) {
                        $shipBy = inventory_transfers_format_ship_by_display($lineRows[0]['JazzShipByDate']);
                    }
                }
                $qtyDisplay = $useShipments
                    ? inventory_ledger_format_quantity($row['TotalQtyRequested'] ?? null)
                    : inventory_ledger_format_quantity($row['QtyRequested'] ?? null);
            ?>
            <tr>
              <td><?= $useShipments ? (int) $row['ShipmentID'] : (int) $row['TransferID'] ?></td>
              <?php if ($useShipments): ?>
              <td><?= (int) ($row['LineCount'] ?? 0) ?></td>
              <?php else: ?>
              <td><?= htmlspecialchars((string) $row['SKUCode']) ?></td>
              <?php endif; ?>
              <td><?= htmlspecialchars((string) $row['FromFacilityCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['ToFacilityCode']) ?></td>
              <td><?= htmlspecialchars($qtyDisplay) ?></td>
              <td><?= htmlspecialchars((string) $row['TransferStatus']) ?></td>
              <td><?= htmlspecialchars($jazzLabel) ?></td>
              <td><?= $shipBy !== '' ? htmlspecialchars($shipBy) : '—' ?></td>
              <td><?= htmlspecialchars((string) ($row['CreateDate'] ?? '')) ?></td>
              <td class="table-actions">
                <?php if ($useShipments): ?>
                <a class="btn-text" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/shipment.php?shipment_id=' . (int) $row['ShipmentID'])) ?>">View</a>
                <?php if (inventory_transfers_can_update()): ?>
                <a class="btn-text" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/edit-shipment.php?shipment_id=' . (int) $row['ShipmentID'])) ?>">Edit</a>
                <?php endif; ?>
                <?php else: ?>
                <a class="btn-text" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/view.php?id=' . (int) $row['TransferID'])) ?>">View</a>
                <?php if (inventory_transfers_can_update()): ?>
                <a class="btn-text" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/edit.php?id=' . (int) $row['TransferID'])) ?>">Edit</a>
                <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
