<?php
/** @var array $order */
/** @var array $lines */
/** @var array $productionByLine keyed by POLineID */
/** @var string|null $error */
$productionByLine = $productionByLine ?? [];
$error = $error ?? null;
$formActions = capture_form_actions(function () use ($order) {
    ?>
    <button type="submit" class="btn-primary">Save production status</button>
    <a class="btn-secondary" href="/po-management/view.php?id=<?= (int) $order['POID'] ?>">Cancel</a>
    <?php
});
?>
      <div class="account-card production-status-card">
        <h2>Production status</h2>
        <p class="account-card-lead">Update manufacturing, packaging, testing, and shipping progress for each PO line.</p>

        <?php if ($error !== null): ?>
        <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="admin-form" method="post" action="/po-management/production-status.php">
          <input type="hidden" name="po_id" value="<?= (int) $order['POID'] ?>" />
          <?php render_form_actions($formActions, 'top'); ?>

          <?php foreach ($lines as $line): ?>
          <?php
            $poLineId = (int) $line['POLineID'];
            $production = po_production_for_line($poLineId, $productionByLine[$poLineId] ?? null);
          ?>
          <div class="production-line-block">
            <div class="production-line-header">
              <strong>Line <?= (int) $line['LineNumber'] ?></strong>
              <span><?= htmlspecialchars($line['ItemDescription']) ?></span>
              <?php if (!empty($line['QuoteNumber']) || !empty($line['ItemSKU'])): ?>
              <span class="text-muted">· <?= htmlspecialchars($line['QuoteNumber'] ?? $line['ItemSKU']) ?></span>
              <?php endif; ?>
            </div>

            <div class="form-grid production-line-grid">
              <div class="form-group">
                <label for="mfg_<?= $poLineId ?>">MFG status</label>
                <select class="form-input" id="mfg_<?= $poLineId ?>" name="production[<?= $poLineId ?>][mfg_status]">
                  <?php foreach (PO_MFG_STATUSES as $status): ?>
                  <option value="<?= htmlspecialchars($status) ?>" <?= $production['MfgStatus'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="bottle_<?= $poLineId ?>">Bottle / packaging</label>
                <select class="form-input" id="bottle_<?= $poLineId ?>" name="production[<?= $poLineId ?>][bottle_packaging_status]">
                  <?php foreach (PO_BOTTLE_PACKAGING_STATUSES as $status): ?>
                  <option value="<?= htmlspecialchars($status) ?>" <?= $production['BottlePackagingStatus'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="bulk_<?= $poLineId ?>">Bulk test</label>
                <select class="form-input" id="bulk_<?= $poLineId ?>" name="production[<?= $poLineId ?>][bulk_test_status]">
                  <?php foreach (PO_BULK_TEST_STATUSES as $status): ?>
                  <option value="<?= htmlspecialchars($status) ?>" <?= $production['BulkTestStatus'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="bottle_test_<?= $poLineId ?>">Bottle test</label>
                <select class="form-input" id="bottle_test_<?= $poLineId ?>" name="production[<?= $poLineId ?>][bottle_test_status]">
                  <?php foreach (PO_BOTTLE_TEST_STATUSES as $status): ?>
                  <option value="<?= htmlspecialchars($status) ?>" <?= $production['BottleTestStatus'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="target_ship_<?= $poLineId ?>">Target ship date</label>
                <input class="form-input" type="date" id="target_ship_<?= $poLineId ?>" name="production[<?= $poLineId ?>][target_ship_date]" value="<?= htmlspecialchars(po_format_date_input($production['TargetShipDate'])) ?>" />
              </div>
              <div class="form-group">
                <label for="actual_ship_<?= $poLineId ?>">Actual ship date</label>
                <input class="form-input" type="date" id="actual_ship_<?= $poLineId ?>" name="production[<?= $poLineId ?>][actual_ship_date]" value="<?= htmlspecialchars(po_format_date_input($production['ActualShipDate'])) ?>" />
              </div>
              <div class="form-group">
                <label for="pallets_<?= $poLineId ?>">Pallet count</label>
                <input class="form-input" type="number" min="0" step="1" id="pallets_<?= $poLineId ?>" name="production[<?= $poLineId ?>][pallet_count]" value="<?= $production['PalletCount'] !== null ? (int) $production['PalletCount'] : '' ?>" />
              </div>
              <div class="form-group">
                <label for="weight_<?= $poLineId ?>">Est. weight (lbs)</label>
                <input class="form-input" type="number" min="0" step="0.01" id="weight_<?= $poLineId ?>" name="production[<?= $poLineId ?>][est_weight_lbs]" value="<?= $production['EstWeightLbs'] !== null ? htmlspecialchars((string) $production['EstWeightLbs']) : '' ?>" />
              </div>
              <div class="form-group form-grid-full">
                <label for="comments_<?= $poLineId ?>">Comments</label>
                <textarea class="form-input" id="comments_<?= $poLineId ?>" name="production[<?= $poLineId ?>][comments]" rows="3"><?= htmlspecialchars($production['Comments'] ?? '') ?></textarea>
              </div>
            </div>

            <?php if ($production['LastUpdatedDate'] !== null): ?>
            <p class="production-meta">
              Last updated <?= htmlspecialchars(admin_format_datetime($production['LastUpdatedDate'])) ?>
              <?php if (!empty($production['LastUpdatedByName'])): ?>
              by <?= htmlspecialchars($production['LastUpdatedByName']) ?>
              <?php endif; ?>
            </p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <?php render_form_actions($formActions, 'bottom'); ?>
        </form>
      </div>
