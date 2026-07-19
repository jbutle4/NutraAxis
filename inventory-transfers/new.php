<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-transfers.php';
require dirname(__DIR__) . '/includes/catalog.php';

inventory_transfers_require_update();

$activeSlug = 'inventory-transfers';
$facilities = inventory_transfers_list_facilities();
$reasons = inventory_transfers_reason_codes();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = facility_insert_transfer([
        'from_facility_code' => $_POST['from_facility_code'] ?? '',
        'to_facility_code'   => $_POST['to_facility_code'] ?? '',
        'sku_code'           => $_POST['sku_code'] ?? '',
        'qty_requested'      => $_POST['qty_requested'] ?? 0,
        'from_status_bucket' => $_POST['from_status_bucket'] ?? 'OK',
        'to_status_bucket'   => $_POST['to_status_bucket'] ?? 'OK',
        'reason_code_id'     => $_POST['reason_code_id'] ?? null,
        'notes'              => $_POST['notes'] ?? '',
    ]);
    if ($result['ok']) {
        header('Location: /inventory-transfers/view.php?id=' . (int) $result['transfer_id'] . '&notice=created', true, 302);
        exit;
    }
    $error = $result['error'] ?? 'Unable to create transfer.';
}

$pageTitle = 'New Facility Transfer | Inventory Management';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/inventory-transfers/',
          'back_label' => 'Back to Transfers',
          'category'   => 'Inventory',
          'title'      => 'New Facility Transfer',
          'lead'       => 'Spoke replenishment must originate at Cart.com.',
      ]); ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form" action="/inventory-transfers/new.php">
        <div class="form-grid">
          <div class="form-field">
            <label for="from_facility_code">From facility</label>
            <select class="form-input" id="from_facility_code" name="from_facility_code" required>
              <?php foreach ($facilities as $facility): ?>
              <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>"<?= (string) ($facility['FacilityCode'] ?? '') === 'CART' ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="to_facility_code">To facility</label>
            <select class="form-input" id="to_facility_code" name="to_facility_code" required>
              <?php foreach ($facilities as $facility): ?>
              <?php if ((string) ($facility['FacilityCode'] ?? '') === 'CART') { continue; } ?>
              <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>">
                <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="sku_code">SKU</label>
            <input class="form-input" id="sku_code" name="sku_code" required maxlength="100" value="<?= htmlspecialchars((string) ($_POST['sku_code'] ?? '')) ?>" />
          </div>
          <div class="form-field">
            <label for="qty_requested">Quantity</label>
            <input class="form-input" id="qty_requested" name="qty_requested" type="number" min="0.0001" step="0.0001" required value="<?= htmlspecialchars((string) ($_POST['qty_requested'] ?? '')) ?>" />
          </div>
          <div class="form-field">
            <label for="reason_code_id">Reason</label>
            <select class="form-input" id="reason_code_id" name="reason_code_id">
              <option value="">—</option>
              <?php foreach ($reasons as $reason): ?>
              <option value="<?= (int) $reason['ReasonCodeID'] ?>"><?= htmlspecialchars((string) $reason['ReasonCode']) ?> — <?= htmlspecialchars((string) $reason['Description']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field form-field--full">
            <label for="notes">Notes</label>
            <textarea class="form-input" id="notes" name="notes" rows="3"><?= htmlspecialchars((string) ($_POST['notes'] ?? '')) ?></textarea>
          </div>
        </div>
        <?php
        render_form_actions(capture_form_actions(static function (): void {
            ?>
            <button type="submit" class="btn-primary">Create transfer</button>
            <a class="btn-secondary" href="/inventory-transfers/">Cancel</a>
            <?php
        }));
        ?>
      </form>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
