<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';
require dirname(__DIR__) . '/includes/po-payment-attachments.php';

po_payment_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-payments/', true, 302);
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$kind = $_POST['attachment_kind'] ?? 'Other';
$result = po_payment_save_attachment($paymentId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /po-payments/edit.php?id=' . $paymentId . '&notice=attachment', true, 302);
    exit;
}

$payment = po_payment_get($paymentId);
$activeSlug = 'po-payments';
$pageTitle = 'Upload Attachment | PO Payments';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <?php if ($payment !== null): ?>
        <a class="btn-secondary" href="/po-payments/edit.php?id=<?= $paymentId ?>">Back to payment</a>
        <?php else: ?>
        <a class="btn-secondary" href="/po-payments/">Back to PO Payments</a>
        <?php endif; ?>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
