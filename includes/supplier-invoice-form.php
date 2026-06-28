<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var bool $isLocked */
/** @var array $suppliers */
/** @var array $poOptions */
$isEdit = $isEdit ?? false;
$isLocked = $isLocked ?? false;
$form = $form ?? supplier_invoice_to_form([]);
$lines = $form['lines'] ?? [supplier_invoice_default_line()];
$poOptions = $poOptions ?? [];
?>
<form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
  <h2 class="admin-form-subhead">Invoice header</h2>
  <div class="form-grid">
    <div class="form-group">
      <label for="supplier_id">Supplier</label>
      <select class="form-input" id="supplier_id" name="supplier_id" required <?= $isLocked ? 'disabled' : '' ?>>
        <option value="">Select supplier</option>
        <?php foreach ($suppliers as $supplier): ?>
        <option value="<?= (int) $supplier['SupplierID'] ?>" <?= (int) ($form['supplier_id'] ?? 0) === (int) $supplier['SupplierID'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($supplier['SupplierName']) ?><?= !empty($supplier['SupplierCode']) ? ' (' . htmlspecialchars($supplier['SupplierCode']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($isLocked): ?>
      <input type="hidden" name="supplier_id" value="<?= (int) ($form['supplier_id'] ?? 0) ?>" />
      <?php endif; ?>
      <p class="form-hint"><?php if (supplier_invoice_is_qbo_stub_mode()): ?>QBO insert test mode: QuickBooks vendor ID is optional.<?php else: ?>Supplier must have a QuickBooks vendor ID.<?php endif; ?></p>
    </div>
    <div class="form-group">
      <label for="doc_number">Invoice number</label>
      <input class="form-input" type="text" id="doc_number" name="doc_number" maxlength="21" value="<?= htmlspecialchars($form['doc_number'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group">
      <label for="txn_date">Invoice date</label>
      <input class="form-input" type="date" id="txn_date" name="txn_date" value="<?= htmlspecialchars($form['txn_date'] ?? date('Y-m-d')) ?>" required <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group">
      <label for="due_date">Due date</label>
      <input class="form-input" type="date" id="due_date" name="due_date" value="<?= htmlspecialchars($form['due_date'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group">
      <label for="po_id">Linked PO (optional)</label>
      <select class="form-input" id="po_id" name="po_id" <?= $isLocked ? 'disabled' : '' ?>>
        <option value="">No purchase order</option>
        <?php foreach ($poOptions as $option): ?>
        <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['po_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($option['label']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($isLocked): ?>
      <input type="hidden" name="po_id" value="<?= htmlspecialchars((string) ($form['po_id'] ?? '')) ?>" />
      <?php endif; ?>
    </div>
    <div class="form-group">
      <label>Sync status</label>
      <p class="form-static">
        <span class="status-badge <?= supplier_invoice_status_class((string) ($form['sync_status'] ?? 'Draft')) ?>">
          <?= htmlspecialchars($form['sync_status'] ?? 'Draft') ?>
        </span>
      </p>
      <p class="form-hint"><?php if (!empty($isStandaloneInvoice)): ?>Status changes through payment approval on the invoice view page.<?php else: ?>Status changes through the QBO insert approval workflow on the invoice view page.<?php endif; ?></p>
    </div>
    <div class="form-group">
      <label for="global_tax_calculation">Tax calculation</label>
      <select class="form-input" id="global_tax_calculation" name="global_tax_calculation" <?= $isLocked ? 'disabled' : '' ?>>
        <?php foreach (SUPPLIER_INVOICE_TAX_CALCULATIONS as $value => $label): ?>
        <option value="<?= htmlspecialchars($value) ?>" <?= ($form['global_tax_calculation'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($isLocked): ?>
      <input type="hidden" name="global_tax_calculation" value="<?= htmlspecialchars($form['global_tax_calculation'] ?? '') ?>" />
      <?php endif; ?>
    </div>
    <div class="form-group">
      <label for="currency_ref_value">Currency</label>
      <input class="form-input" type="text" id="currency_ref_value" name="currency_ref_value" maxlength="10" placeholder="USD" value="<?= htmlspecialchars($form['currency_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group">
      <label for="ap_account_ref_value">AP account ID</label>
      <input class="form-input" type="text" id="ap_account_ref_value" name="ap_account_ref_value" value="<?= htmlspecialchars($form['ap_account_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group">
      <label for="ap_account_ref_name">AP account name</label>
      <input class="form-input" type="text" id="ap_account_ref_name" name="ap_account_ref_name" value="<?= htmlspecialchars($form['ap_account_ref_name'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group form-grid-full">
      <label for="memo">Memo</label>
      <textarea class="form-input" id="memo" name="memo" rows="2" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($form['memo'] ?? '') ?></textarea>
    </div>
    <div class="form-group form-grid-full">
      <label for="private_note">Private note</label>
      <textarea class="form-input" id="private_note" name="private_note" rows="2" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($form['private_note'] ?? '') ?></textarea>
    </div>
  </div>

  <h2 class="admin-form-subhead">Line items</h2>
  <div class="admin-table-wrap">
    <table class="admin-table" id="supplier-invoice-lines">
      <thead>
        <tr>
          <th>Description</th>
          <th>Amount</th>
          <th>Detail type</th>
          <th>Account ID</th>
          <th>Account name</th>
          <th>Item ID</th>
          <th>Item name</th>
          <th>Qty</th>
          <th>Unit price</th>
          <?php if (!$isLocked): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $index => $line): ?>
        <tr class="supplier-invoice-line-row">
          <td><input class="form-input" type="text" name="lines[<?= $index ?>][description]" value="<?= htmlspecialchars($line['description'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td><input class="form-input" type="number" min="0" step="0.01" name="lines[<?= $index ?>][amount]" value="<?= htmlspecialchars($line['amount'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> required /></td>
          <td>
            <select class="form-input" name="lines[<?= $index ?>][detail_type]" <?= $isLocked ? 'disabled' : '' ?>>
              <?php foreach (SUPPLIER_INVOICE_DETAIL_TYPES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= ($line['detail_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($isLocked): ?>
            <input type="hidden" name="lines[<?= $index ?>][detail_type]" value="<?= htmlspecialchars($line['detail_type'] ?? 'AccountBasedExpenseLineDetail') ?>" />
            <?php endif; ?>
          </td>
          <td><input class="form-input" type="text" name="lines[<?= $index ?>][account_ref_value]" value="<?= htmlspecialchars($line['account_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td><input class="form-input" type="text" name="lines[<?= $index ?>][account_ref_name]" value="<?= htmlspecialchars($line['account_ref_name'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td><input class="form-input" type="text" name="lines[<?= $index ?>][item_ref_value]" value="<?= htmlspecialchars($line['item_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td><input class="form-input" type="text" name="lines[<?= $index ?>][item_ref_name]" value="<?= htmlspecialchars($line['item_ref_name'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td><input class="form-input" type="number" min="0" step="0.0001" name="lines[<?= $index ?>][qty]" value="<?= htmlspecialchars($line['qty'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td><input class="form-input" type="number" min="0" step="0.0001" name="lines[<?= $index ?>][unit_price]" value="<?= htmlspecialchars($line['unit_price'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <?php if (!$isLocked): ?>
          <td><button type="button" class="btn-text supplier-invoice-remove-line">Remove</button></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$isLocked): ?>
  <button type="button" class="btn-secondary btn-small" id="supplier-invoice-add-line" style="margin-top: 12px;">Add line</button>
  <?php endif; ?>

  <?php if (!$isLocked): ?>
  <div class="module-actions">
    <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Invoice' ?></button>
    <a class="btn-secondary" href="/accounting/supplier-invoices/">Cancel</a>
  </div>
  <?php endif; ?>
</form>
<?php if (!$isLocked): ?>
<script>
(function () {
  var table = document.getElementById('supplier-invoice-lines');
  var addBtn = document.getElementById('supplier-invoice-add-line');
  if (!table || !addBtn) return;

  function nextIndex() {
    return table.querySelectorAll('.supplier-invoice-line-row').length;
  }

  function bindRemove(row) {
    var btn = row.querySelector('.supplier-invoice-remove-line');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var rows = table.querySelectorAll('.supplier-invoice-line-row');
      if (rows.length <= 1) return;
      row.remove();
    });
  }

  table.querySelectorAll('.supplier-invoice-line-row').forEach(bindRemove);

  addBtn.addEventListener('click', function () {
    var index = nextIndex();
    var row = document.createElement('tr');
    row.className = 'supplier-invoice-line-row';
    row.innerHTML = `
      <td><input class="form-input" type="text" name="lines[${index}][description]" /></td>
      <td><input class="form-input" type="number" min="0" step="0.01" name="lines[${index}][amount]" required /></td>
      <td>
        <select class="form-input" name="lines[${index}][detail_type]">
          <option value="AccountBasedExpenseLineDetail">Expense account</option>
          <option value="ItemBasedExpenseLineDetail">Inventory item</option>
        </select>
      </td>
      <td><input class="form-input" type="text" name="lines[${index}][account_ref_value]" /></td>
      <td><input class="form-input" type="text" name="lines[${index}][account_ref_name]" /></td>
      <td><input class="form-input" type="text" name="lines[${index}][item_ref_value]" /></td>
      <td><input class="form-input" type="text" name="lines[${index}][item_ref_name]" /></td>
      <td><input class="form-input" type="number" min="0" step="0.0001" name="lines[${index}][qty]" /></td>
      <td><input class="form-input" type="number" min="0" step="0.0001" name="lines[${index}][unit_price]" /></td>
      <td><button type="button" class="btn-text supplier-invoice-remove-line">Remove</button></td>
    `;
    table.querySelector('tbody').appendChild(row);
    bindRemove(row);
  });
})();
</script>
<?php endif; ?>
