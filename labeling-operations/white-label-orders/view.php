<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$wlpoId = (int) ($_GET['id'] ?? 0);
$order = $wlpoId > 0 ? wl_get_order($wlpoId) : null;

if ($order === null) {
    header('Location: /labeling-operations/white-label-orders/', true, 302);
    exit;
}

$activeSlug = 'labeling-operations';
$activeLabelSection = 'white-label';
$lines = wl_get_lines($wlpoId);
$notice = $_GET['notice'] ?? null;

$pageTitle = label_page_title('White Label Order');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/labeling-operations/white-label-orders/',
          'back_label' => 'Back to White Label Orders',
          'category'   => 'White Label Production',
          'title'      => 'Order ' . ($order['ExternalOrderNumber'] ?? $order['ExternalOrderID']),
          'lead'       => 'Adobe Commerce order imported for production tracking.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">White label production order saved successfully.</div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Order Header</h2>
          <dl class="detail-list">
            <div><dt>Adobe Commerce Order ID</dt><dd><?= htmlspecialchars($order['ExternalOrderID']) ?></dd></div>
            <div><dt>Order Number</dt><dd><?= htmlspecialchars($order['ExternalOrderNumber'] ?? '—') ?></dd></div>
            <div><dt>Customer</dt><dd><?= htmlspecialchars($order['CustomerName']) ?></dd></div>
            <div><dt>Order Date</dt><dd><?= htmlspecialchars(label_format_date($order['OrderDate'])) ?></dd></div>
            <div><dt>Ship By</dt><dd><?= htmlspecialchars(label_format_date($order['ShipByDate'])) ?></dd></div>
            <div><dt>Status</dt><dd><?= htmlspecialchars($order['OrderStatus']) ?></dd></div>
            <div><dt>Source System</dt><dd><?= htmlspecialchars($order['SourceSystem']) ?></dd></div>
            <div><dt>Imported</dt><dd><?= htmlspecialchars(label_format_datetime($order['ImportedDate'])) ?></dd></div>
          </dl>
          <?php if (!empty($order['Notes'])): ?>
          <p><?= nl2br(htmlspecialchars($order['Notes'])) ?></p>
          <?php endif; ?>
        </section>

        <section class="detail-card">
          <h2>Line Items</h2>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>SKU</th>
                  <th>Product</th>
                  <th>Qty</th>
                  <th>Label Template</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($lines === []): ?>
                <tr><td colspan="6">No line items.</td></tr>
                <?php else: ?>
                <?php foreach ($lines as $line): ?>
                <tr>
                  <td><?= (int) $line['LineNumber'] ?></td>
                  <td><?= htmlspecialchars($line['SKU']) ?></td>
                  <td><?= htmlspecialchars($line['ProductName']) ?></td>
                  <td><?= htmlspecialchars((string) $line['Quantity']) ?></td>
                  <td><?= htmlspecialchars($line['LabelName'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($line['LineStatus']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
