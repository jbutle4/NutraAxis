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
        header('Location: /supplier-management/view.php?id=' . $result['id'] . '&notice=created', true, 302);
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
      <a class="breadcrumb" href="/supplier-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Supplier Management
      </a>

      <div class="page-hero">
        <div class="section-label">Inventory</div>
        <h1>New Supplier</h1>
        <p class="page-lead">Add a supplier profile for use in purchase orders.</p>
      </div>

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
