<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';

enhancement_log_require_update();

$logId = (int) ($_GET['id'] ?? 0);
$entry = $logId > 0 ? enhancement_log_get($logId) : null;

if ($entry === null) {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$activeSlug = 'enhancement-log';
$error = null;
$form = enhancement_log_to_form($entry);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = enhancement_log_from_input($_POST);
    $result = enhancement_log_save($_POST, $logId);

    if ($result['ok']) {
        header('Location: /enhancement-log/view.php?id=' . $logId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit Enhancement #' . $logId . ' | Enhancement Log';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/enhancement-log/view.php?id=<?= $logId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Enhancement #<?= $logId ?>
      </a>

      <div class="page-hero">
        <div class="section-label">Operations</div>
        <h1>Edit Enhancement</h1>
        <p class="page-lead"><?= htmlspecialchars((string) $entry['EnhancementTitle']) ?></p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/enhancement-log/edit.php?id=' . $logId;
        require dirname(__DIR__) . '/includes/enhancement-log-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
