<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog-attachments.php';

catalog_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /product-catalog/', true, 302);
    exit;
}

$skuId = (int) ($_POST['sku_id'] ?? 0);
$kind = $_POST['attachment_kind'] ?? 'Other';
$result = catalog_save_attachment($skuId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /product-catalog/view.php?id=' . $skuId . '&notice=attachment', true, 302);
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
