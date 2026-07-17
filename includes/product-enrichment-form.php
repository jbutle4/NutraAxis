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
  <input type="hidden" name="product_enrichment_id" value="<?= (int) ($form['product_enrichment_id'] ?? 0) ?>" />
  <?php endif; ?>

  <div class="form-grid">
    <div class="form-group">
      <label for="sku_code">SKU code</label>
      <input class="form-input" type="text" id="sku_code" name="sku_code" required maxlength="100"
             value="<?= htmlspecialchars((string) ($form['sku_code'] ?? '')) ?>"
             placeholder="na-gw-002" <?= $isEdit ? 'readonly' : '' ?> />
      <p class="form-hint">Lowercase SKU from the product URL (for example, /products/magrenew/na-gw-002).</p>
    </div>

    <div class="form-group">
      <label for="product_name">Product name</label>
      <input class="form-input" type="text" id="product_name" name="product_name" maxlength="200"
             value="<?= htmlspecialchars((string) ($form['product_name'] ?? '')) ?>"
             placeholder="Optional if SKU exists in Product SKU Master" />
    </div>

    <div class="form-group">
      <label for="pdf_link_text">Information sheet link text</label>
      <input class="form-input" type="text" id="pdf_link_text" name="pdf_link_text" maxlength="200"
             value="<?= htmlspecialchars((string) ($form['pdf_link_text'] ?? '')) ?>"
             placeholder="MagRenew Information Sheet" />
      <p class="form-hint">Used for the PDF link when {{PDF_LINK}} is not embedded in the HTML below.</p>
    </div>

    <div class="form-group">
      <label class="checkbox-label">
        <input type="checkbox" name="is_published" value="1" <?= !empty($form['is_published']) ? 'checked' : '' ?> />
        Publish on nutraaxislabs.com
      </label>
    </div>

    <div class="form-group form-grid-full">
      <label for="enrichment_html">Enrichment content</label>
      <div class="pe-enrichment-editor" data-pe-enrichment-editor>
        <div class="pe-editor-toolbar">
          <div class="pe-editor-mode" role="tablist" aria-label="Editor mode">
            <button type="button" data-mode="visual" class="is-active">Visual</button>
            <button type="button" data-mode="html">HTML source</button>
          </div>
          <div class="pe-editor-actions">
            <button type="button" data-insert="pdf-link">Insert PDF link</button>
            <button type="button" data-insert="callout">Insert callout box</button>
            <button type="button" data-insert="wrapper">Insert page wrapper</button>
          </div>
        </div>
        <div data-pe-quill-mount class="pe-quill-panel"></div>
        <textarea class="pe-html-panel form-input" id="enrichment_html" name="enrichment_html" rows="16" hidden><?= htmlspecialchars((string) ($form['enrichment_html'] ?? '')) ?></textarea>
        <p class="form-hint">
          Use <strong>Visual</strong> for text, lists, and links. Switch to <strong>HTML source</strong> for styled callout boxes.
        Placeholders: <code>{{PDF_URL}}</code>, <code>{{PDF_LINK}}</code>, <code>{{PDF_LINK_TEXT}}</code>.
        Use heading, size, bold, bullet lists, alignment, and links in Visual mode. Numbered lists are saved as bullets.
        Switch to <strong>HTML source</strong> for styled callout boxes.
        </p>
      </div>
    </div>

    <div class="form-group form-grid-full">
      <label for="notes">Internal notes</label>
      <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($form['notes'] ?? '')) ?></textarea>
    </div>

    <?php
    $uploadFieldId = 'info_sheet_pdf';
    $uploadFieldName = 'info_sheet_pdf';
    $uploadLabel = $isEdit ? 'Replace information sheet PDF' : 'Information sheet PDF';
    $uploadTitle = 'Drop, paste, or choose PDF';
    $uploadHint = 'PDF only, up to 15 MB. Saved as ProductNameInfoSheet.pdf (for example, MagRenewInfoSheet.pdf).';
    $uploadAccept = '.pdf,application/pdf';
    $uploadMaxBytes = PRODUCT_ENRICHMENT_MAX_UPLOAD_BYTES;
    $uploadAllowedExt = PRODUCT_ENRICHMENT_ALLOWED_EXTENSIONS;
    $uploadRequired = false;
    require __DIR__ . '/file-upload-dropzone-field.php';
    ?>

    <?php if ($isEdit && $existing !== null && trim((string) ($existing['FileName'] ?? '')) !== ''): ?>
    <div class="form-group form-grid-full">
      <p class="form-hint">
        Current file:
        <a href="<?= htmlspecialchars(product_enrichment_admin_pdf_url((int) ($form['product_enrichment_id'] ?? 0))) ?>" target="_blank" rel="noopener noreferrer">
          <?= htmlspecialchars((string) $existing['FileName']) ?>
        </a>
      </p>
    </div>
    <?php endif; ?>
  </div>

  <?php
  $cancelHref = $isEdit
      ? '/product-enrichment/view.php?id=' . (int) ($form['product_enrichment_id'] ?? 0)
      : '/product-enrichment/';
  render_form_actions(
      '<button type="submit" class="btn-primary">' . ($isEdit ? 'Save changes' : 'Create enrichment') . '</button>'
      . '<a class="btn-secondary" href="' . htmlspecialchars($cancelHref) . '">Cancel</a>'
  );
  ?>
</form>
