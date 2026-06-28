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
      <?php
      render_list_page_header([
          'back_href'  => '/product-catalog/',
          'back_label' => 'Back to SKU Master',
          'category'   => 'Products',
          'title'      => 'New SKU',
          'lead'       => 'Add a product to the SKU master catalog.',
      ]);
      ?>

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
