<?php
/** @var array $form */
/** @var array $application */
/** @var bool $editable */
/** @var bool $canCompleteDocuments */
/** @var array $attachments */
/** @var array{complete: bool, missing: list<string>} $checklist */
/** @var list<string> $documentWarnings */
/** @var ?string $error */
/** @var ?string $notice */
/** @var ?string $warn */

$editable = $editable ?? provider_signup_provider_can_edit($application);
$canSubmit = $canSubmit ?? provider_signup_provider_can_submit($application);
$canCompleteDocuments = $canCompleteDocuments ?? provider_signup_provider_can_complete_documents($application);
$attachments = $attachments ?? provider_signup_list_attachments((int) $application['ApplicationID']);
$checklist = $checklist ?? provider_signup_submit_checklist($form, (int) $application['ApplicationID']);
$documentWarnings = $documentWarnings ?? provider_signup_optional_documents_warnings($form, (int) $application['ApplicationID']);
$token = (string) ($application['AccessToken'] ?? '');
$status = (string) ($application['Status'] ?? '');
$isSubmittedForReview = $status === PROVIDER_SIGNUP_STATUS_SUBMITTED;
$documentsOnly = !$editable && $canCompleteDocuments;
?>
<div class="signup-form-page">
  <?php if (!empty($notice) && !($isSubmittedForReview && ($_GET['notice'] ?? '') === 'submitted')): ?>
  <div class="signup-alert signup-alert--success" role="status"><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if (!empty($warn)): ?>
  <div class="signup-alert signup-alert--warn" role="status"><?= htmlspecialchars($warn) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
  <div class="signup-alert signup-alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($isSubmittedForReview): ?>
  <div class="signup-submitted">
    <div class="section-label">Application Submitted</div>
    <h2 class="section-heading">Thank you for submitting your application</h2>
    <div class="signup-meta">
      <span><strong>Status:</strong> <?= htmlspecialchars($status) ?></span>
      <span><strong>Application ID:</strong> <?= (int) $application['ApplicationID'] ?></span>
      <span><strong>Submitted:</strong> <?= htmlspecialchars(provider_signup_format_datetime($application['SubmittedAt'] ?? null)) ?></span>
    </div>
    <div class="signup-submitted__body">
      <p>Thank you for submitting your application. Your application will be validated for completeness and eligibility for Tax Exemption status. Your store will be provisioned and you will receive further instruction on the next steps. Please note that it can take up to 3-5 business days to set up your clinic store once your application has been reviewed and approved. You will be able, however, to log in and purchase at wholesale prices plus applicable state sales tax. Once your application has been validated for tax exemption, you will no longer be charged any state tax for purchases for resale. If you are operating in a state with existing tax exemptions on food and dietary supplements, that will already be applied at checkout.</p>
      <p>We also emailed you a secure return link<?= $canCompleteDocuments ? ' so you can upload your reseller certificate and/or add ACH payout details later' : '' ?>.</p>
    </div>
    <?php provider_signup_render_support_link('provider-support-link provider-support-link--submitted'); ?>
  </div>
  <?php endif; ?>

  <?php if ($editable): ?>
  <div class="signup-meta">
    <span><strong>Status:</strong> <?= htmlspecialchars($status) ?></span>
    <span><strong>Application ID:</strong> <?= (int) $application['ApplicationID'] ?></span>
    <span><strong>Last saved:</strong> <?= htmlspecialchars(provider_signup_format_datetime($application['LastSavedAt'] ?? null)) ?></span>
  </div>

  <?php foreach ($documentWarnings as $documentWarning): ?>
  <div class="signup-alert signup-alert--warn" role="status"><?= htmlspecialchars($documentWarning) ?></div>
  <?php endforeach; ?>

  <form class="signup-form" method="post" action="/provider-signup/apply.php?token=<?= rawurlencode($token) ?>" novalidate>
    <input type="hidden" name="access_token" value="<?= htmlspecialchars($token) ?>" />

    <fieldset class="signup-fieldset">
      <legend>Company information</legend>
      <div class="signup-grid">
        <label><span>Practice / company name *</span>
          <input type="text" name="company_name" value="<?= htmlspecialchars($form['company_name']) ?>" required />
        </label>
        <label><span>Legal company name *</span>
          <input type="text" name="company_legal_name" value="<?= htmlspecialchars($form['company_legal_name']) ?>" required />
        </label>
        <label><span>Company email *</span>
          <input type="email" name="company_email" value="<?= htmlspecialchars($form['company_email']) ?>" required />
        </label>
        <label><span>Company phone *</span>
          <input type="tel" name="company_phone" value="<?= htmlspecialchars($form['company_phone']) ?>" required />
        </label>
        <label class="signup-grid--full"><span>Street address *</span>
          <input type="text" name="street_address" value="<?= htmlspecialchars($form['street_address']) ?>" required />
        </label>
        <label><span>City *</span>
          <input type="text" name="city" value="<?= htmlspecialchars($form['city']) ?>" required />
        </label>
        <label><span>State *</span>
          <select name="state_code" required>
            <option value="">Select state</option>
            <?php foreach (PROVIDER_SIGNUP_US_STATES as $code => $name): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $form['state_code'] === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><span>Postal code *</span>
          <input type="text" name="postal_code" value="<?= htmlspecialchars($form['postal_code']) ?>" required />
        </label>
        <label class="signup-grid--full"><span>Clinic type *</span>
          <select name="clinic_type" required>
            <option value="">Select clinic type</option>
            <?php foreach (PROVIDER_SIGNUP_CLINIC_TYPES as $clinicType): ?>
            <option value="<?= htmlspecialchars($clinicType) ?>" <?= $form['clinic_type'] === $clinicType ? 'selected' : '' ?>><?= htmlspecialchars($clinicType) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </fieldset>

    <fieldset class="signup-fieldset">
      <legend>Practitioner admin user</legend>
      <div class="signup-grid">
        <label><span>Practitioner email</span>
          <input type="email" value="<?= htmlspecialchars($form['provider_email']) ?>" readonly />
        </label>
        <label><span>Admin first name *</span>
          <input type="text" name="admin_first_name" value="<?= htmlspecialchars($form['admin_first_name']) ?>" required />
        </label>
        <label><span>Admin last name *</span>
          <input type="text" name="admin_last_name" value="<?= htmlspecialchars($form['admin_last_name']) ?>" required />
        </label>
        <label><span>Admin email *</span>
          <input type="email" name="admin_email" value="<?= htmlspecialchars($form['admin_email']) ?>" required />
        </label>
        <label><span>Admin phone</span>
          <input type="tel" name="admin_phone" value="<?= htmlspecialchars($form['admin_phone']) ?>" />
        </label>
      </div>
    </fieldset>

    <fieldset class="signup-fieldset">
      <legend>Qualifications for Wholesale</legend>
      <p class="signup-fieldset__hint">Provide credentials required for wholesale pricing and tax-exempt status.</p>
      <div class="signup-grid">
        <label><span>NPI # *</span>
          <input type="text" name="npi_number" inputmode="numeric" maxlength="10" value="<?= htmlspecialchars($form['npi_number']) ?>" required />
        </label>
        <label><span>Tax ID type *</span>
          <select name="tax_id_type" required>
            <option value="">Select type</option>
            <?php foreach (PROVIDER_SIGNUP_TAX_ID_TYPES as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>" <?= $form['tax_id_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><span>Tax ID (SSN or EIN) *</span>
          <input type="password" name="tax_id" autocomplete="off" placeholder="<?= trim((string) ($application['TaxIdEncrypted'] ?? '')) !== '' ? 'Saved — enter to replace' : 'Required for submit' ?>" />
        </label>
      </div>
    </fieldset>

    <fieldset class="signup-fieldset">
      <legend>Payouts</legend>
      <p class="signup-fieldset__hint">Banking details for monthly sales proceeds payouts (optional for submit). All practitioners receive a Clinic Store.</p>
      <div class="signup-grid">
        <label><span>ACH routing #</span>
          <input type="text" name="ach_routing_number" inputmode="numeric" maxlength="9" value="<?= htmlspecialchars($form['ach_routing_number']) ?>" />
        </label>
        <label><span>ACH account #</span>
          <input type="password" name="ach_account_number" autocomplete="off" placeholder="<?= trim((string) ($application['AchAccountNumberEncrypted'] ?? '')) !== '' ? 'Saved — enter to replace' : 'Optional — required for payouts' ?>" />
        </label>
        <label><span>ACH account type</span>
          <select name="ach_account_type">
            <option value="">Select type</option>
            <?php foreach (PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>" <?= $form['ach_account_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    </fieldset>

    <div class="signup-form__actions">
      <button class="btn-secondary" type="submit" name="action" value="save_draft">Save draft</button>
      <?php if ($canSubmit): ?>
      <button class="btn-cta" type="submit" name="action" value="submit_application">Submit</button>
      <?php endif; ?>
    </div>
  </form>

  <?php elseif ($documentsOnly): ?>
  <?php if (!$isSubmittedForReview): ?>
  <div class="signup-meta">
    <span><strong>Status:</strong> <?= htmlspecialchars($status) ?></span>
    <span><strong>Application ID:</strong> <?= (int) $application['ApplicationID'] ?></span>
    <span><strong>Last saved:</strong> <?= htmlspecialchars(provider_signup_format_datetime($application['LastSavedAt'] ?? null)) ?></span>
  </div>
  <?php endif; ?>

  <div class="signup-complete-documents">
    <div class="section-label">Complete documents</div>
    <h2 class="section-heading">Upload certificate &amp; ACH details</h2>
    <p class="signup-fieldset__hint">You can return to this link anytime to upload your state reseller certificate and add ACH payout information. Company and admin details are locked while your application is under review.</p>

    <?php foreach ($documentWarnings as $documentWarning): ?>
    <div class="signup-alert signup-alert--warn" role="status"><?= htmlspecialchars($documentWarning) ?></div>
    <?php endforeach; ?>

    <form class="signup-form" method="post" action="/provider-signup/apply.php?token=<?= rawurlencode($token) ?>" novalidate>
      <input type="hidden" name="access_token" value="<?= htmlspecialchars($token) ?>" />
      <fieldset class="signup-fieldset">
        <legend>Payouts</legend>
        <p class="signup-fieldset__hint">Required before you can receive clinic payouts.</p>
        <div class="signup-grid">
          <label><span>ACH routing #</span>
            <input type="text" name="ach_routing_number" inputmode="numeric" maxlength="9" value="<?= htmlspecialchars($form['ach_routing_number']) ?>" />
          </label>
          <label><span>ACH account #</span>
            <input type="password" name="ach_account_number" autocomplete="off" placeholder="<?= trim((string) ($application['AchAccountNumberEncrypted'] ?? '')) !== '' ? 'Saved — enter to replace' : 'Enter account number' ?>" />
          </label>
          <label><span>ACH account type</span>
            <select name="ach_account_type">
              <option value="">Select type</option>
              <?php foreach (PROVIDER_SIGNUP_ACH_ACCOUNT_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $form['ach_account_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </fieldset>
      <div class="signup-form__actions">
        <button class="btn-cta" type="submit" name="action" value="save_documents">Save ACH details</button>
      </div>
    </form>
  </div>

  <?php elseif (!$editable): ?>
  <div class="signup-meta">
    <span><strong>Status:</strong> <?= htmlspecialchars($status) ?></span>
    <span><strong>Application ID:</strong> <?= (int) $application['ApplicationID'] ?></span>
  </div>
  <?php if ($status === PROVIDER_SIGNUP_STATUS_APPROVED): ?>
  <div class="signup-alert signup-alert--info" role="status">
    Your application is approved. Our operations team is creating your Clinic Store. You will receive email when your account is ready.
  </div>
  <?php elseif ($status === PROVIDER_SIGNUP_STATUS_PROVISIONED): ?>
  <div class="signup-alert signup-alert--success" role="status">
    Your Clinic Store has been created. Check your email for sign-in details.
  </div>
  <?php else: ?>
  <div class="signup-alert signup-alert--info" role="status">
    This application is under operations review. You can save updates while it is in draft or returned status.
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($editable || $documentsOnly): ?>
  <form
    class="signup-upload"
    method="post"
    action="/provider-signup/apply.php?token=<?= rawurlencode($token) ?>"
    enctype="multipart/form-data"
  >
    <input type="hidden" name="access_token" value="<?= htmlspecialchars($token) ?>" />
    <?php
    $uploadFieldId = 'reseller_certificate';
    $uploadFieldName = 'reseller_certificate';
    $uploadLabel = 'State reseller certificate and Business License (PDF or image)';
    $uploadTitle = 'Drop, paste, or choose certificate';
    $uploadHint = 'Optional for submit — required for tax-exempt status. Drag a PDF or image here, click and paste (Ctrl+V / Cmd+V), or choose a file — up to 15 MB';
    $uploadAccept = '.pdf,image/*,application/pdf';
    $uploadMaxBytes = PROVIDER_SIGNUP_MAX_ATTACHMENT_BYTES;
    $uploadAllowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
    $uploadSuccessMessage = 'Document selected';
    $uploadOnSelectHint = 'Click Upload document to send %s.';
    $uploadGridClass = 'signup-upload__field';
    require dirname(__DIR__) . '/includes/file-upload-dropzone-field.php';
    ?>
    <div class="signup-upload__actions">
      <button class="btn-secondary" type="submit" name="action" value="upload_certificate">Upload document</button>
    </div>
  </form>
  <?php endif; ?>

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

  <p class="signup-back-link"><a href="/provider-signup/">← Back to For Practitioners</a></p>
</div>
