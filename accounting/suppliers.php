<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'suppliers';
$listResult = qbo_is_connected() ? qbo_list_vendors() : ['ok' => true, 'rows' => [], 'error' => null];

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
          <p class="page-lead">QuickBooks vendors. Read-only today; create and update from Operations is planned.</p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Display name</th><th>Company</th><th>Email</th><th>Balance</th><th>Active</th></tr></thead>
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
