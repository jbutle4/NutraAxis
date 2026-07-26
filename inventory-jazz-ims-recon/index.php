<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-jazz-ims-recon.php';

inventory_jazz_ims_recon_require_read();
inventory_ims_bind_page_environments();

$activeSlug = $activeSlug ?? 'inventory-jazz-ims-recon';
$hubBack = app_module_hub_back_link($activeSlug);
$mismatchesOnly = ($_GET['mismatches'] ?? '') === '1';
$jazzEnv = inventory_ledger_profile();

$result = inventory_jazz_ims_recon_build_rows($jazzEnv);
$rows = $result['rows'] ?? [];
if ($mismatchesOnly) {
    $rows = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['mismatch'])));
}

$listPath = inventory_ims_page_path('/inventory-jazz-ims-recon/');
$alignPath = inventory_ims_page_path('/inventory-jazz-ims-align/');

$pageTitle = 'Jazz vs IMS CART Reconciliation | Inventory Management';
$pageDescription = 'Compare Jazz mothership on-hand quantity with IMS CART ledger balances.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => 'Jazz vs IMS CART' . (data_profile_is_uat() ? ' (UAT)' : ''),
          'lead'       => 'Layer 2 mothership pair: Jazz on-hand (facility resolved to CART, e.g. FBF09) versus IMS CART OK + quarantine + on hold.',
          'permission' => permission_label(inventory_ledger_permission_value()),
      ]); ?>

      <?php if (!$result['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars((string) $result['error']) ?></div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong><?= (int) ($result['mismatch_count'] ?? 0) ?> mismatch<?= (int) ($result['mismatch_count'] ?? 0) === 1 ? '' : 'es' ?></strong>
          <p>
            Jazz <?= htmlspecialchars(strtoupper((string) ($result['jazz_env'] ?? $jazzEnv))) ?>
            · IMS ledger <?= htmlspecialchars(strtoupper(inventory_ledger_profile())) ?>
            · facilities <?= htmlspecialchars(($result['jazz_facility_codes'] ?? []) === [] ? '—' : implode(', ', $result['jazz_facility_codes'])) ?>
            · <?= count($result['rows'] ?? []) ?> SKU row<?= count($result['rows'] ?? []) === 1 ? '' : 's' ?> compared
            <?= $mismatchesOnly ? ' · mismatches only' : '' ?>
          </p>
        </div>
        <div>
          <a class="btn-secondary" href="<?= htmlspecialchars($alignPath) ?>">Align IMS CART</a>
          <?php if ($mismatchesOnly): ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($listPath) ?>">Show all</a>
          <?php else: ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($listPath . '?mismatches=1') ?>">Mismatches only</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Jazz facility</th>
              <th>Jazz on hand</th>
              <th>Jazz available</th>
              <th>IMS CART (OK+Q+H)</th>
              <th>IMS OK</th>
              <th>Delta (IMS − Jazz OH)</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="7">No rows to compare<?= $mismatchesOnly ? ' for the current filter' : '' ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr<?= !empty($row['mismatch']) ? ' class="is-warning"' : '' ?>>
              <td><?= htmlspecialchars((string) $row['sku']) ?></td>
              <td><?= htmlspecialchars((string) ($row['jazz_facility'] ?: '—')) ?></td>
              <td><?= $row['has_jazz'] ? htmlspecialchars(inventory_ledger_format_quantity($row['jazz_on_hand'])) : '—' ?></td>
              <td><?= $row['has_jazz'] ? htmlspecialchars(inventory_ledger_format_quantity($row['jazz_available'])) : '—' ?></td>
              <td><?= $row['has_ims'] ? htmlspecialchars(inventory_ledger_format_quantity($row['ims_qty'])) : '—' ?></td>
              <td><?= $row['has_ims'] ? htmlspecialchars(inventory_ledger_format_quantity($row['ims_qty_ok'])) : '—' ?></td>
              <td><?= $row['delta'] === null ? '—' : htmlspecialchars(inventory_ledger_format_quantity($row['delta'])) ?></td>
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
