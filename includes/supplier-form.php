<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
$isEdit = $isEdit ?? false;
$qboPaymentTerms = supplier_form_payment_terms();
$selectedTermValue = (string) ($form['term_ref_value'] ?? '');
$selectedTermName = (string) ($form['term_ref_name'] ?? '');
$formActions = capture_form_actions(function () use ($isEdit, $form) {
    ?>
    <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Supplier' ?></button>
    <a class="btn-secondary" href="<?= $isEdit ? '/supplier-management/view.php?id=' . (int) ($form['supplier_id'] ?? 0) : '/supplier-management/' ?>">Cancel</a>
    <?php
});
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <?php render_form_actions($formActions, 'top'); ?>
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
          <div class="form-group">
            <label for="vendor_1099">1099 vendor</label>
            <select class="form-input" id="vendor_1099" name="vendor_1099">
              <option value="1" <?= !empty($form['vendor_1099']) ? 'selected' : '' ?>>Yes</option>
              <option value="0" <?= empty($form['vendor_1099']) ? 'selected' : '' ?>>No</option>
            </select>
            <p class="form-hint">Required for contractors paid by check. Auto-suggested for contractor types.</p>
          </div>
        </div>

        <h2 class="form-section-title">QuickBooks vendor identity</h2>
        <div class="form-grid">
          <div class="form-group">
            <label for="qbo_display_name">QBO display name</label>
            <input class="form-input" type="text" id="qbo_display_name" name="qbo_display_name" value="<?= htmlspecialchars($form['qbo_display_name'] ?? '') ?>" placeholder="Defaults to supplier name" />
          </div>
          <div class="form-group">
            <label for="company_name">Company name</label>
            <input class="form-input" type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($form['company_name'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="print_on_check_name">Print on check name</label>
            <input class="form-input" type="text" id="print_on_check_name" name="print_on_check_name" value="<?= htmlspecialchars($form['print_on_check_name'] ?? '') ?>" placeholder="Defaults to company or supplier name" />
          </div>
          <div class="form-group">
            <label for="acct_num">QBO vendor account #</label>
            <input class="form-input" type="text" id="acct_num" name="acct_num" value="<?= htmlspecialchars($form['acct_num'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="tax_identifier">Tax ID (EIN/SSN)</label>
            <input class="form-input" type="text" id="tax_identifier" name="tax_identifier" value="<?= htmlspecialchars($form['tax_identifier'] ?? '') ?>" inputmode="numeric" maxlength="11" placeholder="9 digits" />
          </div>
          <div class="form-group">
            <label for="term_ref_value">Payment terms</label>
            <?php if ($qboPaymentTerms !== []): ?>
            <select class="form-input" id="term_ref_value" name="term_ref_value">
              <option value="">Not set</option>
              <?php if ($selectedTermValue !== '' && !in_array($selectedTermValue, array_column($qboPaymentTerms, 'id'), true)): ?>
              <option value="<?= htmlspecialchars($selectedTermValue) ?>" selected><?= htmlspecialchars($selectedTermName !== '' ? $selectedTermName : 'Current QBO term') ?></option>
              <?php endif; ?>
              <?php foreach ($qboPaymentTerms as $term): ?>
              <option value="<?= htmlspecialchars((string) $term['id']) ?>" <?= $selectedTermValue === (string) $term['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $term['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" id="term_ref_name" name="term_ref_name" value="<?= htmlspecialchars($selectedTermName) ?>" />
            <p class="form-hint">Terms are loaded from QuickBooks. The QBO ID is stored automatically when you save.</p>
            <?php else: ?>
            <input class="form-input" type="text" id="term_ref_name" name="term_ref_name" value="<?= htmlspecialchars($selectedTermName) ?>" placeholder="e.g. Net 30" />
            <input type="hidden" name="term_ref_value" value="<?= htmlspecialchars($selectedTermValue) ?>" />
            <p class="form-hint">Connect QuickBooks to pick payment terms from your QBO company.</p>
            <?php endif; ?>
          </div>
        </div>

        <h2 class="form-section-title">Contact person</h2>
        <div class="form-grid">
          <div class="form-group">
            <label for="contact_name">Contact name</label>
            <input class="form-input" type="text" id="contact_name" name="contact_name" value="<?= htmlspecialchars($form['contact_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="title">Title</label>
            <input class="form-input" type="text" id="title" name="title" value="<?= htmlspecialchars($form['title'] ?? '') ?>" maxlength="16" />
          </div>
          <div class="form-group">
            <label for="given_name">Given name</label>
            <input class="form-input" type="text" id="given_name" name="given_name" value="<?= htmlspecialchars($form['given_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="middle_name">Middle name</label>
            <input class="form-input" type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($form['middle_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="family_name">Family name</label>
            <input class="form-input" type="text" id="family_name" name="family_name" value="<?= htmlspecialchars($form['family_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="suffix">Suffix</label>
            <input class="form-input" type="text" id="suffix" name="suffix" value="<?= htmlspecialchars($form['suffix'] ?? '') ?>" maxlength="16" />
          </div>
          <div class="form-group">
            <label for="contact_email">Email</label>
            <input class="form-input" type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($form['contact_email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_phone">Primary phone</label>
            <input class="form-input" type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($form['contact_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="mobile_phone">Mobile phone</label>
            <input class="form-input" type="text" id="mobile_phone" name="mobile_phone" value="<?= htmlspecialchars($form['mobile_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="alternate_phone">Alternate phone</label>
            <input class="form-input" type="text" id="alternate_phone" name="alternate_phone" value="<?= htmlspecialchars($form['alternate_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="fax_phone">Fax</label>
            <input class="form-input" type="text" id="fax_phone" name="fax_phone" value="<?= htmlspecialchars($form['fax_phone'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="web_addr">Website</label>
            <input class="form-input" type="url" id="web_addr" name="web_addr" value="<?= htmlspecialchars($form['web_addr'] ?? '') ?>" placeholder="https://..." />
          </div>
        </div>

        <h2 class="form-section-title">Billing address (checks &amp; 1099)</h2>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="bill_addr_line1">Address line 1</label>
            <input class="form-input" type="text" id="bill_addr_line1" name="bill_addr_line1" value="<?= htmlspecialchars($form['bill_addr_line1'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="bill_addr_line2">Address line 2</label>
            <input class="form-input" type="text" id="bill_addr_line2" name="bill_addr_line2" value="<?= htmlspecialchars($form['bill_addr_line2'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="bill_addr_city">City</label>
            <input class="form-input" type="text" id="bill_addr_city" name="bill_addr_city" value="<?= htmlspecialchars($form['bill_addr_city'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="bill_addr_state">State</label>
            <input class="form-input" type="text" id="bill_addr_state" name="bill_addr_state" value="<?= htmlspecialchars($form['bill_addr_state'] ?? '') ?>" maxlength="50" placeholder="FL" />
          </div>
          <div class="form-group">
            <label for="bill_addr_postal_code">Postal code</label>
            <input class="form-input" type="text" id="bill_addr_postal_code" name="bill_addr_postal_code" value="<?= htmlspecialchars($form['bill_addr_postal_code'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="bill_addr_country">Country</label>
            <input class="form-input" type="text" id="bill_addr_country" name="bill_addr_country" value="<?= htmlspecialchars($form['bill_addr_country'] ?? 'USA') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="address">Legacy address (optional)</label>
            <input class="form-input" type="text" id="address" name="address" value="<?= htmlspecialchars($form['address'] ?? '') ?>" />
            <p class="form-hint">Copied to billing line 1 on save when line 1 is blank.</p>
          </div>
        </div>

        <h2 class="form-section-title">Shipping address (optional)</h2>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="ship_addr_line1">Address line 1</label>
            <input class="form-input" type="text" id="ship_addr_line1" name="ship_addr_line1" value="<?= htmlspecialchars($form['ship_addr_line1'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="ship_addr_line2">Address line 2</label>
            <input class="form-input" type="text" id="ship_addr_line2" name="ship_addr_line2" value="<?= htmlspecialchars($form['ship_addr_line2'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="ship_addr_city">City</label>
            <input class="form-input" type="text" id="ship_addr_city" name="ship_addr_city" value="<?= htmlspecialchars($form['ship_addr_city'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="ship_addr_state">State</label>
            <input class="form-input" type="text" id="ship_addr_state" name="ship_addr_state" value="<?= htmlspecialchars($form['ship_addr_state'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="ship_addr_postal_code">Postal code</label>
            <input class="form-input" type="text" id="ship_addr_postal_code" name="ship_addr_postal_code" value="<?= htmlspecialchars($form['ship_addr_postal_code'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="ship_addr_country">Country</label>
            <input class="form-input" type="text" id="ship_addr_country" name="ship_addr_country" value="<?= htmlspecialchars($form['ship_addr_country'] ?? '') ?>" />
          </div>
        </div>

        <h2 class="form-section-title">Multicurrency (optional)</h2>
        <div class="form-grid">
          <div class="form-group">
            <label for="currency_ref_name">Currency</label>
            <input class="form-input" type="text" id="currency_ref_name" name="currency_ref_name" value="<?= htmlspecialchars($form['currency_ref_name'] ?? '') ?>" placeholder="USD" />
          </div>
          <div class="form-group">
            <label for="currency_ref_value">Currency QBO ID</label>
            <input class="form-input" type="text" id="currency_ref_value" name="currency_ref_value" value="<?= htmlspecialchars($form['currency_ref_value'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Internal notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="4"><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
          </div>
        </div>

        <?php render_form_actions($formActions, 'bottom'); ?>
        <?php if (!empty($qboPaymentTerms)): ?>
        <script>
          (function () {
            var select = document.getElementById('term_ref_value');
            var nameInput = document.getElementById('term_ref_name');
            if (!select || !nameInput) return;
            function syncTermName() {
              var option = select.options[select.selectedIndex];
              nameInput.value = option && option.value ? option.text : '';
            }
            select.addEventListener('change', syncTermName);
            syncTermName();
          })();
        </script>
        <?php endif; ?>
      </form>
