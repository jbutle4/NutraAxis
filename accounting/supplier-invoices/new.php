<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';
require dirname(__DIR__, 2) . '/includes/po.php';

supplier_invoice_require_create();

$activeSlug = 'accounting';
$accountingSection = 'invoices';
$error = null;
$form = supplier_invoice_to_form([]);
$preselectedPo = (int) ($_GET['po_id'] ?? 0);
if ($preselectedPo > 0) {
    $linkedPo = po_get_order($preselectedPo);
    if ($linkedPo !== null) {
        $form['po_id'] = (string) $preselectedPo;
        $form['supplier_id'] = (int) ($linkedPo['SupplierID'] ?? 0);
    }
}
$suppliers = supplier_invoice_list_suppliers();
$poOptions = supplier_invoice_po_options(
    !empty($form['supplier_id']) ? (int) $form['supplier_id'] : null
);

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
          'back_href'  => $preselectedPo > 0
              ? '/po-management/view.php?id=' . $preselectedPo
              : '/accounting/supplier-invoices/',
          'back_label' => $preselectedPo > 0 ? 'Back to Purchase Order' : 'Back to Supplier Invoices',
          'category'   => 'Finance',
          'title'      => 'New Supplier Invoice',
          'lead'       => $preselectedPo > 0
              ? 'Create a supplier invoice linked to this PO. Upload the invoice PDF and submit for payment approval when ready.'
              : 'Enter vendor invoice details and expense lines for QuickBooks Bill sync.',
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
