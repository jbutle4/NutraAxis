<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'inventory';
$search = trim($_GET['q'] ?? '');
$listResult = qbo_is_connected()
    ? qbo_list_product_items()
    : ['ok' => true, 'rows' => [], 'error' => null];
$skuLinks = accounting_skumaster_qbo_links();
$qboSortColumns = [
    'name'           => 'Name',
    'sku'            => 'SKU',
    'qbo_id'         => 'QBO item ID',
    'purchase_cost'  => 'Purchase cost',
    'unit_price'     => 'Unit price',
    'qty_on_hand'    => 'Qty on hand',
    'taxable'        => 'Taxable',
    'active'         => 'Active',
    'nutraaxis'      => 'NutraAxis SKU',
];
$listFilters = table_sort_state($qboSortColumns, 'name', 'asc', $_GET);
$qboSortAccessors = [
    'name'          => fn(array $row): string => (string) ($row['Name'] ?? ''),
    'sku'           => fn(array $row): string => (string) ($row['Sku'] ?? ''),
    'qbo_id'        => fn(array $row): string => (string) ($row['Id'] ?? ''),
    'purchase_cost' => fn(array $row) => $row['PurchaseCost'] ?? 0,
    'unit_price'    => fn(array $row) => $row['UnitPrice'] ?? 0,
    'qty_on_hand'   => fn(array $row) => $row['QtyOnHand'] ?? 0,
    'taxable'       => fn(array $row): string => !empty($row['Taxable']) ? 'Yes' : 'No',
    'active'        => fn(array $row): string => !empty($row['Active']) ? 'Yes' : 'No',
    'nutraaxis'     => function (array $row) use ($skuLinks): string {
        $match = accounting_match_skumaster_for_qbo_item($row, $skuLinks);

        return $match !== null ? (string) ($match['SKUCode'] ?? '') : '';
    },
];

$rows = $listResult['rows'] ?? [];
if ($search !== '') {
    $needle = strtolower($search);
    $rows = array_values(array_filter($rows, static function (array $row) use ($needle, $skuLinks): bool {
        $fields = [
            (string) ($row['Name'] ?? ''),
            (string) ($row['Sku'] ?? ''),
            (string) ($row['Id'] ?? ''),
            (string) ($row['Description'] ?? ''),
        ];
        $match = accounting_match_skumaster_for_qbo_item($row, $skuLinks);
        if ($match !== null) {
            $fields[] = (string) ($match['SKUCode'] ?? '');
            $fields[] = (string) ($match['ProductName'] ?? '');
        }

        foreach ($fields as $field) {
            if ($field !== '' && str_contains(strtolower($field), $needle)) {
                return true;
            }
        }

        return false;
    }));
}

if ($listResult['ok'] && qbo_is_connected()) {
    $rows = table_sort_rows($rows, $listFilters, $qboSortAccessors, ['purchase_cost', 'unit_price', 'qty_on_hand'], 'name', 'asc');
}

$totalCount = count($listResult['rows'] ?? []);
$visibleCount = count($rows);

$inventoryLead = 'Read-only view of inventory items in QuickBooks Online — the QBO-side SKU master.';
if (qbo_is_connected() && $listResult['ok']) {
    $inventoryLead .= ' Showing ' . (int) $visibleCount
        . ($search !== '' ? ' matching' : '')
        . ' of ' . (int) $totalCount
        . ' item' . ($totalCount === 1 ? '' : 's') . '.';
}

$pageTitle = 'QBO SKU Master | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/accounting/',
          'back_label' => 'Back to Accounting',
          'category'   => 'QuickBooks',
          'title'      => 'QBO SKU Master',
          'lead'       => $inventoryLead,
      ]); ?>

      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <form class="po-filter audit-filter" method="get" action="/accounting/inventory.php">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($listFilters['sort']) ?>" />
        <input type="hidden" name="dir" value="<?= htmlspecialchars($listFilters['dir']) ?>" />
        <div class="audit-filter-grid">
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, SKU, QBO item ID, or NutraAxis SKU" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply</button>
          <a class="btn-secondary" href="/accounting/inventory.php">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><?php
            table_sort_render_head_row(
                $qboSortColumns,
                '/accounting/inventory.php',
                $listFilters,
                $search !== '' ? ['q' => $search] : [],
                ['purchase_cost', 'unit_price', 'qty_on_hand'],
                'name',
                'asc'
            );
          ?></thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="9">No inventory items found<?= $search !== '' ? ' for this search' : '' ?>.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <?php $match = accounting_match_skumaster_for_qbo_item($row, $skuLinks); ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['Name'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Sku'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Id'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['PurchaseCost'] ?? null)) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['UnitPrice'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) ($row['QtyOnHand'] ?? '—')) ?></td>
              <td><?= !empty($row['Taxable']) ? 'Yes' : 'No' ?></td>
              <td><?= !empty($row['Active']) ? 'Yes' : 'No' ?></td>
              <td>
                <?php if ($match !== null): ?>
                <a href="/product-catalog/view.php?id=<?= (int) $match['SKUID'] ?>"><?= htmlspecialchars((string) $match['SKUCode']) ?></a>
                <?php if (!empty($match['QBO_SyncStatus']) && $match['QBO_SyncStatus'] !== 'Synced'): ?>
                <span class="status-badge status-submitted"><?= htmlspecialchars((string) $match['QBO_SyncStatus']) ?></span>
                <?php endif; ?>
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
<?php require dirname(__DIR__) . '/includes/footer.php';
