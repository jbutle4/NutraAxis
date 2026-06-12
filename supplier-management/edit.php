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
        header('Location: /supplier-management/view.php?id=' . $supplierId . '&notice=updated', true, 302);
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
      <a class="breadcrumb" href="/supplier-management/view.php?id=<?= $supplierId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars($supplier['SupplierName']) ?>
      </a>

      <div class="page-hero">
        <div class="section-label">Inventory</div>
        <h1>Edit <?= htmlspecialchars($supplier['SupplierName']) ?></h1>
        <p class="page-lead">Update supplier profile and contact details.</p>
      </div>

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
