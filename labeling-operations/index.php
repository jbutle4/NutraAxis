<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'overview';

$pageTitle = label_page_title(label_module_title());
$pageDescription = 'Manage label templates, print batches, compliance review, version control, white label production orders, and One-A-Day pack fulfillment.';

$areas = label_hub_areas();

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

      <?php require dirname(__DIR__) . '/includes/labeling-nav.php'; ?>

      <div class="page-hero">
        <div class="module-icon"><?= icon_svg('tag', 28) ?></div>
        <div class="section-label">Compliance</div>
        <h1><?= htmlspecialchars(label_module_title()) ?></h1>
        <p class="page-lead">Manage supplement label templates, print batches, compliance checks, version history, white label production orders, and One-A-Day pack fulfillment.</p>
        <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(label_permission_value())) ?></p>
      </div>

      <div class="capability-grid">
        <?php foreach ($areas as $area): ?>
        <a class="capability-card capability-card-link" href="<?= htmlspecialchars($area['href']) ?>">
          <h3><?= htmlspecialchars($area['title']) ?></h3>
          <p><?= htmlspecialchars($area['desc']) ?></p>
          <span class="function-link">
            Open
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
