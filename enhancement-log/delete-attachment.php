<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';
require dirname(__DIR__) . '/includes/enhancement-log-attachments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$logId = (int) ($_POST['log_id'] ?? 0);
$attachmentId = (int) ($_POST['attachment_id'] ?? 0);

enhancement_log_require_update();
$result = enh_log_delete_attachment($logId, $attachmentId);

if ($result['ok']) {
    header('Location: /enhancement-log/view.php?id=' . $logId . '&notice=attachment_deleted', true, 302);
    exit;
}

$activeSlug = 'enhancement-log';
$pageTitle = 'Delete Screenshot | IT Product Backlog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/enhancement-log/view.php?id=<?= $logId ?>">Back to Backlog Item</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
