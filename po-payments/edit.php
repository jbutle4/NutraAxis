<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';
require dirname(__DIR__) . '/includes/po-payment-attachments.php';

po_payment_require_update();

$paymentId = (int) ($_GET['id'] ?? 0);
$payment = $paymentId > 0 ? po_payment_get($paymentId) : null;

if ($payment === null) {
    header('Location: /po-payments/', true, 302);
    exit;
}

$activeSlug = 'po-payments';
$error = null;
$notice = $_GET['notice'] ?? null;
$form = po_payment_to_form($payment);
$poOptions = po_payment_po_options();
$invoiceOptions = po_payment_invoice_options();
$attachments = po_payment_list_attachments($paymentId);

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
    <div class="container page-inner page-inner--wide">
      <?php
      render_list_page_header([
          'back_href'  => '/po-payments/',
          'back_label' => 'Back to PO Payments',
          'category'   => 'Inventory',
          'title'      => 'Edit Payment',
          'lead'       => po_payment_reference_label($payment) . ' · ' . ($payment['SupplierName'] ?? '') . ' · ' . po_format_money($payment['PaymentAmount']),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Payment recorded successfully. Upload supporting documents below.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/po-payments/edit.php?id=' . $paymentId;
        require dirname(__DIR__) . '/includes/po-payment-form.php';
      ?>

      <?php
        $showUploadForm = po_payment_can_update();
        $uploadNotice = $notice;
        require dirname(__DIR__) . '/includes/po-payment-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
