<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-attachments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /travel-expense/', true, 302);
    exit;
}

$reportId = (int) ($_POST['report_id'] ?? 0);
$attachmentId = (int) ($_POST['attachment_id'] ?? 0);

if ($attachmentId > 0) {
    te_require_update();
    $result = te_delete_attachment($reportId, $attachmentId);

    if ($result['ok']) {
        header('Location: /travel-expense/view.php?id=' . $reportId . '&notice=attachment_deleted', true, 302);
        exit;
    }

    $activeSlug = 'travel-expense';
    $pageTitle = 'Delete Receipt | Travel & Expense';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner">';
    echo '<div class="admin-notice is-error is-detail" role="alert">' . htmlspecialchars($result['error']) . '</div>';
    echo '<div class="module-actions"><a class="btn-secondary" href="/travel-expense/view.php?id=' . $reportId . '">Back to Report</a></div>';
    echo '</div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if (!te_can_delete()) {
    auth_render_access_denied('You do not have permission to delete expense reports.');
}

$result = te_delete_report($reportId);

if ($result['ok']) {
    header('Location: /travel-expense/?notice=deleted', true, 302);
    exit;
}

$activeSlug = 'travel-expense';
$pageTitle = 'Delete Report | Travel & Expense';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/travel-expense/">Back to Expense Reports</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
