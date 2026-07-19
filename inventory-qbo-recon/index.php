<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-qbo-recon.php';

inventory_qbo_recon_require_read();

$activeSlug = 'inventory-qbo-recon';
$hubBack = app_module_hub_back_link($activeSlug);
$mismatchesOnly = ($_GET['mismatches'] ?? '') === '1';
$result = inventory_qbo_recon_build_rows();
$rows = $result['rows'] ?? [];
$summary = $result['summary'] ?? [];
if ($mismatchesOnly) {
    $rows = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['mismatch'])));
}

$pageTitle = 'QBO Inventory Reconciliation | Inventory Management';
$pageDescription = 'Compare IMS company-wide quantity with QuickBooks Qty on hand.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => 'QBO Inventory Reconciliation',
          'lead'       => 'IMS company total (OK + quarantine + on hold) versus QuickBooks Inventory QtyOnHand.',
          'permission' => permission_label(inventory_ledger_permission_value()),
      ]); ?>

      <?php if (!$result['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $result['error']) ?></div>
      <?php else: ?>
      <div class="admin-notice is-detail" role="note">
        Large IMS − QBO deltas are expected after Jazz→IMS CART align: IMS may match Jazz mothership on-hand while QBO QtyOnHand starts at 0 and only moves with receipt / sale / adjustment posts. Do not bootstrap QBO quantity from Jazz.
      </div>

      <div class="status-banner">
        <div>
          <strong><?= (int) ($result['mismatch_count'] ?? 0) ?> mismatch<?= (int) ($result['mismatch_count'] ?? 0) === 1 ? '' : 'es' ?></strong>
          <p>
            <?= (int) ($summary['compared_count'] ?? count($result['rows'] ?? [])) ?> SKU row<?= (int) ($summary['compared_count'] ?? 0) === 1 ? '' : 's' ?>
            · matched <?= (int) ($summary['matched_count'] ?? 0) ?>
            · qty Δ <?= (int) ($summary['qty_mismatch'] ?? 0) ?>
            · missing QBO <?= (int) ($summary['missing_in_qbo'] ?? 0) ?>
            · missing IMS <?= (int) ($summary['missing_in_ims'] ?? 0) ?>
            <?= qbo_is_connected() ? '' : ' · QuickBooks is not connected (IMS-only view)' ?>
          </p>
          <p>
            IMS total <?= htmlspecialchars(inventory_ledger_format_quantity($summary['ims_total_qty'] ?? 0)) ?>
            · QBO total <?= htmlspecialchars(inventory_ledger_format_quantity($summary['qbo_total_qty'] ?? 0)) ?>
            <?php if (($summary['delta_total'] ?? null) !== null): ?>
            · Δ <?= htmlspecialchars(inventory_ledger_format_quantity($summary['delta_total'])) ?>
            <?php endif; ?>
          </p>
        </div>
        <div>
          <?php if ($mismatchesOnly): ?>
          <a class="btn-secondary" href="/inventory-qbo-recon/">Show all</a>
          <?php else: ?>
          <a class="btn-secondary" href="/inventory-qbo-recon/?mismatches=1">Mismatches only</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>QBO name</th>
              <th>IMS qty</th>
              <th>QBO qty</th>
              <th>Delta (IMS − QBO)</th>
              <th>QBO item Id</th>
              <th>Match</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="7">No rows to compare.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr<?= !empty($row['mismatch']) ? ' class="is-warning"' : '' ?>>
              <td><?= htmlspecialchars((string) $row['sku']) ?></td>
              <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
              <td><?= !empty($row['has_ims']) ? htmlspecialchars(inventory_ledger_format_quantity($row['ims_qty'])) : '—' ?></td>
              <td><?= !empty($row['has_qbo']) ? htmlspecialchars(inventory_ledger_format_quantity($row['qbo_qty'])) : '—' ?></td>
              <td><?= $row['delta'] === null ? '—' : htmlspecialchars(inventory_ledger_format_quantity($row['delta'])) ?></td>
              <td><?= htmlspecialchars((string) (($row['qbo_id'] ?? '') !== '' ? $row['qbo_id'] : '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['match_method'] ?? ($row['has_qbo'] ? '—' : 'none'))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
