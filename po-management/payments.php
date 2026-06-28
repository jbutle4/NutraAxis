<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_read();

$poId = (int) ($_GET['id'] ?? $_POST['po_id'] ?? 0);
if ($poId <= 0) {
    header('Location: /po-management/', true, 302);
    exit;
}

$order = po_get_order($poId);
if ($order === null) {
    http_response_code(404);
    $pageTitle = 'PO Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Purchase order not found</h1><div class="module-actions"><a class="btn-secondary" href="/po-management/">Back to Purchase Orders</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if (!po_payment_can_create() && !po_payment_can_delete()) {
    auth_render_access_denied('You do not have permission to manage payments for this purchase order.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['payment_action'] ?? '';
    $error = null;

    if ($action === 'add') {
        po_payment_require_create();
        $result = po_payment_save($_POST);
        if ($result['ok']) {
            header('Location: /po-payments/edit.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
            exit;
        }
        $error = $result['error'];
    } elseif ($action === 'delete') {
        po_payment_require_delete();
        $result = po_payment_delete((int) ($_POST['payment_id'] ?? 0));
        if ($result['ok']) {
            header('Location: /po-management/view.php?id=' . $poId . '&payment_notice=deleted', true, 302);
            exit;
        }
        $error = $result['error'];
    } else {
        header('Location: /po-management/payments.php?id=' . $poId, true, 302);
        exit;
    }
} else {
    $error = null;
}

$activeSlug = 'po-management';
$activePoSection = 'list';
$poPayments = po_payment_list_for_po($poId);
$poPaymentTotal = po_payment_total_for_po($poId);

$pageTitle = $order['PONumber'] . ' | PO Payments';
$pageDescription = 'Record and manage payments for this purchase order.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      render_list_page_header([
          'back_href'  => '/po-management/view.php?id=' . $poId,
          'back_label' => 'Back to ' . $order['PONumber'],
          'category'   => 'Procurement',
          'title'      => 'PO payments',
          'lead'       => '<span class="status-badge ' . po_status_class($order['POStatus']) . '">' . htmlspecialchars($order['POStatus']) . '</span> · ' . htmlspecialchars($order['PONumber']) . ' · ' . htmlspecialchars($order['SupplierName']),
          'lead_html'  => true,
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php require dirname(__DIR__) . '/includes/po-payment-po-form.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
