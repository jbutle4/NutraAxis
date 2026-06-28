<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'oad-batch-po';
$sectionTitle = 'One-A-Day Pack Batch Order PO';

$pageTitle = label_page_title($sectionTitle);
$pageDescription = 'Manage purchase orders for One-A-Day pack batch production runs.';

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
          'lead'       => 'Purchase orders for One-A-Day pack batch production will be managed here.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="status-banner">
        <div>
          <strong>Coming soon</strong>
          <p>Batch order PO tracking for One-A-Day packs is not configured yet.</p>
        </div>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
