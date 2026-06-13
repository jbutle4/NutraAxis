<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal-attachments.php';

legal_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /legal-agreements/', true, 302);
    exit;
}

$contractId = (int) ($_POST['contract_id'] ?? 0);
$kind = $_POST['attachment_kind'] ?? 'Other';
$result = legal_save_attachment($contractId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /legal-agreements/edit.php?id=' . $contractId . '&notice=attachment', true, 302);
    exit;
}

$activeSlug = 'legal-agreements';
$pageTitle = 'Upload Attachment | Legal Agreements';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/legal-agreements/edit.php?id=<?= $contractId ?>">Back to contract</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
