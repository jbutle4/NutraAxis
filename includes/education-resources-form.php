<?php
/**
 * @var array $form
 * @var bool $isEdit
 * @var string $formAction
 * @var array|null $existing
 */
$isEdit = $isEdit ?? false;
$formAction = $formAction ?? '';
$existing = $existing ?? null;
$type = (string) ($form['type'] ?? '');
$hasExistingPdf = $isEdit
    && strtoupper((string) ($existing['Type'] ?? '')) === 'PDF'
    && trim((string) ($existing['BlobPath'] ?? '')) !== '';
?>
<form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data" id="education-resource-form">
  <?php if ($isEdit): ?>
  <input type="hidden" name="erid" value="<?= (int) ($form['erid'] ?? 0) ?>" />
  <?php endif; ?>

  <div class="form-grid">
    <div class="form-group form-grid-full">
      <label for="description">Description</label>
      <textarea class="form-input" id="description" name="description" rows="3" required maxlength="4000"><?= htmlspecialchars((string) ($form['description'] ?? '')) ?></textarea>
    </div>

    <div class="form-group">
      <label for="type">Type</label>
      <select class="form-input" id="type" name="type" required>
        <option value="">Select type</option>
        <?php foreach (EDUCATION_RESOURCE_TYPES as $value => $label): ?>
        <option value="<?= htmlspecialchars($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group form-grid-full" id="education-video-fields" <?= $type === 'Video' ? '' : 'hidden' ?>>
      <label for="url">Vimeo URL</label>
      <input class="form-input" type="url" id="url" name="url" maxlength="2000"
             value="<?= htmlspecialchars($type === 'Video' ? (string) ($form['url'] ?? '') : '') ?>"
             placeholder="https://vimeo.com/…" />
      <p class="form-hint">Paste the full Vimeo link. It will open as a clickable resource in the list.</p>
    </div>

    <div class="form-group form-grid-full" id="education-pdf-fields" <?= $type === 'PDF' ? '' : 'hidden' ?>>
      <?php
      $uploadFieldId = 'pdf_file';
      $uploadFieldName = 'pdf_file';
      $uploadLabel = $hasExistingPdf ? 'Replace PDF (optional)' : 'PDF file';
      $uploadTitle = 'Drop, paste, or choose PDF';
      $uploadHint = 'PDF only · max 25 MB';
      $uploadFormHint = $hasExistingPdf
          ? 'Current file: ' . htmlspecialchars((string) ($existing['FileName'] ?? 'PDF on file')) . '. Leave blank to keep it.'
          : 'Uploaded PDFs are stored in Azure Blob Storage. The list link opens a secure download.';
      $uploadAccept = '.pdf,application/pdf';
      $uploadMaxBytes = EDUCATION_RESOURCE_MAX_UPLOAD_BYTES;
      $uploadAllowedExt = EDUCATION_RESOURCE_ALLOWED_EXTENSIONS;
      $uploadSuccessMessage = 'PDF attached';
      $uploadRequired = !$hasExistingPdf;
      $uploadGridClass = 'form-grid-full';
      require __DIR__ . '/file-upload-dropzone-field.php';
      ?>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn-primary"><?= $isEdit ? 'Save changes' : 'Create resource' ?></button>
    <a class="btn-secondary" href="/education-resources/">Cancel</a>
  </div>
</form>
<script>
(function () {
  var typeSelect = document.getElementById('type');
  var videoFields = document.getElementById('education-video-fields');
  var pdfFields = document.getElementById('education-pdf-fields');
  var urlInput = document.getElementById('url');
  var pdfInput = document.getElementById('pdf_file');
  if (!typeSelect || !videoFields || !pdfFields) return;

  function syncTypeFields() {
    var type = typeSelect.value;
    var isVideo = type === 'Video';
    var isPdf = type === 'PDF';
    videoFields.hidden = !isVideo;
    pdfFields.hidden = !isPdf;
    if (urlInput) {
      urlInput.required = isVideo;
      if (!isVideo) urlInput.removeAttribute('required');
    }
    if (pdfInput) {
      var requirePdf = isPdf && <?= $hasExistingPdf ? 'false' : 'true' ?>;
      pdfInput.required = requirePdf;
      if (!requirePdf) pdfInput.removeAttribute('required');
    }
  }

  typeSelect.addEventListener('change', syncTypeFields);
  syncTypeFields();
})();
</script>
