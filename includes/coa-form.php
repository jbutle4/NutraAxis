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
?>
<form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data">
  <?php if ($isEdit): ?>
  <input type="hidden" name="coa_document_id" value="<?= (int) ($form['coa_document_id'] ?? 0) ?>" />
  <?php endif; ?>

  <div class="form-grid">
    <div class="form-group">
      <label for="product_name">Product name</label>
      <input class="form-input" type="text" id="product_name" name="product_name" required maxlength="200"
             value="<?= htmlspecialchars((string) ($form['product_name'] ?? '')) ?>" />
    </div>

    <div class="form-group">
      <label for="lot_number">Lot number</label>
      <input class="form-input" type="text" id="lot_number" name="lot_number" required maxlength="50"
             value="<?= htmlspecialchars((string) ($form['lot_number'] ?? '')) ?>" />
    </div>

    <div class="form-group">
      <label for="expiration_date">Expiration date</label>
      <input class="form-input" type="date" id="expiration_date" name="expiration_date" required
             value="<?= htmlspecialchars((string) ($form['expiration_date'] ?? '')) ?>" />
    </div>

    <div class="form-group">
      <label for="expiration_display">Expiration display</label>
      <input class="form-input" type="text" id="expiration_display" name="expiration_display" maxlength="50"
             value="<?= htmlspecialchars((string) ($form['expiration_display'] ?? '')) ?>"
             placeholder="Optional — e.g. 04/2028" />
      <p class="form-hint">Leave blank to show the expiration date as MM/DD/YYYY on the public site.</p>
    </div>

    <div class="form-group">
      <label for="sort_order">Sort order</label>
      <input class="form-input" type="number" id="sort_order" name="sort_order" step="1"
             value="<?= htmlspecialchars((string) ($form['sort_order'] ?? '0')) ?>" />
      <p class="form-hint">Higher numbers appear first in the public COA table.</p>
    </div>

    <div class="form-group">
      <label class="checkbox-label">
        <input type="checkbox" name="is_published" value="1" <?= !empty($form['is_published']) ? 'checked' : '' ?> />
        Publish on nutraaxislabs.com
      </label>
    </div>

    <div class="form-group form-grid-full">
      <label for="notes">Internal notes</label>
      <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($form['notes'] ?? '')) ?></textarea>
    </div>

    <?php
    $uploadFieldId = 'coa_pdf';
    $uploadFieldName = 'coa_pdf';
    $uploadLabel = $isEdit ? 'Replace PDF' : 'COA PDF';
    $uploadTitle = 'Drop, paste, or choose PDF';
    $uploadHint = 'PDF only, up to 15 MB. The file is saved as ProductName+LotNumber.pdf (for example, AdrenaAxis37489.pdf).';
    $uploadAccept = '.pdf,application/pdf';
    $uploadMaxBytes = COA_MAX_UPLOAD_BYTES;
    $uploadAllowedExt = COA_ALLOWED_EXTENSIONS;
    $uploadRequired = !$isEdit;
    require __DIR__ . '/file-upload-dropzone-field.php';
    ?>

    <?php if ($isEdit && $existing !== null && trim((string) ($existing['FileName'] ?? '')) !== ''): ?>
    <div class="form-group form-grid-full">
      <p class="form-hint">
        Current file:
        <a href="/coa-documents/download.php?id=<?= (int) ($existing['CoaDocumentID'] ?? 0) ?>" target="_blank" rel="noopener noreferrer">
          <?= htmlspecialchars((string) $existing['FileName']) ?>
        </a>
      </p>
    </div>
    <?php endif; ?>
  </div>

  <?php
  $cancelHref = $isEdit
      ? '/coa-management/view.php?id=' . (int) ($form['coa_document_id'] ?? 0)
      : '/coa-management/';
  render_form_actions(
      '<button type="submit" class="btn-primary">' . ($isEdit ? 'Save changes' : 'Create COA') . '</button>'
      . '<a class="btn-secondary" href="' . htmlspecialchars($cancelHref) . '">Cancel</a>'
  );
  ?>
</form>
