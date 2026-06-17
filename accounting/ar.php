<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'ar';
$listResult = qbo_is_connected() ? qbo_list_invoices() : ['ok' => true, 'rows' => [], 'error' => null];

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
          <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Due</th><th>Total</th><th>Balance</th></tr></thead>
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
