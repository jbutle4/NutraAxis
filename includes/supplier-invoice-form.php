<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var bool $isLocked */
/** @var array $suppliers */
/** @var array $poOptions */
/** @var array $apAccounts */
/** @var array $expenseAccounts */
$isEdit = $isEdit ?? false;
$isLocked = $isLocked ?? false;
$form = $form ?? supplier_invoice_to_form([]);
$lines = $form['lines'] ?? [supplier_invoice_default_line()];
$poOptions = $poOptions ?? [];
$apAccounts = $apAccounts ?? [];
$expenseAccounts = $expenseAccounts ?? [];
$useAccountPicklists = !supplier_invoice_is_qbo_stub_mode() && ($apAccounts !== [] || $expenseAccounts !== []);
$formActions = '';
if (!$isLocked) {
    $formActions = capture_form_actions(function () use ($isEdit) {
        ?>
        <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Invoice' ?></button>
        <a class="btn-secondary" href="<?= htmlspecialchars(accounting_path('/accounting/supplier-invoices/')) ?>">Cancel</a>
        <?php
    });
}
?>
<form class="admin-form supplier-invoice-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
  <?php if ($formActions !== '') {
      render_form_actions($formActions, 'top');
  } ?>
  <h2 class="admin-form-subhead">Invoice header</h2>
  <div class="form-grid form-grid-compact supplier-invoice-header-grid">
    <div class="form-group-inline form-group-inline--wide">
      <label for="supplier_id">Supplier</label>
      <div class="form-group-inline-field">
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
    </div>
    <div class="form-group-inline">
      <label for="doc_number">INV#</label>
      <input class="form-input" type="text" id="doc_number" name="doc_number" maxlength="21" value="<?= htmlspecialchars($form['doc_number'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group-inline">
      <label for="txn_date">INV Date</label>
      <input class="form-input" type="date" id="txn_date" name="txn_date" value="<?= htmlspecialchars($form['txn_date'] ?? date('Y-m-d')) ?>" required <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group-inline">
      <label for="due_date">DUE Date</label>
      <input class="form-input" type="date" id="due_date" name="due_date" value="<?= htmlspecialchars($form['due_date'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group-inline">
      <label for="po_id">PO (optional)</label>
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
    <div class="form-group-inline">
      <label>Sync Status</label>
      <p class="form-static">
        <span class="status-badge <?= supplier_invoice_status_class((string) ($form['sync_status'] ?? 'Draft')) ?>">
          <?= htmlspecialchars($form['sync_status'] ?? 'Draft') ?>
        </span>
      </p>
    </div>
    <div class="form-group-inline">
      <label for="global_tax_calculation">Tax Calc</label>
      <select class="form-input" id="global_tax_calculation" name="global_tax_calculation" <?= $isLocked ? 'disabled' : '' ?>>
        <?php foreach (SUPPLIER_INVOICE_TAX_CALCULATIONS as $value => $label): ?>
        <option value="<?= htmlspecialchars($value) ?>" <?= ($form['global_tax_calculation'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($isLocked): ?>
      <input type="hidden" name="global_tax_calculation" value="<?= htmlspecialchars($form['global_tax_calculation'] ?? '') ?>" />
      <?php endif; ?>
    </div>
    <div class="form-group-inline">
      <label for="currency_ref_value">Curr</label>
      <input class="form-input" type="text" id="currency_ref_value" name="currency_ref_value" maxlength="10" placeholder="USD" value="<?= htmlspecialchars($form['currency_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
    </div>
    <div class="form-group-inline">
      <label for="ap_account_ref_value">AP ACCT ID</label>
      <?php if ($useAccountPicklists && $apAccounts !== []): ?>
      <select class="form-input supplier-invoice-account-select" id="ap_account_ref_value" name="ap_account_ref_value" data-name-target="ap_account_ref_name" <?= $isLocked ? 'disabled' : '' ?>>
        <?= supplier_invoice_account_select_options($apAccounts, (string) ($form['ap_account_ref_value'] ?? ''), '—') ?>
      </select>
      <?php else: ?>
      <input class="form-input" type="text" id="ap_account_ref_value" name="ap_account_ref_value" value="<?= htmlspecialchars($form['ap_account_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
      <?php endif; ?>
    </div>
    <div class="form-group-inline">
      <label for="ap_account_ref_name">AP ACCT Name</label>
      <input class="form-input supplier-invoice-account-name" type="text" id="ap_account_ref_name" name="ap_account_ref_name" value="<?= htmlspecialchars($form['ap_account_ref_name'] ?? '') ?>" <?= ($isLocked || ($useAccountPicklists && $apAccounts !== [])) ? 'readonly' : '' ?> />
    </div>
    <div class="form-group-inline form-group-inline--wide form-grid-full">
      <p class="form-hint">Status changes through payment approval on the invoice view page. QBO Insert is available there only for accounting posting recovery after payment approval.</p>
    </div>
    <div class="form-group-inline form-group-inline--wide form-grid-full">
      <label for="memo">Memo</label>
      <textarea class="form-input" id="memo" name="memo" rows="2" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($form['memo'] ?? '') ?></textarea>
    </div>
    <div class="form-group-inline form-group-inline--wide form-grid-full">
      <label for="private_note">Private note</label>
      <textarea class="form-input" id="private_note" name="private_note" rows="2" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($form['private_note'] ?? '') ?></textarea>
    </div>
  </div>

  <h2 class="admin-form-subhead">Line items</h2>
  <?php if ($useAccountPicklists && $expenseAccounts === []): ?>
  <div class="admin-notice is-error is-detail" role="alert">No QuickBooks expense accounts are available for line items. Sync chart of accounts or connect QuickBooks before saving.</div>
  <?php endif; ?>
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
            <select class="form-input supplier-invoice-detail-type" name="lines[<?= $index ?>][detail_type]" <?= $isLocked ? 'disabled' : '' ?>>
              <?php foreach (SUPPLIER_INVOICE_DETAIL_TYPES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= ($line['detail_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($isLocked): ?>
            <input type="hidden" name="lines[<?= $index ?>][detail_type]" value="<?= htmlspecialchars($line['detail_type'] ?? 'AccountBasedExpenseLineDetail') ?>" />
            <?php endif; ?>
          </td>
          <td>
            <?php if ($useAccountPicklists && $expenseAccounts !== [] && ($line['detail_type'] ?? 'AccountBasedExpenseLineDetail') === 'AccountBasedExpenseLineDetail'): ?>
            <select class="form-input supplier-invoice-line-account-select" name="lines[<?= $index ?>][account_ref_value]" data-name-target="lines[<?= $index ?>][account_ref_name]" <?= $isLocked ? 'disabled' : '' ?> required>
              <?= supplier_invoice_account_select_options($expenseAccounts, (string) ($line['account_ref_value'] ?? '')) ?>
            </select>
            <?php else: ?>
            <input class="form-input supplier-invoice-line-account-input" type="text" name="lines[<?= $index ?>][account_ref_value]" value="<?= htmlspecialchars($line['account_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
            <?php endif; ?>
          </td>
          <td><input class="form-input supplier-invoice-line-account-name" type="text" name="lines[<?= $index ?>][account_ref_name]" value="<?= htmlspecialchars($line['account_ref_name'] ?? '') ?>" <?= ($isLocked || ($useAccountPicklists && $expenseAccounts !== [])) ? 'readonly' : '' ?> /></td>
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
  <?php render_form_actions($formActions, 'bottom'); ?>
  <?php endif; ?>
</form>
<?php if (!$isLocked): ?>
<script>
(function () {
  var table = document.getElementById('supplier-invoice-lines');
  var addBtn = document.getElementById('supplier-invoice-add-line');
  var expenseAccountOptions = <?= json_encode(array_map(static function (array $account): array {
      return [
          'id'    => (string) ($account['Id'] ?? ''),
          'name'  => (string) ($account['Name'] ?? ''),
          'label' => supplier_invoice_account_option_label($account),
      ];
  }, $expenseAccounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var useAccountPicklists = <?= $useAccountPicklists ? 'true' : 'false' ?>;

  function selectedOptionName(select) {
    if (!select || select.selectedIndex < 0) {
      return '';
    }
    var option = select.options[select.selectedIndex];
    return option ? (option.getAttribute('data-name') || '') : '';
  }

  function syncAccountName(select) {
    if (!select) {
      return;
    }
    var targetName = select.getAttribute('data-name-target');
    if (!targetName) {
      return;
    }
    var target = document.getElementById(targetName) || document.querySelector('[name="' + targetName.replace(/"/g, '\\"') + '"]');
    if (!target) {
      return;
    }
    target.value = selectedOptionName(select);
  }

  function bindAccountSelect(select) {
    if (!select) {
      return;
    }
    select.addEventListener('change', function () {
      syncAccountName(select);
    });
    syncAccountName(select);
  }

  document.querySelectorAll('.supplier-invoice-account-select, .supplier-invoice-line-account-select').forEach(bindAccountSelect);

  function accountSelectHtml(index, selectedId) {
  if (!useAccountPicklists || expenseAccountOptions.length === 0) {
      return '<input class="form-input supplier-invoice-line-account-input" type="text" name="lines[' + index + '][account_ref_value]" />';
    }

    var html = '<select class="form-input supplier-invoice-line-account-select" name="lines[' + index + '][account_ref_value]" data-name-target="lines[' + index + '][account_ref_name]" required>';
    html += '<option value="">Select account</option>';
    expenseAccountOptions.forEach(function (account) {
      var selected = selectedId === account.id ? ' selected' : '';
      html += '<option value="' + account.id + '" data-name="' + account.name.replace(/"/g, '&quot;') + '"' + selected + '>' + account.label + '</option>';
    });
    html += '</select>';
    return html;
  }

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

  if (table) {
    table.querySelectorAll('.supplier-invoice-line-row').forEach(bindRemove);
  }

  if (!table || !addBtn) return;

  addBtn.addEventListener('click', function () {
    var index = nextIndex();
    var row = document.createElement('tr');
    row.className = 'supplier-invoice-line-row';
    row.innerHTML = `
      <td><input class="form-input" type="text" name="lines[${index}][description]" /></td>
      <td><input class="form-input" type="number" min="0" step="0.01" name="lines[${index}][amount]" required /></td>
      <td>
        <select class="form-input supplier-invoice-detail-type" name="lines[${index}][detail_type]">
          <option value="AccountBasedExpenseLineDetail">Expense account</option>
          <option value="ItemBasedExpenseLineDetail">Inventory item</option>
        </select>
      </td>
      <td>${accountSelectHtml(index, '')}</td>
      <td><input class="form-input supplier-invoice-line-account-name" type="text" name="lines[${index}][account_ref_name]" ${useAccountPicklists ? 'readonly' : ''} /></td>
      <td><input class="form-input" type="text" name="lines[${index}][item_ref_value]" /></td>
      <td><input class="form-input" type="text" name="lines[${index}][item_ref_name]" /></td>
      <td><input class="form-input" type="number" min="0" step="0.0001" name="lines[${index}][qty]" /></td>
      <td><input class="form-input" type="number" min="0" step="0.0001" name="lines[${index}][unit_price]" /></td>
      <td><button type="button" class="btn-text supplier-invoice-remove-line">Remove</button></td>
    `;
    table.querySelector('tbody').appendChild(row);
    bindRemove(row);
    row.querySelectorAll('.supplier-invoice-line-account-select').forEach(bindAccountSelect);
  });
})();
</script>
<?php endif; ?>
