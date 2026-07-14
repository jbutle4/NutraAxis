<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/coa.php';

coa_require_create();

$activeSlug = 'coa-management';
$error = null;
$form = coa_from_input([
    'is_published' => '1',
    'sort_order'   => '0',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = coa_from_input($_POST);
    $result = coa_save($_POST, $_FILES['coa_pdf'] ?? null);

    if ($result['ok']) {
        header('Location: /coa-management/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New COA | COA Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/coa-management/',
          'back_label' => 'Back to COA Management',
          'category'   => 'Quality',
          'title'      => 'New COA',
          'lead'       => 'Upload a PDF and metadata for a new Certificate of Analysis.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/coa-management/new.php';
        $existing = null;
        require dirname(__DIR__) . '/includes/coa-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
