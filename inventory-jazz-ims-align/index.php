<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-jazz-ims-align.php';

inventory_jazz_ims_align_require_read();

$activeSlug = 'inventory-jazz-ims-align';
$hubBack = app_module_hub_back_link($activeSlug);
$jazzEnv = strtolower(trim((string) ($_GET['env'] ?? $_POST['env'] ?? 'production'))) === 'uat' ? 'uat' : 'production';
$zeroMissing = (($_GET['zero_missing'] ?? $_POST['zero_missing'] ?? '') === '1');
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$userId = auth_user()['UserID'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && inventory_jazz_ims_align_can_update()) {
    $action = (string) ($_POST['action'] ?? '');
    $confirm = trim((string) ($_POST['confirm_text'] ?? ''));
    $dryRun = $action !== 'apply';
    if ($action === 'apply' && strtoupper($confirm) !== 'ALIGN') {
        header(
            'Location: /inventory-jazz-ims-align/?env=' . rawurlencode($jazzEnv)
                . ($zeroMissing ? '&zero_missing=1' : '')
                . '&error=' . rawurlencode('Type ALIGN to confirm a live IMS CART update.'),
            true,
            302
        );
        exit;
    }

    $result = inventory_jazz_ims_align_run(
        $jazzEnv,
        $dryRun,
        $zeroMissing,
        $userId !== null ? (int) $userId : null
    );

    $query = [
        'env' => $jazzEnv,
        'notice' => $result['ok'] ? ($dryRun ? 'dry_run' : 'applied') : null,
        'error' => $result['ok'] ? null : ($result['error'] ?? 'Align failed.'),
        'run_id' => $result['align_run_id'] ?? null,
    ];
    if ($zeroMissing) {
        $query['zero_missing'] = '1';
    }
    header('Location: /inventory-jazz-ims-align/?' . http_build_query(array_filter($query, static fn($v) => $v !== null && $v !== '')), true, 302);
    exit;
}

$preview = inventory_jazz_ims_align_preview($jazzEnv, $zeroMissing);
$lines = $preview['ok'] ? ($preview['lines'] ?? []) : [];
$recent = inventory_jazz_ims_align_recent_runs(8);

$pageTitle = 'Jazz → IMS CART Align | Inventory Management';
$pageDescription = 'Preview and post JazzSyncReconcile deltas so IMS CART matches Jazz mothership on-hand.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => 'Jazz → IMS CART Align',
          'lead'       => 'Bring IMS CART OK+Q+H in line with Jazz on-hand. Posts JazzSyncReconcile to IMS only — does not change QuickBooks QtyOnHand.',
          'permission' => permission_label(inventory_ledger_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'dry_run'): ?>
      <div class="admin-notice is-success" role="status">Dry run recorded<?= isset($_GET['run_id']) ? ' (run #' . (int) $_GET['run_id'] . ')' : '' ?>.</div>
      <?php elseif ($notice === 'applied'): ?>
      <div class="admin-notice is-success" role="status">IMS CART align applied<?= isset($_GET['run_id']) ? ' (run #' . (int) $_GET['run_id'] . ')' : '' ?>. Re-check <a href="/inventory-jazz-ims-recon/?env=<?= htmlspecialchars($jazzEnv) ?>">Jazz vs IMS CART</a>.</div>
      <?php endif; ?>
      <?php if ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (!$preview['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $preview['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong><?= count($lines) ?> SKU delta<?= count($lines) === 1 ? '' : 's' ?></strong>
          <p>
            Jazz <?= htmlspecialchars(strtoupper($jazzEnv)) ?>
            · facilities <?= htmlspecialchars(($preview['jazz_facility_codes'] ?? []) === [] ? '—' : implode(', ', $preview['jazz_facility_codes'])) ?>
            · IMS-only align (QBO untouched)
          </p>
        </div>
        <div>
          <a class="btn-secondary" href="/inventory-jazz-ims-recon/?env=<?= htmlspecialchars($jazzEnv) ?>">Open recon</a>
          <?php if ($jazzEnv === 'uat'): ?>
          <a class="btn-secondary" href="/inventory-jazz-ims-align/?env=production<?= $zeroMissing ? '&zero_missing=1' : '' ?>">Jazz Production</a>
          <?php else: ?>
          <a class="btn-secondary" href="/inventory-jazz-ims-align/?env=uat<?= $zeroMissing ? '&zero_missing=1' : '' ?>">Jazz UAT</a>
          <?php endif; ?>
        </div>
      </div>

      <form method="get" class="admin-filter-bar" action="/inventory-jazz-ims-align/" style="margin-bottom:1rem;">
        <input type="hidden" name="env" value="<?= htmlspecialchars($jazzEnv) ?>">
        <label class="form-field" style="flex-direction:row;align-items:center;gap:0.5rem;">
          <input type="checkbox" name="zero_missing" value="1"<?= $zeroMissing ? ' checked' : '' ?> onchange="this.form.submit()">
          Also zero IMS CART for SKUs missing from Jazz
        </label>
      </form>

      <?php if (inventory_jazz_ims_align_can_update()): ?>
      <div class="form-actions" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
        <form method="post">
          <input type="hidden" name="env" value="<?= htmlspecialchars($jazzEnv) ?>">
          <input type="hidden" name="zero_missing" value="<?= $zeroMissing ? '1' : '0' ?>">
          <input type="hidden" name="action" value="dry_run">
          <button type="submit" class="btn-secondary">Record dry run</button>
        </form>
        <form method="post" onsubmit="return confirm('Post JazzSyncReconcile to IMS CART for <?= count($lines) ?> SKU(s)? QuickBooks will not change.');" style="display:flex;gap:0.5rem;align-items:end;flex-wrap:wrap;">
          <input type="hidden" name="env" value="<?= htmlspecialchars($jazzEnv) ?>">
          <input type="hidden" name="zero_missing" value="<?= $zeroMissing ? '1' : '0' ?>">
          <input type="hidden" name="action" value="apply">
          <div class="form-field">
            <label for="confirm_text">Type ALIGN to apply</label>
            <input class="form-input" id="confirm_text" name="confirm_text" autocomplete="off" required>
          </div>
          <button type="submit" class="btn-primary"<?= $lines === [] ? ' disabled' : '' ?>>Apply to IMS CART</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($recent !== []): ?>
      <div class="admin-table-wrap" style="margin-bottom:1.5rem;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Run</th>
              <th>Started</th>
              <th>Env</th>
              <th>Status</th>
              <th>Candidates</th>
              <th>Posted</th>
              <th>Txn</th>
              <th>Summary</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $run): ?>
            <tr>
              <td>#<?= (int) $run['AlignRunID'] ?></td>
              <td><?= htmlspecialchars((string) ($run['StartedAt'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($run['JazzEnvironment'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($run['Status'] ?? '')) ?></td>
              <td><?= (int) ($run['CandidateCount'] ?? 0) ?></td>
              <td><?= (int) ($run['PostedCount'] ?? 0) ?></td>
              <td><?= $run['TransactionID'] ? (int) $run['TransactionID'] : '—' ?></td>
              <td><?= htmlspecialchars((string) ($run['SummaryMessage'] ?? $run['ErrorMessage'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Jazz facility</th>
              <th>Jazz on hand</th>
              <th>Align target</th>
              <th>IMS CART</th>
              <th>Qty change (→ OK)</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($lines === []): ?>
            <tr><td colspan="6">IMS CART already matches Jazz on-hand for SKU Master items<?= $zeroMissing ? '' : ' present in Jazz' ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($lines as $line): ?>
            <tr class="is-warning">
              <td><?= htmlspecialchars((string) $line['sku_code']) ?></td>
              <td><?= htmlspecialchars((string) ($line['jazz_facility'] ?: '—')) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($line['jazz_on_hand'])) ?><?= !empty($line['clamped']) ? ' *' : '' ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($line['jazz_target'] ?? $line['jazz_on_hand'])) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($line['ims_qty'])) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($line['qty_change'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        <p style="margin-top:0.75rem;opacity:0.8;">* Jazz on-hand below zero is clamped to align target 0 (IMS cannot store negative OK qty).</p>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
