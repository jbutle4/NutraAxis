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
$accsSortColumns = [
    'sku'      => 'SKU',
    'source'   => 'Source',
    'quantity' => 'Quantity',
    'status'   => 'Status',
];
$listFilters = table_sort_state($accsSortColumns, 'sku', 'asc', $_GET);
$accsSortAccessors = [
    'sku'      => fn(array $row): string => (string) ($row['sku'] ?? ''),
    'source'   => fn(array $row): string => (string) ($row['source_code'] ?? ''),
    'quantity' => fn(array $row) => $row['quantity'] ?? 0,
    'status'   => fn(array $row): string => adobe_commerce_source_item_status_label($row['status'] ?? 0),
];
if ($configError === null && ($listResult['rows'] ?? []) !== []) {
    $listResult['rows'] = table_sort_rows(
        $listResult['rows'],
        $listFilters,
        $accsSortAccessors,
        ['quantity'],
        'sku',
        'asc'
    );
}

$pageTitle = 'ACCS Inventory Reporting | Inventory Management';
$pageDescription = 'View stock levels by SKU and source from Adobe Commerce (ACCS).';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => 'ACCS Inventory Reporting',
          'lead'       => 'Live inventory by SKU and source from Adobe Commerce (ACCS).',
          'permission' => permission_label(accs_inventory_reporting_permission_value()),
      ]); ?>

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
            <?php table_sort_render_head_row(
                $accsSortColumns,
                data_profile_page_path('/accs-inventory-reporting'),
                $listFilters,
                [],
                ['quantity'],
                'sku',
                'asc'
            ); ?>
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
