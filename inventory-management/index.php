<?php
require dirname(__DIR__) . '/includes/init.php';

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
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="page-hero">
        <div class="module-icon"><?= icon_svg('inventory', 28) ?></div>
        <div class="section-label">Supply Chain</div>
        <h1>Supply Chain Management</h1>
        <p class="page-lead">Central hub for inventory reporting, demand forecasting, supplier records, SKU master data, purchase orders, and advanced shipping notices.</p>
      </div>

      <?php if ($areas === []): ?>
      <div class="status-banner">
        <div>
          <strong>No inventory modules assigned</strong>
          <p>Your role does not include access to any Inventory Management sub-modules. Contact a site administrator.</p>
        </div>
      </div>
      <?php else: ?>
      <div class="capability-grid">
        <?php foreach ($areas as $area):
          $href = trim((string) ($area['href'] ?? ''));
          $isLinked = $href !== '';
        ?>
        <?php if ($isLinked): ?>
        <a class="capability-card capability-card-link" href="<?= htmlspecialchars($href) ?>">
          <div class="function-icon"><?= icon_svg($area['icon']) ?></div>
          <h3><?= htmlspecialchars($area['title']) ?></h3>
          <p><?= htmlspecialchars($area['desc']) ?></p>
          <span class="function-link">
            Open
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </span>
        </a>
        <?php else: ?>
        <div class="capability-card capability-card-static">
          <div class="function-icon"><?= icon_svg($area['icon']) ?></div>
          <h3><?= htmlspecialchars($area['title']) ?></h3>
          <p><?= htmlspecialchars($area['desc']) ?></p>
          <span class="function-link function-link-muted">Coming soon</span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
