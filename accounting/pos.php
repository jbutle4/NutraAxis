<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_bind_qbo_environment();
accounting_require_read();

$activeSlug = $activeSlug ?? 'accounting';
$pagePath = accounting_path('/accounting/pos.php');
$accountingSection = 'pos';
$listResult = qbo_is_connected() ? qbo_list_purchase_orders() : ['ok' => true, 'rows' => [], 'error' => null];
$qboSortColumns = [
    'po_number' => 'PO #',
    'vendor'    => 'Vendor',
    'date'      => 'Date',
    'status'    => 'Status',
    'total'     => 'Total',
];
$listFilters = table_sort_state($qboSortColumns, 'date', 'desc', $_GET);
$qboSortAccessors = [
    'po_number' => fn(array $row): string => (string) ($row['DocNumber'] ?? $row['Id'] ?? ''),
    'vendor'    => fn(array $row): string => accounting_ref_name($row['VendorRef'] ?? null),
    'date'      => fn(array $row): string => (string) ($row['TxnDate'] ?? ''),
    'status'    => fn(array $row): string => (string) ($row['POStatus'] ?? ''),
    'total'     => fn(array $row) => $row['TotalAmt'] ?? 0,
];
if ($listResult['ok'] && qbo_is_connected()) {
    $listResult['rows'] = table_sort_rows($listResult['rows'] ?? [], $listFilters, $qboSortAccessors, ['total'], 'date', 'desc');
}

$pageTitle = 'Purchase Orders | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks</div>
          <h1>Purchase Orders</h1>
          <p class="page-lead">QuickBooks purchase orders. Use <a href="/accounting/sync-production.php">Production sync</a> to link and create POs bidirectionally.</p>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/sync-production.php?entity=pos">Sync POs</a>
        <?php endif; ?>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><?php table_sort_render_head_row($qboSortColumns, $pagePath, $listFilters, [], ['total'], 'date', 'desc', 'date'); ?></thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?><tr><td colspan="5">No purchase orders found.</td></tr><?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['DocNumber'] ?? $row['Id'] ?? '')) ?></td>
              <td><?= htmlspecialchars(accounting_ref_name($row['VendorRef'] ?? null)) ?></td>
              <td><?= htmlspecialchars(accounting_format_date($row['TxnDate'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) ($row['POStatus'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['TotalAmt'] ?? null)) ?></td>
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
