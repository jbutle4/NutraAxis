<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-rework.php';

inventory_transfers_require_update();
inventory_ims_bind_page_environments();

$activeSlug = $activeSlug ?? 'inventory-transfers';
$sourceFacilities = inventory_transfers_list_source_facilities();
$selectedFrom = (string) ($_POST['from_facility_code'] ?? 'CART');
$selectedTo = (string) ($_POST['to_facility_code'] ?? '');
$destinationFacilities = inventory_transfers_list_destination_facilities($selectedFrom);
if ($selectedTo === '' && $destinationFacilities !== []) {
    $selectedTo = (string) $destinationFacilities[0]['FacilityCode'];
}
$reasons = inventory_transfers_reason_codes();
$cmoSuppliers = po_rework_list_cmo_suppliers();
$cmoReasonId = po_rework_reason_code_id();
$error = null;
$lines = [
    ['sku_code' => '', 'qty_requested' => ''],
    ['sku_code' => '', 'qty_requested' => ''],
    ['sku_code' => '', 'qty_requested' => ''],
];
$jazzShipBy = (string) ($_POST['jazz_ship_by_date'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = inventory_transfers_create_batch([
            'from_facility_code' => $_POST['from_facility_code'] ?? '',
            'to_facility_code'   => $_POST['to_facility_code'] ?? '',
            'from_status_bucket' => $_POST['from_status_bucket'] ?? 'OK',
            'to_status_bucket'   => $_POST['to_status_bucket'] ?? 'OK',
            'reason_code_id'     => $_POST['reason_code_id'] ?? null,
            'supplier_id'        => $_POST['supplier_id'] ?? null,
            'notes'              => $_POST['notes'] ?? '',
            'jazz_ship_by_date'  => $_POST['jazz_ship_by_date'] ?? '',
        ], is_array($_POST['lines'] ?? null) ? $_POST['lines'] : []);

        if ($result['ok']) {
            $shipmentId = (int) ($result['shipment_id'] ?? 0);
            $count = count($result['transfer_ids']);
            if ($shipmentId > 0 && inventory_transfers_use_shipment_model()) {
                $target = '/inventory-transfers/shipment.php?shipment_id=' . $shipmentId . '&notice=batch_created&n=' . $count;
            } elseif ($count > 0) {
                $target = '/inventory-transfers/view.php?id=' . (int) $result['transfer_ids'][0] . '&notice=batch_created&n=' . $count;
            } else {
                $target = '/inventory-transfers/?notice=batch_created&n=' . $count;
            }
            header('Location: ' . inventory_ims_page_path($target), true, 302);
            exit;
        }
        $error = $result['error'] ?? 'Unable to create transfer.';
        $selectedFrom = (string) ($_POST['from_facility_code'] ?? $selectedFrom);
        $selectedTo = (string) ($_POST['to_facility_code'] ?? $selectedTo);
        $destinationFacilities = inventory_transfers_list_destination_facilities($selectedFrom);
        $jazzShipBy = (string) ($_POST['jazz_ship_by_date'] ?? '');
        if (is_array($_POST['lines'] ?? null) && $_POST['lines'] !== []) {
            $lines = $_POST['lines'];
        }
    } catch (Throwable) {
        $error = 'Unable to create transfer. Please try again or contact support.';
    }
}

$routePreview = [
    'FromFacilityCode' => $selectedFrom,
    'ToFacilityCode'   => $selectedTo,
    'JazzShipByDate'   => $jazzShipBy !== '' ? $jazzShipBy : date('Y-m-d'),
];
$showPharmacyBatchHint = inventory_transfers_jazz_is_pharmacy_replenishment($routePreview);
$batchOrderPreview = $showPharmacyBatchHint
    ? inventory_transfers_jazz_batch_order_number($routePreview)
    : '';

$skuOptions = po_sku_options();
$skuCodes = array_map(static fn(array $opt): string => (string) $opt['SKUCode'], $skuOptions);
$renderTransferSkuSelect = static function (int $index, string $selected) use ($skuOptions, $skuCodes): string {
    $html = '<select class="form-input transfer-line-sku" name="lines[' . $index . '][sku_code]">';
    $html .= '<option value="">Select SKU</option>';
    if ($selected !== '' && !in_array($selected, $skuCodes, true)) {
        $html .= '<option value="' . htmlspecialchars($selected) . '" selected>'
            . htmlspecialchars($selected) . ' (not in SKU Master)</option>';
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

$pageTitle = 'New Facility Transfer | Inventory Management';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => inventory_ims_page_path('/inventory-transfers/'),
          'back_label' => 'Back to Transfers',
          'category'   => 'Inventory',
          'title'      => 'New Facility Transfer',
          'lead'       => 'One shipment header with multiple SKU lines. Use <strong>Send to Jazz</strong> on the transfer detail page after lines are created (while Pending).',
          'lead_html'  => true,
      ]); ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form" id="transfer-form" action="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/new.php')) ?>">
        <h2 class="form-section-title">Shipment</h2>
        <div class="form-grid">
          <div class="form-group">
            <label for="from_facility_code">From facility</label>
            <select class="form-input" id="from_facility_code" name="from_facility_code" required>
              <?php foreach ($sourceFacilities as $facility): ?>
              <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>"<?= strcasecmp((string) ($facility['FacilityCode'] ?? ''), $selectedFrom) === 0 ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="to_facility_code">To facility</label>
            <select class="form-input" id="to_facility_code" name="to_facility_code" required>
              <?php foreach ($destinationFacilities as $facility):
                  $toCode = (string) $facility['FacilityCode'];
                  $isPharmacy = facility_is_pharmacy_location($facility);
                  $isCmo = facility_is_cmo_storage($facility);
                  ?>
              <option value="<?= htmlspecialchars($toCode) ?>" data-pharmacy="<?= $isPharmacy ? '1' : '0' ?>" data-cmo="<?= $isCmo ? '1' : '0' ?>"<?= strcasecmp($toCode, $selectedTo) === 0 ? ' selected' : '' ?>>
                <?= htmlspecialchars($toCode) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full is-hidden" id="cmo-supplier-field">
            <label for="supplier_id">CMO partner</label>
            <select class="form-input" id="supplier_id" name="supplier_id">
              <option value="">Select CMO supplier</option>
              <?php foreach ($cmoSuppliers as $supplier): ?>
              <option value="<?= (int) $supplier['SupplierID'] ?>"<?= (int) ($_POST['supplier_id'] ?? 0) === (int) $supplier['SupplierID'] ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) $supplier['SupplierName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group is-hidden" id="jazz-ship-by-field">
            <label for="jazz_ship_by_date">Jazz ship-by date</label>
            <input class="form-input" id="jazz_ship_by_date" name="jazz_ship_by_date" type="date" value="<?= htmlspecialchars($jazzShipBy) ?>" />
          </div>
          <div class="form-group form-grid-full is-hidden" id="jazz-batch-hint">
            <p class="form-hint">Jazz batch order: <strong id="jazz-batch-order"><?= htmlspecialchars($batchOrderPreview) ?></strong> (all lines on this form).</p>
          </div>
          <div class="form-group">
            <label for="reason_code_id">Reason</label>
            <select class="form-input" id="reason_code_id" name="reason_code_id">
              <option value="">—</option>
              <?php foreach ($reasons as $reason): ?>
              <option value="<?= (int) $reason['ReasonCodeID'] ?>"<?= $cmoReasonId !== null && (int) $reason['ReasonCodeID'] === $cmoReasonId ? ' data-cmo-reason="1"' : '' ?><?= (int) ($_POST['reason_code_id'] ?? 0) === (int) $reason['ReasonCodeID'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $reason['ReasonCode']) ?> — <?= htmlspecialchars((string) $reason['Description']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="2"><?= htmlspecialchars((string) ($_POST['notes'] ?? '')) ?></textarea>
          </div>
        </div>

        <div class="po-lines-section">
          <div class="po-lines-header">
            <h2>Line items</h2>
            <button type="button" class="btn-secondary btn-small" id="add-transfer-line">Add line</button>
          </div>
          <div class="admin-table-wrap">
            <table class="admin-table po-lines-table" id="transfer-lines-table">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Description</th>
                  <th>Quantity</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="transfer-lines-body">
                <?php foreach ($lines as $index => $line):
                    $selectedSku = trim((string) ($line['sku_code'] ?? $line['sku'] ?? ''));
                    $description = '';
                    if ($selectedSku !== '') {
                        foreach ($skuOptions as $opt) {
                            if (strcasecmp((string) $opt['SKUCode'], $selectedSku) === 0) {
                                $description = (string) $opt['ProductName'];
                                break;
                            }
                        }
                    }
                    ?>
                <tr class="transfer-line-row">
                  <td><?= $renderTransferSkuSelect($index, $selectedSku) ?></td>
                  <td><input class="form-input transfer-line-description" type="text" value="<?= htmlspecialchars($description) ?>" readonly tabindex="-1" aria-label="Product description" /></td>
                  <td><input class="form-input" type="number" min="0.0001" step="0.0001" name="lines[<?= (int) $index ?>][qty_requested]" value="<?= htmlspecialchars((string) ($line['qty_requested'] ?? '')) ?>" /></td>
                  <td><button type="button" class="btn-text btn-text-danger remove-transfer-line">Remove</button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php
        render_form_actions(capture_form_actions(static function (): void {
            ?>
            <button type="submit" class="btn-primary">Create shipment</button>
            <a class="btn-secondary" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/')) ?>">Cancel</a>
            <?php
        }));
        ?>
      </form>
    </div>
  </main>
  <script>
  (function () {
    var form = document.getElementById('transfer-form');
    var fromFacility = document.getElementById('from_facility_code');
    var toFacility = document.getElementById('to_facility_code');
    var cmoField = document.getElementById('cmo-supplier-field');
    var supplier = document.getElementById('supplier_id');
    var reason = document.getElementById('reason_code_id');
    var shipByField = document.getElementById('jazz-ship-by-field');
    var shipByInput = document.getElementById('jazz_ship_by_date');
    var batchHint = document.getElementById('jazz-batch-hint');
    var batchOrder = document.getElementById('jazz-batch-order');
    var body = document.getElementById('transfer-lines-body');
    var addBtn = document.getElementById('add-transfer-line');
    var skuSelectTemplate = <?= json_encode($renderTransferSkuSelect(0, '')) ?>;
    var cmoReasonId = <?= $cmoReasonId !== null ? (int) $cmoReasonId : 'null' ?>;
    var mothershipCode = <?= json_encode(facility_default_po_receipt_code()) ?>;

    var pharmacySlugs = <?= json_encode([
        'OCALA' => 'Ocala',
        'DALLAS' => 'Dallas',
        'MELBOURNE' => 'Melbourne',
    ]) ?>;

    function syncFacilityPair() {
      if (!fromFacility || !toFacility) {
        return;
      }

      if (toFacility.value === 'CMO') {
        fromFacility.value = mothershipCode;
      } else if (fromFacility.value === 'CMO') {
        toFacility.value = mothershipCode;
      }

      if (fromFacility.value === toFacility.value) {
        for (var i = 0; i < toFacility.options.length; i++) {
          if (toFacility.options[i].value !== fromFacility.value) {
            toFacility.value = toFacility.options[i].value;
            break;
          }
        }
      }

      syncCmoFields();
      syncPharmacyFields();
    }

    function selectedToIsPharmacy() {
      if (!toFacility) return false;
      var opt = toFacility.options[toFacility.selectedIndex];
      return opt && opt.getAttribute('data-pharmacy') === '1';
    }

    function syncCmoFields() {
      var isCmo = toFacility && toFacility.value === 'CMO';
      if (cmoField) {
        cmoField.classList.toggle('is-hidden', !isCmo);
      }
      if (supplier) {
        supplier.required = isCmo;
        if (!isCmo) {
          supplier.value = '';
        }
      }
      if (isCmo && reason && cmoReasonId) {
        reason.value = String(cmoReasonId);
      }
    }

    function syncPharmacyFields() {
      var isPharmacy = fromFacility && fromFacility.value === mothershipCode && selectedToIsPharmacy();
      if (shipByField) {
        shipByField.classList.toggle('is-hidden', !isPharmacy);
      }
      if (shipByInput) {
        shipByInput.required = isPharmacy;
      }
      if (batchHint) {
        batchHint.classList.toggle('is-hidden', !isPharmacy);
      }
      if (isPharmacy && batchOrder && shipByInput && toFacility) {
        var shipBy = shipByInput.value || '';
        var slug = pharmacySlugs[toFacility.value] || toFacility.value;
        var ymd = shipBy.replace(/-/g, '');
        batchOrder.textContent = ymd ? ('TRN-' + slug + '-' + ymd) : '(set ship-by date)';
      }
    }

    function reindexRows() {
      if (!body) return;
      body.querySelectorAll('.transfer-line-row').forEach(function (row, index) {
        row.querySelectorAll('input, select').forEach(function (input) {
          if (input.name) {
            input.name = input.name.replace(/lines\[\d+\]/, 'lines[' + index + ']');
          }
        });
      });
    }

    function syncDescriptionFromSku(select) {
      if (!select) return;
      var row = select.closest('tr');
      if (!row) return;
      var description = row.querySelector('.transfer-line-description');
      if (!description) return;
      var option = select.options[select.selectedIndex];
      description.value = option && option.getAttribute('data-product-name') ? option.getAttribute('data-product-name') : '';
    }

    function bindRemoveButtons() {
      if (!body) return;
      body.querySelectorAll('.remove-transfer-line').forEach(function (btn) {
        btn.onclick = function () {
          if (body.querySelectorAll('.transfer-line-row').length <= 1) return;
          btn.closest('tr').remove();
          reindexRows();
        };
      });
    }

    if (fromFacility) {
      fromFacility.addEventListener('change', syncFacilityPair);
    }
    if (toFacility) {
      toFacility.addEventListener('change', syncFacilityPair);
    }
    if (shipByInput) {
      shipByInput.addEventListener('change', syncPharmacyFields);
      shipByInput.addEventListener('input', syncPharmacyFields);
    }
    if (form) {
      form.addEventListener('submit', syncFacilityPair);
    }
    if (body) {
      body.addEventListener('change', function (event) {
        var select = event.target.closest('.transfer-line-sku');
        if (select) {
          syncDescriptionFromSku(select);
        }
      });
    }
    if (addBtn && body) {
      addBtn.addEventListener('click', function () {
        var index = body.querySelectorAll('.transfer-line-row').length;
        var row = document.createElement('tr');
        row.className = 'transfer-line-row';
        row.innerHTML =
          '<td>' + skuSelectTemplate.replace('lines[0][sku_code]', 'lines[' + index + '][sku_code]') + '</td>' +
          '<td><input class="form-input transfer-line-description" type="text" value="" readonly tabindex="-1" aria-label="Product description" /></td>' +
          '<td><input class="form-input" type="number" min="0.0001" step="0.0001" name="lines[' + index + '][qty_requested]" /></td>' +
          '<td><button type="button" class="btn-text btn-text-danger remove-transfer-line">Remove</button></td>';
        body.appendChild(row);
        bindRemoveButtons();
      });
    }

    bindRemoveButtons();
    body.querySelectorAll('.transfer-line-sku').forEach(syncDescriptionFromSku);
    syncFacilityPair();
  })();
  </script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>