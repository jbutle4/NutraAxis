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
      <a class="breadcrumb" href="/labeling-operations/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars(label_module_title()) ?>
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">One-A-Day Pack</div>
          <h1><?= htmlspecialchars($sectionTitle) ?></h1>
          <p class="page-lead">Purchase orders for One-A-Day pack batch production will be managed here.</p>
        </div>
      </div>

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
