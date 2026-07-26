<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/inventory-ledger.php';

inventory_ledger_require_read();

$activeSlug = $activeSlug ?? 'inventory-balances';
$module = get_module('inventory-balances');

$facilityFilter = trim($_GET['facility'] ?? '');
$skuFilter = trim($_GET['sku'] ?? '');
$listFilters = [
    'facility' => $facilityFilter !== '' ? $facilityFilter : null,
    'sku'      => $skuFilter !== '' ? $skuFilter : null,
] + table_sort_state(INVENTORY_LEDGER_LIST_SORT_COLUMNS, 'sku', 'asc', $_GET);

$facilities = inventory_ledger_list_facilities();
$rows = inventory_ledger_list_balances($listFilters);

$pageTitle = 'Inventory Balances | Inventory Management';
$pageDescription = 'Live IMS balances by SKU and facility from the NutraAxis inventory ledger.';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Inventory',
          'title'      => $module['headline'] ?? 'Inventory Balances',
          'lead'       => $module['lead'] ?? 'Operational stock on hand by SKU, facility, and status bucket from the IMS ledger.',
          'permission' => permission_label(inventory_ledger_permission_value()),
      ]); ?>

      <form class="admin-filter-bar" method="get" action="<?= htmlspecialchars(data_profile_page_path('/inventory-balances')) ?>">
        <div class="form-field">
          <label for="facility">Facility</label>
          <select class="form-input" id="facility" name="facility">
            <option value="">All facilities</option>
            <?php foreach ($facilities as $facility): ?>
            <option value="<?= htmlspecialchars((string) $facility['FacilityCode']) ?>"<?= $facilityFilter === (string) $facility['FacilityCode'] ? ' selected' : '' ?>>
              <?= htmlspecialchars((string) $facility['FacilityCode']) ?> — <?= htmlspecialchars((string) $facility['FacilityName']) ?><?= (int) ($facility['IsActive'] ?? 0) === 1 ? '' : ' (inactive)' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label for="sku">SKU</label>
          <input class="form-input" type="search" id="sku" name="sku" value="<?= htmlspecialchars($skuFilter) ?>" placeholder="Search SKU" />
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-secondary">Apply filters</button>
          <?php if ($facilityFilter !== '' || $skuFilter !== ''): ?>
          <a class="btn-link" href="<?= htmlspecialchars(data_profile_page_path('/inventory-balances')) ?>">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="status-banner">
        <div>
          <strong>IMS ledger loaded</strong>
          <p><?= count($rows) ?> balance row<?= count($rows) === 1 ? '' : 's' ?> · QBO valuation uses OK + Quarantine + On hold across all facilities (excludes Destroy)</p>
        </div>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                INVENTORY_LEDGER_LIST_SORT_COLUMNS,
                data_profile_page_path('/inventory-balances'),
                $listFilters,
                ['facility', 'sku'],
                INVENTORY_LEDGER_LIST_SORT_NUMERIC,
                'sku',
                'asc',
                '',
                'QBO qty'
            ); ?>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="10">No inventory balance rows found. Run the IMS bootstrap migration or post a receipt to populate balances.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['SKUCode'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['FacilityCode'] ?? '')) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyOK'] ?? null)) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyQuarantine'] ?? null)) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyOnHold'] ?? null)) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyDestroy'] ?? null)) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity($row['QtyReserved'] ?? null)) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity(inventory_ledger_qty_on_hand($row))) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity(inventory_ledger_qty_available($row))) ?></td>
              <td><?= htmlspecialchars(inventory_ledger_format_quantity(inventory_ledger_qbo_qty_for_sku((string) ($row['SKUCode'] ?? '')))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
