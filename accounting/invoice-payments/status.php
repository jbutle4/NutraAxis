<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/po-payment.php';
require dirname(__DIR__, 2) . '/includes/payment-approval.php';

accounting_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounting/invoice-payments/', true, 302);
    exit;
}

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

$result = match ($action) {
    'submit'   => payment_approval_submit($paymentId),
    'resubmit' => payment_approval_resubmit($paymentId),
    default    => ['ok' => false, 'error' => 'Invalid status action.'],
};

if ($result['ok']) {
    $notice = $action === 'resubmit' ? 'resubmitted' : 'submitted';
    $params = ['id' => $paymentId, 'notice' => $notice];
    if (!empty($result['notify']) && is_array($result['notify'])) {
        $mailMessage = payment_approval_format_notify_message($result['notify']);
        if ($mailMessage !== '') {
            $params['mail_message'] = $mailMessage;
        }
        if (($result['notify']['skipped_reason'] ?? null) !== null || ($result['notify']['failed'] ?? []) !== []) {
            $params['mail_warning'] = '1';
        }
    }
    header('Location: /accounting/invoice-payments/edit.php?' . http_build_query($params), true, 302);
    exit;
}

$pageTitle = 'Submit Payment | Accounting';
require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/accounting/invoice-payments/edit.php?id=<?= $paymentId ?>">Back to Payment</a>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
