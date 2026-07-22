<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/qbo-reconcile.php';
require dirname(__DIR__) . '/includes/qbo-sync-results.php';

accounting_bind_qbo_environment();
accounting_require_update();

$entity = $_GET['entity'] ?? 'all';
$ran = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

$results = [];
if ($ran) {
    if ($entity === 'suppliers' || $entity === 'all') {
        $results['suppliers'] = qbo_reconcile_suppliers_production();
        supplier_refresh_invoice_vendor_refs();
    }
    if ($entity === 'pos' || $entity === 'all') {
        $results['pos'] = qbo_reconcile_purchase_orders_production();
    }
    if ($entity === 'bills' || $entity === 'all') {
        $results['bills'] = qbo_reconcile_bills_production();
    }
}

$pageTitle = 'Production QBO Sync | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks Production</div>
          <h1>Sync with QuickBooks</h1>
          <p class="page-lead">Bidirectional reconcile for suppliers, purchase orders, and bills against the Production company. To copy Production QBO data into Sandbox for UAT, use <a href="/accounting/sync-sandbox-mirror.php">Sandbox Mirror</a>.</p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>

      <?php if (!qbo_is_connected()): ?>
      <div class="admin-notice is-error is-detail" role="alert">Connect QuickBooks Production on the Accounting home page before running sync.</div>
      <?php else: ?>
      <div class="admin-actions" style="margin-bottom: 1.5rem;">
        <form method="post" action="/accounting/sync-production.php?entity=all" class="inline-form" onsubmit="return confirm('Run full Production sync for suppliers, POs, and bills?');">
          <button type="submit" class="btn-primary">Run full Production sync</button>
        </form>
        <form method="post" action="/accounting/sync-production.php?entity=suppliers" class="inline-form">
          <button type="submit" class="btn-secondary">Sync suppliers only</button>
        </form>
        <form method="post" action="/accounting/sync-production.php?entity=pos" class="inline-form">
          <button type="submit" class="btn-secondary">Sync POs only</button>
        </form>
        <form method="post" action="/accounting/sync-production.php?entity=bills" class="inline-form">
          <button type="submit" class="btn-secondary">Sync bills only</button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($ran): ?>
        <?php if (isset($results['suppliers'])): ?>
          <?php qbo_sync_render_results($results['suppliers'], 'Suppliers'); ?>
        <?php endif; ?>
        <?php if (isset($results['pos'])): ?>
          <?php qbo_sync_render_results($results['pos'], 'Purchase orders'); ?>
        <?php endif; ?>
        <?php if (isset($results['bills'])): ?>
          <?php qbo_sync_render_results($results['bills'], 'Supplier invoices / bills'); ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
