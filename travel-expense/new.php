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
      <?php
      render_list_page_header([
          'back_href'  => '/travel-expense/',
          'back_label' => 'Back to Expense Reports',
          'category'   => 'Finance',
          'title'      => 'New Expense Report',
          'lead'       => 'Complete the NFC expense report form, attach receipts after saving, and submit for approval.',
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/te-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php require dirname(__DIR__) . '/includes/te-form.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
