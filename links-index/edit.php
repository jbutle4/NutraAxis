<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';

links_require_update();

$linkId = (int) ($_GET['id'] ?? 0);
$link = $linkId > 0 ? links_get($linkId) : null;

if ($link === null) {
    header('Location: /links-index/', true, 302);
    exit;
}

$activeSlug = 'links-index';
$error = null;
$form = links_link_to_form($link);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = links_from_input($_POST);
    $result = links_save($_POST, $linkId);

    if ($result['ok']) {
        header('Location: /links-index/?notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit Link | Links Index';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/links-index/view.php?id=<?= $linkId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Link Details
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Resources</div>
          <h1>Edit Link</h1>
          <p class="page-lead"><?= htmlspecialchars((string) $link['LinkName']) ?></p>
        </div>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $formAction = '/links-index/edit.php?id=' . $linkId;
        $isEdit = true;
        require dirname(__DIR__) . '/includes/link-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
