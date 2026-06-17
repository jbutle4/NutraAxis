<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'pos';
$listResult = qbo_is_connected() ? qbo_list_purchase_orders() : ['ok' => true, 'rows' => [], 'error' => null];

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
          <p class="page-lead">QuickBooks purchase orders. Read-only today; create and update from Operations is planned.</p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>
      <?php if (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php elseif (qbo_is_connected()): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>PO #</th><th>Vendor</th><th>Date</th><th>Status</th><th>Total</th></tr></thead>
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
