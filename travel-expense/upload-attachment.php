<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-attachments.php';

te_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /travel-expense/', true, 302);
    exit;
}

$reportId = (int) ($_POST['report_id'] ?? 0);
$kind = $_POST['attachment_kind'] ?? 'Receipt';
$result = te_save_attachment($reportId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /travel-expense/view.php?id=' . $reportId . '&notice=attachment', true, 302);
    exit;
}

$activeSlug = 'travel-expense';
$pageTitle = 'Upload Receipt | Travel & Expense';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/travel-expense/view.php?id=<?= $reportId ?>">Back to Report</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
