<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';

po_require_create();

$activeSlug = 'po-management';
$activePoSection = 'new';
$error = null;
$form = po_default_header();
$suppliers = po_list_suppliers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $result = po_save_order($_POST);

    if ($result['ok']) {
        if (!empty($_FILES['source_pdf']['name'])) {
            po_save_attachment($result['id'], $_FILES['source_pdf'], 'SourcePDF');
        }
        header('Location: /po-management/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Purchase Order | PO Management';
$pageDescription = 'Create a new supplier purchase order.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/po-management/',
          'back_label' => 'Back to Purchase Orders',
          'category'   => 'Procurement',
          'title'      => 'New Purchase Order',
          'lead'       => 'Enter NutraSeal-style header fields, line items, and optionally attach the source PDF.',
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $lines = $_POST['lines'] ?? [['sku' => '', 'quote_number' => '', 'description' => '', 'quantity' => '', 'unit_price' => '', 'expiration_date' => '']];
        $isEdit = false;
        require dirname(__DIR__) . '/includes/po-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
