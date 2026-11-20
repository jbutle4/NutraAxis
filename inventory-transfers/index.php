<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';
require dirname(__DIR__) . '/includes/inventory-transfers-jazz.php';

inventory_transfers_require_read();

$activeSlug = $activeSlug ?? 'inventory-transfers';
$statusFilter = trim($_GET['status'] ?? '');
$rows = inventory_transfers_list(['status' => $statusFilter]);
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

      <?php if ($notice === 'created'): ?>
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
              <th>ID</th>
              <th>SKU</th>
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
                $batchOrder = '';
                if ($jazzRequired && inventory_transfers_jazz_is_pharmacy_replenishment($miniTransfer)) {
                    $batchOrder = inventory_transfers_jazz_shipment_number_for_transfer($row);
                }
                if ($jazzRequired) {
                    $jazzLabel = trim((string) ($row['JazzSendStatus'] ?? '')) !== ''
                        ? (string) $row['JazzSendStatus']
                        : 'Pending';
                    if ($batchOrder !== '' && strcasecmp($jazzLabel, 'Sent') !== 0) {
                        $jazzLabel .= ' · ' . $batchOrder;
                    } elseif ($batchOrder !== '' && trim((string) ($row['JazzShipmentNumber'] ?? '')) === '') {
                        $jazzLabel .= ' · ' . $batchOrder;
                    }
                }
                $shipBy = '';
                if (!empty($row['JazzShipByDate'])) {
                    try {
                        $shipBy = (new DateTimeImmutable((string) $row['JazzShipByDate']))->format('Y-m-d');
                    } catch (Throwable) {
                        $shipBy = '';
                    }
                }
            ?>
            <tr>
              <td><?= (int) $row['TransferID'] ?></td>
              <td><?= htmlspecialchars((string) $row['SKUCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['FromFacilityCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['ToFacilityCode']) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyRequested'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) $row['TransferStatus']) ?></td>
              <td><?= htmlspecialchars($jazzLabel) ?></td>
              <td><?= $shipBy !== '' ? htmlspecialchars($shipBy) : '—' ?></td>
              <td><?= htmlspecialchars((string) ($row['CreateDate'] ?? '')) ?></td>
              <td class="table-actions">
                <a class="btn-text" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/view.php?id=' . (int) $row['TransferID'])) ?>">View</a>
                <?php if (inventory_transfers_can_update()): ?>
                <a class="btn-text" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/edit.php?id=' . (int) $row['TransferID'])) ?>">Edit</a>
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
