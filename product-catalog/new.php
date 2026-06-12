<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';

catalog_require_create();

$activeSlug = 'product-catalog';
$error = null;
$form = catalog_sku_from_input([
    'sku_status'   => 'In Development',
    'brand'        => 'NutraAxis',
    'manufacturer' => 'Other',
    'primary_therapeutic_category' => 'Other',
    'non_gmo_certified' => '0',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = catalog_sku_from_input($_POST);
    $result = catalog_save_sku($_POST);

    if ($result['ok']) {
        header('Location: /product-catalog/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New SKU | Product Catalog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/product-catalog/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to SKU Master
      </a>

      <div class="page-hero">
        <div class="section-label">Products</div>
        <h1>New SKU</h1>
        <p class="page-lead">Add a product to the SKU master catalog.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/product-catalog/new.php';
        $supplierOptions = catalog_supplier_options();
        require dirname(__DIR__) . '/includes/catalog-sku-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
