<?php
/** @var array $form */
/** @var array $application */
/** @var bool $editable */
/** @var array $attachments */
/** @var array{complete: bool, missing: list<string>} $checklist */
/** @var ?string $error */
/** @var ?string $notice */
/** @var ?string $warn */

$editable = $editable ?? provider_signup_provider_can_edit($application);
$attachments = $attachments ?? provider_signup_list_attachments((int) $application['ApplicationID']);
$checklist = $checklist ?? provider_signup_submit_checklist($form, (int) $application['ApplicationID']);
$token = (string) ($application['AccessToken'] ?? '');
?>
<div class="signup-form-page">
  <div class="section-header signup-form-page__header">
    <div class="section-label">Provider Application</div>
    <h2 class="section-heading">Complete your provider signup</h2>
    <p class="section-sub">Save a draft at any time. Submit when all required company, compliance, and banking information is complete.</p>
  </div>

  <?php if (!empty($notice)): ?>
  <div class="signup-alert signup-alert--success" role="status"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if (!empty($warn)): ?>
  <div class="signup-alert signup-alert--warn" role="status"><?= htmlspecialchars($warn) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
  <div class="signup-alert signup-alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="signup-meta">
    <span><strong>Status:</strong> <?= htmlspecialchars((string) $application['Status']) ?></span>
    <span><strong>Application ID:</strong> <?= (int) $application['ApplicationID'] ?></span>
    <span><strong>Last saved:</strong> <?= htmlspecialchars(provider_signup_format_datetime($application['LastSavedAt'] ?? null)) ?></span>
  </div>

  <?php if (!$editable): ?>
  <div class="signup-alert signup-alert--info" role="status">
    This application has been submitted and is under review. Contact operations if you need changes.
  </div>
  <?php endif; ?>

  <form class="signup-form" method="post" action="/provider-signup/apply.php?token=<?= rawurlencode($token) ?>" novalidate>
    <input type="hidden" name="access_token" value="<?= htmlspecialchars($token) ?>" />

    <fieldset class="signup-fieldset" <?= $editable ? '' : 'disabled' ?>>
      <legend>ACCS company information</legend>
      <div class="signup-grid">
        <label>Practice / company name *
          <input type="text" name="company_name" value="<?= htmlspecialchars($form['company_name']) ?>" required />
        </label>
        <label>Legal company name *
          <input type="text" name="company_legal_name" value="<?= htmlspecialchars($form['company_legal_name']) ?>" required />
        </label>
        <label>Company email *
          <input type="email" name="company_email" value="<?= htmlspecialchars($form['company_email']) ?>" required />
        </label>
        <label>Company phone *
          <input type="tel" name="company_phone" value="<?= htmlspecialchars($form['company_phone']) ?>" required />
        </label>
        <label class="signup-grid--full">Street address *
          <input type="text" name="street_address" value="<?= htmlspecialchars($form['street_address']) ?>" required />
        </label>
        <label>City *
          <input type="text" name="city" value="<?= htmlspecialchars($form['city']) ?>" required />
        </label>
        <label>State *
          <select name="state_code" required>
            <option value="">Select state</option>
            <?php foreach (PROVIDER_SIGNUP_US_STATES as $code => $name): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $form['state_code'] === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Postal code *
          <input type="text" name="postal_code" value="<?= htmlspecialchars($form['postal_code']) ?>" required />
        </label>
      </div>
    </fieldset>

    <fieldset class="signup-fieldset" <?= $editable ? '' : 'disabled' ?>>
      <legend>Provider admin user (ACCS)</legend>
      <div class="signup-grid">
        <label>Provider email
          <input type="email" value="<?= htmlspecialchars($form['provider_email']) ?>" readonly />
        </label>
        <label>Admin first name *
          <input type="text" name="admin_first_name" value="<?= htmlspecialchars($form['admin_first_name']) ?>" required />
        </label>
        <label>Admin last name *
          <input type="text" name="admin_last_name" value="<?= htmlspecialchars($form['admin_last_name']) ?>" required />
        </label>
        <label>Admin email *
          <input type="email" name="admin_email" value="<?= htmlspecialchars($form['admin_email']) ?>" required />
        </label>
        <label>Admin phone
          <input type="tel" name="admin_phone" value="<?= htmlspecialchars($form['admin_phone']) ?>" />
        </label>
      </div>
    </fieldset>

    <fieldset class="signup-fieldset" <?= $editable ? '' : 'disabled' ?>>
      <legend>Compliance and payouts</legend>
      <div class="signup-grid">
        <label>NPI number *
          <input type="text" name="npi_number" inputmode="numeric" maxlength="10" value="<?= htmlspecialchars($form['npi_number']) ?>" required />
        </label>
        <label>Tax ID type *
          <select name="tax_id_type" required>
            <option value="">Select type</option>
            <?php foreach (PROVIDER_SIGNUP_TAX_ID_TYPES as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>" <?= $form['tax_id_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Tax ID (SSN or EIN) *
          <input type="password" name="tax_id" autocomplete="off" placeholder="<?= trim((string) ($application['TaxIdEncrypted'] ?? '')) !== '' ? 'Saved — enter to replace' : 'Required for submit' ?>" />
        </label>
        <label>ACH routing number *
          <input type="text" name="ach_routing_number" inputmode="numeric" maxlength="9" value="<?= htmlspecialchars($form['ach_routing_number']) ?>" required />
        </label>
        <label>ACH account number *
          <input type="password" name="ach_account_number" autocomplete="off" placeholder="<?= trim((string) ($application['AchAccountNumberEncrypted'] ?? '')) !== '' ? 'Saved — enter to replace' : 'Required for submit' ?>" />
        </label>
        <label>ACH account type *
          <select name="ach_account_type" required>
            <?php foreach (PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>" <?= $form['ach_account_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </fieldset>

    <?php if ($editable): ?>
    <div class="signup-form__actions">
      <button class="btn-secondary" type="submit" name="action" value="save_draft">Save draft</button>
      <button
        class="btn-cta"
        type="submit"
        name="action"
        value="submit_application"
        <?= $checklist['complete'] ? '' : 'disabled' ?>
      >Submit application</button>
    </div>
    <?php if (!$checklist['complete']): ?>
    <p class="signup-checklist">Still needed before submit: <?= htmlspecialchars(implode(', ', $checklist['missing'])) ?></p>
    <?php endif; ?>
    <?php endif; ?>
  </form>

  <form
    class="signup-upload"
    method="post"
    action="/provider-signup/apply.php?token=<?= rawurlencode($token) ?>"
    enctype="multipart/form-data"
    <?= $editable ? '' : 'hidden' ?>
  >
    <input type="hidden" name="access_token" value="<?= htmlspecialchars($token) ?>" />
    <label>State reseller certificate (PDF or image) *
      <input type="file" name="reseller_certificate" accept=".pdf,image/*" />
    </label>
    <button class="btn-secondary" type="submit" name="action" value="upload_certificate">Upload certificate</button>
  </form>

  <?php if ($attachments !== []): ?>
  <div class="signup-uploaded">
    <p class="signup-uploaded__title">Uploaded documents</p>
    <ul>
      <?php foreach ($attachments as $attachment): ?>
      <li><?= htmlspecialchars((string) $attachment['FileName']) ?> (<?= htmlspecialchars(provider_signup_format_datetime($attachment['UploadDate'] ?? null)) ?>)</li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <p class="signup-back-link"><a href="/provider-signup/">← Back to For Providers</a></p>
</div>
