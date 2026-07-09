<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';

sales_reporting_require_read();

$activeSlug = $activeSlug ?? 'accs-order-report';
$reportListPath = data_profile_page_path('/sales-reporting/accs-order-report/');
$orderDetailPath = data_profile_page_path('/sales-reporting/order.php');
$reportListSortPath = data_profile_page_path('/sales-reporting/accs-order-report');
$configError = adobe_commerce_config_error();
$search = trim($_GET['order'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$listResult = [
    'ok'        => true,
    'error'     => null,
    'rows'      => [],
    'total'     => 0,
    'page'      => $page,
    'page_size' => adobe_commerce_page_size(),
];

if ($configError === null) {
    $apiFilters = [];
    if ($statusFilter !== '') {
        $apiFilters['status'] = $statusFilter;
    }
    $listResult = adobe_commerce_list_orders($page, $apiFilters);
}
$orderSortColumns = [
    'order_number' => 'Order #',
    'date'         => 'Date',
    'status'       => 'Status',
    'customer'     => 'Customer',
    'total'        => 'Total',
    'items'        => 'Items',
];
$listFilters = table_sort_state($orderSortColumns, 'date', 'desc', $_GET);
if ($statusFilter !== '') {
    $listFilters['status'] = $statusFilter;
}
$orderSortAccessors = [
    'order_number' => fn(array $row): string => (string) ($row['increment_id'] ?? ''),
    'date'         => fn(array $row): string => (string) ($row['created_at'] ?? ''),
    'status'       => fn(array $row): string => (string) ($row['status'] ?? ''),
    'customer'     => fn(array $row): string => trim((string) ($row['customer_firstname'] ?? '') . ' ' . (string) ($row['customer_lastname'] ?? '')),
    'total'        => fn(array $row) => $row['grand_total'] ?? 0,
    'items'        => fn(array $row) => adobe_commerce_order_item_qty($row),
];
if ($configError === null && ($listResult['rows'] ?? []) !== []) {
    $listResult['rows'] = table_sort_rows(
        $listResult['rows'],
        $listFilters,
        $orderSortAccessors,
        ['total', 'items'],
        'date',
        'desc'
    );
}

$totalPages = $listResult['page_size'] > 0
    ? max(1, (int) ceil($listResult['total'] / $listResult['page_size']))
    : 1;
$paginationQuery = static function (int $targetPage) use ($listFilters, $statusFilter): string {
    $query = ['page' => $targetPage];
    if (($listFilters['sort'] ?? '') !== 'date' || ($listFilters['dir'] ?? '') !== 'desc') {
        $query['sort'] = $listFilters['sort'];
        $query['dir'] = $listFilters['dir'];
    }
    if ($statusFilter !== '') {
        $query['status'] = $statusFilter;
    }

    return '?' . http_build_query($query);
};

$pageTitle = 'ACCS Order Report | Sales Reporting Summaries';
$pageDescription = 'View Adobe Commerce orders and order detail from ACCS.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/sales-reporting/',
          'back_label' => 'Back to Sales Reporting Summaries',
          'category'   => 'Sales',
          'title'      => 'ACCS Order Report',
          'lead'       => 'Adobe Commerce (ACCS) orders — search by order number or browse recent orders.',
          'permission' => permission_label(sales_reporting_permission_value()),
      ]); ?>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($orderDetailPath) ?>">
        <div class="audit-filter-grid">
          <div class="audit-filter-wide">
            <label for="order">Order number</label>
            <input class="form-input" type="search" id="order" name="order" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. 000000062" required />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">View Order Detail</button>
        </div>
      </form>

      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($reportListSortPath) ?>">
        <?php table_sort_hidden_inputs($listFilters, 'date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (adobe_commerce_order_status_options() as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-secondary">Apply Filters</button>
          <?php if ($statusFilter !== ''): ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($reportListSortPath) ?>">Clear filters</a>
          <?php endif; ?>
        </div>
      </form>

      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Adobe Commerce connected</strong>
          <p>
            <?= (int) $listResult['total'] ?> order<?= (int) $listResult['total'] === 1 ? '' : 's' ?> in <?= htmlspecialchars(adobe_commerce_environment()) ?>
            · page <?= (int) $listResult['page'] ?> of <?= $totalPages ?>
            · showing <?= count($listResult['rows']) ?>
            <?php if ($statusFilter !== ''): ?>
            · status filter <?= htmlspecialchars($statusFilter) ?>
            <?php endif; ?>
            · <?= htmlspecialchars(adobe_commerce_base_url()) ?>
          </p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                $orderSortColumns,
                $reportListSortPath,
                $listFilters,
                ['status'],
                ['total', 'items'],
                'date',
                'desc',
                'date',
                'View'
            ); ?>
          </thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?>
            <tr><td colspan="7">No orders found.</td></tr>
            <?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['increment_id'] ?? '')) ?></td>
              <td><?= htmlspecialchars(substr((string) ($row['created_at'] ?? ''), 0, 19)) ?></td>
              <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
              <td><?= htmlspecialchars(trim((string) ($row['customer_firstname'] ?? '') . ' ' . (string) ($row['customer_lastname'] ?? ''))) ?></td>
              <td><?= htmlspecialchars(adobe_commerce_format_money($row['grand_total'] ?? null)) ?></td>
              <td><?= adobe_commerce_order_item_qty($row) ?></td>
              <?php table_actions_cell([
                  ['href' => $orderDetailPath . '?order=' . rawurlencode((string) ($row['increment_id'] ?? '')), 'label' => 'View'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="module-actions">
        <?php if ($listResult['page'] > 1): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars($reportListSortPath . $paginationQuery($listResult['page'] - 1)) ?>">Previous page</a>
        <?php endif; ?>
        <?php if ($listResult['page'] < $totalPages): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars($reportListSortPath . $paginationQuery($listResult['page'] + 1)) ?>">Next page</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
