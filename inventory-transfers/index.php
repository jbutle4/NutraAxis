<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';

inventory_transfers_require_read();

$activeSlug = 'inventory-transfers';
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
          'lead'       => 'Hub-and-spoke transfers from Cart.com to CPPC, White Label / WPC, and transit.',
          'permission' => permission_label(inventory_transfers_permission_value()),
      ]);
      if (inventory_transfers_can_update()) {
          render_list_page_toolbar('<a class="btn-primary" href="/inventory-transfers/new.php">New transfer</a>');
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

      <form class="admin-filter-bar" method="get" action="/inventory-transfers/">
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
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="8">No transfers found.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= (int) $row['TransferID'] ?></td>
              <td><?= htmlspecialchars((string) $row['SKUCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['FromFacilityCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['ToFacilityCode']) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyRequested'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) $row['TransferStatus']) ?></td>
              <td><?= htmlspecialchars((string) ($row['CreateDate'] ?? '')) ?></td>
              <td class="table-actions">
                <a class="btn-text" href="/inventory-transfers/view.php?id=<?= (int) $row['TransferID'] ?>">View</a>
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
