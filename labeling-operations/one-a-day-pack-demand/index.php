<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'oad-demand';
$sectionTitle = 'One-A-Day Pack Demand';

$pageTitle = label_page_title($sectionTitle);
$pageDescription = 'Review projected and actual demand for One-A-Day pack SKUs.';

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
          'lead'       => 'Demand projections and actuals for One-A-Day pack SKUs will be shown here.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="status-banner">
        <div>
          <strong>Coming soon</strong>
          <p>One-A-Day pack demand reporting is not configured yet.</p>
        </div>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
