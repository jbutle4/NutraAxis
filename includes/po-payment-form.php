<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $poOptions */
/** @var array $invoiceOptions */
/** @var bool $invoiceOnly */
$isEdit = $isEdit ?? false;
$invoiceOptions = $invoiceOptions ?? [];
$invoiceOnly = $invoiceOnly ?? false;
$paymentTarget = $invoiceOnly ? 'invoice' : (($form['payment_target'] ?? 'po') === 'invoice' ? 'invoice' : 'po');
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>" id="po-payment-form">
        <div class="form-grid">
          <?php if ($invoiceOnly): ?>
          <input type="hidden" name="payment_target" value="invoice" />
          <?php else: ?>
          <div class="form-group form-grid-full">
            <label for="payment_target">Pay against</label>
            <select class="form-input" id="payment_target" name="payment_target" <?= $isEdit ? 'disabled' : '' ?>>
              <option value="po" <?= $paymentTarget === 'po' ? 'selected' : '' ?>>Purchase order</option>
              <option value="invoice" <?= $paymentTarget === 'invoice' ? 'selected' : '' ?>>Supplier invoice (no PO)</option>
            </select>
            <?php if ($isEdit): ?>
            <input type="hidden" name="payment_target" value="<?= htmlspecialchars($paymentTarget) ?>" />
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if (!$invoiceOnly): ?>
          <div class="form-group form-grid-full" id="payment-target-po" <?= $paymentTarget === 'invoice' ? 'hidden' : '' ?>>
            <label for="po_id">Purchase order</label>
            <select class="form-input" id="po_id" name="po_id" <?= $isEdit ? 'disabled' : '' ?>>
              <option value="">Select PO</option>
              <?php foreach ($poOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['po_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?>
            <input type="hidden" name="po_id" value="<?= htmlspecialchars((string) ($form['po_id'] ?? '')) ?>" />
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="form-group form-grid-full" id="payment-target-invoice" <?= (!$invoiceOnly && $paymentTarget === 'po') ? 'hidden' : '' ?>>
            <label for="supplier_invoice_id">Supplier invoice</label>
            <select class="form-input" id="supplier_invoice_id" name="supplier_invoice_id" <?= $isEdit ? 'disabled' : '' ?>>
              <option value="">Select invoice</option>
              <?php foreach ($invoiceOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['supplier_invoice_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?>
            <input type="hidden" name="supplier_invoice_id" value="<?= htmlspecialchars((string) ($form['supplier_invoice_id'] ?? '')) ?>" />
            <?php endif; ?>
            <?php if (!$isEdit && $invoiceOptions === []): ?>
            <p class="form-help">No supplier invoices without a PO are available yet.</p>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="payment_date">Payment date</label>
            <input class="form-input" type="datetime-local" id="payment_date" name="payment_date" value="<?= htmlspecialchars($form['payment_date'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label for="payment_amount">Payment amount ($)</label>
            <input class="form-input" type="number" min="0.01" step="0.01" id="payment_amount" name="payment_amount" value="<?= htmlspecialchars($form['payment_amount'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label for="payment_type">Payment type</label>
            <select class="form-input" id="payment_type" name="payment_type" required>
              <?php foreach (PO_PAYMENT_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= ($form['payment_type'] ?? '') === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($invoiceOnly): ?>
          <div class="form-group">
            <p class="form-static">
              <span class="status-badge <?= po_payment_status_class((string) ($form['payment_status'] ?? 'Pending')) ?>">
                <?= htmlspecialchars(po_payment_format_status($form['payment_status'] ?? 'Pending')) ?>
              </span>
            </p>
            <input type="hidden" name="payment_status" value="<?= htmlspecialchars($form['payment_status'] ?? 'Pending') ?>" />
            <p class="form-hint">Status changes through the payment approval workflow.</p>
          </div>
          <?php else: ?>
          <div class="form-group">
            <label for="payment_status">Payment status</label>
            <select class="form-input" id="payment_status" name="payment_status" required>
              <?php foreach (PO_PAYMENT_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['payment_status'] ?? 'Paid') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="form-group">
            <label for="payment_conf_number">Confirmation number</label>
            <input class="form-input" type="text" id="payment_conf_number" name="payment_conf_number" value="<?= htmlspecialchars($form['payment_conf_number'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="payment_made_by">Payment made by</label>
            <input class="form-input" type="text" id="payment_made_by" name="payment_made_by" value="<?= htmlspecialchars($form['payment_made_by'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="payment_comments">Comments</label>
            <textarea class="form-input" id="payment_comments" name="payment_comments" rows="4"><?= htmlspecialchars($form['payment_comments'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Record Payment' ?></button>
          <a class="btn-secondary" href="/po-payments/">Cancel</a>
        </div>
      </form>
      <?php if (!$isEdit && !$invoiceOnly): ?>
      <script>
        (function () {
          var target = document.getElementById('payment_target');
          var poBlock = document.getElementById('payment-target-po');
          var invoiceBlock = document.getElementById('payment-target-invoice');
          if (!target || !poBlock || !invoiceBlock) {
            return;
          }
          function syncPaymentTarget() {
            var isInvoice = target.value === 'invoice';
            poBlock.hidden = isInvoice;
            invoiceBlock.hidden = !isInvoice;
          }
          target.addEventListener('change', syncPaymentTarget);
          syncPaymentTarget();
        })();
      </script>
      <?php endif; ?>
