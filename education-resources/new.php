<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/education-resources.php';

education_resources_require_create();

$activeSlug = 'education-resources';
$error = null;
$form = education_resources_from_input([
    'type' => 'PDF',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = education_resources_from_input($_POST);
    $result = education_resources_save($_POST, $_FILES['pdf_file'] ?? null);

    if ($result['ok']) {
        header('Location: /education-resources/?notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Education Resource | NutraAxis Operations';
$pageDescription = 'Add a PDF or Vimeo education resource.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/education-resources/',
          'back_label' => 'Back to Education Resources',
          'category'   => 'Operations',
          'title'      => 'New education resource',
          'lead'       => 'Add a Vimeo video link or upload a PDF to Azure Blob Storage.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/education-resources/new.php';
        $existing = null;
        require dirname(__DIR__) . '/includes/education-resources-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
