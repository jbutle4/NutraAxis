<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';
require dirname(__DIR__) . '/includes/enhancement-log-attachments.php';

enhancement_log_require_read();

$id = (int) ($_GET['id'] ?? 0);
$logId = (int) ($_GET['log_id'] ?? 0);
$attachment = $id > 0 ? enh_log_get_attachment($id) : null;

if ($attachment === null) {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$resolvedLogId = (int) $attachment['EnhancementLogID'];
if ($logId > 0 && $resolvedLogId !== $logId) {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$entry = enhancement_log_get($resolvedLogId);
if ($entry === null) {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$bytes = enh_log_read_file_bytes($id);
$imageDataUri = $bytes !== '' ? ('data:' . enh_log_attachment_content_type($attachment) . ';base64,' . base64_encode($bytes)) : null;
$imageError = $imageDataUri === null ? 'Screenshot could not be loaded. Try downloading the file or re-uploading it.' : null;

$activeSlug = 'enhancement-log';
$fileName = (string) $attachment['FileName'];
$downloadSrc = enh_log_attachment_download_path($resolvedLogId, $id) . '&download=1';
$pageTitle = $fileName . ' | Backlog Item #' . $resolvedLogId;

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      ob_start();
      ?>
      <a class="btn-secondary" href="<?= htmlspecialchars($downloadSrc) ?>">Download</a>
      <?php
      $pageToolbar = ob_get_clean();

      render_list_page_header([
          'back_href'  => '/enhancement-log/view.php?id=' . $resolvedLogId,
          'back_label' => 'Back to Backlog Item #' . $resolvedLogId,
          'category'   => 'Screenshot',
          'title'      => $fileName,
          'lead'       => (string) $entry['EnhancementTitle'],
      ]);
      ?>

      <?php render_list_page_toolbar($pageToolbar); ?>

      <section class="detail-card enh-log-image-viewer">
        <?php if ($imageDataUri !== null): ?>
        <img
          class="enh-log-image-full"
          src="<?= $imageDataUri ?>"
          alt="<?= htmlspecialchars($fileName) ?>"
        />
        <?php else: ?>
        <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $imageError) ?></div>
        <?php endif; ?>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
