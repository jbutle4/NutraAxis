<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'batch-printing';
$listFilters = $_GET;
$runs = label_list_order_runs();
$printOrders = label_list_print_orders();
$runsSortFilters = $listFilters;
$printsSortFilters = $listFilters;
$batchQueryKeys = ['sort_runs', 'dir_runs', 'sort_prints', 'dir_prints'];
$notice = $_GET['notice'] ?? null;

$pageTitle = label_page_title('Label Batch Printing');
$pageDescription = 'Track third-party print orders associated with label order runs.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/labeling-operations/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars(label_module_title()) ?>
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Label Batch Printing</div>
          <h1>Label Order Runs &amp; Print Orders</h1>
          <p class="page-lead">Track label order runs and the third-party print orders associated with each run.</p>
        </div>
        <?php if (label_can_create()): ?>
        <a class="btn-primary" href="/labeling-operations/batch-printing/new-run.php">New Order Run</a>
        <a class="btn-secondary" href="/labeling-operations/batch-printing/new-print-order.php">New Print Order</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Record created successfully.</div>
      <?php endif; ?>

      <section class="detail-card">
        <h2>Label Order Runs</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <?php table_sort_render_head_row(
                  LABEL_RUN_LIST_SORT_COLUMNS,
                  '/labeling-operations/batch-printing',
                  $runsSortFilters,
                  $batchQueryKeys,
                  LABEL_RUN_LIST_SORT_NUMERIC,
                  'run_date',
                  'desc',
                  'run_date',
                  null,
                  'sort_runs',
                  'dir_runs'
              ); ?>
            </thead>
            <tbody>
              <?php if ($runs === []): ?>
              <tr><td colspan="5">No label order runs yet.</td></tr>
              <?php else: ?>
              <?php foreach ($runs as $run): ?>
              <tr>
                <td><?= htmlspecialchars($run['RunNumber']) ?></td>
                <td><?= htmlspecialchars(label_format_date($run['RunDate'])) ?></td>
                <td><?= htmlspecialchars($run['RunStatus']) ?></td>
                <td><?= (int) $run['PrintOrderCount'] ?></td>
                <td><?= htmlspecialchars($run['CreatedByName']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="detail-card">
        <h2>Third-Party Print Orders</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <?php table_sort_render_head_row(
                  LABEL_PRINT_LIST_SORT_COLUMNS,
                  '/labeling-operations/batch-printing',
                  $printsSortFilters,
                  $batchQueryKeys,
                  [],
                  'order_date',
                  'desc',
                  'order_date',
                  null,
                  'sort_prints',
                  'dir_prints'
              ); ?>
            </thead>
            <tbody>
              <?php if ($printOrders === []): ?>
              <tr><td colspan="6">No batch print orders yet.</td></tr>
              <?php else: ?>
              <?php foreach ($printOrders as $order): ?>
              <tr>
                <td><?= htmlspecialchars($order['VendorName']) ?></td>
                <td><?= htmlspecialchars($order['VendorOrderNumber'] ?? '—') ?></td>
                <td><?= htmlspecialchars($order['RunNumber']) ?></td>
                <td><?= htmlspecialchars(label_format_date($order['OrderDate'])) ?></td>
                <td><?= htmlspecialchars($order['OrderStatus']) ?></td>
                <td><?= htmlspecialchars(label_format_date($order['ExpectedDeliveryDate'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
