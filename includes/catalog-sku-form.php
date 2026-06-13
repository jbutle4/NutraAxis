<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $supplierOptions */
$isEdit = $isEdit ?? false;
$supplierOptions = $supplierOptions ?? catalog_supplier_options(
    ($form['supplier_id'] ?? '') !== '' ? (int) $form['supplier_id'] : null
);
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label for="sku_code">SKU / Item code</label>
            <input
              class="form-input"
              type="text"
              id="sku_code"
              name="sku_code"
              value="<?= htmlspecialchars($form['sku_code'] ?? '') ?>"
              required
              placeholder="e.g. NA-AndroSync-60ct"
            />
          </div>
          <div class="form-group">
            <label for="sku_status">Status</label>
            <select class="form-input" id="sku_status" name="sku_status">
              <?php foreach (CATALOG_SKU_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['sku_status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="product_name">Product name</label>
            <input class="form-input" type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($form['product_name'] ?? '') ?>" required />
          </div>
          <div class="form-group form-grid-full">
            <label for="formulation">Formulation</label>
            <textarea class="form-input" id="formulation" name="formulation" rows="3" placeholder="Formula or ingredient description"><?= htmlspecialchars($form['formulation'] ?? '') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="product">Product</label>
            <textarea class="form-input" id="product" name="product" rows="3" placeholder="Product description from label master"><?= htmlspecialchars($form['product'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label for="brand">Brand</label>
            <select class="form-input" id="brand" name="brand" required>
              <?php foreach (CATALOG_BRANDS as $brand): ?>
              <option value="<?= htmlspecialchars($brand) ?>" <?= ($form['brand'] ?? '') === $brand ? 'selected' : '' ?>><?= htmlspecialchars($brand) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="manufacturer">Manufacturer</label>
            <select class="form-input" id="manufacturer" name="manufacturer" required>
              <?php foreach (CATALOG_MANUFACTURERS as $manufacturer): ?>
              <option value="<?= htmlspecialchars($manufacturer) ?>" <?= ($form['manufacturer'] ?? '') === $manufacturer ? 'selected' : '' ?>><?= htmlspecialchars($manufacturer) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select class="form-input" id="supplier_id" name="supplier_id">
              <option value="">Not linked</option>
              <?php foreach ($supplierOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['supplier_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="primary_therapeutic_category">Primary therapeutic category</label>
            <select class="form-input" id="primary_therapeutic_category" name="primary_therapeutic_category" required>
              <?php foreach (CATALOG_THERAPEUTIC_CATEGORIES as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>" <?= ($form['primary_therapeutic_category'] ?? '') === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="secondary_category">Secondary category</label>
            <select class="form-input" id="secondary_category" name="secondary_category">
              <option value="">None</option>
              <?php foreach (CATALOG_THERAPEUTIC_CATEGORIES as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>" <?= ($form['secondary_category'] ?? '') === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="capsule_count">Capsule count</label>
            <input class="form-input" type="number" min="1" id="capsule_count" name="capsule_count" value="<?= htmlspecialchars($form['capsule_count'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="serving_count">Servings per container</label>
            <input class="form-input" type="number" min="1" id="serving_count" name="serving_count" value="<?= htmlspecialchars($form['serving_count'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="label_selection">Label selection</label>
            <select class="form-input" id="label_selection" name="label_selection">
              <option value="">None</option>
              <?php foreach (CATALOG_LABEL_SELECTIONS as $selection): ?>
              <option value="<?= htmlspecialchars($selection) ?>" <?= ($form['label_selection'] ?? '') === $selection ? 'selected' : '' ?>><?= htmlspecialchars($selection) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="bottle_size">Bottle size</label>
            <input class="form-input" type="text" id="bottle_size" name="bottle_size" value="<?= htmlspecialchars($form['bottle_size'] ?? '') ?>" placeholder="e.g. 60ct bottle" />
          </div>
          <div class="form-group">
            <label for="gtin14">GTIN-14</label>
            <input class="form-input" type="text" id="gtin14" name="gtin14" value="<?= htmlspecialchars($form['gtin14'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="upc">UPC (GTIN-12)</label>
            <input class="form-input" type="text" id="upc" name="upc" value="<?= htmlspecialchars($form['upc'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="sku_case_barcode">SKU case barcode</label>
            <input class="form-input" type="text" id="sku_case_barcode" name="sku_case_barcode" maxlength="100" value="<?= htmlspecialchars($form['sku_case_barcode'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="launch_date">Launch date</label>
            <input class="form-input" type="date" id="launch_date" name="launch_date" value="<?= htmlspecialchars($form['launch_date'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="non_gmo_certified">Non-GMO certified</label>
            <select class="form-input" id="non_gmo_certified" name="non_gmo_certified">
              <option value="0" <?= empty($form['non_gmo_certified']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($form['non_gmo_certified']) ? 'selected' : '' ?>>Yes</option>
            </select>
          </div>
          <div class="form-group">
            <label for="cogs">COGS ($)</label>
            <input class="form-input" type="number" min="0" step="0.01" id="cogs" name="cogs" value="<?= htmlspecialchars($form['cogs'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="wholesale_price">Wholesale price ($)</label>
            <input class="form-input" type="number" min="0" step="0.01" id="wholesale_price" name="wholesale_price" value="<?= htmlspecialchars($form['wholesale_price'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="msrp">MSRP ($)</label>
            <input class="form-input" type="number" min="0" step="0.01" id="msrp" name="msrp" value="<?= htmlspecialchars($form['msrp'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="supplement_facts_panel">Supplement facts panel</label>
            <input class="form-input" type="text" id="supplement_facts_panel" name="supplement_facts_panel" value="<?= htmlspecialchars($form['supplement_facts_panel'] ?? '') ?>" placeholder="Reference code or brief description" />
          </div>
          <div class="form-group form-grid-full">
            <label>Allergen statement</label>
            <div class="module-actions" style="flex-wrap: wrap; gap: 0.75rem 1.25rem;">
              <?php foreach (CATALOG_ALLERGENS as $allergen): ?>
              <label class="perm-check">
                <input
                  type="checkbox"
                  name="allergens[]"
                  value="<?= htmlspecialchars($allergen) ?>"
                  <?= in_array($allergen, $form['allergens'] ?? [], true) ? 'checked' : '' ?>
                />
                <span><?= htmlspecialchars($allergen) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group form-grid-full">
            <label for="claims">Claims</label>
            <textarea class="form-input" id="claims" name="claims" rows="4" placeholder="Label claims and certifications"><?= htmlspecialchars($form['claims'] ?? '') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="directions">Directions</label>
            <textarea class="form-input" id="directions" name="directions" rows="3" placeholder="Dosing and usage directions"><?= htmlspecialchars($form['directions'] ?? '') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="certs_on_label">Certs on label</label>
            <textarea class="form-input" id="certs_on_label" name="certs_on_label" rows="3" placeholder="Certification boilerplate printed on the label"><?= htmlspecialchars($form['certs_on_label'] ?? '') ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="sfp_link">SFP link</label>
            <input class="form-input" type="url" id="sfp_link" name="sfp_link" value="<?= htmlspecialchars($form['sfp_link'] ?? '') ?>" placeholder="https://..." />
          </div>
          <div class="form-group form-grid-full">
            <label for="label_print_ready_link">Label (print-ready) link</label>
            <input class="form-input" type="url" id="label_print_ready_link" name="label_print_ready_link" value="<?= htmlspecialchars($form['label_print_ready_link'] ?? '') ?>" placeholder="https://..." />
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="4"><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create SKU' ?></button>
          <a class="btn-secondary" href="<?= $isEdit ? '/product-catalog/view.php?id=' . (int) ($form['sku_id'] ?? 0) : '/product-catalog/' ?>">Cancel</a>
        </div>
      </form>
