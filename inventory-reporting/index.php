<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-reporting.php';

inventory_reporting_require_read();

$activeSlug = $activeSlug ?? 'inventory-reporting';
$configError = jazz_oms_config_error();
$listResult = $configError === null ? jazz_oms_list_inventory() : ['ok' => true, 'error' => null, 'rows' => []];

$pageTitle = 'Jazz Current Inventory | Supply Chain Management';
$pageDescription = 'View stock on hand and availability from Jazz OMS.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/inventory-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Supply Chain Management
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Inventory</div>
          <h1>Jazz Current Inventory</h1>
          <p class="page-lead">Live inventory by SKU and facility from Jazz OMS.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(inventory_reporting_permission_value())) ?></p>
        </div>
      </div>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Jazz OMS connected</strong>
          <p><?= count($listResult['rows']) ?> inventory record<?= count($listResult['rows']) === 1 ? '' : 's' ?> loaded from <?= htmlspecialchars(jazz_oms_base_url()) ?> · tenant <?= htmlspecialchars(jazz_oms_tenant_code()) ?></p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Facility</th>
              <th>Available</th>
              <th>On hand</th>
              <th>Ordered</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?>
            <tr><td colspan="6">No inventory records returned from Jazz OMS.</td></tr>
            <?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['sku_code'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['facility_code'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['available_quantity'] ?? null)) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['on_hand_quantity'] ?? null)) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['qty_ordered'] ?? null)) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['total_quantity'] ?? null)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
