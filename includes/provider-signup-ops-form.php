<?php
/** @var array $form */
/** @var array $application */
/** @var ?string $error */

$hasStoredTaxId = trim((string) ($application['TaxIdEncrypted'] ?? '')) !== '';
$hasStoredAccount = trim((string) ($application['AchAccountNumberEncrypted'] ?? '')) !== '';
$opsFormAction = $opsFormAction ?? '/operations-dashboard/signup-review/application-form.php?id=' . (int) ($application['ApplicationID'] ?? 0);
$opsFormCancelHref = $opsFormCancelHref ?? '/operations-dashboard/signup-review/view.php?id=' . (int) ($application['ApplicationID'] ?? 0);
?>
<form class="admin-form" method="post" action="<?= htmlspecialchars($opsFormAction) ?>" novalidate>
  <?php if (!empty($error)): ?>
  <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <h2 class="admin-form-subhead">Company</h2>
  <div class="form-grid">
    <div class="form-group">
      <label for="company_name">Practice / company name *</label>
      <input class="form-input" type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($form['company_name']) ?>" required />
    </div>
    <div class="form-group">
      <label for="company_legal_name">Legal company name *</label>
      <input class="form-input" type="text" id="company_legal_name" name="company_legal_name" value="<?= htmlspecialchars($form['company_legal_name']) ?>" required />
    </div>
    <div class="form-group">
      <label for="company_email">Company email *</label>
      <input class="form-input" type="email" id="company_email" name="company_email" value="<?= htmlspecialchars($form['company_email']) ?>" required />
    </div>
    <div class="form-group">
      <label for="company_phone">Company phone *</label>
      <input class="form-input" type="tel" id="company_phone" name="company_phone" value="<?= htmlspecialchars($form['company_phone']) ?>" required />
    </div>
    <div class="form-group form-grid-full">
      <label for="street_address">Street address *</label>
      <input class="form-input" type="text" id="street_address" name="street_address" value="<?= htmlspecialchars($form['street_address']) ?>" required />
    </div>
    <div class="form-group">
      <label for="city">City *</label>
      <input class="form-input" type="text" id="city" name="city" value="<?= htmlspecialchars($form['city']) ?>" required />
    </div>
    <div class="form-group">
      <label for="state_code">State *</label>
      <select class="form-input" id="state_code" name="state_code" required>
        <option value="">Select state</option>
        <?php foreach (PROVIDER_SIGNUP_US_STATES as $code => $name): ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= $form['state_code'] === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="postal_code">Postal code *</label>
      <input class="form-input" type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($form['postal_code']) ?>" required />
    </div>
    <div class="form-group form-grid-full">
      <label for="clinic_type">Clinic type *</label>
      <select class="form-input" id="clinic_type" name="clinic_type" required>
        <option value="">Select clinic type</option>
        <?php foreach (PROVIDER_SIGNUP_CLINIC_TYPES as $clinicType): ?>
        <option value="<?= htmlspecialchars($clinicType) ?>" <?= $form['clinic_type'] === $clinicType ? 'selected' : '' ?>><?= htmlspecialchars($clinicType) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="form-hint">Required for ACCS company creation (maps to the clinic-type company attribute).</p>
    </div>
  </div>

  <h2 class="admin-form-subhead">Admin user</h2>
  <div class="form-grid">
    <div class="form-group">
      <label for="provider_email">Provider email</label>
      <input class="form-input" type="email" id="provider_email" value="<?= htmlspecialchars($form['provider_email']) ?>" readonly />
    </div>
    <div class="form-group">
      <label for="admin_first_name">Admin first name *</label>
      <input class="form-input" type="text" id="admin_first_name" name="admin_first_name" value="<?= htmlspecialchars($form['admin_first_name']) ?>" required />
    </div>
    <div class="form-group">
      <label for="admin_last_name">Admin last name *</label>
      <input class="form-input" type="text" id="admin_last_name" name="admin_last_name" value="<?= htmlspecialchars($form['admin_last_name']) ?>" required />
    </div>
    <div class="form-group">
      <label for="admin_email">Admin email *</label>
      <input class="form-input" type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($form['admin_email']) ?>" required />
    </div>
    <div class="form-group">
      <label for="admin_phone">Admin phone</label>
      <input class="form-input" type="tel" id="admin_phone" name="admin_phone" value="<?= htmlspecialchars($form['admin_phone']) ?>" />
    </div>
  </div>

  <h2 class="admin-form-subhead">Compliance &amp; banking</h2>
  <div class="form-grid">
    <div class="form-group">
      <label for="npi_number">NPI number *</label>
      <input class="form-input" type="text" id="npi_number" name="npi_number" inputmode="numeric" maxlength="10" value="<?= htmlspecialchars($form['npi_number']) ?>" required />
      <p class="form-hint">Must be exactly 10 digits. Use a valid NPPES-registered NPI for approval testing.</p>
    </div>
    <div class="form-group">
      <label for="tax_id_type">Tax ID type *</label>
      <select class="form-input" id="tax_id_type" name="tax_id_type" required>
        <option value="">Select type</option>
        <?php foreach (PROVIDER_SIGNUP_TAX_ID_TYPES as $type): ?>
        <option value="<?= htmlspecialchars($type) ?>" <?= $form['tax_id_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="tax_id">Tax ID (SSN or EIN)</label>
      <input class="form-input" type="password" id="tax_id" name="tax_id" autocomplete="off" placeholder="<?= $hasStoredTaxId ? 'Saved — enter to replace' : 'Leave blank to keep empty' ?>" />
    </div>
    <div class="form-group">
      <label for="ach_routing_number">ACH routing number *</label>
      <input class="form-input" type="text" id="ach_routing_number" name="ach_routing_number" inputmode="numeric" maxlength="9" value="<?= htmlspecialchars($form['ach_routing_number']) ?>" required />
    </div>
    <div class="form-group">
      <label for="ach_account_number">ACH account number</label>
      <input class="form-input" type="password" id="ach_account_number" name="ach_account_number" autocomplete="off" placeholder="<?= $hasStoredAccount ? 'Saved — enter to replace' : 'Leave blank to keep empty' ?>" />
    </div>
    <div class="form-group">
      <label for="ach_account_type">ACH account type *</label>
      <select class="form-input" id="ach_account_type" name="ach_account_type" required>
        <?php foreach (PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES as $type): ?>
        <option value="<?= htmlspecialchars($type) ?>" <?= $form['ach_account_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label for="edit_note">Edit note (optional)</label>
    <textarea class="form-input form-textarea" id="edit_note" name="edit_note" rows="3" placeholder="Brief note for review history, e.g. corrected NPI typo"></textarea>
  </div>

  <div class="module-actions">
    <a class="btn-secondary" href="<?= htmlspecialchars($opsFormCancelHref) ?>">Cancel</a>
    <button class="btn-primary" type="submit" name="action" value="save">Save changes</button>
  </div>
</form>
