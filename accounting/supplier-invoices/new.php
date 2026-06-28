<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';

supplier_invoice_require_create();

$activeSlug = 'accounting';
$accountingSection = 'invoices';
$error = null;
$form = supplier_invoice_to_form([]);
$suppliers = supplier_invoice_list_suppliers();
$poOptions = supplier_invoice_po_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, supplier_invoice_from_input($_POST), ['lines' => $_POST['lines'] ?? []]);
    $result = supplier_invoice_save($_POST);

    if ($result['ok']) {
        header('Location: /accounting/supplier-invoices/view.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Supplier Invoice | Accounting';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      render_list_page_header([
          'back_href'  => '/accounting/supplier-invoices/',
          'back_label' => 'Back to Supplier Invoices',
          'category'   => 'Finance',
          'title'      => 'New Supplier Invoice',
          'lead'       => 'Enter vendor invoice details and expense lines for QuickBooks Bill sync.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $isLocked = false;
        $formAction = '/accounting/supplier-invoices/new.php';
        require dirname(__DIR__, 2) . '/includes/supplier-invoice-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
