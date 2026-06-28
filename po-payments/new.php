<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_create();

$activeSlug = 'po-payments';
$error = null;
$preselectedPo = (int) ($_GET['po_id'] ?? 0);
$form = po_payment_from_input([
    'payment_target'      => $preselectedPo > 0 ? 'po' : 'po',
    'po_id'               => $preselectedPo > 0 ? (string) $preselectedPo : '',
    'supplier_invoice_id' => '',
    'payment_date'        => date('Y-m-d\TH:i'),
    'payment_type'        => 'ACH',
    'payment_status'      => 'Paid',
    'payment_made_by'     => auth_user()['UserName'] ?? '',
]);
$poOptions = po_payment_po_options();
$invoiceOptions = po_payment_invoice_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = po_payment_from_input($_POST);
    $result = po_payment_save($_POST);

    if ($result['ok']) {
        header('Location: /po-payments/edit.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Record Payment | PO Payments';

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
          'title'      => 'Record Payment',
          'lead'       => 'Log a payment against a purchase order or a supplier invoice that is not tied to a PO.',
      ]);
      ?>

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
