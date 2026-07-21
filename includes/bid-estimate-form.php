<?php
/** @var array $form */
/** @var array $suppliers */
/** @var bool $isLocked */
$isLocked = $isLocked ?? false;
$suppliers = $suppliers ?? [];
?>
<div class="form-grid">
  <div class="form-group">
    <label for="supplier_id">Existing supplier (optional)</label>
    <select class="form-input" id="supplier_id" name="supplier_id" <?= $isLocked ? 'disabled' : '' ?>>
      <option value="">— New / ad-hoc vendor —</option>
      <?php foreach ($suppliers as $supplier): ?>
      <option value="<?= (int) $supplier['SupplierID'] ?>" <?= (int) ($form['supplier_id'] ?? 0) === (int) $supplier['SupplierID'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($supplier['SupplierName']) ?><?= !empty($supplier['SupplierCode']) ? ' (' . htmlspecialchars($supplier['SupplierCode']) . ')' : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if ($isLocked): ?><input type="hidden" name="supplier_id" value="<?= htmlspecialchars($form['supplier_id'] ?? '') ?>" /><?php endif; ?>
    <p class="form-hint">If blank, enter vendor contact fields below. Award will create a Supplier when needed.</p>
  </div>
  <div class="form-group">
    <label for="vendor_name">Vendor name</label>
    <input class="form-input" type="text" id="vendor_name" name="vendor_name" maxlength="200" required value="<?= htmlspecialchars($form['vendor_name'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="contact_name">Contact name</label>
    <input class="form-input" type="text" id="contact_name" name="contact_name" maxlength="200" value="<?= htmlspecialchars($form['contact_name'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="contact_email">Contact email</label>
    <input class="form-input" type="email" id="contact_email" name="contact_email" maxlength="200" value="<?= htmlspecialchars($form['contact_email'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="contact_phone">Contact phone</label>
    <input class="form-input" type="text" id="contact_phone" name="contact_phone" maxlength="50" value="<?= htmlspecialchars($form['contact_phone'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="bid_amount">Bid amount</label>
    <input class="form-input" type="number" min="0" step="0.01" id="bid_amount" name="bid_amount" required value="<?= htmlspecialchars($form['bid_amount'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="currency_code">Currency</label>
    <input class="form-input" type="text" id="currency_code" name="currency_code" maxlength="10" value="<?= htmlspecialchars($form['currency_code'] ?? 'USD') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="submitted_date">Submitted date</label>
    <input class="form-input" type="date" id="submitted_date" name="submitted_date" value="<?= htmlspecialchars($form['submitted_date'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="valid_until">Valid until</label>
    <input class="form-input" type="date" id="valid_until" name="valid_until" value="<?= htmlspecialchars($form['valid_until'] ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?> />
  </div>
  <div class="form-group">
    <label for="status">Status</label>
    <select class="form-input" id="status" name="status" <?= $isLocked ? 'disabled' : '' ?>>
      <?php foreach (BID_ESTIMATE_STATUSES as $status): ?>
        <?php if ($status === 'Selected') continue; ?>
      <option value="<?= htmlspecialchars($status) ?>" <?= ($form['status'] ?? 'Received') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($isLocked): ?><input type="hidden" name="status" value="<?= htmlspecialchars($form['status'] ?? 'Received') ?>" /><?php endif; ?>
  </div>
  <div class="form-group form-grid-full">
    <label for="notes">Notes</label>
    <textarea class="form-input" id="notes" name="notes" rows="3" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($form['notes'] ?? '') ?></textarea>
  </div>
</div>
<script>
(function () {
  var supplierSelect = document.getElementById('supplier_id');
  var vendorName = document.getElementById('vendor_name');
  var contactName = document.getElementById('contact_name');
  var contactEmail = document.getElementById('contact_email');
  var contactPhone = document.getElementById('contact_phone');
  if (!supplierSelect || !vendorName) return;
  var suppliers = <?= json_encode(array_values(array_map(static function (array $s): array {
      return [
          'id'    => (int) $s['SupplierID'],
          'name'  => (string) $s['SupplierName'],
          'contact' => (string) ($s['ContactName'] ?? ''),
          'email' => (string) ($s['ContactEmail'] ?? ''),
          'phone' => (string) ($s['ContactPhone'] ?? ''),
      ];
  }, $suppliers)), JSON_UNESCAPED_SLASHES) ?>;
  supplierSelect.addEventListener('change', function () {
    var id = parseInt(supplierSelect.value || '0', 10);
    if (!id) return;
    var match = suppliers.find(function (row) { return row.id === id; });
    if (!match) return;
    if (!vendorName.value) vendorName.value = match.name || '';
    if (contactName && !contactName.value) contactName.value = match.contact || '';
    if (contactEmail && !contactEmail.value) contactEmail.value = match.email || '';
    if (contactPhone && !contactPhone.value) contactPhone.value = match.phone || '';
  });
})();
</script>
