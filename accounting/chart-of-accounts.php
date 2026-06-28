<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'accounts';
$listResult = qbo_list_accounts();
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
if ($listResult['ok']) {
    $listResult['rows'] = table_sort_rows($listResult['rows'] ?? [], $listFilters, $qboSortAccessors, ['balance'], 'number', 'asc');
}

$coaLead = 'General ledger accounts synced nightly from QuickBooks Online into Operations. Read-only.';
if (!empty($listResult['synced_at'])) {
    $coaLead .= ' Last synced ' . (string) $listResult['synced_at'] . ' UTC.';
}

$pageTitle = 'Chart of Accounts | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/accounting/',
          'back_label' => 'Back to Accounting',
          'category'   => 'QuickBooks',
          'title'      => 'Chart of Accounts',
          'lead'       => $coaLead,
      ]); ?>

      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (($listResult['rows'] ?? []) !== []): ?>
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
      <?php else: ?>
      <div class="admin-notice is-detail" role="status">
        No cached chart of accounts yet. Connect QuickBooks in Accounting, then run the <strong>QBO Chart of Accounts Sync</strong> process from Process Log.
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
