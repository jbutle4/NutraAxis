<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $poOptions */
$isEdit = $isEdit ?? false;
$poOptions = $poOptions ?? por_po_options();
$lines = $form['lines'] ?? [];
$lineColspan = 12;
?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="po_id">Purchase order</label>
            <select class="form-input" id="po_id" name="po_id" required <?= $isEdit ? 'disabled' : 'onchange="if (this.value) { window.location.href = \'/po-receiving/new.php?po_id=\' + encodeURIComponent(this.value); }"' ?>>
              <option value="">Select PO</option>
              <?php foreach ($poOptions as $option): ?>
              <option value="<?= (int) $option['id'] ?>" <?= (string) ($form['po_id'] ?? '') === (string) $option['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($option['label']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if ($isEdit): ?>
            <input type="hidden" name="po_id" value="<?= (int) ($form['po_id'] ?? 0) ?>" />
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label for="por_status">Receipt status</label>
            <select class="form-input" id="por_status" name="por_status">
              <?php foreach (POR_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['por_status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="jazz_asn">Jazz ASN number</label>
            <input class="form-input" type="text" id="jazz_asn" name="jazz_asn" maxlength="50" value="<?= htmlspecialchars($form['jazz_asn'] ?? '') ?>" placeholder="Assigned after ASN transmit" />
          </div>
          <div class="form-group">
            <label for="business_type">Business type</label>
            <input class="form-input" type="text" id="business_type" name="business_type" maxlength="100" value="<?= htmlspecialchars($form['business_type'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="shipment_number">Shipment number</label>
            <input class="form-input" type="text" id="shipment_number" name="shipment_number" maxlength="100" value="<?= htmlspecialchars($form['shipment_number'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="facility">Facility</label>
            <input class="form-input" type="text" id="facility" name="facility" maxlength="100" value="<?= htmlspecialchars($form['facility'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="carrier_number">Carrier number</label>
            <input class="form-input" type="text" id="carrier_number" name="carrier_number" maxlength="100" value="<?= htmlspecialchars($form['carrier_number'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="seal_number">Seal number</label>
            <input class="form-input" type="text" id="seal_number" name="seal_number" maxlength="100" value="<?= htmlspecialchars($form['seal_number'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="load_number">Load number</label>
            <input class="form-input" type="text" id="load_number" name="load_number" maxlength="100" value="<?= htmlspecialchars($form['load_number'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="shipping_method">Shipping method</label>
            <input class="form-input" type="text" id="shipping_method" name="shipping_method" maxlength="100" value="<?= htmlspecialchars($form['shipping_method'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="shipped_at">Shipped at</label>
            <input class="form-input" type="datetime-local" id="shipped_at" name="shipped_at" value="<?= htmlspecialchars($form['shipped_at'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="appointment_made">Appointment made</label>
            <select class="form-input" id="appointment_made" name="appointment_made">
              <option value="0" <?= empty($form['appointment_made']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($form['appointment_made']) ? 'selected' : '' ?>>Yes</option>
            </select>
          </div>
          <div class="form-group">
            <label for="expected_date">Expected date</label>
            <input class="form-input" type="date" id="expected_date" name="expected_date" value="<?= htmlspecialchars($form['expected_date'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="scheduled_receipt_date">Scheduled receipt date</label>
            <input class="form-input" type="date" id="scheduled_receipt_date" name="scheduled_receipt_date" value="<?= htmlspecialchars($form['scheduled_receipt_date'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="scheduled_receipt_time">Scheduled receipt time</label>
            <input class="form-input" type="time" id="scheduled_receipt_time" name="scheduled_receipt_time" value="<?= htmlspecialchars($form['scheduled_receipt_time'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label for="actual_receipt_date">Actual receipt date</label>
            <input class="form-input" type="date" id="actual_receipt_date" name="actual_receipt_date" value="<?= htmlspecialchars($form['actual_receipt_date'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="delivery_address">Delivery address</label>
            <input class="form-input" type="text" id="delivery_address" name="delivery_address" value="<?= htmlspecialchars($form['delivery_address'] ?? '') ?>" />
          </div>
          <div class="form-group form-grid-full">
            <label for="por_notes">Receipt notes</label>
            <textarea class="form-input" id="por_notes" name="por_notes" rows="4"><?= htmlspecialchars($form['por_notes'] ?? '') ?></textarea>
          </div>
        </div>

        <h2 class="production-line-header">Line items</h2>
        <div class="admin-table-wrap production-status-table-wrap">
          <table class="admin-table production-status-table">
            <thead>
              <tr>
                <th>Line</th>
                <th>SKU</th>
                <th>Description</th>
                <th>Qty ordered</th>
                <th>Qty scheduled</th>
                <th>Qty expected</th>
                <th>Qty received</th>
                <th>Case barcode</th>
                <th>SKU barcode</th>
                <th>Country of origin</th>
                <th>On hold</th>
                <th>Line note</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($lines === []): ?>
              <tr><td colspan="<?= $lineColspan ?>">Select a purchase order to load line items.</td></tr>
              <?php else: ?>
              <?php foreach ($lines as $index => $line): ?>
              <tr>
                <td><?= (int) ($line['line_number'] ?? ($index + 1)) ?></td>
                <td>
                  <?= htmlspecialchars($line['item_sku'] ?? '—') ?>
                  <input type="hidden" name="lines[<?= $index ?>][po_line_id]" value="<?= (int) ($line['po_line_id'] ?? 0) ?>" />
                  <input type="hidden" name="lines[<?= $index ?>][item_sku]" value="<?= htmlspecialchars($line['item_sku'] ?? '') ?>" />
                  <input type="hidden" name="lines[<?= $index ?>][item_description]" value="<?= htmlspecialchars($line['item_description'] ?? '') ?>" />
                </td>
                <td><?= htmlspecialchars($line['item_description'] ?? '') ?></td>
                <td><?= htmlspecialchars($line['quantity_ordered'] ?? '—') ?></td>
                <td><?= htmlspecialchars($line['quantity_scheduled'] ?? '0') ?></td>
                <td><input class="form-input" type="number" min="0" step="1" name="lines[<?= $index ?>][quantity_expected]" value="<?= htmlspecialchars($line['quantity_expected'] ?? '') ?>" /></td>
                <td><input class="form-input" type="number" min="0" step="1" name="lines[<?= $index ?>][quantity_received]" value="<?= htmlspecialchars($line['quantity_received'] ?? '0') ?>" /></td>
                <td><input class="form-input" type="text" maxlength="100" name="lines[<?= $index ?>][case_barcode]" value="<?= htmlspecialchars($line['case_barcode'] ?? '') ?>" /></td>
                <td><input class="form-input" type="text" maxlength="100" name="lines[<?= $index ?>][sku_barcode]" value="<?= htmlspecialchars($line['sku_barcode'] ?? '') ?>" /></td>
                <td><input class="form-input" type="text" maxlength="100" name="lines[<?= $index ?>][country_of_origin]" value="<?= htmlspecialchars($line['country_of_origin'] ?? '') ?>" /></td>
                <td>
                  <label class="permission-note">
                    <input type="checkbox" name="lines[<?= $index ?>][on_hold]" value="1" <?= !empty($line['on_hold']) ? 'checked' : '' ?> />
                    Hold
                  </label>
                </td>
                <td><input class="form-input" type="text" maxlength="250" name="lines[<?= $index ?>][li_note]" value="<?= htmlspecialchars($line['li_note'] ?? '') ?>" /></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="module-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Receipt' ?></button>
          <a class="btn-secondary" href="<?= $isEdit ? '/po-receiving/view.php?id=' . (int) ($form['por_id'] ?? 0) : '/po-receiving/' ?>">Cancel</a>
        </div>
      </form>
