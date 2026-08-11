<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';
require dirname(__DIR__, 2) . '/includes/sales-rep-reporting.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

sales_reporting_require_read();

$activeSlug = $activeSlug ?? 'sales-rep-reporting';
$reportListPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/');
$refreshPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/refresh.php');
$exportPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/export.php');
$orderViewPath = data_profile_page_path('/sales-reporting/sales-rep-reporting/view.php');
$sourceEnvironment = sales_rep_reporting_source_environment();
$pageSize = sales_rep_reporting_page_size($_GET['limit'] ?? SALES_REP_REPORTING_DEFAULT_PAGE_SIZE);

$filters = [
    'q'         => trim($_GET['q'] ?? ''),
    'rep'       => trim($_GET['rep'] ?? ''),
    'status'    => trim($_GET['status'] ?? ''),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to'   => trim($_GET['date_to'] ?? ''),
    'limit'     => $pageSize,
] + table_sort_state(SALES_REP_REPORTING_SORT_COLUMNS, 'date', 'desc', $_GET);

$rows = [];
$dbError = null;
$lastSyncedAt = null;
$statuses = [];
$reps = [];

try {
    $rows = sales_rep_reporting_list($filters);
    $rows = table_sort_rows(
        $rows,
        $filters,
        [
            'order_id'      => fn(array $r): string => (string) ($r['order_id'] ?? ''),
            'date'          => fn(array $r): string => (string) ($r['date'] ?? ''),
            'amount'        => fn(array $r) => $r['amount'] ?? 0,
            'item_subtotal' => fn(array $r) => $r['item_subtotal'] ?? 0,
            'status'        => fn(array $r): string => (string) ($r['status'] ?? ''),
            'sales_rep'     => fn(array $r): string => (string) ($r['sales_rep'] ?? ''),
            'company_name'  => fn(array $r): string => (string) ($r['company_name'] ?? ''),
            'company_id'    => fn(array $r) => $r['company_id'] ?? 0,
            'company_state' => fn(array $r): string => (string) ($r['company_state'] ?? ''),
            'company_zip'   => fn(array $r): string => (string) ($r['company_zip'] ?? ''),
            'purchaser'     => fn(array $r): string => (string) ($r['purchaser'] ?? ''),
            'ship_state'    => fn(array $r): string => (string) ($r['ship_state'] ?? ''),
            'ship_zip'      => fn(array $r): string => (string) ($r['ship_zip'] ?? ''),
        ],
        SALES_REP_REPORTING_SORT_NUMERIC,
        'date',
        'desc'
    );
    $lastSyncedAt = sales_rep_reporting_last_synced_at($sourceEnvironment);
    $statuses = sales_rep_reporting_distinct_statuses();
    $reps = sales_rep_reporting_distinct_reps();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$notice = $_GET['notice'] ?? null;
$errorNotice = trim((string) ($_GET['error'] ?? ''));
$filterPreserve = ['q', 'rep', 'status', 'date_from', 'date_to', 'limit'];

$pageTitle = 'Sales Rep Reporting | Sales Reporting Summaries';
$pageDescription = data_profile_is_uat()
    ? 'UAT Sales Rep Reporting from ACCS Stage orders and territory assignments.'
    : 'Sales Rep Reporting from ACCS production orders and territory assignments.';

$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main page-main--fluid">
    <div class="container page-inner page-inner--full page-inner--sales-rep-reporting">
      <?php
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Sales',
          'title'      => 'Sales Rep Reporting' . (data_profile_is_uat() ? ' (UAT)' : ''),
          'lead'       => data_profile_is_uat()
              ? 'Reads synced ACCS Stage orders from SQL and matches billing state/zip to territory assignments when a Company ID is present. Refresh runs the Stage sync job (manual only).'
              : 'Reads synced ACCS production orders from SQL and matches billing state/zip to territory assignments when a Company ID is present. Refresh runs the production sync job (also hourly).',
          'permission' => permission_label(sales_reporting_permission_value()),
      ]);
      ?>

      <div class="status-banner">
        <div>
          <strong>Last data refresh</strong>
          <p>
            <?= $lastSyncedAt !== null
                ? htmlspecialchars(admin_format_datetime($lastSyncedAt))
                : 'No synced ACCS orders found for ' . htmlspecialchars($sourceEnvironment) . '.' ?>
            · Source: <?= htmlspecialchars($sourceEnvironment) ?>
            <?= data_profile_is_uat() ? ' · Auto-schedule: off (manual refresh)' : ' · Auto-schedule: hourly' ?>
          </p>
        </div>
        <?php if (sales_rep_reporting_can_refresh()): ?>
        <form method="post" action="<?= htmlspecialchars($refreshPath) ?>" onsubmit="return confirm('Run ACCS sales order sync for <?= htmlspecialchars($sourceEnvironment) ?>? This may take several minutes.');">
          <button type="submit" class="btn-primary">Refresh data</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'refresh_success'): ?>
      <div class="admin-notice is-success" role="status">ACCS sales order sync completed. Report data has been refreshed.</div>
      <?php elseif ($notice === 'refresh_failed'): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($errorNotice !== '' ? $errorNotice : 'ACCS sales order sync failed.') ?></div>
      <?php endif; ?>

      <?php if ($dbError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($dbError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($reportListPath) ?>">
        <?php table_sort_hidden_inputs($filters, 'date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Order #, purchaser, company, email" />
          </div>
          <div>
            <label for="rep">Sales rep</label>
            <select class="form-input" id="rep" name="rep">
              <option value="">All reps</option>
              <?php foreach ($reps as $rep): ?>
              <option value="<?= htmlspecialchars($rep) ?>" <?= $filters['rep'] === $rep ? 'selected' : '' ?>><?= htmlspecialchars($rep) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach ($statuses as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="date_from">Date from</label>
            <input class="form-input" type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" />
          </div>
          <div>
            <label for="date_to">Date to</label>
            <input class="form-input" type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" />
          </div>
          <div>
            <label for="limit">Rows per page</label>
            <select class="form-input" id="limit" name="limit">
              <?php foreach (SALES_REP_REPORTING_PAGE_SIZES as $size): ?>
              <option value="<?= (int) $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= (int) $size ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($reportListPath) ?>">Clear</a>
        </div>
      </form>

      <div class="status-banner">
        <div>
          <strong><?= count($rows) ?> order<?= count($rows) === 1 ? '' : 's' ?></strong>
          <p>Showing up to <?= (int) $pageSize ?> rows from synced ACCS <?= htmlspecialchars($sourceEnvironment) ?> orders.</p>
        </div>
        <?php
        $exportQuery = array_filter([
            'q'         => $filters['q'] !== '' ? $filters['q'] : null,
            'rep'       => $filters['rep'] !== '' ? $filters['rep'] : null,
            'status'    => $filters['status'] !== '' ? $filters['status'] : null,
            'date_from' => $filters['date_from'] !== '' ? $filters['date_from'] : null,
            'date_to'   => $filters['date_to'] !== '' ? $filters['date_to'] : null,
            'sort'      => $filters['sort'] ?? null,
            'dir'       => $filters['dir'] ?? null,
        ], static fn($v) => $v !== null && $v !== '');
        $exportHref = $exportPath . ($exportQuery !== [] ? ('?' . http_build_query($exportQuery)) : '');
        ?>
        <a class="btn-secondary" href="<?= htmlspecialchars($exportHref) ?>">Export CSV</a>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table admin-table--sales-rep-reporting">
          <thead>
            <?php table_sort_render_head_row(
                SALES_REP_REPORTING_SORT_COLUMNS,
                rtrim($reportListPath, '/'),
                $filters,
                $filterPreserve,
                SALES_REP_REPORTING_SORT_NUMERIC,
                'date',
                'desc'
            ); ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="13">No orders found. Refresh ACCS data or adjust filters.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <?php if ((string) $row['order_id'] !== ''): ?>
                <a href="<?= htmlspecialchars($orderViewPath . '?order=' . rawurlencode((string) $row['order_id'])) ?>"><?= htmlspecialchars((string) $row['order_id']) ?></a>
                <?php else: ?>
                —
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars(sales_rep_reporting_format_date($row['date'] ?? null)) ?></td>
              <td><?= htmlspecialchars(sales_rep_reporting_format_money($row['amount'] ?? 0)) ?></td>
              <td><?= htmlspecialchars(sales_rep_reporting_format_money($row['item_subtotal'] ?? 0)) ?></td>
              <td><?= htmlspecialchars((string) ($row['status'] !== '' ? $row['status'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['sales_rep'] !== '' ? $row['sales_rep'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['company_name'] !== '' ? $row['company_name'] : '—')) ?></td>
              <td><?= htmlspecialchars($row['company_id'] !== null ? (string) $row['company_id'] : '—') ?></td>
              <td><?= htmlspecialchars((string) ($row['company_state'] !== '' ? $row['company_state'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['company_zip'] !== '' ? $row['company_zip'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['purchaser'] !== '' ? $row['purchaser'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['ship_state'] !== '' ? $row['ship_state'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['ship_zip'] !== '' ? $row['ship_zip'] : '—')) ?></td>
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
