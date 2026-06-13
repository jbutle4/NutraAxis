<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_update();

$paymentId = (int) ($_GET['id'] ?? 0);
$payment = $paymentId > 0 ? po_payment_get($paymentId) : null;

if ($payment === null) {
    header('Location: /po-payments/', true, 302);
    exit;
}

$activeSlug = 'po-payments';
$error = null;
$form = po_payment_to_form($payment);
$poOptions = po_payment_po_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, po_payment_from_input($_POST));
    $result = po_payment_save($_POST, $paymentId);

    if ($result['ok']) {
        header('Location: /po-payments/?notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit Payment | PO Payments';

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
        <h1>Edit Payment</h1>
        <p class="page-lead"><?= htmlspecialchars($payment['PONumber']) ?> · <?= htmlspecialchars(po_format_money($payment['PaymentAmount'])) ?></p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/po-payments/edit.php?id=' . $paymentId;
        require dirname(__DIR__) . '/includes/po-payment-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
