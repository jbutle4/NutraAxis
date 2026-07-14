<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_update();

$activeSlug = 'product-enrichment';
$id = (int) ($_GET['id'] ?? 0);
$existing = product_enrichment_get($id);

if ($existing === null) {
    http_response_code(404);
    exit('Product enrichment record not found.');
}

$error = null;
$form = product_enrichment_row_to_form($existing);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = product_enrichment_from_input($_POST);
    $result = product_enrichment_save($_POST, $_FILES['info_sheet_pdf'] ?? null);

    if ($result['ok']) {
        header('Location: /product-enrichment/view.php?id=' . $result['id'] . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit Product Enrichment | NutraAxis Operations';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/product-enrichment/view.php?id=' . $id,
          'back_label' => 'Back to enrichment detail',
          'category'   => 'Products',
          'title'      => 'Edit product enrichment',
          'lead'       => htmlspecialchars((string) ($existing['ProductName'] ?? $existing['SKUCode'])) . ' · ' . htmlspecialchars((string) $existing['SKUCode']),
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/product-enrichment/edit.php?id=' . $id;
        require dirname(__DIR__) . '/includes/product-enrichment-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/product-enrichment-editor.php';
product_enrichment_editor_assets();
require dirname(__DIR__) . '/includes/footer.php';
