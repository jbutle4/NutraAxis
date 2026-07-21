<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_bind_qbo_environment();
accounting_require_read();

$activeSlug = $activeSlug ?? 'accounting';
$pagePath = accounting_path('/accounting/inventory.php');
$accountingSection = 'inventory';
$listResult = qbo_is_connected() ? qbo_list_inventory_items() : ['ok' => true, 'rows' => [], 'error' => null];
$qboSortColumns = [
    'name'        => 'Name',
    'sku'         => 'SKU',
    'qty_on_hand' => 'Qty on hand',
    'unit_price'  => 'Unit price',
    'active'      => 'Active',
];
$listFilters = table_sort_state($qboSortColumns, 'name', 'asc', $_GET);
$qboSortAccessors = [
    'name'        => fn(array $row): string => (string) ($row['Name'] ?? ''),
    'sku'         => fn(array $row): string => (string) ($row['Sku'] ?? ''),
    'qty_on_hand' => fn(array $row) => $row['QtyOnHand'] ?? 0,
    'unit_price'  => fn(array $row) => $row['UnitPrice'] ?? 0,
    'active'      => fn(array $row): string => !empty($row['Active']) ? 'Yes' : 'No',
];
if ($listResult['ok'] && qbo_is_connected()) {
    $listResult['rows'] = table_sort_rows($listResult['rows'] ?? [], $listFilters, $qboSortAccessors, ['qty_on_hand', 'unit_price'], 'name', 'asc');
}

$pageTitle = 'Inventory | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks</div>
          <h1>Inventory</h1>
          <p class="page-lead">Inventory items from QuickBooks Online. Read-only.</p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><?php table_sort_render_head_row($qboSortColumns, $pagePath, $listFilters, [], ['qty_on_hand', 'unit_price'], 'name', 'asc'); ?></thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?><tr><td colspan="5">No inventory items found.</td></tr><?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['Name'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Sku'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['QtyOnHand'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['UnitPrice'] ?? null)) ?></td>
              <td><?= !empty($row['Active']) ? 'Yes' : 'No' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
