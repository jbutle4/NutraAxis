<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_create();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'white-label';
$templateOptions = label_template_options();
$error = null;
$form = [
    'external_order_id'     => '',
    'external_order_number' => '',
    'customer_name'         => '',
    'order_date'            => date('Y-m-d'),
    'order_status'          => 'Received',
    'ship_by_date'          => '',
    'notes'                 => '',
];
$lines = [['sku' => '', 'product_name' => '', 'quantity' => '', 'template_id' => '', 'line_status' => 'Open', 'notes' => '']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $lines = $_POST['lines'] ?? $lines;
    $result = wl_save_order($_POST);
    if ($result['ok']) {
        header('Location: /labeling-operations/white-label-orders/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = label_page_title('Import White Label Order');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/labeling-operations/white-label-orders/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to White Label Orders
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">White Label Production</div>
        <h1>Import Adobe Commerce Order</h1>
        <p class="page-lead">Enter order header and line item detail received from Adobe Commerce.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/labeling-operations/white-label-orders/new.php">
        <div class="form-grid">
          <div class="form-group">
            <label for="external_order_id">Adobe Commerce Order ID</label>
            <input class="form-input" type="text" id="external_order_id" name="external_order_id" value="<?= htmlspecialchars($form['external_order_id']) ?>" required />
          </div>
          <div class="form-group">
            <label for="external_order_number">Order Number</label>
            <input class="form-input" type="text" id="external_order_number" name="external_order_number" value="<?= htmlspecialchars($form['external_order_number']) ?>" />
          </div>
          <div class="form-group">
            <label for="customer_name">Customer Name</label>
            <input class="form-input" type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($form['customer_name']) ?>" required />
          </div>
          <div class="form-group">
            <label for="order_date">Order Date</label>
            <input class="form-input" type="date" id="order_date" name="order_date" value="<?= htmlspecialchars($form['order_date']) ?>" required />
          </div>
          <div class="form-group">
            <label for="ship_by_date">Ship By Date</label>
            <input class="form-input" type="date" id="ship_by_date" name="ship_by_date" value="<?= htmlspecialchars($form['ship_by_date']) ?>" />
          </div>
          <div class="form-group">
            <label for="order_status">Production Status</label>
            <select class="form-input" id="order_status" name="order_status">
              <?php foreach (WL_ORDER_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $form['order_status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Order Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars($form['notes']) ?></textarea>
          </div>
        </div>

        <div class="po-lines-section">
          <div class="po-lines-header">
            <h2>Line Items</h2>
          </div>
          <div class="admin-table-wrap">
            <table class="admin-table po-lines-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Product Name</th>
                  <th>Quantity</th>
                  <th>Label Template</th>
                  <th>Line Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $index => $line): ?>
                <tr>
                  <td><input class="form-input" type="text" name="lines[<?= $index ?>][sku]" value="<?= htmlspecialchars($line['sku'] ?? '') ?>" /></td>
                  <td><input class="form-input" type="text" name="lines[<?= $index ?>][product_name]" value="<?= htmlspecialchars($line['product_name'] ?? '') ?>" /></td>
                  <td><input class="form-input" type="number" step="0.0001" min="0" name="lines[<?= $index ?>][quantity]" value="<?= htmlspecialchars((string) ($line['quantity'] ?? '')) ?>" /></td>
                  <td>
                    <select class="form-input" name="lines[<?= $index ?>][template_id]">
                      <option value="">None</option>
                      <?php foreach ($templateOptions as $option): ?>
                      <option value="<?= (int) $option['id'] ?>" <?= (string) ($line['template_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option['label']) ?>
                      </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <select class="form-input" name="lines[<?= $index ?>][line_status]">
                      <?php foreach (WL_LINE_STATUSES as $status): ?>
                      <option value="<?= htmlspecialchars($status) ?>" <?= ($line['line_status'] ?? 'Open') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="module-actions">
          <button type="submit" class="btn-primary">Save Order</button>
          <a class="btn-secondary" href="/labeling-operations/white-label-orders/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
