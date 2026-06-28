<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog-attachments.php';

catalog_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /product-catalog/', true, 302);
    exit;
}

$skuId = (int) ($_POST['sku_id'] ?? 0);
$result = catalog_save_attachment(
    $skuId,
    $_FILES['attachment'] ?? [],
    (string) ($_POST['attachment_kind'] ?? 'Other')
);
$isAjax = !empty($_POST['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$returnTo = trim((string) ($_POST['return_to'] ?? ''));
$successRedirect = '/product-catalog/view.php?id=' . $skuId . '&notice=attachment';
if ($returnTo !== '' && str_starts_with($returnTo, '/product-catalog/')) {
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

$activeSlug = 'product-catalog';
$pageTitle = 'Upload Attachment | Product Catalog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/product-catalog/view.php?id=<?= $skuId ?>">Back to SKU</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
