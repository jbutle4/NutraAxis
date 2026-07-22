<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_bind_qbo_environment();
accounting_require_read();

$activeSlug = $activeSlug ?? 'accounting';
$pagePath = accounting_path('/accounting/suppliers.php');
$accountingSection = 'suppliers';
$listResult = qbo_is_connected() ? qbo_list_vendors() : ['ok' => true, 'rows' => [], 'error' => null];
$qboSortColumns = [
    'display_name' => 'Display name',
    'company'      => 'Company',
    'email'        => 'Email',
    'balance'      => 'Balance',
    'active'       => 'Active',
];
$listFilters = table_sort_state($qboSortColumns, 'display_name', 'asc', $_GET);
$qboSortAccessors = [
    'display_name' => fn(array $row): string => (string) ($row['DisplayName'] ?? ''),
    'company'      => fn(array $row): string => (string) ($row['CompanyName'] ?? ''),
    'email'        => fn(array $row): string => (string) ($row['PrimaryEmailAddr']['Address'] ?? ''),
    'balance'      => fn(array $row) => $row['Balance'] ?? 0,
    'active'       => fn(array $row): string => !empty($row['Active']) ? 'Yes' : 'No',
];
if ($listResult['ok'] && qbo_is_connected()) {
    $listResult['rows'] = table_sort_rows($listResult['rows'] ?? [], $listFilters, $qboSortAccessors, ['balance'], 'display_name', 'asc');
}

$pageTitle = 'Suppliers | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks</div>
          <h1>Suppliers</h1>
          <p class="page-lead">QuickBooks vendors. Use <a href="/accounting/sync-production.php">Production sync</a> to link and create vendors bidirectionally.</p>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/sync-production.php?entity=suppliers">Sync suppliers</a>
        <?php endif; ?>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><?php table_sort_render_head_row($qboSortColumns, $pagePath, $listFilters, [], ['balance'], 'display_name', 'asc'); ?></thead>
          <tbody>
            <?php if (($listResult['rows'] ?? []) === []): ?><tr><td colspan="5">No suppliers found.</td></tr><?php else: ?>
            <?php foreach ($listResult['rows'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['DisplayName'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['CompanyName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['PrimaryEmailAddr']['Address'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($row['Balance'] ?? null)) ?></td>
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
