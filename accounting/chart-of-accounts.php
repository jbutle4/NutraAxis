<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'accounts';
$listResult = qbo_is_connected() ? qbo_list_accounts() : ['ok' => true, 'rows' => [], 'error' => null];
$qboSortColumns = [
    'number'  => 'Number',
    'name'    => 'Name',
    'type'    => 'Type',
    'subtype' => 'Subtype',
    'balance' => 'Balance',
    'active'  => 'Active',
];
$listFilters = table_sort_state($qboSortColumns, 'number', 'asc', $_GET);
$qboSortAccessors = [
    'number'  => fn(array $row): string => (string) ($row['AcctNum'] ?? ''),
    'name'    => fn(array $row): string => (string) ($row['Name'] ?? ''),
    'type'    => fn(array $row): string => (string) ($row['AccountType'] ?? ''),
    'subtype' => fn(array $row): string => (string) ($row['AccountSubType'] ?? ''),
    'balance' => fn(array $row) => $row['CurrentBalance'] ?? 0,
    'active'  => fn(array $row): string => !empty($row['Active']) ? 'Yes' : 'No',
];
if ($listResult['ok'] && qbo_is_connected()) {
    $listResult['rows'] = table_sort_rows($listResult['rows'] ?? [], $listFilters, $qboSortAccessors, ['balance'], 'number', 'asc');
}

$pageTitle = 'QBO Chart of Accounts | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks</div>
          <h1>QBO Chart of Accounts</h1>
          <p class="page-lead">QuickBooks Online general ledger accounts (not Certificate of Analysis). Read-only.</p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><?php table_sort_render_head_row($qboSortColumns, '/accounting/chart-of-accounts.php', $listFilters, [], ['balance'], 'number', 'asc'); ?></thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?><tr><td colspan="6">No accounts found.</td></tr><?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['AcctNum'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Name'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['AccountType'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['AccountSubType'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['CurrentBalance'] ?? null)) ?></td>
              <td><?= !empty($row['Active']) ? 'Yes' : 'No' ?></td>
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
