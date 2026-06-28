<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/jazz-item-master.php';

jazz_item_master_require_read();

$activeSlug = $activeSlug ?? 'jazz-item-master';
$pageBasePath = data_profile_page_path('/jazz-item-master');
$pageContainerClass = 'page-inner--wide';
$view = jazz_item_master_view_from_query();
$search = trim($_GET['q'] ?? '');
$configError = jazz_oms_config_error();
$listResult = $configError === null
    ? jazz_item_master_list($view)
    : ['ok' => true, 'error' => null, 'rows' => [], 'endpoint' => ''];
$rows = jazz_item_master_filter_rows($listResult['rows'] ?? [], $search);
$columns = jazz_item_master_columns($view, $listResult['rows'] ?? []);
$sortColumnDefs = [];
$sortAccessors = [];
foreach ($columns as $column) {
    $sortColumnDefs[$column] = jazz_oms_field_label($column);
    $sortAccessors[$column] = fn(array $row) => $row[$column] ?? '';
}
$defaultSortColumn = $columns[0] ?? 'record';
$listFilters = [
    'view' => $view,
    'q'    => $search,
] + table_sort_state($sortColumnDefs !== [] ? $sortColumnDefs : ['record' => 'Record'], $defaultSortColumn, 'asc', $_GET);
if ($rows !== [] && $sortAccessors !== []) {
    $rows = table_sort_rows($rows, $listFilters, $sortAccessors, [], $defaultSortColumn, 'asc');
}

$pageTitle = 'Jazz Item Master | Supply Chain Management';
$pageDescription = 'SKU and item reference data from Jazz OMS.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner <?= htmlspecialchars($pageContainerClass ?? '') ?>">
      <?php render_list_page_header([
          'back_href'  => '/inventory-management/',
          'back_label' => 'Back to Supply Chain Management',
          'category'   => 'Supply Chain',
          'title'      => 'Jazz Item Master',
          'lead'       => 'SKU and item reference data from Jazz OMS.',
          'permission' => permission_label(inventory_reporting_permission_value()),
      ]); ?>

      <nav class="admin-nav" aria-label="Jazz Item Master views">
        <a href="<?= htmlspecialchars($pageBasePath) ?>/?<?= htmlspecialchars(http_build_query(array_filter(['view' => 'sku', 'q' => $search !== '' ? $search : null]))) ?>" class="<?= $view === 'sku' ? 'is-active' : '' ?>">SKUs</a>
        <a href="<?= htmlspecialchars($pageBasePath) ?>/?<?= htmlspecialchars(http_build_query(array_filter(['view' => 'item', 'q' => $search !== '' ? $search : null]))) ?>" class="<?= $view === 'item' ? 'is-active' : '' ?>">Items</a>
      </nav>

      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($pageBasePath) ?>/">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>" />
        <?php table_sort_hidden_inputs($listFilters, $defaultSortColumn, 'asc'); ?>
        <div class="audit-filter-grid">
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="SKU code, item code, description, or barcode" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Search</button>
          <?php if ($search !== ''): ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($pageBasePath) ?>/?view=<?= rawurlencode($view) ?>">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Jazz OMS connected</strong>
          <p>
            <?= count($rows) ?> <?= strtolower(jazz_item_master_view_label($view)) ?><?= count($rows) === 1 ? '' : 's' ?>
            <?php if ($search !== ''): ?>
            matching “<?= htmlspecialchars($search) ?>”
            <?php endif; ?>
            · <?= count($listResult['rows'] ?? []) ?> total loaded from <?= htmlspecialchars(jazz_oms_base_url() . ($listResult['endpoint'] ?? '')) ?>
            · tenant <?= htmlspecialchars(jazz_oms_tenant_code()) ?>
          </p>
        </div>
      </div>

      <div class="admin-table-wrap production-status-table-wrap">
        <table class="admin-table production-status-table">
          <thead>
            <tr>
              <?php if ($columns === []): ?>
              <th>Record</th>
              <?php else: ?>
              <?php foreach ($columns as $column): ?>
              <?php table_sort_render_th(
                  $column,
                  jazz_oms_field_label($column),
                  $pageBasePath,
                  $sortColumnDefs,
                  $listFilters,
                  ['view', 'q'],
                  [],
                  $defaultSortColumn,
                  'asc'
              ); ?>
              <?php endforeach; ?>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="<?= max(1, count($columns)) ?>">No <?= strtolower(jazz_item_master_view_label($view)) ?> records<?= $search !== '' ? ' match your search' : ' returned from Jazz OMS' ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($columns as $column): ?>
              <td><?= htmlspecialchars(jazz_oms_format_cell($row[$column] ?? null)) ?></td>
              <?php endforeach; ?>
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
