<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-reconciliation.php';

inventory_reconciliation_require_read();

$activeSlug = $activeSlug ?? 'inventory-reconciliation';

$jazzConfigError = jazz_oms_config_error();
$accsConfigError = adobe_commerce_config_error();

$jazzResult = $jazzConfigError === null
    ? jazz_oms_list_inventory()
    : ['ok' => true, 'error' => null, 'rows' => []];

$accsResult = $accsConfigError === null
    ? adobe_commerce_list_inventory()
    : ['ok' => true, 'error' => null, 'rows' => [], 'total' => 0];

$apiErrors = [];
if ($jazzConfigError !== null) {
    $apiErrors[] = 'Jazz OMS: ' . $jazzConfigError;
} elseif (!$jazzResult['ok']) {
    $apiErrors[] = 'Jazz OMS: ' . (string) $jazzResult['error'];
}

if ($accsConfigError !== null) {
    $apiErrors[] = 'Adobe Commerce: ' . $accsConfigError;
} elseif (!$accsResult['ok']) {
    $apiErrors[] = 'Adobe Commerce: ' . (string) $accsResult['error'];
}

$canRenderTable = $apiErrors === [];
$rows = $canRenderTable
    ? inventory_reconciliation_build_rows($jazzResult['rows'] ?? [], $accsResult['rows'] ?? [])
    : [];
$mismatchCount = $canRenderTable ? inventory_reconciliation_count_mismatches($rows) : 0;

$pageTitle = 'Inventory Reconciliation (Jazz-ACCS) | Inventory Management';
$pageDescription = 'Compare Jazz OMS and Adobe Commerce inventory levels by SKU.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/inventory-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Inventory Management
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Inventory</div>
          <h1>Inventory Reconciliation (Jazz-ACCS)</h1>
          <p class="page-lead">Side-by-side Jazz OMS and Adobe Commerce quantities for the same SKU to spot reporting differences.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(inventory_reconciliation_permission_value())) ?></p>
        </div>
      </div>

      <?php foreach ($apiErrors as $apiError): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($apiError) ?></div>
      <?php endforeach; ?>

      <?php if ($canRenderTable): ?>
      <div class="status-banner">
        <div>
          <strong>Reconciliation loaded</strong>
          <p>
            <?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?>
            · <?= count($jazzResult['rows'] ?? []) ?> Jazz record<?= count($jazzResult['rows'] ?? []) === 1 ? '' : 's' ?>
            · <?= count($accsResult['rows'] ?? []) ?> ACCS record<?= count($accsResult['rows'] ?? []) === 1 ? '' : 's' ?>
            · <?= $mismatchCount ?> mismatch<?= $mismatchCount === 1 ? '' : 'es' ?> (Jazz available vs ACCS quantity)
          </p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table inventory-reconciliation-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Facility</th>
              <th>Available</th>
              <th>On Hand</th>
              <th>Ordered</th>
              <th>Total</th>
              <th>ACCS Qty</th>
              <th>ACCS Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="8">No inventory records returned from Jazz OMS or Adobe Commerce.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php $isMismatch = inventory_reconciliation_row_has_mismatch($row); ?>
            <tr class="<?= $isMismatch ? 'is-inventory-mismatch' : '' ?>">
              <td><?= htmlspecialchars((string) $row['sku']) ?></td>
              <td><?= htmlspecialchars((string) $row['facility']) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['available'])) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['on_hand'])) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['ordered'])) ?></td>
              <td><?= htmlspecialchars(jazz_oms_format_quantity($row['total'])) ?></td>
              <td><?= htmlspecialchars($row['has_accs'] ? adobe_commerce_format_quantity($row['accs_qty']) : '—') ?></td>
              <td>
                <?php if ($row['has_accs']): ?>
                <span class="status-badge <?= adobe_commerce_source_item_status_class($row['accs_status'] ?? 0) ?>"><?= htmlspecialchars(adobe_commerce_source_item_status_label($row['accs_status'] ?? 0)) ?></span>
                <?php else: ?>
                —
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
