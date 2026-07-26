<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-adjustments.php';

inventory_adjustments_require_update();

$activeSlug = 'inventory-adjustments';
$facilities = inventory_adjustments_list_facilities();
$reasons = inventory_adjustments_reason_codes();
$error = null;

$defaultDirection = '-';
$postedReasonId = (int) ($_POST['reason_code_id'] ?? 0);
if ($postedReasonId > 0) {
    foreach ($reasons as $reason) {
        if ((int) $reason['ReasonCodeID'] === $postedReasonId && !empty($reason['DefaultDirection'])) {
            $defaultDirection = (string) $reason['DefaultDirection'];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = inventory_adjustments_create([
        'sku_code'       => $_POST['sku_code'] ?? '',
        'facility_code'  => $_POST['facility_code'] ?? '',
        'status_bucket'  => $_POST['status_bucket'] ?? 'OK',
        'reason_code_id' => $_POST['reason_code_id'] ?? 0,
        'direction'      => $_POST['direction'] ?? '-',
        'qty'            => $_POST['qty'] ?? 0,
        'notes'          => $_POST['notes'] ?? '',
    ]);
    if ($result['ok']) {
        header('Location: /inventory-adjustments/view.php?id=' . (int) $result['adjustment_id'] . '&notice=created', true, 302);
        exit;
    }
    $error = $result['error'] ?? 'Unable to create adjustment.';
    $defaultDirection = (string) ($_POST['direction'] ?? $defaultDirection);
}

$pageTitle = 'New Inventory Adjustment | Inventory Management';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/inventory-adjustments/',
          'back_label' => 'Back to Adjustments',
          'category'   => 'Inventory',
          'title'      => 'New Inventory Adjustment',
          'lead'       => 'Create a Pending shrink (−) or gain (+) request. Approve on the detail page to post IMS + QBO.',
      ]); ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="admin-form" action="/inventory-adjustments/new.php">
        <div class="form-grid">
          <div class="form-field">
            <label for="sku_code">SKU</label>
            <input class="form-input" id="sku_code" name="sku_code" required maxlength="100" value="<?= htmlspecialchars((string) ($_POST['sku_code'] ?? '')) ?>" />
          </div>
          <div class="form-field">
            <label for="facility_code">Facility</label>
            <select class="form-input" id="facility_code" name="facility_code" required>
              <?php foreach ($facilities as $facility): ?>
              <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>"<?= (string) ($facility['FacilityCode'] ?? '') === (string) ($_POST['facility_code'] ?? 'CART') ? ' selected' : '' ?>>
                <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="status_bucket">Status bucket</label>
            <select class="form-input" id="status_bucket" name="status_bucket" required>
              <?php foreach (INV_STATUS_BUCKETS as $bucket): ?>
              <option value="<?= $bucket ?>"<?= (string) ($_POST['status_bucket'] ?? 'OK') === $bucket ? ' selected' : '' ?>><?= $bucket ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label for="direction">Direction</label>
            <select class="form-input" id="direction" name="direction" required>
              <option value="-"<?= $defaultDirection === '-' ? ' selected' : '' ?>>Shrink (−)</option>
              <option value="+"<?= $defaultDirection === '+' ? ' selected' : '' ?>>Gain (+)</option>
            </select>
          </div>
          <div class="form-field">
            <label for="qty">Quantity</label>
            <input class="form-input" id="qty" name="qty" type="number" min="0.0001" step="0.0001" required value="<?= htmlspecialchars((string) ($_POST['qty'] ?? '1')) ?>" />
          </div>
          <div class="form-field">
            <label for="reason_code_id">Reason</label>
            <select class="form-input" id="reason_code_id" name="reason_code_id" required>
              <option value="">—</option>
              <?php foreach ($reasons as $reason): ?>
              <option
                value="<?= (int) $reason['ReasonCodeID'] ?>"
                data-direction="<?= htmlspecialchars((string) ($reason['DefaultDirection'] ?? '')) ?>"
                <?= (int) ($_POST['reason_code_id'] ?? 0) === (int) $reason['ReasonCodeID'] ? ' selected' : '' ?>
              >
                <?= htmlspecialchars((string) $reason['ReasonCode']) ?> — <?= htmlspecialchars((string) $reason['Description']) ?>
              </option>
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
            <button type="submit" class="btn-primary">Create pending adjustment</button>
            <a class="btn-secondary" href="/inventory-adjustments/">Cancel</a>
            <?php
        }));
        ?>
      </form>
    </div>
  </main>
  <script>
    (function () {
      var reason = document.getElementById('reason_code_id');
      var direction = document.getElementById('direction');
      if (!reason || !direction) return;
      reason.addEventListener('change', function () {
        var opt = reason.options[reason.selectedIndex];
        var dir = opt && opt.getAttribute('data-direction');
        if (dir === '+' || dir === '-') {
          direction.value = dir;
        }
      });
    })();
  </script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
