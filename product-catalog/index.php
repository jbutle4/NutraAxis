<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

catalog_require_read();

$activeSlug = 'product-catalog';
$listFilters = catalog_list_filters();
$statusFilter = $listFilters['status'];
$brandFilter = $listFilters['brand'];
$categoryFilter = $listFilters['category'];
$search = $listFilters['q'];
$skus = catalog_list_skus([
    'status'   => $statusFilter !== '' ? $statusFilter : null,
    'brand'    => $brandFilter !== '' ? $brandFilter : null,
    'category' => $categoryFilter !== '' ? $categoryFilter : null,
    'q'        => $search !== '' ? $search : null,
    'sort'     => $listFilters['sort'],
    'dir'      => $listFilters['dir'],
]);
$notice = $_GET['notice'] ?? null;
$listError = isset($_GET['error']) ? qbo_humanize_error((string) $_GET['error']) : null;
$listWarning = $_GET['warning'] ?? null;
$bulkSyncResult = catalog_take_bulk_sync_result();
$qboConnected = qbo_is_connected();
$canSyncQbo = catalog_can_update() && $qboConnected;
$pageContainerClass = 'page-inner--full';
$bodyClass = 'page-catalog-list';
$actionLabels = catalog_can_update() ? ['View', 'Edit'] : ['View'];
if ($canSyncQbo) {
    $actionLabels[] = 'Sync';
}
$actionHeader = table_actions_header($actionLabels);
$catalogListColumnClasses = [
    'product_name'    => 'catalog-col-product-name',
    'serving_count'   => 'catalog-col-srv',
    'capsule_count'   => 'catalog-col-cap',
    'wholesale_price' => 'catalog-col-whsle',
];

$pageTitle = 'Product SKU Master | Product Master';
$pageDescription = 'View and manage the master product catalog and SKU reference data.';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main page-main--fluid">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass) ?>">
      <?php
      $catalogHeaderActions = '';
      if ($canSyncQbo) {
          $catalogHeaderActions .= '<form method="post" action="/product-catalog/sync-qbo-all.php" class="inline-form" onsubmit="return confirm(\'Sync every SKU in the catalog to QuickBooks? This may take a minute.\');"><button type="submit" class="btn-secondary">Sync All to QuickBooks</button></form>';
          $catalogHeaderActions .= '<form method="post" action="/product-catalog/convert-qbo-inventory.php" class="inline-form" onsubmit="return confirm(\'Convert every SKU to QuickBooks Inventory items with Qty on hand = 0? Existing Non-inventory items will be inactivated.\');"><input type="hidden" name="convert_all" value="1" /><button type="submit" class="btn-secondary">Convert All to Inventory</button></form>';
      }
      if (catalog_can_create()) {
          $catalogHeaderActions .= '<a class="btn-primary" href="/product-catalog/new.php">New SKU</a>';
      }
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Master Data',
          'title'      => 'Product SKU Master',
          'lead'       => 'Maintain SKU codes, product attributes, pricing, and catalog data used across NutraAxis operations. Sync to QuickBooks from row actions or the SKU detail page. Compare against <a href="/accounting/inventory.php">QBO SKU Master</a>.',
          'lead_html'  => true,
          'permission' => permission_label(catalog_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">SKU created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">SKU updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">SKU deleted successfully.</div>
      <?php elseif ($notice === 'qbo_synced'): ?>
      <div class="admin-notice is-success" role="status">SKU synced to QuickBooks successfully.</div>
      <?php elseif ($notice === 'qbo_reconciled'): ?>
      <div class="admin-notice is-success" role="status">QuickBooks item linked successfully.</div>
      <?php elseif (($notice === 'qbo_bulk_sync' || $notice === 'qbo_inventory_convert_bulk') && $bulkSyncResult !== null): ?>
      <?php
        $isConvertBulk = $notice === 'qbo_inventory_convert_bulk';
        $bulkOk = !empty($bulkSyncResult['ok']) || (
            $isConvertBulk
                ? ((int) ($bulkSyncResult['converted'] ?? 0) + (int) ($bulkSyncResult['already'] ?? 0)) > 0
                : ((int) ($bulkSyncResult['synced'] ?? 0) + (int) ($bulkSyncResult['reconciled'] ?? 0)) > 0
        );
        $bulkClass = $bulkOk ? 'is-success' : 'is-error';
      ?>
      <div class="admin-notice <?= $bulkClass ?>" role="status">
        <?php if ($isConvertBulk): ?>
        QuickBooks Inventory conversion finished:
        <?= (int) ($bulkSyncResult['converted'] ?? 0) ?> converted,
        <?= (int) ($bulkSyncResult['already'] ?? 0) ?> already Inventory,
        <?= (int) ($bulkSyncResult['failed'] ?? 0) ?> failed
        (<?= (int) ($bulkSyncResult['total'] ?? 0) ?> total).
        <?php else: ?>
        QuickBooks bulk sync finished:
        <?= (int) ($bulkSyncResult['synced'] ?? 0) ?> synced,
        <?= (int) ($bulkSyncResult['reconciled'] ?? 0) ?> linked,
        <?= (int) ($bulkSyncResult['failed'] ?? 0) ?> failed
        (<?= (int) ($bulkSyncResult['total'] ?? 0) ?> total).
        <?php if ((int) ($bulkSyncResult['warnings'] ?? 0) > 0): ?>
        <?= (int) $bulkSyncResult['warnings'] ?> synced as Non-inventory (Essentials).
        <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($bulkSyncResult['error'])): ?>
        <?= htmlspecialchars((string) $bulkSyncResult['error']) ?>
        <?php endif; ?>
      </div>
      <?php if (($bulkSyncResult['failures'] ?? []) !== []): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        <strong>Failed SKUs</strong>
        <ul class="notice-list">
          <?php foreach ($bulkSyncResult['failures'] as $failure): ?>
          <li>
            <a href="/product-catalog/view.php?id=<?= (int) ($failure['sku_id'] ?? 0) ?>"><?= htmlspecialchars((string) ($failure['sku_code'] ?? 'SKU')) ?></a>:
            <?= htmlspecialchars(qbo_humanize_error((string) ($failure['error'] ?? 'Sync failed.'))) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <?php if ($listWarning !== null): ?>
      <div class="admin-notice is-warning" role="status"><?= htmlspecialchars($listWarning) ?></div>
      <?php endif; ?>
      <?php if ($listError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listError) ?></div>
      <?php endif; ?>
      <?php if (catalog_can_update() && !$qboConnected): ?>
      <div class="admin-notice is-warning" role="status">QuickBooks is not connected. Connect in <a href="/accounting/">Accounting</a> to enable SKU sync.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/product-catalog/">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($listFilters['sort']) ?>" />
        <input type="hidden" name="dir" value="<?= htmlspecialchars($listFilters['dir']) ?>" />
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (CATALOG_SKU_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="brand">Brand</label>
            <select class="form-input" id="brand" name="brand">
              <option value="">All brands</option>
              <?php foreach (CATALOG_BRANDS as $brand): ?>
              <option value="<?= htmlspecialchars($brand) ?>" <?= $brandFilter === $brand ? 'selected' : '' ?>><?= htmlspecialchars($brand) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="category">Category</label>
            <select class="form-input" id="category" name="category">
              <option value="">All categories</option>
              <?php foreach (CATALOG_THERAPEUTIC_CATEGORIES as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="SKU code, product name, GTIN, or UPC" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/product-catalog/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($catalogHeaderActions !== '' ? $catalogHeaderActions : null); ?>

      <div class="admin-table-wrap admin-table-wrap--catalog">
        <table class="admin-table admin-table--catalog">
          <thead>
            <tr>
              <?php foreach (CATALOG_LIST_SORT_COLUMNS as $column => $label): ?>
              <?php $columnClass = $catalogListColumnClasses[$column] ?? ''; ?>
              <th class="admin-table-sort<?= $columnClass !== '' ? ' ' . htmlspecialchars($columnClass) : '' ?>">
                <a
                  class="admin-table-sort-link<?= catalog_sort_is_active($column, $listFilters) ? ' is-active' : '' ?>"
                  href="<?= htmlspecialchars(catalog_list_sort_href($column, $listFilters)) ?>"
                >
                  <span><?= htmlspecialchars($label) ?></span>
                  <span class="admin-table-sort-indicator" aria-hidden="true"><?php if (catalog_sort_is_active($column, $listFilters)): ?><?= catalog_sort_direction($column, $listFilters) === 'asc' ? '▲' : '▼' ?><?php else: ?>↕<?php endif; ?></span>
                </a>
              </th>
              <?php endforeach; ?>
              <th class="catalog-col-actions"><?= htmlspecialchars($actionHeader) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($skus === []): ?>
            <tr><td colspan="13">No SKUs match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($skus as $sku): ?>
            <tr>
              <td><?= htmlspecialchars($sku['SKUCode']) ?></td>
              <td class="catalog-col-product-name"><?= htmlspecialchars($sku['ProductName']) ?></td>
              <td><?= !empty($sku['UPC']) ? htmlspecialchars($sku['UPC']) : '—' ?></td>
              <td><?= htmlspecialchars($sku['Brand']) ?></td>
              <td><?= htmlspecialchars($sku['PrimaryTherapeuticCategory']) ?></td>
              <td><span class="status-badge <?= catalog_status_class($sku['SKUStatus']) ?>"><?= htmlspecialchars($sku['SKUStatus']) ?></span></td>
              <td class="catalog-col-srv"><?= $sku['ServingCount'] !== null ? (int) $sku['ServingCount'] : '—' ?></td>
              <td class="catalog-col-cap"><?= $sku['CapsuleCount'] !== null ? (int) $sku['CapsuleCount'] : '—' ?></td>
              <td><?= htmlspecialchars(catalog_format_money($sku['COGS'])) ?></td>
              <td class="catalog-col-whsle"><?= htmlspecialchars(catalog_format_money($sku['WholesalePrice'])) ?></td>
              <td><?= htmlspecialchars(catalog_format_money($sku['MSRP'])) ?></td>
              <?php $qboStatus = (string) ($sku['QBO_SyncStatus'] ?? 'NotSynced'); ?>
              <td><span class="status-badge <?= catalog_qbo_sync_status_class($qboStatus) ?>" title="<?= htmlspecialchars(catalog_qbo_sync_status_label($qboStatus)) ?>"><?= htmlspecialchars(catalog_qbo_sync_status_short_label($qboStatus)) ?></span></td>
              <?php
                $rowActions = [
                    ['href' => '/product-catalog/view.php?id=' . (int) $sku['SKUID'], 'label' => 'View'],
                ];
                if (catalog_can_update()) {
                    $rowActions[] = ['href' => '/product-catalog/edit.php?id=' . (int) $sku['SKUID'], 'label' => 'Edit'];
                }
                if ($canSyncQbo) {
                    $skuCode = htmlspecialchars((string) $sku['SKUCode'], ENT_QUOTES);
                    $rowActions[] = [
                        'html' => '<form method="post" action="/product-catalog/sync-qbo.php" class="inline-form table-action-form" onsubmit="return confirm(\'Sync '
                            . $skuCode
                            . ' to QuickBooks as a product item?\');">'
                            . '<input type="hidden" name="sku_id" value="' . (int) $sku['SKUID'] . '" />'
                            . '<input type="hidden" name="return" value="list" />'
                            . '<button type="submit" class="table-action-btn">Sync</button>'
                            . '</form>',
                    ];
                }
                table_actions_cell($rowActions);
              ?>
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
