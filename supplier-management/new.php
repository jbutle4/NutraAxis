<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/supplier.php';

supplier_require_create();

$activeSlug = 'supplier-management';
$error = null;
$form = supplier_from_input(['is_active' => '1']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = supplier_from_input($_POST);
    $result = supplier_save($_POST);

    if ($result['ok']) {
        $supplierId = (int) $result['id'];
        $syncError = supplier_maybe_sync_qbo($supplierId);
        $query = http_build_query(array_filter([
            'id'     => $supplierId,
            'notice' => 'created',
            'error'  => $syncError,
        ]));
        header('Location: /supplier-management/view.php?' . $query, true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Supplier | Supplier Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/supplier-management/',
          'back_label' => 'Back to Supplier Management',
          'category'   => 'Inventory',
          'title'      => 'New Supplier',
          'lead'       => 'Add a supplier profile for use in purchase orders.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/supplier-management/new.php';
        require dirname(__DIR__) . '/includes/supplier-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
