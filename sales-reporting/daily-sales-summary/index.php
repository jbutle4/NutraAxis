<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';
require dirname(__DIR__, 2) . '/includes/daily-sales-summary.php';

sales_reporting_require_read();

$activeSlug = 'sales-daily-summary';

$filters = [
    'summary_date' => trim($_GET['summary_date'] ?? ''),
    'sku'          => trim($_GET['sku'] ?? ''),
    'limit'        => 500,
] + table_sort_state(DAILY_SALES_SUMMARY_LIST_SORT_COLUMNS, 'summary_date', 'desc', $_GET);

$rows = [];
$dbError = null;

try {
    $rows = daily_sales_summary_list($filters);
    $availableDates = daily_sales_summary_distinct_dates();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $availableDates = [];
}

$pageTitle = 'Daily Sales Summary | Sales Reporting Summaries';
$pageDescription = 'Daily SKU quantity totals from ACCS orders.';

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
          <h1>Daily Sales Summary</h1>
          <p class="page-lead">SKU quantities sold per day, populated nightly from ACCS orders (US Central sales date).</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(sales_reporting_permission_value())) ?></p>
        </div>
      </div>

      <?php if ($dbError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($dbError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter" method="get" action="/sales-reporting/daily-sales-summary/">
        <?php table_sort_hidden_inputs($filters, 'summary_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="summary_date">Summary date</label>
            <select class="form-input" id="summary_date" name="summary_date">
              <option value="">All dates</option>
              <?php foreach ($availableDates as $date): ?>
              <option value="<?= htmlspecialchars($date) ?>" <?= $filters['summary_date'] === $date ? 'selected' : '' ?>>
                <?= htmlspecialchars($date) ?>
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
          <a class="btn-secondary" href="/sales-reporting/daily-sales-summary/">Clear</a>
        </div>
      </form>

      <div class="status-banner">
        <div>
          <strong><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?></strong>
          <p>Showing up to 500 rows<?= $filters['summary_date'] !== '' ? ' for ' . htmlspecialchars($filters['summary_date']) : ', most recent dates first' ?>.</p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                DAILY_SALES_SUMMARY_LIST_SORT_COLUMNS,
                '/sales-reporting/daily-sales-summary',
                $filters,
                ['summary_date', 'sku'],
                DAILY_SALES_SUMMARY_LIST_SORT_NUMERIC,
                'summary_date',
                'desc',
                'summary_date'
            ); ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="6">No daily sales summary rows found. Run the daily sales summary job or adjust filters.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['SummaryDate'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['SKU'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['SKUName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['SKUDescription'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(daily_sales_summary_format_qty($row['QtySold'] ?? 0)) ?></td>
              <td><?= htmlspecialchars(substr((string) ($row['SummaryCaptureDate'] ?? ''), 0, 19)) ?></td>
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
