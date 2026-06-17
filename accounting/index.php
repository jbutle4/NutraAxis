<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/accounting-hub-cards.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_read();

$activeSlug = 'accounting';
$accountingSection = 'overview';
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Accounting | NutraAxis Operations';
$pageDescription = 'Read-only QuickBooks Online views for AP, AR, purchase orders, inventory, suppliers, and chart of accounts.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Finance</div>
          <h1>Accounting</h1>
          <p class="page-lead">QuickBooks Online read-only views for accounts payable, receivable, purchase orders, inventory, suppliers, and the chart of accounts.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(accounting_permission_value())) ?></p>
        </div>
      </div>

      <?php require dirname(__DIR__) . '/includes/accounting-nav.php'; ?>

      <?php if ($notice === 'connected'): ?>
      <div class="admin-notice is-success" role="status">QuickBooks connected successfully.</div>
      <?php elseif ($notice === 'disconnected'): ?>
      <div class="admin-notice is-success" role="status">QuickBooks disconnected.</div>
      <?php endif; ?>

      <?php require dirname(__DIR__) . '/includes/accounting-connection-banner.php'; ?>

      <?php accounting_render_hub_cards(); ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
