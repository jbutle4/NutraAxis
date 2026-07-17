<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_create();

$activeSlug = 'product-enrichment';
$error = null;
$form = product_enrichment_from_input([
    'is_published' => '0',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = product_enrichment_from_input($_POST);
    $result = product_enrichment_save($_POST, $_FILES['info_sheet_pdf'] ?? null);

    if ($result['ok']) {
        header('Location: /product-enrichment/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Product Enrichment | NutraAxis Operations';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/product-enrichment/',
          'back_label' => 'Back to Product Page Enrichment',
          'category'   => 'Products',
          'title'      => 'New product enrichment',
          'lead'       => 'Add HTML content and an information sheet PDF for a product page SKU.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/product-enrichment/new.php';
        $existing = null;
        require dirname(__DIR__) . '/includes/product-enrichment-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/product-enrichment-editor.php';
product_enrichment_editor_assets();
require dirname(__DIR__) . '/includes/footer.php';
