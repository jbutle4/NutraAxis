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
$canSubmit = $canSubmit ?? provider_signup_provider_can_submit($application);
$attachments = $attachments ?? provider_signup_list_attachments((int) $application['ApplicationID']);
$checklist = $checklist ?? provider_signup_submit_checklist($form, (int) $application['ApplicationID']);
$token = (string) ($application['AccessToken'] ?? '');
?>
<div class="signup-form-page">
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

  <?php if (!$editable && (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_APPROVED): ?>
  <div class="signup-alert signup-alert--info" role="status">
    Your application is approved. Our operations team is creating your Clinic Store in ACCS. You will receive email when your account is ready.
  </div>
  <?php elseif (!$editable && (string) ($application['Status'] ?? '') === PROVIDER_SIGNUP_STATUS_PROVISIONED): ?>
  <div class="signup-alert signup-alert--success" role="status">
    Your Clinic Store has been created. Check your email for sign-in details.
  </div>
  <?php elseif (!$editable): ?>
  <div class="signup-alert signup-alert--info" role="status">
    This application is under operations review. You can save updates while it is in draft or returned status.
  </div>
  <?php endif; ?>

  <form class="signup-form" method="post" action="/provider-signup/apply.php?token=<?= rawurlencode($token) ?>" enctype="multipart/form-data" novalidate>
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
        <label>Clinic type *
          <select name="clinic_type" required>
            <option value="">Select clinic type</option>
            <?php foreach (PROVIDER_SIGNUP_CLINIC_TYPES as $clinicType): ?>
            <option value="<?= htmlspecialchars($clinicType) ?>" <?= $form['clinic_type'] === $clinicType ? 'selected' : '' ?>><?= htmlspecialchars($clinicType) ?></option>
            <?php endforeach; ?>
          </select>
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
    <div class="signup-upload">
      <?php
      $uploadFieldId = 'reseller_doc';
      $uploadFieldName = 'reseller_doc';
      $uploadLabel = 'State reseller certificate (PDF or image) *';
      $uploadTitle = 'Drop, paste, or choose certificate';
      $uploadHint = 'Drag a PDF or image here, click and paste (Ctrl+V / Cmd+V), or choose a file — up to 15 MB';
      $uploadAccept = '.pdf,image/*,application/pdf';
      $uploadMaxBytes = PROVIDER_SIGNUP_MAX_ATTACHMENT_BYTES;
      $uploadAllowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
      $uploadSuccessMessage = 'Certificate selected';
      $uploadOnSelectHint = 'Click Upload certificate to save your progress and send %s.';
      $uploadGridClass = 'signup-upload__field';
      require dirname(__DIR__) . '/includes/file-upload-dropzone-field.php';
      ?>
      <div class="signup-upload__actions">
        <button
          class="btn-secondary"
          type="button"
          id="reseller-doc-upload-btn"
          data-upload-url="/provider-signup/save-reseller-doc.php?token=<?= rawurlencode($token) ?>"
        >Upload certificate</button>
      </div>
    </div>

    <div class="signup-form__actions">
      <button class="btn-secondary" type="submit" name="action" value="save_draft">Save draft</button>
    </div>
    <script>
    (function () {
      var form = document.querySelector('.signup-form');
      var uploadBtn = document.getElementById('reseller-doc-upload-btn');
      var dropzoneWrap = document.getElementById('reseller_doc-dropzone-form');
      var pasteZone = document.getElementById('reseller_doc-dropzone');
      var fileInput = document.getElementById('reseller_doc');
      if (!form || !uploadBtn || !dropzoneWrap || !pasteZone || !fileInput) return;

      var selectedFile = null;
      dropzoneWrap.addEventListener('dropzone-file-selected', function (event) {
        selectedFile = event.detail && event.detail.file ? event.detail.file : null;
      });

      function showUploadError(message) {
        var existing = form.querySelector('.signup-upload-error');
        if (existing) {
          existing.textContent = message;
          return;
        }
        var alert = document.createElement('div');
        alert.className = 'signup-alert signup-alert--error signup-upload-error';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;
        var uploadSection = form.querySelector('.signup-upload');
        if (uploadSection) {
          uploadSection.parentNode.insertBefore(alert, uploadSection);
        }
      }

      function clearUploadError() {
        var existing = form.querySelector('.signup-upload-error');
        if (existing) {
          existing.remove();
        }
      }

      function setUploading(isUploading) {
        uploadBtn.disabled = isUploading;
        uploadBtn.textContent = isUploading ? 'Uploading…' : 'Upload certificate';
        pasteZone.classList.toggle('is-uploading', isUploading);
      }

      function collectFormPayload() {
        var payload = {};
        Array.prototype.forEach.call(form.elements, function (element) {
          if (!element.name || element.disabled) {
            return;
          }
          if (element.type === 'file' || element.type === 'submit' || element.type === 'button') {
            return;
          }
          if (element.type === 'checkbox') {
            if (element.checked) {
              payload[element.name] = element.value;
            }
            return;
          }
          if (element.type === 'radio') {
            if (element.checked) {
              payload[element.name] = element.value;
            }
            return;
          }
          if (element.tagName === 'SELECT' || element.type === 'textarea' || element.type === 'password' || element.type === 'hidden' || element.type === 'email' || element.type === 'tel' || element.type === 'text') {
            payload[element.name] = element.value;
          }
        });
        return payload;
      }

      function uploadCertificate(file) {
        clearUploadError();
        setUploading(true);

        var reader = new FileReader();
        reader.onload = function () {
          var payload = collectFormPayload();
          var encoded = String(reader.result || '');
          if (encoded.indexOf(',') !== -1) {
            encoded = encoded.split(',')[1];
          }
          if (!encoded) {
            setUploading(false);
            showUploadError('Unable to read the selected certificate file.');
            return;
          }

          payload.attachment_payload = encoded;
          payload.attachment_name = file.name || 'reseller-certificate.pdf';
          payload.attachment_type = file.type || 'application/pdf';

          fetch(uploadBtn.getAttribute('data-upload-url') || '', {
            method: 'POST',
            body: JSON.stringify(payload),
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
          })
            .then(function (response) {
              return response.json().then(function (body) {
                return { response: response, payload: body };
              });
            })
            .then(function (result) {
              if (result.payload && result.payload.ok && result.payload.redirect) {
                window.location.href = result.payload.redirect;
                return;
              }
              throw new Error((result.payload && result.payload.error) || 'Unable to upload certificate.');
            })
            .catch(function (error) {
              setUploading(false);
              showUploadError(error && error.message ? error.message : 'Unable to upload certificate.');
            });
        };
        reader.onerror = function () {
          setUploading(false);
          showUploadError('Unable to read the selected certificate file.');
        };
        reader.readAsDataURL(file);
      }

      uploadBtn.addEventListener('click', function () {
        var file = selectedFile || dropzoneWrap.__dropzoneSelectedFile
          || (fileInput.files && fileInput.files[0] ? fileInput.files[0] : null);
        if (!file) {
          showUploadError('Choose or drop a certificate file before uploading.');
          return;
        }

        uploadCertificate(file);
      });
    })();
    </script>
    <?php endif; ?>
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
