<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-attachments.php';

te_require_update();

$reportId = (int) ($_GET['id'] ?? 0);
$report = te_get_report($reportId);

if ($report === null) {
    http_response_code(404);
    $pageTitle = 'Report Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Expense report not found</h1><div class="module-actions"><a class="btn-secondary" href="/travel-expense/">Back to Expense Reports</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if (!te_can_edit_report($report)) {
    auth_render_access_denied('This expense report cannot be edited in its current status.');
}

$activeSlug = 'travel-expense';
$activeTeSection = 'list';
$error = null;
$form = te_default_form($report);
$notice = $_GET['notice'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, te_form_from_post($_POST));
    $result = te_save_report(te_form_from_post($_POST), $reportId);

    if ($result['ok']) {
        header('Location: /travel-expense/view.php?id=' . $reportId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit ' . $report['ReportNumber'] . ' | Travel & Expense';
$pageDescription = 'Edit travel and expense report details.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/travel-expense/view.php?id=<?= $reportId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars($report['ReportNumber']) ?>
      </a>

      <?php require dirname(__DIR__) . '/includes/te-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Finance</div>
        <h1>Edit <?= htmlspecialchars($report['ReportNumber']) ?></h1>
        <p class="page-lead">Update expense lines, mileage, and receipt attachments before submitting for approval.</p>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Expense report created. Add receipt PDFs below, then submit for approval when ready.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $formAction = '/travel-expense/edit.php?id=' . $reportId;
        require dirname(__DIR__) . '/includes/te-form.php';
      ?>

      <?php
        $showUploadForm = te_can_add_attachments($report);
        require dirname(__DIR__) . '/includes/te-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
