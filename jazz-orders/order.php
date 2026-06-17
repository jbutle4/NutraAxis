<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/jazz-order-report.php';

jazz_order_report_require_read();

$activeSlug = 'jazz-orders';
$orderNumber = trim($_GET['order'] ?? '');
if ($orderNumber === '') {
    header('Location: /jazz-orders/', true, 302);
    exit;
}

$configError = jazz_oms_config_error();
$result = $configError === null
    ? jazz_oms_get_order($orderNumber)
    : ['ok' => false, 'error' => $configError, 'row' => null];
$order = $result['row'] ?? null;
$details = is_array($order['detail_set'] ?? null) ? $order['detail_set'] : [];
$detailColumns = jazz_order_report_detail_columns($details);

$pageTitle = 'Jazz Order ' . $orderNumber . ' | Supply Chain Management';
$pageDescription = 'Jazz OMS order detail.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <a class="breadcrumb" href="<?= htmlspecialchars(jazz_order_report_list_href()) ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Jazz Orders
      </a>

      <?php if ($configError !== null): ?>
      <div class="admin-header">
        <div>
          <div class="section-label">Supply Chain</div>
          <h1>Jazz Order <?= htmlspecialchars($orderNumber) ?></h1>
        </div>
      </div>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>

      <?php elseif (!$result['ok'] || $order === null): ?>
      <div class="admin-header">
        <div>
          <div class="section-label">Supply Chain</div>
          <h1>Jazz Order <?= htmlspecialchars($orderNumber) ?></h1>
        </div>
      </div>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error'] ?? 'Order not found.') ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="<?= htmlspecialchars(jazz_order_report_list_href()) ?>">Back to Jazz Orders</a>
      </div>

      <?php else: ?>
      <div class="admin-header">
        <div>
          <div class="section-label">Supply Chain</div>
          <h1>Order <?= htmlspecialchars((string) ($order['order_number'] ?? $orderNumber)) ?></h1>
          <p class="page-lead">
            <?= htmlspecialchars((string) ($order['order_date'] ?? '')) ?>
            · <span class="status-badge <?= jazz_order_report_status_class((string) ($order['status'] ?? '')) ?>"><?= htmlspecialchars((string) ($order['status'] ?? '—')) ?></span>
          </p>
        </div>
      </div>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Order Summary</h2>
          <dl class="detail-list">
            <div><dt>PO number</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['po_number'] ?? null)) ?></dd></div>
            <div><dt>Source</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['source_code'] ?? null)) ?></dd></div>
            <div><dt>Offer code</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['offer_code'] ?? null)) ?></dd></div>
            <div><dt>Business type</dt><dd><?= htmlspecialchars(jazz_oms_format_cell($order['business_type_code'] ?? null)) ?></dd></div>
            <div><dt>Qty ordered</dt><dd><?= htmlspecialchars(jazz_oms_format_quantity($order['qty_ordered'] ?? null)) ?></dd></div>
            <div><dt>Qty shipped</dt><dd><?= htmlspecialchars(jazz_oms_format_quantity($order['qty_shipped'] ?? null)) ?></dd></div>
            <div><dt>Qty allocated</dt><dd><?= htmlspecialchars(jazz_oms_format_quantity($order['qty_allocated'] ?? null)) ?></dd></div>
            <div><dt>Qty backordered</dt><dd><?= htmlspecialchars(jazz_oms_format_quantity($order['qty_backordered'] ?? null)) ?></dd></div>
          </dl>
        </section>
      </div>

      <div class="admin-table-wrap production-status-table-wrap">
        <table class="admin-table production-status-table">
          <thead>
            <tr>
              <?php foreach ($detailColumns as $column): ?>
              <th><?= htmlspecialchars(jazz_oms_field_label($column)) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($details === []): ?>
            <tr><td colspan="<?= max(1, count($detailColumns)) ?>">No line items on this order.</td></tr>
            <?php else: ?>
            <?php foreach ($details as $line): ?>
            <?php if (!is_array($line)) { continue; } ?>
            <tr>
              <?php foreach ($detailColumns as $column): ?>
              <td><?= htmlspecialchars(jazz_oms_format_cell($line[$column] ?? null)) ?></td>
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
