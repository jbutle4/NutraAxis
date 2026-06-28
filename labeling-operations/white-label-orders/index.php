<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'white-label';
$statusFilter = $_GET['status'] ?? '';
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
] + table_sort_state(WL_LIST_SORT_COLUMNS, 'order_date', 'desc', $_GET);
$orders = wl_list_orders($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = label_page_title('White Label Production Orders');
$pageDescription = 'Track white label production orders received from Adobe Commerce.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = label_can_create() ? '<a class="btn-primary" href="/labeling-operations/white-label-orders/new.php">Import Order</a>' : '';
      render_list_page_header([
          'back_href'  => '/labeling-operations/',
          'back_label' => 'Back to ' . label_module_title(),
          'category'   => 'White Label Production',
          'title'      => 'Adobe Commerce Orders',
          'lead'       => 'Track production orders received from Adobe Commerce with order header and line item detail.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">White label production order saved successfully.</div>
      <?php endif; ?>

      <form class="po-filter" method="get" action="/labeling-operations/white-label-orders/">
        <?php table_sort_hidden_inputs($listFilters, 'order_date', 'desc'); ?>
        <label for="status">Filter by status</label>
        <select class="form-input" id="status" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (WL_ORDER_STATUSES as $status): ?>
          <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                WL_LIST_SORT_COLUMNS,
                '/labeling-operations/white-label-orders',
                $listFilters,
                ['status'],
                WL_LIST_SORT_NUMERIC,
                'order_date',
                'desc',
                'order_date',
                'View'
            ); ?>
          </thead>
          <tbody>
            <?php if ($orders === []): ?>
            <tr><td colspan="8">No white label production orders found.</td></tr>
            <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= htmlspecialchars($order['ExternalOrderID']) ?></td>
              <td><?= htmlspecialchars($order['ExternalOrderNumber'] ?? '—') ?></td>
              <td><?= htmlspecialchars($order['CustomerName']) ?></td>
              <td><?= htmlspecialchars(label_format_date($order['OrderDate'])) ?></td>
              <td><?= htmlspecialchars($order['OrderStatus']) ?></td>
              <td><?= (int) $order['LineCount'] ?></td>
              <td><?= htmlspecialchars(label_format_datetime($order['ImportedDate'])) ?></td>
              <?php table_actions_cell([
                  ['href' => '/labeling-operations/white-label-orders/view.php?id=' . (int) $order['WLPOID'], 'label' => 'View'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
