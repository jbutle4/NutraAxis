<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/hub-cards.php';

auth_require_module_read('sales-reporting');

$activeSlug = 'sales-reporting';
$areas = auth_filter_sales_submodules(app_sales_submodules());

$pageTitle = 'Sales Reporting Summaries | NutraAxis Operations';
$pageDescription = 'ACCS order lookup, daily sales, and monthly sales summary tables.';

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

      <div class="page-hero">
        <div class="module-icon"><?= icon_svg('chart', 28) ?></div>
        <div class="section-label">Sales</div>
        <h1>Sales Reporting Summaries</h1>
        <p class="page-lead">ACCS order lookup plus daily and monthly SKU sales tables populated by scheduled background jobs.</p>
      </div>

      <?php if ($areas === []): ?>
      <div class="status-banner">
        <div>
          <strong>No sales reports assigned</strong>
          <p>Your role does not include access to any Sales Reporting modules. Contact a site administrator.</p>
        </div>
      </div>
      <?php else: ?>
      <?php hub_render_capability_cards($areas); ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
