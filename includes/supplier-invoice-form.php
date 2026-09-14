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
$qboItems = $qboItems ?? supplier_invoice_item_picklist();
$useAccountPicklists = !supplier_invoice_is_qbo_stub_mode() && ($apAccounts !== [] || $expenseAccounts !== []);
$useItemPicklists = $qboItems !== [];
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
      <p class="form-hint">When the invoice is ready, submit it for QBO Insert from the invoice view page. Accounting approves and creates the QuickBooks bill.</p>
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
  <?php if ($qboItems === []): ?>
  <div class="admin-notice is-error is-detail" role="alert">No Product Master SKUs are linked to QuickBooks items in this company. Inventory lines need a mapped QBO SKU before the bill can post.</div>
  <?php endif; ?>
  <div class="admin-table-wrap">
    <table class="admin-table" id="supplier-invoice-lines">
      <thead>
        <tr>
          <th>Description</th>
          <th>Amount</th>
          <th>Detail type</th>
          <th>QBO SKU</th>
          <th>Item name</th>
          <th>Account</th>
          <th>Account name</th>
          <th>Qty</th>
          <th>Unit price</th>
          <?php if (!$isLocked): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $index => $line): ?>
        <?php $isItemLine = ($line['detail_type'] ?? '') === 'ItemBasedExpenseLineDetail'; ?>
        <tr class="supplier-invoice-line-row">
          <td class="supplier-invoice-line-description"><input class="form-input" type="text" name="lines[<?= $index ?>][description]" value="<?= htmlspecialchars($line['description'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td class="supplier-invoice-line-amount"><input class="form-input" type="number" min="0" step="0.01" name="lines[<?= $index ?>][amount]" value="<?= htmlspecialchars($line['amount'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> required /></td>
          <td class="supplier-invoice-line-detail">
            <select class="form-input supplier-invoice-detail-type" name="lines[<?= $index ?>][detail_type]" <?= $isLocked ? 'disabled' : '' ?>>
              <?php foreach (SUPPLIER_INVOICE_DETAIL_TYPES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= ($line['detail_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($isLocked): ?>
            <input type="hidden" name="lines[<?= $index ?>][detail_type]" value="<?= htmlspecialchars($line['detail_type'] ?? 'AccountBasedExpenseLineDetail') ?>" />
            <?php endif; ?>
          </td>
          <td class="supplier-invoice-line-item-id">
            <?php if ($useItemPicklists): ?>
            <select class="form-input supplier-invoice-line-item-select" name="lines[<?= $index ?>][item_ref_value]" data-name-target="lines[<?= $index ?>][item_ref_name]" <?= $isLocked ? 'disabled' : '' ?> <?= $isItemLine ? 'required' : '' ?>>
              <?= supplier_invoice_item_select_options($qboItems, (string) ($line['item_ref_value'] ?? '')) ?>
            </select>
            <?php else: ?>
            <input class="form-input" type="text" name="lines[<?= $index ?>][item_ref_value]" value="<?= htmlspecialchars($line['item_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
            <?php endif; ?>
          </td>
          <td class="supplier-invoice-line-item-name-cell"><input class="form-input supplier-invoice-line-item-name" type="text" name="lines[<?= $index ?>][item_ref_name]" value="<?= htmlspecialchars($line['item_ref_name'] ?? '') ?>" <?= ($isLocked || $useItemPicklists) ? 'readonly' : '' ?> /></td>
          <td class="supplier-invoice-line-account-id">
            <?php if ($useAccountPicklists && $expenseAccounts !== []): ?>
            <select class="form-input supplier-invoice-line-account-select" name="lines[<?= $index ?>][account_ref_value]" data-name-target="lines[<?= $index ?>][account_ref_name]" <?= $isLocked ? 'disabled' : '' ?> <?= $isItemLine ? '' : 'required' ?>>
              <?= supplier_invoice_account_select_options($expenseAccounts, (string) ($line['account_ref_value'] ?? '')) ?>
            </select>
            <?php else: ?>
            <input class="form-input supplier-invoice-line-account-input" type="text" name="lines[<?= $index ?>][account_ref_value]" value="<?= htmlspecialchars($line['account_ref_value'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
            <?php endif; ?>
          </td>
          <td class="supplier-invoice-line-account-name-cell"><input class="form-input supplier-invoice-line-account-name" type="text" name="lines[<?= $index ?>][account_ref_name]" value="<?= htmlspecialchars($line['account_ref_name'] ?? '') ?>" <?= ($isLocked || ($useAccountPicklists && $expenseAccounts !== [])) ? 'readonly' : '' ?> /></td>
          <td class="supplier-invoice-line-qty"><input class="form-input" type="number" min="0" step="0.0001" name="lines[<?= $index ?>][qty]" value="<?= htmlspecialchars($line['qty'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
          <td class="supplier-invoice-line-unit-price"><input class="form-input" type="number" min="0" step="0.0001" name="lines[<?= $index ?>][unit_price]" value="<?= htmlspecialchars($line['unit_price'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> /></td>
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
          'id'      => (string) ($account['Id'] ?? ''),
          'name'    => (string) ($account['Name'] ?? ''),
          'acctNum' => (string) ($account['AcctNum'] ?? ''),
          'label'   => supplier_invoice_account_option_label($account),
      ];
  }, $expenseAccounts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var itemOptions = <?= json_encode(array_map(static function (array $item): array {
      return [
          'id'          => (string) ($item['QBO_ItemID'] ?? ''),
          'name'        => (string) ($item['ProductName'] ?? ''),
          'sku'         => (string) ($item['SKUCode'] ?? ''),
          'label'       => supplier_invoice_item_option_label($item),
          'accountId'   => (string) ($item['QBO_ExpenseAccountRefValue'] ?? ''),
          'accountName' => (string) ($item['QBO_ExpenseAccountRefName'] ?? ''),
      ];
  }, $qboItems), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var useAccountPicklists = <?= $useAccountPicklists ? 'true' : 'false' ?>;
  var useItemPicklists = <?= $useItemPicklists ? 'true' : 'false' ?>;

  function escapeAttr(value) {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function selectedOptionName(select) {
    if (!select || select.selectedIndex < 0) {
      return '';
    }
    var option = select.options[select.selectedIndex];
    return option ? (option.getAttribute('data-name') || '') : '';
  }

  function syncNamedSelect(select) {
    if (!select) {
      return;
    }
    var targetName = select.getAttribute('data-name-target');
    if (!targetName) {
      return;
    }
    var target = document.querySelector('[name="' + targetName.replace(/"/g, '\\"') + '"]');
    if (!target) {
      return;
    }
    target.value = selectedOptionName(select);
  }

  function applyItemAccount(row, select) {
    if (!select || !select.classList.contains('supplier-invoice-line-item-select')) {
      return;
    }
    var option = select.options[select.selectedIndex];
    var accountId = option ? (option.getAttribute('data-account-id') || '') : '';
    if (!accountId) {
      return;
    }
    var accountSelect = row.querySelector('.supplier-invoice-line-account-select');
    if (!accountSelect) {
      return;
    }
    var matched = false;
    Array.prototype.forEach.call(accountSelect.options, function (accountOption) {
      if (accountOption.value === accountId || accountOption.getAttribute('data-acct-num') === accountId) {
        accountSelect.value = accountOption.value;
        matched = true;
      }
    });
    if (!matched) {
      var accountName = option.getAttribute('data-account-name') || '';
      var extra = document.createElement('option');
      extra.value = accountId;
      extra.setAttribute('data-name', accountName);
      extra.textContent = accountName ? accountId + ' — ' + accountName : accountId;
      extra.selected = true;
      accountSelect.appendChild(extra);
    }
    syncNamedSelect(accountSelect);
  }

  function bindNamedSelect(select) {
    if (!select || select.dataset.boundNamedSelect === '1') {
      return;
    }
    select.dataset.boundNamedSelect = '1';
    select.addEventListener('change', function () {
      syncNamedSelect(select);
      var row = select.closest('.supplier-invoice-line-row');
      if (row) {
        applyItemAccount(row, select);
      }
    });
    syncNamedSelect(select);
  }

  function accountSelectHtml(index, selectedId, required) {
    if (!useAccountPicklists || expenseAccountOptions.length === 0) {
      return '<input class="form-input supplier-invoice-line-account-input" type="text" name="lines[' + index + '][account_ref_value]" />';
    }

    var html = '<select class="form-input supplier-invoice-line-account-select" name="lines[' + index + '][account_ref_value]" data-name-target="lines[' + index + '][account_ref_name]"' + (required ? ' required' : '') + '>';
    html += '<option value="">Select account</option>';
    var matched = false;
    expenseAccountOptions.forEach(function (account) {
      var selected = selectedId === account.id || selectedId === account.acctNum;
      if (selected) {
        matched = true;
      }
      html += '<option value="' + escapeAttr(account.id) + '" data-name="' + escapeAttr(account.name) + '" data-acct-num="' + escapeAttr(account.acctNum) + '"' + (selected ? ' selected' : '') + '>' + escapeAttr(account.label) + '</option>';
    });
    if (selectedId && !matched) {
      html += '<option value="' + escapeAttr(selectedId) + '" selected>' + escapeAttr(selectedId) + ' (not in chart of accounts)</option>';
    }
    html += '</select>';
    return html;
  }

  function itemSelectHtml(index, selectedId, required) {
    if (!useItemPicklists || itemOptions.length === 0) {
      return '<input class="form-input" type="text" name="lines[' + index + '][item_ref_value]" value="' + escapeAttr(selectedId || '') + '"' + (required ? ' required' : '') + ' />';
    }

    var html = '<select class="form-input supplier-invoice-line-item-select" name="lines[' + index + '][item_ref_value]" data-name-target="lines[' + index + '][item_ref_name]"' + (required ? ' required' : '') + '>';
    html += '<option value="">Select SKU</option>';
    var matched = false;
    itemOptions.forEach(function (item) {
      var selected = selectedId === item.id || selectedId === item.sku || selectedId === item.name;
      if (selected) {
        matched = true;
      }
      html += '<option value="' + escapeAttr(item.id) + '" data-name="' + escapeAttr(item.name) + '" data-sku="' + escapeAttr(item.sku) + '" data-account-id="' + escapeAttr(item.accountId) + '" data-account-name="' + escapeAttr(item.accountName) + '"' + (selected ? ' selected' : '') + '>' + escapeAttr(item.label) + '</option>';
    });
    if (selectedId && !matched) {
      html += '<option value="' + escapeAttr(selectedId) + '" selected>' + escapeAttr(selectedId) + ' (not in Product Master)</option>';
    }
    html += '</select>';
    return html;
  }

  function rowIndex(row) {
    var named = row.querySelector('[name^="lines["]');
    var match = named && named.name.match(/^lines\[(\d+)\]/);
    return match ? match[1] : '0';
  }

  function currentValue(row, field) {
    var el = row.querySelector('[name="lines[' + rowIndex(row) + '][' + field + ']"]');
    return el ? el.value : '';
  }

  function refreshLineRow(row) {
    var typeSelect = row.querySelector('.supplier-invoice-detail-type');
    var itemMode = typeSelect && typeSelect.value === 'ItemBasedExpenseLineDetail';
    var index = rowIndex(row);
    var accountCell = row.querySelector('.supplier-invoice-line-account-id');
    var itemCell = row.querySelector('.supplier-invoice-line-item-id');
    var accountName = row.querySelector('.supplier-invoice-line-account-name');
    var itemName = row.querySelector('.supplier-invoice-line-item-name');
    var accountValue = currentValue(row, 'account_ref_value');
    var itemValue = currentValue(row, 'item_ref_value');

    if (accountCell) {
      accountCell.innerHTML = accountSelectHtml(index, accountValue, !itemMode);
    }
    if (itemCell) {
      itemCell.innerHTML = itemSelectHtml(index, itemMode ? itemValue : '', !!itemMode);
    }
    if (accountName && useAccountPicklists) {
      accountName.readOnly = true;
    }
    if (itemName && useItemPicklists) {
      itemName.readOnly = true;
      if (!itemMode) {
        itemName.value = '';
      }
    }

    row.querySelectorAll('.supplier-invoice-line-account-select, .supplier-invoice-line-item-select').forEach(bindNamedSelect);
    var accountSelect = row.querySelector('.supplier-invoice-line-account-select');
    if (accountSelect) {
      syncNamedSelect(accountSelect);
    }
  }

  function bindRow(row) {
    bindRemove(row);
    var typeSelect = row.querySelector('.supplier-invoice-detail-type');
    if (typeSelect && !typeSelect.dataset.boundDetailType) {
      typeSelect.dataset.boundDetailType = '1';
      typeSelect.addEventListener('change', function () {
        refreshLineRow(row);
      });
    }
    row.querySelectorAll('.supplier-invoice-line-account-select, .supplier-invoice-line-item-select').forEach(bindNamedSelect);
  }

  function nextIndex() {
    var max = -1;
    table.querySelectorAll('.supplier-invoice-line-row [name^="lines["]').forEach(function (input) {
      var match = input.name.match(/^lines\[(\d+)\]/);
      if (match) {
        max = Math.max(max, parseInt(match[1], 10));
      }
    });
    return max + 1;
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

  document.querySelectorAll('.supplier-invoice-account-select').forEach(bindNamedSelect);

  if (table) {
    table.querySelectorAll('.supplier-invoice-line-row').forEach(bindRow);
  }

  if (!table || !addBtn) return;

  addBtn.addEventListener('click', function () {
    var index = nextIndex();
    var row = document.createElement('tr');
    row.className = 'supplier-invoice-line-row';
    row.innerHTML =
      '<td class="supplier-invoice-line-description"><input class="form-input" type="text" name="lines[' + index + '][description]" /></td>' +
      '<td class="supplier-invoice-line-amount"><input class="form-input" type="number" min="0" step="0.01" name="lines[' + index + '][amount]" required /></td>' +
      '<td class="supplier-invoice-line-detail"><select class="form-input supplier-invoice-detail-type" name="lines[' + index + '][detail_type]">' +
      '<option value="AccountBasedExpenseLineDetail">Expense account</option>' +
      '<option value="ItemBasedExpenseLineDetail">Inventory item</option>' +
      '</select></td>' +
      '<td class="supplier-invoice-line-item-id">' + itemSelectHtml(index, '', false) + '</td>' +
      '<td class="supplier-invoice-line-item-name-cell"><input class="form-input supplier-invoice-line-item-name" type="text" name="lines[' + index + '][item_ref_name]"' + (useItemPicklists ? ' readonly' : '') + ' /></td>' +
      '<td class="supplier-invoice-line-account-id">' + accountSelectHtml(index, '', true) + '</td>' +
      '<td class="supplier-invoice-line-account-name-cell"><input class="form-input supplier-invoice-line-account-name" type="text" name="lines[' + index + '][account_ref_name]"' + (useAccountPicklists ? ' readonly' : '') + ' /></td>' +
      '<td class="supplier-invoice-line-qty"><input class="form-input" type="number" min="0" step="0.0001" name="lines[' + index + '][qty]" /></td>' +
      '<td class="supplier-invoice-line-unit-price"><input class="form-input" type="number" min="0" step="0.0001" name="lines[' + index + '][unit_price]" /></td>' +
      '<td><button type="button" class="btn-text supplier-invoice-remove-line">Remove</button></td>';
    table.querySelector('tbody').appendChild(row);
    bindRow(row);
  });
})();
</script>
<?php endif; ?>
