<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/supplier.php';

supplier_require_read();

$activeSlug = 'supplier-management';
$statusFilter = $_GET['status'] ?? 'active';
$search = trim($_GET['q'] ?? '');
$suppliers = supplier_list([
    'status' => $statusFilter !== '' ? $statusFilter : 'active',
    'q'      => $search !== '' ? $search : null,
]);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Supplier Management | NutraAxis Operations';
$pageDescription = 'View and manage supplier profiles used across purchase orders.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/inventory-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Inventory Management
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Inventory</div>
          <h1>Supplier Management</h1>
          <p class="page-lead">Maintain supplier profiles, contacts, and addresses used when creating purchase orders.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(supplier_permission_value())) ?></p>
        </div>
        <?php if (supplier_can_create()): ?>
        <a class="btn-primary" href="/supplier-management/new.php">New Supplier</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Supplier created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Supplier updated successfully.</div>
      <?php elseif ($notice === 'deactivated'): ?>
      <div class="admin-notice is-success" role="status">Supplier deactivated successfully.</div>
      <?php elseif ($notice === 'activated'): ?>
      <div class="admin-notice is-success" role="status">Supplier activated successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/supplier-management/">
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active only</option>
              <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
              <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All suppliers</option>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, code, contact, or email" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/supplier-management/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Supplier name</th>
              <th>Type</th>
              <th>Contact</th>
              <th>Status</th>
              <th>POs</th>
              <th><?= htmlspecialchars(table_actions_header(supplier_can_update() ? ['View', 'Edit'] : ['View'])) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($suppliers === []): ?>
            <tr><td colspan="7">No suppliers match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($suppliers as $supplier): ?>
            <tr>
              <td><?= htmlspecialchars($supplier['SupplierCode'] ?? '—') ?></td>
              <td><?= htmlspecialchars($supplier['SupplierName']) ?></td>
              <td><?= htmlspecialchars($supplier['SupplierType'] ?? '—') ?></td>
              <td>
                <?= htmlspecialchars($supplier['ContactName'] ?? '—') ?>
                <?php if (!empty($supplier['ContactEmail'])): ?>
                <br><small><?= htmlspecialchars($supplier['ContactEmail']) ?></small>
                <?php endif; ?>
              </td>
              <td><span class="status-badge <?= supplier_status_class(!empty($supplier['IsActive'])) ?>"><?= htmlspecialchars(supplier_status_label(!empty($supplier['IsActive']))) ?></span></td>
              <td><?= (int) $supplier['POCount'] ?></td>
              <?php table_view_edit_cell(
                  '/supplier-management/view.php?id=' . (int) $supplier['SupplierID'],
                  '/supplier-management/edit.php?id=' . (int) $supplier['SupplierID'],
                  supplier_can_update()
              ); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
