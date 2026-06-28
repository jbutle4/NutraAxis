<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-forecasting.php';

inventory_forecasting_require_read();

$module = get_module('inventory-forecasting');
$activeSlug = 'inventory-forecasting';

$skuFilter = trim($_GET['sku'] ?? '');
$shortageFilter = trim($_GET['shortage'] ?? '');
$listFilters = [
    'sku'      => $skuFilter !== '' ? $skuFilter : null,
    'shortage' => $shortageFilter !== '' ? $shortageFilter : null,
] + table_sort_state(INVENTORY_FORECASTING_LIST_SORT_COLUMNS, 'sku', 'asc', $_GET);
$skus = inventory_forecasting_list_skus();
$meta = inventory_forecasting_meta();
$planRows = inventory_forecasting_list_plan_rows($listFilters);

$pageTitle = 'Inventory Forecasting | NutraAxis Operations';
$pageDescription = $module['lead'] ?? 'Project demand and plan replenishment with confidence.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/inventory-management/',
          'back_label' => 'Back to Supply Chain Management',
          'category'   => $module['label'],
          'title'      => $module['headline'],
          'lead'       => $module['lead'],
          'permission' => auth_module_permission_label('inventory-forecasting'),
      ]);
      ?>

      <section class="capability-card demand-summary-card">
        <div class="demand-summary-card-head">
          <div>
            <h3>SKU Demand Summary</h3>
            <p>Weighted moving average projection by SKU and month. Receipts come from open purchase orders; beginning on-hand uses the latest Jazz inventory snapshot.</p>
          </div>
          <div class="demand-summary-head-actions">
            <?php if ($meta['row_count'] > 0): ?>
            <a class="btn-secondary btn-small" href="<?= htmlspecialchars(inventory_forecasting_export_url($skuFilter, $shortageFilter)) ?>">Export to Excel</a>
            <?php endif; ?>
            <?php if ($meta['row_count'] > 0): ?>
            <div class="demand-summary-meta">
              <span><strong><?= (int) $meta['sku_count'] ?></strong> SKU<?= $meta['sku_count'] === 1 ? '' : 's' ?></span>
              <span><strong><?= (int) $meta['row_count'] ?></strong> month row<?= $meta['row_count'] === 1 ? '' : 's' ?></span>
              <span>Updated <?= htmlspecialchars(inventory_forecasting_format_generated_at($meta['last_generated_at'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($meta['row_count'] === 0): ?>
        <div class="status-banner">
          <div>
            <strong>No demand data yet</strong>
            <p>The weekly rollup job has not populated demand rows yet. After daily sales summaries are running, trigger the monthly rollup from Process Log or wait for the Sunday scheduled job.</p>
          </div>
          <?php if (auth_can_read(MODULE_PERMISSION_COLUMNS['process-log'] ?? '')): ?>
          <a class="btn-secondary" href="/process-log/">View Process Log</a>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <form class="po-filter demand-summary-filter" method="get" action="/inventory-demand/">
          <?php table_sort_hidden_inputs($listFilters, 'sku', 'asc'); ?>
          <div class="audit-filter-grid">
            <div>
              <label for="sku">SKU</label>
              <select class="form-input" id="sku" name="sku">
                <option value="">All SKUs</option>
                <?php foreach ($skus as $sku): ?>
                <option value="<?= htmlspecialchars($sku) ?>" <?= $skuFilter === $sku ? 'selected' : '' ?>>
                  <?= htmlspecialchars($sku) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="shortage">Shortage</label>
              <select class="form-input" id="shortage" name="shortage">
                <option value="">All rows</option>
                <option value="1" <?= $shortageFilter === '1' ? 'selected' : '' ?>>Shortage only</option>
                <option value="0" <?= $shortageFilter === '0' ? 'selected' : '' ?>>No shortage</option>
              </select>
            </div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filter</button>
            <?php if ($skuFilter !== '' || $shortageFilter !== ''): ?>
            <a class="btn-secondary" href="/inventory-demand/">Clear</a>
            <?php endif; ?>
          </div>
        </form>

        <div class="admin-table-wrap">
          <table class="admin-table demand-summary-table">
            <thead>
              <?php table_sort_render_head_row(
                  INVENTORY_FORECASTING_LIST_SORT_COLUMNS,
                  '/inventory-demand',
                  $listFilters,
                  ['sku', 'shortage'],
                  INVENTORY_FORECASTING_LIST_SORT_NUMERIC,
                  'sku',
                  'asc',
                  ''
              ); ?>
            </thead>
            <tbody>
              <?php if ($planRows === []): ?>
              <tr>
                <td colspan="10">No rows match the selected filter.</td>
              </tr>
              <?php else: ?>
              <?php foreach ($planRows as $row): ?>
              <tr class="<?= !empty($row['ShortageFlag']) ? 'is-inventory-mismatch' : '' ?>">
                <td><?= htmlspecialchars((string) $row['SKU']) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_format_month((int) $row['PlanYear'], (int) $row['PlanMonth'])) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_display_qty($row, 'ForecastBeginOH', 'ActualBeginOH')) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_display_qty($row, 'ForecastReceipts', 'ActualReceipts')) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_display_qty($row, 'ForecastSales', 'ActualSales')) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_display_qty($row, 'ForecastEndOH', 'ActualEndOH')) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_shortage_label($row)) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_format_qty($row['BaselineAvg'] ?? null)) ?></td>
                <td><?= htmlspecialchars(inventory_forecasting_format_qty($row['TrendFactor'] ?? null)) ?></td>
                <td>
                  <?php if (!empty($row['IsLocked'])): ?>
                  <span class="status-badge status-approved">Actual</span>
                  <?php else: ?>
                  <span class="status-badge status-submitted">Projected</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

      <div class="module-actions">
        <a class="btn-secondary" href="/inventory-management/">All Supply Chain Applications</a>
        <?php if (auth_can_read(MODULE_PERMISSION_COLUMNS['process-log'] ?? '')): ?>
        <a class="btn-secondary" href="/process-log/">Process Log</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
