<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/supplier.php';

supplier_require_update();

$supplierId = (int) ($_GET['id'] ?? 0);
$supplier = $supplierId > 0 ? supplier_get($supplierId) : null;

if ($supplier === null) {
    header('Location: /supplier-management/', true, 302);
    exit;
}

$activeSlug = 'supplier-management';
$error = null;
$form = supplier_to_form($supplier);
$supplierPurchaseOrders = supplier_list_purchase_orders($supplierId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, supplier_from_input($_POST));
    $form['supplier_id'] = $supplierId;
    $result = supplier_save($_POST, $supplierId);

    if ($result['ok']) {
        $syncError = supplier_maybe_sync_qbo($supplierId);
        $query = http_build_query(array_filter([
            'id'     => $supplierId,
            'notice' => 'updated',
            'error'  => $syncError,
        ]));
        header('Location: /supplier-management/view.php?' . $query, true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit ' . $supplier['SupplierName'] . ' | Supplier Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/supplier-management/view.php?id=' . $supplierId,
          'back_label' => 'Back to ' . (string) $supplier['SupplierName'],
          'category'   => 'Inventory',
          'title'      => 'Edit ' . (string) $supplier['SupplierName'],
          'lead'       => 'Update supplier profile and contact details.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/supplier-management/edit.php?id=' . $supplierId;
        require dirname(__DIR__) . '/includes/supplier-form.php';
      ?>

      <?php require dirname(__DIR__) . '/includes/supplier-po-report.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
