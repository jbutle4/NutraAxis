<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_require_update();

$poId = (int) ($_GET['id'] ?? 0);
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

if (!po_can_edit_order($order)) {
    auth_render_access_denied('This purchase order cannot be edited in its current status.');
}

$activeSlug = 'po-management';
$activePoSection = 'list';
$error = null;
$suppliers = po_list_suppliers();
$dbLines = po_get_lines($poId);

$form = po_order_to_form_fields($order);

$lines = po_lines_to_form($dbLines);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $result = po_save_order($_POST, $poId);

    if ($result['ok']) {
        $params = ['id' => $poId, 'notice' => 'updated'];
        if (!empty($result['requires_reapproval'])) {
            $params['reapproval'] = '1';
        }
        header('Location: /po-management/view.php?' . http_build_query($params), true, 302);
        exit;
    }

    $error = $result['error'];
    $lines = $_POST['lines'] ?? $lines;
}

$poPayments = po_payment_list_for_po($poId);
$poPaymentTotal = po_payment_total_for_po($poId);
$paymentNotice = $_GET['payment_notice'] ?? null;
$paymentError = $_GET['payment_error'] ?? null;

$pageTitle = 'Edit ' . $order['PONumber'] . ' | PO Management';
$pageDescription = 'Edit purchase order details.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-management/view.php?id=<?= $poId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars($order['PONumber']) ?>
      </a>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Procurement</div>
        <h1>Edit <?= htmlspecialchars($order['PONumber']) ?></h1>
        <p class="page-lead"><?= po_is_post_approval_edit($order)
            ? 'Update supplier details and line items for this approved purchase order.'
            : 'Update supplier details and line items for this draft PO.' ?></p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/po-management/edit.php?id=' . $poId;
        require dirname(__DIR__) . '/includes/po-form.php';
      ?>

      <?php require dirname(__DIR__) . '/includes/po-payment-detail.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
