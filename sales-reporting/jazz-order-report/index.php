<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';
require dirname(__DIR__, 2) . '/includes/jazz-oms.php';

sales_reporting_require_read();

$activeSlug = $activeSlug ?? 'jazz-order-report';
$reportListPath = data_profile_page_path('/sales-reporting/jazz-order-report/');
$orderDetailPath = data_profile_page_path('/sales-reporting/jazz-order.php');
$reportListSortPath = data_profile_page_path('/sales-reporting/jazz-order-report');
$configError = jazz_oms_config_error();
$search = trim($_GET['order'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$listResult = ['ok' => true, 'error' => null, 'rows' => [], 'total' => 0, 'page' => $page, 'page_size' => jazz_oms_page_size(), 'has_next' => false];

$orderSortColumns = [
    'order_number'    => 'Order #',
    'order_date'      => 'Date',
    'status'          => 'Status',
    'po_number'       => 'PO #',
    'customer_number' => 'Customer #',
    'customer'        => 'Customer',
    'source_code'     => 'Source',
    'items'           => 'Items',
];
$listFilters = table_sort_state($orderSortColumns, 'order_date', 'desc', $_GET);
$apiSortable = in_array($listFilters['sort'], ['order_number', 'order_date', 'status', 'po_number', 'customer_number', 'source_code'], true);

if ($configError === null) {
    $filters = [];
    if ($search !== '') {
        $filters['order_number'] = $search;
    }
    if ($statusFilter !== '') {
        $filters['status'] = $statusFilter;
    }
    if ($apiSortable) {
        $filters['ordering'] = jazz_oms_order_list_ordering($listFilters);
    } else {
        $filters['ordering'] = '-order_date';
    }
    $listResult = jazz_oms_list_orders_report($page, $filters);
}

$orderSortAccessors = [
    'order_number'    => fn(array $row): string => (string) ($row['order_number'] ?? ''),
    'order_date'      => fn(array $row): string => (string) ($row['order_date'] ?? ''),
    'status'          => fn(array $row): string => (string) ($row['status'] ?? ''),
    'po_number'       => fn(array $row): string => (string) ($row['po_number'] ?? ''),
    'customer_number' => fn(array $row): string => (string) ($row['customer_number'] ?? ''),
    'customer'        => fn(array $row): string => jazz_oms_order_customer_label($row),
    'source_code'     => fn(array $row): string => (string) ($row['source_code'] ?? ''),
    'items'           => fn(array $row) => jazz_oms_order_item_qty($row),
];
if ($configError === null && ($listResult['rows'] ?? []) !== [] && !$apiSortable) {
    $listResult['rows'] = table_sort_rows(
        $listResult['rows'],
        $listFilters,
        $orderSortAccessors,
        ['items'],
        'order_date',
        'desc'
    );
}

$totalPages = $listResult['page_size'] > 0
    ? max(1, (int) ceil($listResult['total'] / $listResult['page_size']))
    : 1;
$paginationQuery = static function (int $targetPage) use ($listFilters, $statusFilter): string {
    $query = ['page' => $targetPage];
    if (($listFilters['sort'] ?? '') !== 'order_date' || ($listFilters['dir'] ?? '') !== 'desc') {
        $query['sort'] = $listFilters['sort'];
        $query['dir'] = $listFilters['dir'];
    }
    if ($statusFilter !== '') {
        $query['status'] = $statusFilter;
    }

    return '?' . http_build_query($query);
};

$pageTitle = 'Jazz Order Report | Sales Reporting Summaries';
$pageDescription = 'View Jazz OMS orders and order detail.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/sales-reporting/',
          'back_label' => 'Back to Sales Reporting Summaries',
          'category'   => 'Sales',
          'title'      => 'Jazz Order Report',
          'lead'       => 'Jazz OMS orders — search by order number or browse recent orders.',
          'permission' => permission_label(sales_reporting_permission_value()),
      ]); ?>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($orderDetailPath) ?>">
        <div class="audit-filter-grid">
          <div class="audit-filter-wide">
            <label for="order">Order number</label>
            <input class="form-input" type="search" id="order" name="order" value="<?= htmlspecialchars($search) ?>" placeholder="e.g. STG-000000105" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">View Order Detail</button>
        </div>
      </form>

      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($reportListSortPath) ?>">
        <?php table_sort_hidden_inputs($listFilters, 'order_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <input class="form-input" type="search" id="status" name="status" value="<?= htmlspecialchars($statusFilter) ?>" placeholder="e.g. NEW or PRINTED" />
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
          <strong>Jazz OMS connected</strong>
          <p>
            <?php if ($listResult['total'] > 0): ?>
            <?= (int) $listResult['total'] ?> order<?= (int) $listResult['total'] === 1 ? '' : 's' ?>
            <?php else: ?>
            Orders loaded
            <?php endif; ?>
            · page <?= (int) $listResult['page'] ?> of <?= $totalPages ?>
            · showing <?= count($listResult['rows']) ?>
            <?php if ($statusFilter !== ''): ?>
            · status filter <?= htmlspecialchars($statusFilter) ?>
            <?php endif; ?>
            · <?= htmlspecialchars(jazz_oms_base_url()) ?> · tenant <?= htmlspecialchars(jazz_oms_tenant_code()) ?>
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
                [],
                ['items'],
                'order_date',
                'desc',
                'order_date',
                'View'
            ); ?>
          </thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?>
            <tr><td colspan="9">No orders found.</td></tr>
            <?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['order_number'] ?? '')) ?></td>
              <td><?= htmlspecialchars(substr((string) ($row['order_date'] ?? ''), 0, 19)) ?></td>
              <td><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['po_number'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['customer_number'] ?? '')) ?></td>
              <td><?= htmlspecialchars(jazz_oms_order_customer_label($row)) ?></td>
              <td><?= htmlspecialchars((string) ($row['source_code'] ?? '')) ?></td>
              <td><?= jazz_oms_order_item_qty($row) ?></td>
              <?php table_actions_cell([
                  ['href' => $orderDetailPath . '?order=' . rawurlencode((string) ($row['order_number'] ?? '')), 'label' => 'View'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($search === '' && $totalPages > 1): ?>
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
