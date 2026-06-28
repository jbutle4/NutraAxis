<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'oad-inventory';
$sectionTitle = 'One-A-Day Pack Inventory';

$pageTitle = label_page_title($sectionTitle);
$pageDescription = 'View on-hand and available One-A-Day pack inventory by SKU.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/labeling-operations/',
          'back_label' => 'Back to ' . label_module_title(),
          'category'   => 'One-A-Day Pack',
          'title'      => $sectionTitle,
          'lead'       => 'Inventory levels for One-A-Day pack SKUs will be displayed here.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="status-banner">
        <div>
          <strong>Coming soon</strong>
          <p>One-A-Day pack inventory reporting is not configured yet.</p>
        </div>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
