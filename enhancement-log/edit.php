<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';
require dirname(__DIR__) . '/includes/enhancement-log-attachments.php';

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

$pageTitle = 'Edit Backlog Item #' . $logId . ' | IT Product Backlog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/enhancement-log/view.php?id=' . $logId,
          'back_label' => 'Back to Backlog Item #' . $logId,
          'category'   => 'Operations',
          'title'      => 'Edit Backlog Item',
          'lead'       => (string) $entry['EnhancementTitle'],
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/enhancement-log/edit.php?id=' . $logId;
        require dirname(__DIR__) . '/includes/enhancement-log-form.php';
      ?>

      <?php
        $showUploadForm = enh_log_can_add_attachments();
        require dirname(__DIR__) . '/includes/enhancement-log-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
