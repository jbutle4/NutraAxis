<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_create();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'batch-printing';
$error = null;
$form = [
    'run_date'   => date('Y-m-d'),
    'run_status' => 'Planned',
    'notes'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $result = label_save_order_run($_POST);
    if ($result['ok']) {
        header('Location: /labeling-operations/batch-printing/?notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = label_page_title('New Label Order Run');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/labeling-operations/batch-printing/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Label Batch Printing
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Label Batch Printing</div>
        <h1>New Label Order Run</h1>
        <p class="page-lead">Create a label order run to group third-party print orders.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/labeling-operations/batch-printing/new-run.php">
        <div class="form-grid">
          <div class="form-group">
            <label for="run_date">Run Date</label>
            <input class="form-input" type="date" id="run_date" name="run_date" value="<?= htmlspecialchars($form['run_date']) ?>" required />
          </div>
          <div class="form-group">
            <label for="run_status">Status</label>
            <select class="form-input" id="run_status" name="run_status">
              <?php foreach (LABEL_RUN_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $form['run_status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars($form['notes']) ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary">Create Run</button>
          <a class="btn-secondary" href="/labeling-operations/batch-printing/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
