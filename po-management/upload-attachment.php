<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';

po_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$kind = $_POST['attachment_kind'] ?? 'SourcePDF';
$result = po_save_attachment($poId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /po-management/view.php?id=' . $poId . '&notice=attachment', true, 302);
    exit;
}

$activeSlug = 'po-management';
$pageTitle = 'Upload Attachment | PO Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/po-management/view.php?id=<?= $poId ?>">Back to PO</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
