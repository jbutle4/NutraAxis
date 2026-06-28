<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/hub-cards.php';

auth_require_module_read('inventory-management');

$activeSlug = 'inventory-management';
$areas = auth_filter_inventory_submodules(app_inventory_submodules());

$pageTitle = 'Supply Chain Management | NutraAxis Operations';
$pageDescription = 'Inventory reporting, forecasting, suppliers, SKU master, purchase orders, and ASN.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Supply Chain',
          'title'      => 'Supply Chain Management',
          'lead'       => 'Central hub for inventory reporting, demand forecasting, supplier records, SKU master data, purchase orders, and advanced shipping notices.',
      ]); ?>

      <?php if ($areas === []): ?>
      <div class="status-banner">
        <div>
          <strong>No inventory modules assigned</strong>
          <p>Your role does not include access to any Inventory Management sub-modules. Contact a site administrator.</p>
        </div>
      </div>
      <?php else: ?>
      <?php hub_render_capability_cards($areas, 'capability-card capability-card-link', 'capability-grid capability-grid--six'); ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
