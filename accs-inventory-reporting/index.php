<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accs-inventory-reporting.php';

accs_inventory_reporting_require_read();

$activeSlug = $activeSlug ?? 'accs-inventory-reporting';
$configError = adobe_commerce_config_error();
$listResult = $configError === null
    ? adobe_commerce_list_inventory()
    : ['ok' => true, 'error' => null, 'rows' => [], 'total' => 0];

$pageTitle = 'ACCS Inventory Reporting | Inventory Management';
$pageDescription = 'View stock levels by SKU and source from Adobe Commerce (ACCS).';

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
          <h1>ACCS Inventory Reporting</h1>
          <p class="page-lead">Live inventory by SKU and source from Adobe Commerce (ACCS).</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(accs_inventory_reporting_permission_value())) ?></p>
        </div>
      </div>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Adobe Commerce connected</strong>
          <p><?= count($listResult['rows']) ?> inventory record<?= count($listResult['rows']) === 1 ? '' : 's' ?> from <?= htmlspecialchars(adobe_commerce_base_url()) ?> · environment <?= htmlspecialchars(adobe_commerce_environment()) ?></p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Source</th>
              <th>Quantity</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?>
            <tr><td colspan="4">No inventory records returned from Adobe Commerce.</td></tr>
            <?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['sku'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['source_code'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(adobe_commerce_format_quantity($row['quantity'] ?? null)) ?></td>
              <td><span class="status-badge <?= adobe_commerce_source_item_status_class($row['status'] ?? 0) ?>"><?= htmlspecialchars(adobe_commerce_source_item_status_label($row['status'] ?? 0)) ?></span></td>
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
