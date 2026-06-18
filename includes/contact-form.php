<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var int|null $contactId */
/** @var array $supplierOptions */
$isEdit = $isEdit ?? false;
$contactId = isset($contactId) ? (int) $contactId : 0;
$supplierOptions = $supplierOptions ?? contacts_supplier_options(
    ($form['related_supplier_company'] ?? '') !== '' ? (int) $form['related_supplier_company'] : null
);
?>
      <div class="admin-form">
      <?php if ($isEdit && $contactId > 0 && contacts_can_delete()): ?>
      <form id="contact-delete-form" method="post" action="/contacts-list/delete.php" class="visually-hidden-form" onsubmit="return confirm('Delete this contact?');">
        <input type="hidden" name="contact_id" value="<?= $contactId ?>" />
      </form>
      <?php endif; ?>
      <form id="contact-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label for="contact_first_name">First name</label>
            <input class="form-input" type="text" id="contact_first_name" name="contact_first_name" value="<?= htmlspecialchars($form['contact_first_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_last_name">Last name</label>
            <input class="form-input" type="text" id="contact_last_name" name="contact_last_name" value="<?= htmlspecialchars($form['contact_last_name'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="contact_company">Company</label>
            <input class="form-input" type="text" id="contact_company" name="contact_company" value="<?= htmlspecialchars($form['contact_company'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_type">Contact type</label>
            <select class="form-input" id="contact_type" name="contact_type">
              <option value="">Select type</option>
              <?php foreach (CONTACT_TYPES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= ($form['contact_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="related_supplier_company">Related supplier</label>
            <select class="form-input" id="related_supplier_company" name="related_supplier_company">
              <option value="">None</option>
              <?php foreach ($supplierOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= !empty($option['selected']) ? 'selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="contact_phone">Phone</label>
            <input class="form-input" type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($form['contact_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_email">Email</label>
            <input class="form-input" type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($form['contact_email'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="contact_address">Address</label>
            <input class="form-input" type="text" id="contact_address" name="contact_address" value="<?= htmlspecialchars($form['contact_address'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_city">City</label>
            <input class="form-input" type="text" id="contact_city" name="contact_city" value="<?= htmlspecialchars($form['contact_city'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_state">State</label>
            <select class="form-input" id="contact_state" name="contact_state">
              <option value="">Select state</option>
              <?php foreach (CONTACT_US_STATES as $code => $label): ?>
              <option value="<?= htmlspecialchars($code) ?>" <?= strtoupper((string) ($form['contact_state'] ?? '')) === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="contact_zip">ZIP</label>
            <input class="form-input" type="text" id="contact_zip" name="contact_zip" value="<?= htmlspecialchars($form['contact_zip'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="contact_notes">Notes</label>
            <textarea class="form-input" id="contact_notes" name="contact_notes" rows="4"><?= htmlspecialchars($form['contact_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </form>
      <div class="form-actions">
        <button type="submit" form="contact-form" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Contact' ?></button>
        <a class="btn-secondary" href="<?= $isEdit && $contactId > 0 ? '/contacts-list/view.php?id=' . $contactId : '/contacts-list/' ?>">Cancel</a>
        <?php if ($isEdit && $contactId > 0 && contacts_can_delete()): ?>
        <button type="submit" form="contact-delete-form" class="btn-danger">Delete</button>
        <?php endif; ?>
      </div>
      </div>
