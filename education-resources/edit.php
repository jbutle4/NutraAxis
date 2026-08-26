<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/education-resources.php';

education_resources_require_update();

$activeSlug = 'education-resources';
$id = (int) ($_GET['id'] ?? $_POST['erid'] ?? 0);
$existing = education_resources_get($id);
if ($existing === null) {
    http_response_code(404);
    $pageTitle = 'Education Resource Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Resource not found</h1><div class="module-actions"><a class="btn-secondary" href="/education-resources/">Back to Education Resources</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$error = null;
$form = education_resources_row_to_form($existing);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = education_resources_from_input($_POST);
    $form['erid'] = $id;
    $_POST['erid'] = (string) $id;
    $result = education_resources_save($_POST, $_FILES['pdf_file'] ?? null);

    if ($result['ok']) {
        header('Location: /education-resources/?notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
    $existing = education_resources_get($id) ?? $existing;
}

$pageTitle = 'Edit Education Resource | NutraAxis Operations';
$pageDescription = 'Update a PDF or Vimeo education resource.';

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
          'title'      => 'Edit education resource',
          'lead'       => 'Update description, type, Vimeo URL, or replace the uploaded PDF.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/education-resources/edit.php?id=' . $id;
        require dirname(__DIR__) . '/includes/education-resources-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
