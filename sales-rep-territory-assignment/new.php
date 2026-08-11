<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/sales-rep-territory.php';

sales_rep_territory_require_create();

$activeSlug = 'sales-rep-territory-assignment';
$error = null;
$form = sales_rep_territory_to_form();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = sales_rep_territory_from_input($_POST);
    $result = sales_rep_territory_save($_POST);

    if ($result['ok']) {
        header('Location: /sales-rep-territory-assignment/?notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Territory Assignment | Sales Rep Territory Assignment';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/sales-rep-territory-assignment/',
          'back_label' => 'Back to Sales Rep Territory Assignment',
          'category'   => 'Operations',
          'title'      => 'New Territory Assignment',
          'lead'       => 'Add a State / Zip / County → sales rep row.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $formAction = '/sales-rep-territory-assignment/new.php';
        $isEdit = false;
        $assignmentId = 0;
        require dirname(__DIR__) . '/includes/sales-rep-territory-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
