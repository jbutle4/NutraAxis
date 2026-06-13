<?php
/** @var array $form */
/** @var string $importSupplierName */
?>
      <div class="admin-notice is-error is-detail" role="alert">
        Supplier <strong><?= htmlspecialchars($importSupplierName) ?></strong> was not found. Create a new supplier to continue this import.
      </div>

      <form class="admin-form" method="post" action="/po-management/import.php?step=supplier">
        <input type="hidden" name="import_action" value="create_supplier" />
        <div class="form-grid">
          <div class="form-group">
            <label for="supplier_code">Supplier code</label>
            <input
              class="form-input"
              type="text"
              id="supplier_code"
              name="supplier_code"
              value="<?= htmlspecialchars($form['supplier_code'] ?? '') ?>"
              placeholder="Auto-generated if blank"
            />
          </div>
          <div class="form-group form-grid-full">
            <label for="supplier_name">Supplier name</label>
            <input class="form-input" type="text" id="supplier_name" name="supplier_name" value="<?= htmlspecialchars($form['supplier_name'] ?? '') ?>" required />
          </div>
          <div class="form-group form-grid-full">
            <label for="address">Address</label>
            <input class="form-input" type="text" id="address" name="address" value="<?= htmlspecialchars($form['address'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_name">Contact name</label>
            <input class="form-input" type="text" id="contact_name" name="contact_name" value="<?= htmlspecialchars($form['contact_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="contact_phone">Contact phone</label>
            <input class="form-input" type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($form['contact_phone'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="contact_email">Contact email</label>
            <input class="form-input" type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($form['contact_email'] ?? '') ?>" />
          </div>
        </div>
        <div class="module-actions">
          <button type="submit" class="btn-primary">Create Supplier and Import PO</button>
          <a class="btn-secondary" href="/po-management/import.php?cancel=1">Cancel Import</a>
        </div>
      </form>
