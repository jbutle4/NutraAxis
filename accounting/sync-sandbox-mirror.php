<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/qbo-prod-to-sandbox.php';
require dirname(__DIR__) . '/includes/qbo-sync-results.php';

accounting_require_update();

$entity = $_GET['entity'] ?? 'all';
$ran = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

$results = [];
if ($ran) {
    if ($entity === 'vendors' || $entity === 'all') {
        $results['vendors'] = qbo_sync_production_to_sandbox_vendors();
    }
    if ($entity === 'pos' || $entity === 'all') {
        $results['pos'] = qbo_sync_production_to_sandbox_purchase_orders();
    }
    if ($entity === 'bills' || $entity === 'all') {
        $results['bills'] = qbo_sync_production_to_sandbox_bills();
    }
}

$bothConnected = qbo_is_connected(QBO_ENV_PRODUCTION) && qbo_is_connected(QBO_ENV_SANDBOX);
$connectionError = qbo_ptos_require_connections();
$accountingSection = 'sync-sandbox-mirror';

$pageTitle = 'Sandbox QBO Mirror | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/accounting/">Back to Accounting</a>
      <div class="admin-header">
        <div>
          <div class="section-label">QuickBooks Sandbox mirror</div>
          <h1>Mirror Production into Sandbox</h1>
          <p class="page-lead">One-way copy from QuickBooks Production into the Sandbox company so UAT matches live vendors, purchase orders, and bills. Existing Sandbox records with the same document number or vendor display name are left in place.</p>
        </div>
      </div>
      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>
      <?php require dirname(__DIR__) . '/includes/accounting-connection-dual-banner.php'; ?>

      <?php if ($connectionError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($connectionError) ?></div>
      <?php elseif ($bothConnected): ?>
      <div class="admin-actions" style="margin-bottom: 1.5rem;">
        <form method="post" action="/accounting/sync-sandbox-mirror.php?entity=all" class="inline-form" onsubmit="return confirm('Copy Production vendors, POs, and bills into Sandbox? Existing Sandbox matches are skipped.');">
          <button type="submit" class="btn-primary">Run full mirror (vendors → POs → bills)</button>
        </form>
        <form method="post" action="/accounting/sync-sandbox-mirror.php?entity=vendors" class="inline-form">
          <button type="submit" class="btn-secondary">Mirror vendors only</button>
        </form>
        <form method="post" action="/accounting/sync-sandbox-mirror.php?entity=pos" class="inline-form">
          <button type="submit" class="btn-secondary">Mirror POs only</button>
        </form>
        <form method="post" action="/accounting/sync-sandbox-mirror.php?entity=bills" class="inline-form">
          <button type="submit" class="btn-secondary">Mirror bills only</button>
        </form>
      </div>
      <p class="permission-note">Operations ↔ Production reconcile lives on <a href="/accounting/sync-production.php">QBO Sync</a>. This page only copies between QuickBooks companies.</p>
      <?php endif; ?>

      <?php if ($ran): ?>
        <?php if (isset($results['vendors'])): ?>
          <?php qbo_sync_render_results($results['vendors'], 'Vendors (Production → Sandbox)'); ?>
        <?php endif; ?>
        <?php if (isset($results['pos'])): ?>
          <?php qbo_sync_render_results($results['pos'], 'Purchase orders (Production → Sandbox)'); ?>
        <?php endif; ?>
        <?php if (isset($results['bills'])): ?>
          <?php qbo_sync_render_results($results['bills'], 'Bills (Production → Sandbox)'); ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php';
