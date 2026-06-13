<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_create();

$activeSlug = 'po-payments';
$error = null;
$preselectedPo = (int) ($_GET['po_id'] ?? 0);
$form = po_payment_from_input([
    'po_id'          => $preselectedPo > 0 ? (string) $preselectedPo : '',
    'payment_date'   => date('Y-m-d\TH:i'),
    'payment_type'   => 'ACH',
    'payment_made_by'=> auth_user()['UserName'] ?? '',
]);
$poOptions = po_payment_po_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = po_payment_from_input($_POST);
    $result = po_payment_save($_POST);

    if ($result['ok']) {
        header('Location: /po-payments/?notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Record Payment | PO Payments';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-payments/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to PO Payments
      </a>

      <div class="page-hero">
        <div class="section-label">Inventory</div>
        <h1>Record Payment</h1>
        <p class="page-lead">Log a payment made against a purchase order.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/po-payments/new.php';
        require dirname(__DIR__) . '/includes/po-payment-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
