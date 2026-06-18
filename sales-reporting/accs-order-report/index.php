<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/page-data-profile.php';
require dirname(__DIR__, 2) . '/includes/sales-reporting.php';

sales_reporting_require_read();

$activeSlug = $activeSlug ?? 'accs-order-report';
$reportListPath = data_profile_is_uat()
    ? '/sales-reporting/accs-order-report-uat/'
    : '/sales-reporting/accs-order-report/';
$orderDetailPath = data_profile_is_uat()
    ? '/sales-reporting/order-uat.php'
    : '/sales-reporting/order.php';
$reportListSortPath = data_profile_is_uat()
    ? '/sales-reporting/accs-order-report-uat'
    : '/sales-reporting/accs-order-report';
$orderDetailPath = data_profile_is_uat()
    ? '/sales-reporting/order-uat.php'
    : '/sales-reporting/order.php';
$configError = adobe_commerce_config_error();
$search = trim($_GET['order'] ?? '');
$listResult = ['ok' => true, 'error' => null, 'rows' => [], 'total' => 0];

if ($configError === null) {
    $listResult = adobe_commerce_list_orders();
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

$pageTitle = 'ACCS Order Report | Sales Reporting Summaries';
$pageDescription = 'View Adobe Commerce orders and order detail from ACCS.';

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
          <h1>ACCS Order Report</h1>
          <p class="page-lead">Adobe Commerce (ACCS) orders — search by order number or browse recent orders.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(sales_reporting_permission_value())) ?></p>
        </div>
      </div>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php else: ?>

      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($orderDetailPath) ?>">
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

      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Adobe Commerce connected</strong>
          <p><?= (int) $listResult['total'] ?> total orders in <?= htmlspecialchars(adobe_commerce_environment()) ?> · showing <?= count($listResult['rows']) ?> most recent · <?= htmlspecialchars(adobe_commerce_base_url()) ?></p>
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
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
