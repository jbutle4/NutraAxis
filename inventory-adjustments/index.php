<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-adjustments.php';

inventory_adjustments_require_read();

$activeSlug = 'inventory-adjustments';
$statusFilter = trim($_GET['status'] ?? '');
$rows = inventory_adjustments_list(['status' => $statusFilter]);
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$hubBack = app_module_hub_back_link($activeSlug);

$pageTitle = 'Inventory Adjustments | Inventory Management';
$pageDescription = 'Create and approve shrink/gain adjustments against the IMS ledger and QBO QtyOnHand.';

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
          'title'      => 'Inventory Adjustments',
          'lead'       => 'Pending shrink/gain requests; approve to post IMS and QuickBooks InventoryAdjustment.',
          'permission' => permission_label(inventory_adjustments_permission_value()),
      ]);
      if (inventory_adjustments_can_update()) {
          render_list_page_toolbar('<a class="btn-primary" href="/inventory-adjustments/new.php">New adjustment</a>');
      }
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Adjustment created as Pending.</div>
      <?php elseif ($notice === 'approved'): ?>
      <div class="admin-notice is-success" role="status">Adjustment approved and posted.</div>
      <?php elseif ($notice === 'rejected'): ?>
      <div class="admin-notice is-success" role="status">Adjustment rejected.</div>
      <?php endif; ?>
      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-filter-bar" method="get" action="/inventory-adjustments/">
        <div class="form-field">
          <label for="status">Status</label>
          <select class="form-input" id="status" name="status">
            <option value="">All</option>
            <?php foreach (['Pending', 'Approved', 'Rejected'] as $status): ?>
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
              <th>Facility</th>
              <th>Bucket</th>
              <th>Qty Δ</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="9">No adjustments found.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= (int) $row['AdjustmentID'] ?></td>
              <td><?= htmlspecialchars((string) $row['SKUCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['FacilityCode']) ?></td>
              <td><?= htmlspecialchars((string) $row['StatusBucket']) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyAdjusted'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) ($row['ReasonCode'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) $row['AdjStatus']) ?></td>
              <td><?= htmlspecialchars((string) ($row['CreateDate'] ?? '')) ?></td>
              <td><a href="/inventory-adjustments/view.php?id=<?= (int) $row['AdjustmentID'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
