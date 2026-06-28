<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';
require dirname(__DIR__) . '/includes/enhancement-log-attachments.php';

enhancement_log_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$logId = (int) ($_POST['log_id'] ?? 0);
$kind = $_POST['attachment_kind'] ?? 'Screenshot';
$result = enh_log_save_attachment($logId, $_FILES['attachment'] ?? [], $kind);
$isAjax = !empty($_POST['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if ($result['ok']) {
        echo json_encode([
            'ok'       => true,
            'error'    => null,
            'redirect' => '/enhancement-log/view.php?id=' . $logId . '&notice=attachment',
        ], JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => $result['error'] ?? 'Unable to upload screenshot.',
        ], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($result['ok']) {
    header('Location: /enhancement-log/view.php?id=' . $logId . '&notice=attachment', true, 302);
    exit;
}

$activeSlug = 'enhancement-log';
$pageTitle = 'Upload Screenshot | IT Product Backlog';

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
