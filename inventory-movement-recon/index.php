<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-movement-recon.php';

inventory_movement_recon_require_read();

$activeSlug = 'inventory-movement-recon';
$hubBack = app_module_hub_back_link($activeSlug);
$movement = trim((string) ($_GET['movement'] ?? ''));
$severity = trim((string) ($_GET['severity'] ?? ''));
$runId = (int) ($_GET['run_id'] ?? 0);

$result = inventory_movement_recon_latest([
    'movement' => $movement,
    'severity' => $severity,
    'run_id' => $runId,
]);
$run = $result['run'] ?? null;
$lines = $result['lines'] ?? [];
$recentRuns = inventory_movement_recon_recent_runs(8);

$pageTitle = 'Inventory Movement Completeness | Inventory Management';
$pageDescription = 'Exceptions where receipts, sales, transfers, or adjustments are missing IMS or QBO posts.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => 'Inventory Movement Completeness',
          'lead'       => 'Layer 1 recon: find movements that should have posted to IMS and/or QBO but did not. Run via Process Log → Inventory Movement Completeness Recon.',
          'permission' => permission_label(inventory_ledger_permission_value()),
      ]); ?>

      <?php if (!$result['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $result['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <?php if ($run === null): ?>
          <strong>No recon runs yet</strong>
          <p>Use Process Log to run <em>Inventory Movement Completeness Recon</em>, then refresh this page.</p>
          <?php else: ?>
          <strong>Run #<?= (int) $run['ReconRunID'] ?> · <?= htmlspecialchars((string) $run['Status']) ?> · <?= (int) ($run['TotalExceptions'] ?? 0) ?> exception<?= (int) ($run['TotalExceptions'] ?? 0) === 1 ? '' : 's' ?></strong>
          <p>
            <?= htmlspecialchars((string) ($run['SummaryMessage'] ?? '')) ?>
            · Lookback <?= (int) ($run['LookbackDays'] ?? 0) ?>d
            · <?= htmlspecialchars((string) ($run['TriggerType'] ?? '')) ?>
            · Started <?= htmlspecialchars((string) ($run['StartedAt'] ?? '')) ?>
          </p>
          <?php endif; ?>
        </div>
        <div>
          <a class="btn-secondary" href="/process-log/">Process Log</a>
          <?php if ($run !== null): ?>
          <a class="btn-secondary" href="/inventory-movement-recon/?run_id=<?= (int) $run['ReconRunID'] ?>">Clear filters</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($recentRuns !== []): ?>
      <div class="admin-table-wrap" style="margin-bottom:1.5rem;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Run</th>
              <th>Started</th>
              <th>Status</th>
              <th>Receipt</th>
              <th>Sale</th>
              <th>Transfer</th>
              <th>Adj</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentRuns as $r): ?>
            <tr<?= $run && (int) $r['ReconRunID'] === (int) $run['ReconRunID'] ? ' class="is-warning"' : '' ?>>
              <td><a href="/inventory-movement-recon/?run_id=<?= (int) $r['ReconRunID'] ?>">#<?= (int) $r['ReconRunID'] ?></a></td>
              <td><?= htmlspecialchars((string) ($r['StartedAt'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($r['Status'] ?? '')) ?></td>
              <td><?= (int) ($r['ReceiptExceptions'] ?? 0) ?></td>
              <td><?= (int) ($r['SaleExceptions'] ?? 0) ?></td>
              <td><?= (int) ($r['TransferExceptions'] ?? 0) ?></td>
              <td><?= (int) ($r['AdjustmentExceptions'] ?? 0) ?></td>
              <td><?= (int) ($r['TotalExceptions'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($run !== null): ?>
      <form method="get" class="filter-bar" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
        <input type="hidden" name="run_id" value="<?= (int) $run['ReconRunID'] ?>">
        <label>
          Movement
          <select name="movement">
            <option value="">All</option>
            <?php foreach (['Receipt', 'Sale', 'Transfer', 'Adjustment'] as $opt): ?>
            <option value="<?= $opt ?>"<?= $movement === $opt ? ' selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Severity
          <select name="severity">
            <option value="">All</option>
            <?php foreach (['Action', 'Warning', 'Info'] as $opt): ?>
            <option value="<?= $opt ?>"<?= $severity === $opt ? ' selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn-secondary">Filter</button>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Severity</th>
              <th>Type</th>
              <th>Action</th>
              <th>SKU</th>
              <th>Facility</th>
              <th>Qty</th>
              <th>Source</th>
              <th>IMS</th>
              <th>QBO</th>
              <th>Recommended action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($lines === []): ?>
            <tr><td colspan="10">No exceptions for this run<?= ($movement !== '' || $severity !== '') ? ' / filter' : '' ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($lines as $line): ?>
            <tr<?= ($line['Severity'] ?? '') === 'Action' ? ' class="is-warning"' : '' ?>>
              <td><?= htmlspecialchars((string) ($line['Severity'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($line['MovementType'] ?? '')) ?></td>
              <td title="<?= htmlspecialchars((string) ($line['DetailMessage'] ?? '')) ?>"><?= htmlspecialchars((string) ($line['ActionCode'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($line['SKUCode'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($line['FacilityCode'] ?? '—')) ?></td>
              <td><?= $line['Qty'] === null ? '—' : htmlspecialchars(inventory_ledger_format_quantity($line['Qty'])) ?></td>
              <td><?= htmlspecialchars((string) ($line['SourceStatus'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($line['ImsStatus'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($line['QboStatus'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($line['RecommendedAction'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
