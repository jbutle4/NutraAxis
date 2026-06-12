<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/catalog-attachments.php';

catalog_require_update();

$skuId = (int) ($_GET['id'] ?? 0);
$sku = $skuId > 0 ? catalog_get_sku($skuId) : null;

if ($sku === null) {
    header('Location: /product-catalog/', true, 302);
    exit;
}

$activeSlug = 'product-catalog';
$error = null;
$form = catalog_sku_to_form($sku);
$attachments = catalog_list_attachments($skuId);
$notice = $_GET['notice'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, catalog_sku_from_input($_POST));
    $form['sku_id'] = $skuId;
    $result = catalog_save_sku($_POST, $skuId);

    if ($result['ok']) {
        header('Location: /product-catalog/view.php?id=' . $skuId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit ' . $sku['SKUCode'] . ' | Product Catalog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/product-catalog/view.php?id=<?= $skuId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars($sku['SKUCode']) ?>
      </a>

      <div class="page-hero">
        <div class="section-label">Products</div>
        <h1>Edit <?= htmlspecialchars($sku['SKUCode']) ?></h1>
        <p class="page-lead">Update catalog details for <?= htmlspecialchars($sku['ProductName']) ?>.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/product-catalog/edit.php?id=' . $skuId;
        $supplierOptions = catalog_supplier_options(
            $sku['SupplierID'] !== null ? (int) $sku['SupplierID'] : null
        );
        require dirname(__DIR__) . '/includes/catalog-sku-form.php';
      ?>

      <?php
        $showUploadForm = true;
        require dirname(__DIR__) . '/includes/catalog-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
