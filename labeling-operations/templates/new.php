<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_create();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'templates';
$error = null;
$form = [
    'label_scope'      => 'Customer',
    'customer_name'    => '',
    'sku'              => '',
    'label_name'       => '',
    'template_status'  => 'Active',
    'notes'            => '',
    'version_notes'    => 'Initial version',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $result = label_save_template($_POST);
    if ($result['ok']) {
        header('Location: /labeling-operations/templates/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = label_page_title('New Label Template');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/labeling-operations/templates/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Label Templates
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Label Templates</div>
        <h1>New Label Template</h1>
        <p class="page-lead">Create a customer SKU label or internal label definition.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/labeling-operations/templates/new.php">
        <div class="form-grid">
          <div class="form-group">
            <label for="label_scope">Label Scope</label>
            <select class="form-input" id="label_scope" name="label_scope">
              <?php foreach (LABEL_SCOPES as $scope): ?>
              <option value="<?= htmlspecialchars($scope) ?>" <?= $form['label_scope'] === $scope ? 'selected' : '' ?>><?= htmlspecialchars($scope) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="customer_name">Customer Name</label>
            <input class="form-input" type="text" id="customer_name" name="customer_name" value="<?= htmlspecialchars($form['customer_name']) ?>" />
          </div>
          <div class="form-group">
            <label for="sku">SKU</label>
            <input class="form-input" type="text" id="sku" name="sku" value="<?= htmlspecialchars($form['sku']) ?>" required />
          </div>
          <div class="form-group">
            <label for="label_name">Label Name</label>
            <input class="form-input" type="text" id="label_name" name="label_name" value="<?= htmlspecialchars($form['label_name']) ?>" required />
          </div>
          <div class="form-group">
            <label for="template_status">Status</label>
            <select class="form-input" id="template_status" name="template_status">
              <?php foreach (LABEL_TEMPLATE_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $form['template_status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="version_notes">Initial Version Notes</label>
            <textarea class="form-input" id="version_notes" name="version_notes" rows="3"><?= htmlspecialchars($form['version_notes']) ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Template Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars($form['notes']) ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary">Create Template</button>
          <a class="btn-secondary" href="/labeling-operations/templates/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
