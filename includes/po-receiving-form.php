<?php
/** @var array $form */
/** @var string $formAction */
/** @var bool $isEdit */
/** @var array $poOptions */
$isEdit = $isEdit ?? false;
$poOptions = $poOptions ?? por_po_options();
$lines = $form['lines'] ?? [];
$lineColspan = 16;
$newPagePath = por_page_path('/po-receiving/new.php');
$listPagePath = por_page_path('/po-receiving/');
$viewPagePath = por_page_path('/po-receiving/view.php');
$poId = (int) ($form['po_id'] ?? 0);
$priorReceipts = $poId > 0 ? por_list_for_po($poId) : [];
$excludePorId = $isEdit ? (int) ($form['por_id'] ?? 0) : null;
?>
      <form class="admin-form por-receiving-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($formAction) ?>">
        <div class="form-grid por-receiving-header-grid">
          <div class="form-group form-grid-full por-inline-field">
            <label for="po_id">Purchase order</label>
            <select class="form-input" id="po_id" name="po_id" required <?= $isEdit ? 'disabled' : 'onchange="if (this.value) { window.location.href = \'' . htmlspecialchars($newPagePath, ENT_QUOTES) . '?po_id=\' + encodeURIComponent(this.value); }"' ?>>
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
          <div class="form-group por-inline-field">
            <label for="por_status">Receipt status</label>
            <select class="form-input" id="por_status" name="por_status">
              <?php foreach (POR_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= ($form['por_status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group por-inline-field">
            <label for="jazz_asn">Jazz ASN number</label>
            <input class="form-input" type="text" id="jazz_asn" name="jazz_asn" maxlength="50" value="<?= htmlspecialchars($form['jazz_asn'] ?? '') ?>" placeholder="Assigned after ASN transmit" />
          </div>
          <div class="form-group por-inline-field">
            <label for="business_type">Business type</label>
            <input class="form-input" type="text" id="business_type" name="business_type" maxlength="100" value="<?= htmlspecialchars($form['business_type'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="shipment_number">Shipment number</label>
            <input class="form-input" type="text" id="shipment_number" name="shipment_number" maxlength="100" value="<?= htmlspecialchars($form['shipment_number'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="facility">Facility</label>
            <input class="form-input" type="text" id="facility" name="facility" maxlength="100" value="<?= htmlspecialchars($form['facility'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="carrier_number">Carrier number</label>
            <input class="form-input" type="text" id="carrier_number" name="carrier_number" maxlength="100" value="<?= htmlspecialchars($form['carrier_number'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="seal_number">Seal number</label>
            <input class="form-input" type="text" id="seal_number" name="seal_number" maxlength="100" value="<?= htmlspecialchars($form['seal_number'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="load_number">Load number</label>
            <input class="form-input" type="text" id="load_number" name="load_number" maxlength="100" value="<?= htmlspecialchars($form['load_number'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="shipping_method">Shipping method</label>
            <input class="form-input" type="text" id="shipping_method" name="shipping_method" maxlength="100" value="<?= htmlspecialchars($form['shipping_method'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="shipped_at">Shipped at</label>
            <input class="form-input" type="datetime-local" id="shipped_at" name="shipped_at" value="<?= htmlspecialchars($form['shipped_at'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="appointment_made">Appointment made</label>
            <select class="form-input" id="appointment_made" name="appointment_made">
              <option value="0" <?= empty($form['appointment_made']) ? 'selected' : '' ?>>No</option>
              <option value="1" <?= !empty($form['appointment_made']) ? 'selected' : '' ?>>Yes</option>
            </select>
          </div>
          <div class="form-group por-inline-field">
            <label for="expected_date">Expected date</label>
            <input class="form-input" type="date" id="expected_date" name="expected_date" value="<?= htmlspecialchars($form['expected_date'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="scheduled_receipt_date">Scheduled receipt date</label>
            <input class="form-input" type="date" id="scheduled_receipt_date" name="scheduled_receipt_date" value="<?= htmlspecialchars($form['scheduled_receipt_date'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="scheduled_receipt_time">Scheduled receipt time</label>
            <input class="form-input" type="time" id="scheduled_receipt_time" name="scheduled_receipt_time" value="<?= htmlspecialchars($form['scheduled_receipt_time'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="actual_receipt_date">Actual receipt date</label>
            <input class="form-input" type="date" id="actual_receipt_date" name="actual_receipt_date" value="<?= htmlspecialchars($form['actual_receipt_date'] ?? '') ?>" />
          </div>
          <div class="form-group por-inline-field">
            <label for="delivery_address">Delivery address</label>
            <textarea class="form-input por-address-textarea" id="delivery_address" name="delivery_address" rows="4"><?= htmlspecialchars($form['delivery_address'] ?? '') ?></textarea>
          </div>
          <div class="form-group por-inline-field">
            <label for="por_notes">Receipt notes</label>
            <textarea class="form-input por-address-textarea" id="por_notes" name="por_notes" rows="4"><?= htmlspecialchars($form['por_notes'] ?? '') ?></textarea>
          </div>
        </div>

        <?php require __DIR__ . '/po-receiving-prior-receipts-section.php'; ?>

        <h2 class="production-line-header">Line items</h2>
        <div class="admin-table-wrap por-receiving-table-wrap">
          <table class="admin-table por-receiving-lines-table" id="por-lines-table">
            <thead>
              <tr>
                <th class="por-sticky-col">LN#</th>
                <th class="por-sticky-col">SKU</th>
                <th class="por-sticky-col">Desc</th>
                <th>QTY ORD</th>
                <th>QTY SCHED</th>
                <th>QTY Prev Rec</th>
                <th>QTY REM</th>
                <th>LOT#</th>
                <th>QTY EXP</th>
                <th>QTY REC</th>
                <th>CS BC</th>
                <th>SKU BC</th>
                <th>COO</th>
                <th>On HLD</th>
                <th>Note</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="por-lines-body">
              <?php if ($lines === []): ?>
              <tr><td colspan="<?= $lineColspan ?>">Select a purchase order to load line items.</td></tr>
              <?php else: ?>
              <?php foreach ($lines as $index => $line): ?>
              <?php
                $nextPoLineId = (int) ($lines[$index + 1]['po_line_id'] ?? 0);
                $isLastLotForLine = $nextPoLineId !== (int) ($line['po_line_id'] ?? 0);
                $showMeta = !empty($line['show_line_meta']);
                $rowClass = $showMeta ? 'por-lot-row' : 'por-lot-row por-lot-continuation';
              ?>
              <tr class="<?= $rowClass ?>" data-po-line-id="<?= (int) ($line['po_line_id'] ?? 0) ?>">
                <td class="por-sticky-col por-line-meta"><?= $showMeta ? (int) ($line['line_number'] ?? ($index + 1)) : '' ?></td>
                <td class="por-sticky-col por-line-meta">
                  <?php if ($showMeta): ?>
                  <?= htmlspecialchars($line['item_sku'] ?? '—') ?>
                  <?php endif; ?>
                  <input type="hidden" name="lines[<?= $index ?>][po_line_id]" value="<?= (int) ($line['po_line_id'] ?? 0) ?>" />
                  <input type="hidden" name="lines[<?= $index ?>][pord_id]" value="<?= htmlspecialchars((string) ($line['pord_id'] ?? '')) ?>" />
                  <input type="hidden" name="lines[<?= $index ?>][item_sku]" value="<?= htmlspecialchars($line['item_sku'] ?? '') ?>" />
                  <input type="hidden" name="lines[<?= $index ?>][item_description]" value="<?= htmlspecialchars($line['item_description'] ?? '') ?>" />
                </td>
                <td class="por-sticky-col por-line-meta"><?= $showMeta ? htmlspecialchars($line['item_description'] ?? '') : '' ?></td>
                <td class="por-line-meta"><?= $showMeta ? htmlspecialchars($line['quantity_ordered'] ?? '—') : '' ?></td>
                <td class="por-line-meta"><?= $showMeta ? htmlspecialchars($line['quantity_scheduled'] ?? '0') : '' ?></td>
                <td class="por-line-meta"><?= $showMeta ? htmlspecialchars($line['quantity_prev_received'] ?? '0') : '' ?></td>
                <td class="por-line-meta"><?= $showMeta ? htmlspecialchars($line['quantity_remaining'] ?? '0') : '' ?></td>
                <td><input class="form-input por-compact-input" type="text" maxlength="50" name="lines[<?= $index ?>][lot_number]" value="<?= htmlspecialchars($line['lot_number'] ?? '') ?>" /></td>
                <td><input class="form-input por-compact-input" type="number" min="0" step="1" name="lines[<?= $index ?>][quantity_expected]" value="<?= htmlspecialchars($line['quantity_expected'] ?? '') ?>" /></td>
                <td><input class="form-input por-compact-input" type="number" min="0" step="1" name="lines[<?= $index ?>][quantity_received]" value="<?= htmlspecialchars($line['quantity_received'] ?? '0') ?>" /></td>
                <td><input class="form-input por-compact-input" type="text" maxlength="100" name="lines[<?= $index ?>][case_barcode]" value="<?= htmlspecialchars($line['case_barcode'] ?? '') ?>" /></td>
                <td><input class="form-input por-compact-input" type="text" maxlength="100" name="lines[<?= $index ?>][sku_barcode]" value="<?= htmlspecialchars($line['sku_barcode'] ?? '') ?>" /></td>
                <td><input class="form-input por-compact-input" type="text" maxlength="100" name="lines[<?= $index ?>][country_of_origin]" value="<?= htmlspecialchars($line['country_of_origin'] ?? '') ?>" /></td>
                <td>
                  <label class="permission-note por-hold-label">
                    <input type="checkbox" name="lines[<?= $index ?>][on_hold]" value="1" <?= !empty($line['on_hold']) ? 'checked' : '' ?> />
                    HLD
                  </label>
                </td>
                <td><input class="form-input por-compact-input" type="text" maxlength="250" name="lines[<?= $index ?>][li_note]" value="<?= htmlspecialchars($line['li_note'] ?? '') ?>" /></td>
                <td class="por-lot-actions">
                  <?php if ($isLastLotForLine): ?>
                  <button type="button" class="btn-text por-add-lot-btn" title="Add lot number">+ Lot</button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php require __DIR__ . '/po-receiving-form-attachments-section.php'; ?>

        <div class="module-actions">
          <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Receipt' ?></button>
          <a class="btn-secondary" href="<?= htmlspecialchars($isEdit ? $viewPagePath . '?id=' . (int) ($form['por_id'] ?? 0) : $listPagePath) ?>">Cancel</a>
        </div>
      </form>
      <script src="/assets/js/po-receiving-form.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/assets/js/po-receiving-form.js') ?>"></script>
