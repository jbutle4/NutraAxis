<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
$isEdit = $isEdit ?? false;
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label for="supplier_code">Supplier code</label>
            <input
              class="form-input"
              type="text"
              id="supplier_code"
              name="supplier_code"
              value="<?= htmlspecialchars($form['supplier_code'] ?? '') ?>"
              <?= $isEdit ? 'required' : '' ?>
              placeholder="<?= $isEdit ? '' : 'Auto-generated if blank' ?>"
            />
          </div>
          <div class="form-group">
            <label for="is_active">Status</label>
            <select class="form-input" id="is_active" name="is_active">
              <option value="1" <?= !empty($form['is_active']) ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= empty($form['is_active']) ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="supplier_name">Supplier name</label>
            <input class="form-input" type="text" id="supplier_name" name="supplier_name" value="<?= htmlspecialchars($form['supplier_name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label for="supplier_type">Supplier type</label>
            <select class="form-input" id="supplier_type" name="supplier_type">
              <option value="">Not set</option>
              <?php foreach (SUPPLIER_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= ($form['supplier_type'] ?? '') === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="address">Address</label>
            <input class="form-input" type="text" id="address" name="address" value="<?= htmlspecialchars($form['address'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_name">Contact name</label>
            <input class="form-input" type="text" id="contact_name" name="contact_name" value="<?= htmlspecialchars($form['contact_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_phone">Contact phone</label>
            <input class="form-input" type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($form['contact_phone'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="contact_email">Contact email</label>
            <input class="form-input" type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($form['contact_email'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="4"><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Supplier' ?></button>
          <a class="btn-secondary" href="<?= $isEdit ? '/supplier-management/view.php?id=' . (int) ($form['supplier_id'] ?? 0) : '/supplier-management/' ?>">Cancel</a>
        </div>
      </form>
