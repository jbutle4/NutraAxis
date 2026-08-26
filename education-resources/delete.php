<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/education-resources.php';

education_resources_require_delete();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = education_resources_delete((int) ($_POST['id'] ?? 0));
    if ($result['ok']) {
        header('Location: /education-resources/?notice=deleted', true, 302);
        exit;
    }
    http_response_code(400);
    exit($result['error'] ?? 'Unable to delete education resource.');
}

$row = education_resources_get($id);
if ($row === null) {
    header('Location: /education-resources/', true, 302);
    exit;
}

$activeSlug = 'education-resources';
$pageTitle = 'Delete Education Resource | NutraAxis Operations';

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
          'title'      => 'Delete education resource',
          'lead'       => 'This permanently removes the resource record and any stored PDF from Azure Blob Storage.',
      ]);
      ?>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <div>
            <dt>Description</dt>
            <dd><?= htmlspecialchars((string) ($row['Description'] ?? '')) ?></dd>
          </div>
          <div>
            <dt>Type</dt>
            <dd><?= htmlspecialchars((string) ($row['Type'] ?? '')) ?></dd>
          </div>
        </dl>
        <form method="post" action="/education-resources/delete.php" class="module-actions" style="margin-top: 1rem;">
          <input type="hidden" name="id" value="<?= $id ?>" />
          <button type="submit" class="btn-danger">Delete resource</button>
          <a class="btn-secondary" href="/education-resources/">Cancel</a>
        </form>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
