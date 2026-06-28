<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-reporting.php';

inventory_reporting_require_read();

$activeSlug = $activeSlug ?? 'inventory-reporting';
$configError = jazz_oms_config_error();
$listResult = $configError === null ? jazz_oms_list_inventory() : ['ok' => true, 'error' => null, 'rows' => []];
$inventorySortColumns = [
    'sku_code'           => 'SKU',
    'facility_code'      => 'Facility',
    'available_quantity' => 'Available',
    'on_hand_quantity'   => 'On hand',
    'qty_ordered'        => 'Ordered',
    'total_quantity'     => 'Total',
];
$listFilters = table_sort_state($inventorySortColumns, 'sku_code', 'asc', $_GET);
$inventorySortAccessors = [
    'sku_code'           => fn(array $row): string => (string) ($row['sku_code'] ?? ''),
    'facility_code'      => fn(array $row): string => (string) ($row['facility_code'] ?? ''),
    'available_quantity' => fn(array $row) => $row['available_quantity'] ?? 0,
    'on_hand_quantity'   => fn(array $row) => $row['on_hand_quantity'] ?? 0,
    'qty_ordered'        => fn(array $row) => $row['qty_ordered'] ?? 0,
    'total_quantity'     => fn(array $row) => $row['total_quantity'] ?? 0,
];
if ($configError === null && ($listResult['rows'] ?? []) !== []) {
    $listResult['rows'] = table_sort_rows(
        $listResult['rows'],
        $listFilters,
        $inventorySortAccessors,
        ['available_quantity', 'on_hand_quantity', 'qty_ordered', 'total_quantity'],
        'sku_code',
        'asc'
    );
}

$pageTitle = 'Jazz Current Inventory | Supply Chain Management';
$pageDescription = 'View stock on hand and availability from Jazz OMS.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/inventory-management/',
          'back_label' => 'Back to Supply Chain Management',
          'category'   => 'Inventory',
          'title'      => 'Jazz Current Inventory',
          'lead'       => 'Live inventory by SKU and facility from Jazz OMS.',
          'permission' => permission_label(inventory_reporting_permission_value()),
      ]); ?>

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
            <?php table_sort_render_head_row(
                $inventorySortColumns,
                data_profile_page_path('/inventory-reporting'),
                $listFilters,
                [],
                ['available_quantity', 'on_hand_quantity', 'qty_ordered', 'total_quantity'],
                'sku_code',
                'asc'
            ); ?>
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
