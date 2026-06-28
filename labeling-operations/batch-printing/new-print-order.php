<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_create();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'batch-printing';
$runs = label_list_order_runs();
$error = null;
$form = [
    'run_id'                 => '',
    'vendor_name'            => '',
    'vendor_order_number'    => '',
    'order_status'           => 'Ordered',
    'order_date'             => date('Y-m-d'),
    'expected_delivery_date' => '',
    'notes'                  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $result = label_save_print_order($_POST);
    if ($result['ok']) {
        header('Location: /labeling-operations/batch-printing/?notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = label_page_title('New Print Order');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/labeling-operations/batch-printing/',
          'back_label' => 'Back to Label Batch Printing',
          'category'   => 'Label Batch Printing',
          'title'      => 'New Third-Party Print Order',
          'lead'       => 'Associate a vendor print order with a label order run.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/labeling-operations/batch-printing/new-print-order.php">
        <div class="form-grid">
          <div class="form-group">
            <label for="run_id">Label Order Run</label>
            <select class="form-input" id="run_id" name="run_id" required>
              <option value="">Select run</option>
              <?php foreach ($runs as $run): ?>
              <option value="<?= (int) $run['RunID'] ?>" <?= (string) $form['run_id'] === (string) $run['RunID'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($run['RunNumber'] . ' · ' . $run['RunStatus']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="vendor_name">Vendor Name</label>
            <input class="form-input" type="text" id="vendor_name" name="vendor_name" value="<?= htmlspecialchars($form['vendor_name']) ?>" required />
          </div>
          <div class="form-group">
            <label for="vendor_order_number">Vendor Order Number</label>
            <input class="form-input" type="text" id="vendor_order_number" name="vendor_order_number" value="<?= htmlspecialchars($form['vendor_order_number']) ?>" />
          </div>
          <div class="form-group">
            <label for="order_date">Order Date</label>
            <input class="form-input" type="date" id="order_date" name="order_date" value="<?= htmlspecialchars($form['order_date']) ?>" required />
          </div>
          <div class="form-group">
            <label for="expected_delivery_date">Expected Delivery</label>
            <input class="form-input" type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?= htmlspecialchars($form['expected_delivery_date']) ?>" />
          </div>
          <div class="form-group">
            <label for="order_status">Status</label>
            <select class="form-input" id="order_status" name="order_status">
              <?php foreach (LABEL_PRINT_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $form['order_status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars($form['notes']) ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary">Create Print Order</button>
          <a class="btn-secondary" href="/labeling-operations/batch-printing/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
