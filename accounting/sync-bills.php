<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/qbo-reconcile.php';
require dirname(__DIR__) . '/includes/qbo-sync-results.php';

require dirname(__DIR__) . '/includes/procurement-ledger.php';

accounting_bind_qbo_environment();
accounting_require_update();

$ran = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$ledgerProfile = procurement_page_ledger_profile();
$ledgerLabel = po_ledger_profile_label($ledgerProfile);
$accountingSection = 'sync-bills';
$results = [];

if ($ran) {
    $results['bills'] = qbo_reconcile_bills($ledgerProfile);
}

$pageTitle = 'Sync Bills from QuickBooks | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks <?= htmlspecialchars($ledgerLabel) ?></div>
          <h1>Sync bills from QuickBooks</h1>
          <p class="page-lead">
            Link supplier invoices to QuickBooks bills and pull payment status back into Operations.
            Use this after recording bill payments manually in QuickBooks.
          </p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>

      <?php if (!qbo_is_connected()): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        Connect QuickBooks <?= htmlspecialchars($ledgerLabel) ?> on the Accounting home page before running sync.
      </div>
      <?php else: ?>
      <div class="admin-actions" style="margin-bottom: 1.5rem;">
        <form method="post" action="<?= htmlspecialchars(accounting_path('/accounting/sync-bills.php')) ?>" class="inline-form" onsubmit="return confirm('Sync bill balances and payment status from QuickBooks <?= htmlspecialchars($ledgerLabel) ?>?');">
          <button type="submit" class="btn-primary">Sync bills from QuickBooks</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($ran && isset($results['bills'])): ?>
        <?php qbo_sync_render_results($results['bills'], 'Supplier invoices / bills'); ?>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
