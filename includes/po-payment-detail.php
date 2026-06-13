<?php
/** @var int $poId */
/** @var array $order */
/** @var array $poPayments */
/** @var float $poPaymentTotal */
/** @var string|null $paymentNotice */
/** @var string|null $paymentError */
$paymentNotice = $paymentNotice ?? null;
$paymentError = $paymentError ?? null;
$poBalance = null;
if (isset($order['TotalDue']) && $order['TotalDue'] !== null && $order['TotalDue'] !== '') {
    $poBalance = (float) $order['TotalDue'] - $poPaymentTotal;
} elseif (isset($order['Subtotal'])) {
    $shipping = (float) ($order['ShippingHandling'] ?? 0);
    $poBalance = (float) $order['Subtotal'] + $shipping - $poPaymentTotal;
}
$canManagePayments = po_payment_can_create() || po_payment_can_delete();
?>
      <section class="detail-card supplier-po-report production-status-card">
        <div class="production-status-header">
          <div>
            <h2>PO payments</h2>
            <p class="account-card-lead">
              <?= count($poPayments) === 1 ? '1 payment' : count($poPayments) . ' payments' ?> recorded
              · Total paid: <strong><?= htmlspecialchars(po_format_money($poPaymentTotal)) ?></strong>
              <?php if ($poBalance !== null): ?>
              · Remaining: <strong><?= htmlspecialchars(po_format_money($poBalance)) ?></strong>
              <?php endif; ?>
            </p>
          </div>
          <?php if ($canManagePayments): ?>
          <div class="module-actions">
            <a class="btn-secondary" href="/po-management/payments.php?id=<?= $poId ?>">Manage payments</a>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($paymentNotice === 'added'): ?>
        <div class="admin-notice is-success" role="status">Payment recorded successfully.</div>
        <?php elseif ($paymentNotice === 'deleted'): ?>
        <div class="admin-notice is-success" role="status">Payment deleted successfully.</div>
        <?php elseif ($paymentNotice === 'updated'): ?>
        <div class="admin-notice is-success" role="status">Payment updated successfully.</div>
        <?php endif; ?>

        <?php if ($paymentError !== null): ?>
        <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($paymentError) ?></div>
        <?php endif; ?>

        <div class="admin-table-wrap production-status-table-wrap">
          <table class="admin-table production-status-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Confirmation #</th>
                <th>Made by</th>
                <th>Comments</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($poPayments === []): ?>
              <tr><td colspan="6">No payments recorded for this purchase order.</td></tr>
              <?php else: ?>
              <?php foreach ($poPayments as $payment): ?>
              <?php
                $comments = trim((string) ($payment['PaymentComments'] ?? ''));
                $commentsPreview = $comments !== '' ? (strlen($comments) > 48 ? substr($comments, 0, 45) . '…' : $comments) : '—';
              ?>
              <tr>
                <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
                <td><?= htmlspecialchars(po_format_money($payment['PaymentAmount'])) ?></td>
                <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
                <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
                <td><?= htmlspecialchars($payment['PaymentMadeBy'] ?? '—') ?></td>
                <td class="production-comments-cell"<?= $comments !== '' ? ' title="' . htmlspecialchars($comments) . '"' : '' ?>><?= htmlspecialchars($commentsPreview) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
