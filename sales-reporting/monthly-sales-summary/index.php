<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';
require dirname(__DIR__, 2) . '/includes/monthly-sales-summary.php';

sales_reporting_require_read();

$activeSlug = 'sales-monthly-summary';

$period = trim($_GET['period'] ?? '');
$saleYear = 0;
$saleMonth = 0;
if ($period !== '' && preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
    $saleYear = (int) $matches[1];
    $saleMonth = (int) $matches[2];
}

$filters = [
    'period' => $period,
    'sku'    => trim($_GET['sku'] ?? ''),
    'limit'  => 500,
    'sale_year'  => $saleYear,
    'sale_month' => $saleMonth,
] + table_sort_state(MONTHLY_SALES_SUMMARY_LIST_SORT_COLUMNS, 'month', 'desc', $_GET);

$rows = [];
$dbError = null;

try {
    $rows = monthly_sales_summary_list([
        'sale_year'  => $saleYear,
        'sale_month' => $saleMonth,
        'sku'        => $filters['sku'],
        'limit'      => $filters['limit'],
        'sort'       => $filters['sort'],
        'dir'        => $filters['dir'],
    ]);
    $availableMonths = monthly_sales_summary_distinct_months();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $availableMonths = [];
}

$pageTitle = 'Monthly Sales Summary | Sales Reporting Summaries';
$pageDescription = 'Monthly SKU quantity totals from daily sales rollup.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/sales-reporting/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Sales Reporting Summaries
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Sales</div>
          <h1>Monthly Sales Summary</h1>
          <p class="page-lead">SKU quantities sold per calendar month, rolled up from daily sales by the weekly chain job.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(sales_reporting_permission_value())) ?></p>
        </div>
      </div>

      <?php if ($dbError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($dbError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter" method="get" action="/sales-reporting/monthly-sales-summary/">
        <?php table_sort_hidden_inputs($filters, 'month', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="period">Month</label>
            <select class="form-input" id="period" name="period">
              <option value="">All months</option>
              <?php foreach ($availableMonths as $month): ?>
              <?php
                $year = (int) ($month['SaleYear'] ?? 0);
                $mon = (int) ($month['SaleMonth'] ?? 0);
                $key = $year . '-' . str_pad((string) $mon, 2, '0', STR_PAD_LEFT);
                $label = $month['MonthStart'] ?? $key;
              ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $filters['period'] === $key ? 'selected' : '' ?>>
                <?= htmlspecialchars((string) $label) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="sku">SKU contains</label>
            <input class="form-input" type="search" id="sku" name="sku" value="<?= htmlspecialchars($filters['sku']) ?>" placeholder="Filter by SKU" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/sales-reporting/monthly-sales-summary/">Clear</a>
        </div>
      </form>

      <div class="status-banner">
        <div>
          <strong><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?></strong>
          <p>Showing up to 500 rows<?= $filters['period'] !== '' ? ' for ' . htmlspecialchars($filters['period']) : ', most recent months first' ?>.</p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                MONTHLY_SALES_SUMMARY_LIST_SORT_COLUMNS,
                '/sales-reporting/monthly-sales-summary',
                $filters,
                ['period', 'sku'],
                MONTHLY_SALES_SUMMARY_LIST_SORT_NUMERIC,
                'month',
                'desc',
                'month'
            ); ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="4">No monthly sales summary rows found. Run the monthly sales summary job or adjust filters.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['MonthStart'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['SKU'] ?? '')) ?></td>
              <td><?= htmlspecialchars(monthly_sales_summary_format_qty($row['TotalQty'] ?? 0)) ?></td>
              <td><?= htmlspecialchars(substr((string) ($row['LastUpdatedAt'] ?? ''), 0, 19)) ?></td>
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
require dirname(__DIR__, 2) . '/includes/footer.php';
