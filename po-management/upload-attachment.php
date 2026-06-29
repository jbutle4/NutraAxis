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
$isAjax = !empty($_POST['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$returnTo = trim((string) ($_POST['return_to'] ?? ''));
$successRedirect = '/po-management/view.php?id=' . $poId . '&notice=attachment';
if ($returnTo !== '' && str_starts_with($returnTo, '/po-management/')) {
    $successRedirect = $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'notice=attachment';
}

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if ($result['ok']) {
        echo json_encode([
            'ok'       => true,
            'error'    => null,
            'redirect' => $successRedirect,
        ], JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => $result['error'] ?? 'Unable to upload attachment.',
        ], JSON_UNESCAPED_SLASHES);
    }
    exit;
}

if ($result['ok']) {
    header('Location: ' . $successRedirect, true, 302);
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
