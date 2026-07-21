<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_bind_qbo_environment();
accounting_require_read();

$activeSlug = $activeSlug ?? 'accounting';
$pagePath = accounting_path('/accounting/ar.php');
$accountingSection = 'ar';
$listResult = qbo_is_connected() ? qbo_list_invoices() : ['ok' => true, 'rows' => [], 'error' => null];
$qboSortColumns = [
    'invoice_number' => 'Invoice #',
    'customer'       => 'Customer',
    'date'           => 'Date',
    'due'            => 'Due',
    'total'          => 'Total',
    'balance'        => 'Balance',
];
$listFilters = table_sort_state($qboSortColumns, 'date', 'desc', $_GET);
$qboSortAccessors = [
    'invoice_number' => fn(array $row): string => (string) ($row['DocNumber'] ?? $row['Id'] ?? ''),
    'customer'       => fn(array $row): string => accounting_ref_name($row['CustomerRef'] ?? null),
    'date'           => fn(array $row): string => (string) ($row['TxnDate'] ?? ''),
    'due'            => fn(array $row): string => (string) ($row['DueDate'] ?? ''),
    'total'          => fn(array $row) => $row['TotalAmt'] ?? 0,
    'balance'        => fn(array $row) => $row['Balance'] ?? 0,
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

$pageTitle = 'Accounts Receivable | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks</div>
          <h1>Accounts Receivable</h1>
          <p class="page-lead">Customer invoices from QuickBooks Online. Read-only.</p>
        </div>
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
            <?php if (($listResult['rows'] ?? []) === []): ?><tr><td colspan="6">No invoices found.</td></tr><?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['DocNumber'] ?? $row['Id'] ?? '')) ?></td>
              <td><?= htmlspecialchars(accounting_ref_name($row['CustomerRef'] ?? null)) ?></td>
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
