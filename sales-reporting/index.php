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
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Sales',
          'title'      => 'Sales Reporting Summaries',
          'lead'       => 'ACCS order lookup plus daily and monthly SKU sales tables populated by scheduled background jobs.',
      ]); ?>

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
