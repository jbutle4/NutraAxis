<?php
/** @var int $poId */
/** @var array $order */
/** @var array $poPayments */
/** @var float $poPaymentTotal */
/** @var bool $showPaymentForm */
/** @var string|null $paymentNotice */
/** @var string|null $paymentError */
/** @var string $returnTo */
$showPaymentForm = $showPaymentForm ?? po_payment_can_create();
$paymentNotice = $paymentNotice ?? null;
$paymentError = $paymentError ?? null;
$returnTo = $returnTo ?? 'view';
$poBalance = null;
if (isset($order['TotalDue']) && $order['TotalDue'] !== null && $order['TotalDue'] !== '') {
    $poBalance = (float) $order['TotalDue'] - $poPaymentTotal;
} elseif (isset($order['Subtotal'])) {
    $shipping = (float) ($order['ShippingHandling'] ?? 0);
    $poBalance = (float) $order['Subtotal'] + $shipping - $poPaymentTotal;
}
?>
      <section class="detail-card supplier-po-report">
        <h2>PO payments</h2>
        <p class="page-lead">
          <?= count($poPayments) === 1 ? '1 payment' : count($poPayments) . ' payments' ?> recorded
          · Total paid: <strong><?= htmlspecialchars(po_format_money($poPaymentTotal)) ?></strong>
          <?php if ($poBalance !== null): ?>
          · Remaining: <strong><?= htmlspecialchars(po_format_money($poBalance)) ?></strong>
          <?php endif; ?>
        </p>

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

        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Status</th>
                <th>Confirmation #</th>
                <th>Made by</th>
                <th>Comments</th>
                <th>Files</th>
                <?php if (po_payment_can_update() || po_payment_can_delete()): ?>
                <th>Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if ($poPayments === []): ?>
              <tr><td colspan="<?= (po_payment_can_update() || po_payment_can_delete()) ? 9 : 8 ?>">No payments recorded for this purchase order.</td></tr>
              <?php else: ?>
              <?php foreach ($poPayments as $payment): ?>
              <tr>
                <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
                <td><?= htmlspecialchars(po_format_money($payment['PaymentAmount'])) ?></td>
                <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
                <td><span class="status-badge <?= po_payment_status_class((string) ($payment['PaymentStatus'] ?? '')) ?>"><?= htmlspecialchars(po_payment_format_status($payment['PaymentStatus'] ?? null)) ?></span></td>
                <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
                <td><?= htmlspecialchars($payment['PaymentMadeBy'] ?? '—') ?></td>
                <td><?= htmlspecialchars($payment['PaymentComments'] ?? '—') ?></td>
                <td>
                  <?php $attachmentCount = (int) ($payment['AttachmentCount'] ?? 0); ?>
                  <?php if ($attachmentCount > 0 && po_payment_can_update()): ?>
                  <a class="btn-text" href="/po-payments/edit.php?id=<?= (int) $payment['PaymentID'] ?>"><?= $attachmentCount === 1 ? '1 file' : $attachmentCount . ' files' ?></a>
                  <?php else: ?>
                  <?= $attachmentCount > 0 ? ($attachmentCount === 1 ? '1 file' : $attachmentCount . ' files') : '—' ?>
                  <?php endif; ?>
                </td>
                <?php if (po_payment_can_update() || po_payment_can_delete()): ?>
                <?php
                $paymentRowActions = [];
                if (po_payment_can_update()) {
                    $paymentRowActions[] = ['href' => '/po-payments/edit.php?id=' . (int) $payment['PaymentID'], 'label' => 'Edit'];
                }
                if (po_payment_can_delete()) {
                    $paymentRowActions[] = [
                        'html' => table_action_delete_form(
                            '/po-management/payment.php',
                            [
                                'payment_action' => 'delete',
                                'payment_id'     => (int) $payment['PaymentID'],
                                'po_id'          => $poId,
                                'return_to'      => $returnTo,
                            ],
                            'Delete this payment record?'
                        ),
                    ];
                }
                table_actions_cell($paymentRowActions);
                ?>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($showPaymentForm): ?>
        <h3 class="production-line-header">Record payment</h3>
        <form class="admin-form" method="post" action="/po-management/payment.php">
          <input type="hidden" name="payment_action" value="add" />
          <input type="hidden" name="po_id" value="<?= $poId ?>" />
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>" />
          <div class="form-grid">
            <div class="form-group">
              <label for="payment_date">Payment date</label>
              <input class="form-input" type="datetime-local" id="payment_date" name="payment_date" value="<?= htmlspecialchars(date('Y-m-d\TH:i')) ?>" required />
            </div>
            <div class="form-group">
              <label for="payment_amount">Payment amount ($)</label>
              <input class="form-input" type="number" min="0.01" step="0.01" id="payment_amount" name="payment_amount" required />
            </div>
            <div class="form-group">
              <label for="payment_type">Payment type</label>
              <select class="form-input" id="payment_type" name="payment_type" required>
                <?php foreach (PO_PAYMENT_TYPES as $type): ?>
                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="payment_status">Payment status</label>
              <select class="form-input" id="payment_status" name="payment_status" required>
                <?php foreach (PO_PAYMENT_STATUSES as $status): ?>
                <option value="<?= htmlspecialchars($status) ?>" <?= $status === 'Paid' ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="payment_conf_number">Confirmation number</label>
              <input class="form-input" type="text" id="payment_conf_number" name="payment_conf_number" />
            </div>
            <div class="form-group">
              <label for="payment_made_by">Payment made by</label>
              <input class="form-input" type="text" id="payment_made_by" name="payment_made_by" value="<?= htmlspecialchars(auth_user()['UserName'] ?? '') ?>" />
            </div>
            <div class="form-group form-grid-full">
              <label for="payment_comments">Comments</label>
              <textarea class="form-input" id="payment_comments" name="payment_comments" rows="3"></textarea>
            </div>
          </div>
          <div class="module-actions">
            <button type="submit" class="btn-primary">Add Payment</button>
          </div>
        </form>
        <?php endif; ?>
      </section>
