<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';

te_require_create();

$activeSlug = 'travel-expense';
$activeTeSection = 'new';
$error = null;
$form = te_default_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, te_form_from_post($_POST));
    $result = te_save_report(te_form_from_post($_POST));

    if ($result['ok']) {
        header('Location: /travel-expense/edit.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Expense Report | Travel & Expense';
$pageDescription = 'Create a new travel and expense reimbursement report.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/travel-expense/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Expense Reports
      </a>

      <?php require dirname(__DIR__) . '/includes/te-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Finance</div>
        <h1>New Expense Report</h1>
        <p class="page-lead">Complete the NFC expense report form, attach receipts after saving, and submit for approval.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php require dirname(__DIR__) . '/includes/te-form.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
