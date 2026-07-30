<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';
require dirname(__DIR__) . '/includes/po-rework.php';

inventory_transfers_require_update();
inventory_ims_bind_page_environments();

$activeSlug = $activeSlug ?? 'inventory-transfers';
$facilities = inventory_transfers_list_facilities();
$reasons = inventory_transfers_reason_codes();
$cmoSuppliers = po_rework_list_cmo_suppliers();
$cmoReasonId = po_rework_reason_code_id();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = facility_insert_transfer([
            'from_facility_code' => $_POST['from_facility_code'] ?? '',
            'to_facility_code'   => $_POST['to_facility_code'] ?? '',
            'sku_code'           => $_POST['sku_code'] ?? '',
            'qty_requested'      => $_POST['qty_requested'] ?? 0,
            'from_status_bucket' => $_POST['from_status_bucket'] ?? 'OK',
            'to_status_bucket'   => $_POST['to_status_bucket'] ?? 'OK',
            'reason_code_id'     => $_POST['reason_code_id'] ?? null,
            'supplier_id'        => $_POST['supplier_id'] ?? null,
            'notes'              => $_POST['notes'] ?? '',
        ]);
        if ($result['ok']) {
            header('Location: ' . inventory_ims_page_path('/inventory-transfers/view.php?id=' . (int) $result['transfer_id'] . '&notice=created'), true, 302);
            exit;
        }
        $error = $result['error'] ?? 'Unable to create transfer.';
    } catch (Throwable) {
        $error = 'Unable to create transfer. Please try again or contact support.';
    }
}

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
          'lead'       => 'Spoke replenishment from Cart.com, or send company-owned stock to CMO storage for rework.',
      ]); ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form" action="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/new.php')) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label for="from_facility_code">From facility</label>
            <select class="form-input" id="from_facility_code" name="from_facility_code" required>
              <?php foreach ($facilities as $facility): ?>
              <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>"<?= (string) ($facility['FacilityCode'] ?? '') === 'CART' ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="to_facility_code">To facility</label>
            <select class="form-input" id="to_facility_code" name="to_facility_code" required>
              <?php foreach ($facilities as $facility): ?>
              <?php if ((string) ($facility['FacilityCode'] ?? '') === 'CART') { continue; } ?>
              <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>"<?= (string) ($_POST['to_facility_code'] ?? '') === (string) ($facility['FacilityCode'] ?? '') ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
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
          <div class="form-group">
            <label for="sku_code">SKU</label>
            <input class="form-input" id="sku_code" name="sku_code" required maxlength="100" value="<?= htmlspecialchars((string) ($_POST['sku_code'] ?? '')) ?>" />
          </div>
          <div class="form-group">
            <label for="qty_requested">Quantity</label>
            <input class="form-input" id="qty_requested" name="qty_requested" type="number" min="0.0001" step="0.0001" required value="<?= htmlspecialchars((string) ($_POST['qty_requested'] ?? '')) ?>" />
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
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($_POST['notes'] ?? '')) ?></textarea>
          </div>
        </div>
        <?php
        render_form_actions(capture_form_actions(static function (): void {
            ?>
            <button type="submit" class="btn-primary">Create transfer</button>
            <a class="btn-secondary" href="<?= htmlspecialchars(inventory_ims_page_path('/inventory-transfers/')) ?>">Cancel</a>
            <?php
        }));
        ?>
      </form>
    </div>
  </main>
  <script>
  (function () {
    var toFacility = document.getElementById('to_facility_code');
    var cmoField = document.getElementById('cmo-supplier-field');
    var supplier = document.getElementById('supplier_id');
    var reason = document.getElementById('reason_code_id');
    var cmoReasonId = <?= $cmoReasonId !== null ? (int) $cmoReasonId : 'null' ?>;

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

    if (toFacility) {
      toFacility.addEventListener('change', syncCmoFields);
      syncCmoFields();
    }
  })();
  </script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
