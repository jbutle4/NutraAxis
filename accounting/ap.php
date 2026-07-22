<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_bind_qbo_environment();
accounting_require_read();

$activeSlug = $activeSlug ?? 'accounting';
$pagePath = accounting_path('/accounting/ap.php');
$accountingSection = 'ap';
$listResult = qbo_is_connected() ? qbo_list_bills() : ['ok' => true, 'rows' => [], 'error' => null];
$qboSortColumns = [
    'bill_number' => 'Bill #',
    'vendor'      => 'Vendor',
    'date'        => 'Date',
    'due'         => 'Due',
    'total'       => 'Total',
    'balance'     => 'Balance',
];
$listFilters = table_sort_state($qboSortColumns, 'date', 'desc', $_GET);
$qboSortAccessors = [
    'bill_number' => fn(array $row): string => (string) ($row['DocNumber'] ?? $row['Id'] ?? ''),
    'vendor'      => fn(array $row): string => accounting_ref_name($row['VendorRef'] ?? null),
    'date'        => fn(array $row): string => (string) ($row['TxnDate'] ?? ''),
    'due'         => fn(array $row): string => (string) ($row['DueDate'] ?? ''),
    'total'       => fn(array $row) => $row['TotalAmt'] ?? 0,
    'balance'     => fn(array $row) => $row['Balance'] ?? 0,
];
if ($listResult['ok'] && qbo_is_connected()) {
    $listResult['rows'] = table_sort_rows(
        $listResult['rows'] ?? [],
        $listFilters,
        $qboSortAccessors,
        ['total', 'balance'],
        'date',
        'desc'
    );
}

$pageTitle = 'Accounts Payable | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks</div>
          <h1>Accounts Payable</h1>
          <p class="page-lead">Vendor bills from QuickBooks Online. Use <a href="/accounting/sync-production.php">Production sync</a> to link and import bills.</p>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/sync-production.php?entity=bills">Sync bills</a>
        <?php endif; ?>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><?php table_sort_render_head_row($qboSortColumns, $pagePath, $listFilters, [], ['total', 'balance'], 'date', 'desc', 'date'); ?></thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?><tr><td colspan="6">No bills found.</td></tr><?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['DocNumber'] ?? $row['Id'] ?? '')) ?></td>
              <td><?= htmlspecialchars(accounting_ref_name($row['VendorRef'] ?? null)) ?></td>
              <td><?= htmlspecialchars(accounting_format_date($row['TxnDate'] ?? null)) ?></td>
              <td><?= htmlspecialchars(accounting_format_date($row['DueDate'] ?? null)) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['TotalAmt'] ?? null)) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['Balance'] ?? null)) ?></td>
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
