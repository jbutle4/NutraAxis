<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/sales-reporting.php';

sales_reporting_require_read();

$activeSlug = 'accs-order-report';
$orderNumber = trim($_GET['order'] ?? '');
$configError = adobe_commerce_config_error();
$order = null;
$error = $configError;

if ($error === null) {
    if ($orderNumber === '') {
        header('Location: /sales-reporting/accs-order-report/', true, 302);
        exit;
    }

    $result = adobe_commerce_fetch_order_by_number($orderNumber);
    if ($result['ok']) {
        $order = $result['order'];
    } else {
        $error = $result['error'];
    }
}

$pageTitle = ($orderNumber !== '' ? 'Order ' . $orderNumber : 'Order Detail') . ' | ACCS Order Report';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/sales-reporting/accs-order-report/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to ACCS Order Report
      </a>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/sales-reporting/accs-order-report/">Back to Orders</a>
      </div>
      <?php elseif ($order !== null): ?>
      <div class="admin-header">
        <div>
          <div class="section-label">Adobe Commerce</div>
          <h1>Order #<?= htmlspecialchars((string) ($order['increment_id'] ?? $orderNumber)) ?></h1>
          <p class="page-lead">
            <?= htmlspecialchars(substr((string) ($order['created_at'] ?? ''), 0, 19)) ?>
            · <?= htmlspecialchars((string) ($order['status'] ?? '')) ?>
            · <?= htmlspecialchars((string) ($order['store_name'] ?? '')) ?>
          </p>
        </div>
      </div>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Customer</h2>
          <dl class="detail-list">
            <div><dt>Name</dt><dd><?= htmlspecialchars(trim((string) ($order['customer_firstname'] ?? '') . ' ' . (string) ($order['customer_lastname'] ?? ''))) ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars((string) ($order['customer_email'] ?? '—')) ?></dd></div>
            <div><dt>Ship method</dt><dd><?= htmlspecialchars((string) ($order['shipping_description'] ?? '—')) ?></dd></div>
            <div><dt>Payment</dt><dd><?= htmlspecialchars((string) ($order['payment']['method'] ?? '—')) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Ship to</h2>
          <dl class="detail-list">
            <div><dt>Address</dt><dd><?= nl2br(htmlspecialchars(adobe_commerce_order_shipping_lines($order))) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Totals</h2>
          <dl class="detail-list">
            <div><dt>Subtotal</dt><dd><?= htmlspecialchars(adobe_commerce_format_money($order['subtotal'] ?? null)) ?></dd></div>
            <div><dt>Shipping</dt><dd><?= htmlspecialchars(adobe_commerce_format_money($order['shipping_amount'] ?? null)) ?></dd></div>
            <div><dt>Tax</dt><dd><?= htmlspecialchars(adobe_commerce_format_money($order['tax_amount'] ?? null)) ?></dd></div>
            <?php if (!empty($order['discount_amount'])): ?>
            <div><dt>Discount</dt><dd><?= htmlspecialchars(adobe_commerce_format_money($order['discount_amount'])) ?></dd></div>
            <?php endif; ?>
            <div><dt>Grand total</dt><dd><strong><?= htmlspecialchars(adobe_commerce_format_money($order['grand_total'] ?? null)) ?></strong></dd></div>
          </dl>
        </section>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Product</th>
              <th>Type</th>
              <th>Qty ordered</th>
              <th>Qty shipped</th>
              <th>Unit price</th>
              <th>Row total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($order['items'] ?? []) as $item): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($item['sku'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($item['name'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($item['product_type'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($item['qty_ordered'] ?? '0')) ?></td>
              <td><?= htmlspecialchars((string) ($item['qty_shipped'] ?? '0')) ?></td>
              <td><?= htmlspecialchars(adobe_commerce_format_money($item['price'] ?? null)) ?></td>
              <td><?= htmlspecialchars(adobe_commerce_format_money($item['row_total'] ?? null)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
