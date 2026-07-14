<?php
/** @var array $form */
/** @var array $suppliers */
/** @var array $lines */
/** @var bool $isEdit */
/** @var bool $allowAttachment */
$isEdit = $isEdit ?? false;
$allowAttachment = $allowAttachment ?? !$isEdit;
$form = array_merge(po_default_header(), $form ?? []);
$lines = $lines ?? [['sku' => '', 'quote_number' => '', 'description' => '', 'quantity' => '', 'unit_price' => '', 'expiration_date' => '']];
$skuOptions = po_sku_options();
$skuCodes = array_map(static fn(array $opt): string => (string) $opt['SKUCode'], $skuOptions);

$renderSkuSelect = static function (int $index, string $selected) use ($skuOptions, $skuCodes): string {
    $html = '<select class="form-input po-line-sku" name="lines[' . $index . '][sku]">';
    $html .= '<option value="">— No SKU —</option>';
    if ($selected !== '' && !in_array($selected, $skuCodes, true)) {
        $html .= '<option value="' . htmlspecialchars($selected) . '" selected>' . htmlspecialchars($selected) . ' (not in SKU Master)</option>';
    }
    foreach ($skuOptions as $opt) {
        $code = (string) $opt['SKUCode'];
        $html .= '<option value="' . htmlspecialchars($code) . '" data-product-name="' . htmlspecialchars((string) $opt['ProductName']) . '"'
            . ($selected === $code ? ' selected' : '') . '>'
            . htmlspecialchars($code . ' · ' . $opt['ProductName'])
            . '</option>';
    }
    $html .= '</select>';

    return $html;
};
?>
<form class="admin-form po-form" method="post" enctype="multipart/form-data"<?= !empty($formAction) ? ' action="' . htmlspecialchars($formAction) . '"' : '' ?>>
  <h2 class="admin-form-subhead">Purchase order</h2>
  <div class="form-grid">
    <div class="form-group">
      <label for="po_number">PO number</label>
      <input class="form-input" type="text" id="po_number" name="po_number" value="<?= htmlspecialchars($form['po_number'] ?? '') ?>" placeholder="Auto-generated if blank" />
    </div>
    <div class="form-group">
      <label for="supplier_id">Supplier</label>
      <select class="form-input" id="supplier_id" name="supplier_id" required>
        <option value="">Select supplier</option>
        <?php foreach ($suppliers as $supplier): ?>
        <option value="<?= (int) $supplier['SupplierID'] ?>" <?= (int) ($form['supplier_id'] ?? 0) === (int) $supplier['SupplierID'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($supplier['SupplierName']) ?><?= $supplier['SupplierCode'] ? ' (' . htmlspecialchars($supplier['SupplierCode']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="order_date">PO date</label>
      <input class="form-input" type="date" id="order_date" name="order_date" value="<?= htmlspecialchars($form['order_date'] ?? date('Y-m-d')) ?>" required />
    </div>
    <div class="form-group">
      <label for="expected_delivery_date">Expected delivery</label>
      <input class="form-input" type="date" id="expected_delivery_date" name="expected_delivery_date" value="<?= htmlspecialchars($form['expected_delivery_date'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label for="payment_terms">Payment terms</label>
      <input class="form-input" type="text" id="payment_terms" name="payment_terms" value="<?= htmlspecialchars($form['payment_terms'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label for="delivery_terms">Delivery terms</label>
      <input class="form-input" type="text" id="delivery_terms" name="delivery_terms" value="<?= htmlspecialchars($form['delivery_terms'] ?? '') ?>" placeholder="FOB Mt. Bethel, PA" />
    </div>
    <div class="form-group">
      <label for="shipping_handling">Shipping &amp; handling</label>
      <input class="form-input" type="number" min="0" step="0.01" id="shipping_handling" name="shipping_handling" value="<?= htmlspecialchars((string) ($form['shipping_handling'] ?? '')) ?>" />
    </div>
    <input type="hidden" name="po_status" value="<?= htmlspecialchars($form['po_status'] ?? 'Created') ?>" />
  </div>

  <h2 class="admin-form-subhead">Buyer</h2>
  <div class="form-grid">
    <div class="form-group">
      <label for="buyer_name">Buyer name</label>
      <input class="form-input" type="text" id="buyer_name" name="buyer_name" value="<?= htmlspecialchars($form['buyer_name'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label for="buyer_contact_name">Contact name</label>
      <input class="form-input" type="text" id="buyer_contact_name" name="buyer_contact_name" value="<?= htmlspecialchars($form['buyer_contact_name'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label for="buyer_contact_email">Contact email</label>
      <input class="form-input" type="email" id="buyer_contact_email" name="buyer_contact_email" value="<?= htmlspecialchars($form['buyer_contact_email'] ?? '') ?>" />
    </div>
    <div class="form-group">
      <label for="buyer_contact_phone">Contact phone</label>
      <input class="form-input" type="text" id="buyer_contact_phone" name="buyer_contact_phone" value="<?= htmlspecialchars($form['buyer_contact_phone'] ?? '') ?>" />
    </div>
  </div>
  <div class="form-group">
    <label for="buyer_address">Buyer address</label>
    <textarea class="form-input form-textarea" id="buyer_address" name="buyer_address" rows="2"><?= htmlspecialchars($form['buyer_address'] ?? '') ?></textarea>
  </div>

  <div class="form-group form-grid-full">
    <label for="delivery_address">Delivery address</label>
    <input class="form-input" type="text" id="delivery_address" name="delivery_address" maxlength="500" value="<?= htmlspecialchars($form['delivery_address'] ?? '') ?>" />
    <p class="form-hint">Where inbound shipments for this PO should be delivered.</p>
  </div>

  <h2 class="admin-form-subhead">Supplier snapshot</h2>
  <div class="form-group">
    <label for="supplier_address">Supplier address on PO</label>
    <textarea class="form-input form-textarea" id="supplier_address" name="supplier_address" rows="2"><?= htmlspecialchars($form['supplier_address'] ?? '') ?></textarea>
    <p class="form-hint">Defaults from supplier record if left blank.</p>
  </div>

  <div class="form-group">
    <label for="reference_documents">Reference documents</label>
    <textarea class="form-input form-textarea" id="reference_documents" name="reference_documents" rows="2"><?= htmlspecialchars($form['reference_documents'] ?? '') ?></textarea>
  </div>

  <div class="form-group">
    <label for="special_instructions">Special instructions</label>
    <textarea class="form-input form-textarea" id="special_instructions" name="special_instructions" rows="3"><?= htmlspecialchars($form['special_instructions'] ?? '') ?></textarea>
  </div>

  <div class="form-group">
    <label for="notes">Internal notes</label>
    <textarea class="form-input form-textarea" id="notes" name="notes" rows="2"><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
  </div>

  <?php if ($allowAttachment): ?>
  <?php
  $uploadFieldId = 'source_pdf';
  $uploadFieldName = 'source_pdf';
  $uploadLabel = 'Attach source PDF (optional)';
  $uploadTitle = 'Drop, paste, or choose PDF';
  $uploadHint = 'Drag a PDF here, click and paste (Ctrl+V / Cmd+V), or choose a file';
  $uploadFormHint = 'Signed or draft PO PDF stored via shared attachment storage (max 15 MB).';
  $uploadAccept = '.pdf,application/pdf';
  $uploadMaxBytes = PO_MAX_ATTACHMENT_BYTES;
  $uploadAllowedExt = ['pdf'];
  $uploadRequired = false;
  $uploadGridClass = '';
  require __DIR__ . '/file-upload-dropzone-field.php';
  ?>
  <?php endif; ?>

  <div class="po-lines-section">
    <div class="po-lines-header">
      <h2>Line items</h2>
      <button type="button" class="btn-secondary btn-small" id="add-line-item">Add line</button>
    </div>

    <div class="admin-table-wrap">
      <table class="admin-table po-lines-table" id="po-lines-table">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Quote #</th>
            <th>Product / bottle title</th>
            <th>Qty (bottles)</th>
            <th>Unit price</th>
            <th>Exp date</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="po-lines-body">
          <?php foreach ($lines as $index => $line): ?>
          <tr class="po-line-row">
            <td><?= $renderSkuSelect($index, trim((string) ($line['sku'] ?? ''))) ?></td>
            <td><input class="form-input" type="text" name="lines[<?= $index ?>][quote_number]" value="<?= htmlspecialchars($line['quote_number'] ?? '') ?>" /></td>
            <td><input class="form-input" type="text" name="lines[<?= $index ?>][description]" value="<?= htmlspecialchars($line['description'] ?? '') ?>" /></td>
            <td><input class="form-input" type="number" min="0" step="1" name="lines[<?= $index ?>][quantity]" value="<?= htmlspecialchars($line['quantity'] !== '' && $line['quantity'] !== null ? po_format_qty($line['quantity']) : '') ?>" /></td>
            <td><input class="form-input" type="number" min="0" step="0.01" name="lines[<?= $index ?>][unit_price]" value="<?= htmlspecialchars((string) ($line['unit_price'] ?? '')) ?>" /></td>
            <td><input class="form-input" type="date" name="lines[<?= $index ?>][expiration_date]" value="<?= htmlspecialchars((string) ($line['expiration_date'] ?? '')) ?>" /></td>
            <td><button type="button" class="btn-text btn-text-danger remove-line">Remove</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="module-actions">
    <button class="btn-primary" type="submit"><?= $isEdit ? 'Save Changes' : 'Create Purchase Order' ?></button>
    <a class="btn-secondary" href="/po-management/">Cancel</a>
  </div>
</form>

<script>
(function () {
  var body = document.getElementById('po-lines-body');
  var addBtn = document.getElementById('add-line-item');
  if (!body || !addBtn) return;

  var skuSelectTemplate = <?= json_encode($renderSkuSelect(0, '')) ?>;

  function bindRemoveButtons() {
    body.querySelectorAll('.remove-line').forEach(function (btn) {
      btn.onclick = function () {
        if (body.querySelectorAll('.po-line-row').length <= 1) return;
        btn.closest('tr').remove();
        reindexRows();
      };
    });
  }

  function reindexRows() {
    body.querySelectorAll('.po-line-row').forEach(function (row, index) {
      row.querySelectorAll('input, select').forEach(function (input) {
        input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
      });
    });
  }

  body.addEventListener('change', function (event) {
    var select = event.target.closest('.po-line-sku');
    if (!select) return;
    var option = select.options[select.selectedIndex];
    var productName = option ? option.getAttribute('data-product-name') : null;
    if (!productName) return;
    var description = select.closest('tr').querySelector('input[name$="[description]"]');
    if (description) description.value = productName;
  });

  addBtn.addEventListener('click', function () {
    var index = body.querySelectorAll('.po-line-row').length;
    var row = document.createElement('tr');
    row.className = 'po-line-row';
    row.innerHTML =
      '<td>' + skuSelectTemplate.replace('lines[0][sku]', 'lines[' + index + '][sku]') + '</td>' +
      '<td><input class="form-input" type="text" name="lines[' + index + '][quote_number]" /></td>' +
      '<td><input class="form-input" type="text" name="lines[' + index + '][description]" /></td>' +
      '<td><input class="form-input" type="number" min="0" step="1" name="lines[' + index + '][quantity]" /></td>' +
      '<td><input class="form-input" type="number" min="0" step="0.01" name="lines[' + index + '][unit_price]" /></td>' +
      '<td><input class="form-input" type="date" name="lines[' + index + '][expiration_date]" /></td>' +
      '<td><button type="button" class="btn-text btn-text-danger remove-line">Remove</button></td>';
    body.appendChild(row);
    bindRemoveButtons();
  });

  bindRemoveButtons();
})();
</script>
