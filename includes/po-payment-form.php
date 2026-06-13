<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $poOptions */
$isEdit = $isEdit ?? false;
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="po_id">Purchase order</label>
            <select class="form-input" id="po_id" name="po_id" required <?= $isEdit ? 'disabled' : '' ?>>
              <option value="">Select PO</option>
              <?php foreach ($poOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['po_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?>
            <input type="hidden" name="po_id" value="<?= (int) ($form['po_id'] ?? 0) ?>" />
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
