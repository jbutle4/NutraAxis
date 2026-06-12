<?php
require dirname(__DIR__) . '/includes/init.php';

auth_require_module_read('system-performance-dashboard');

$activeSlug = 'system-performance-dashboard';

$pageTitle = 'System Performance Dashboard | NutraAxis Operations';
$pageDescription = 'IT system monitoring, Geckoboard dashboards, and Zendesk totals.';

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
        <div class="module-icon"><?= icon_svg('trend', 28) ?></div>
        <div class="section-label">Monitoring</div>
        <h1>System Performance Dashboard</h1>
        <p class="page-lead">Central view for IT system health, commerce platform metrics, and support volume totals.</p>
        <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('system-performance-dashboard')) ?></p>
      </div>

      <div class="status-banner">
        <div>
          <strong>Dashboards coming soon</strong>
          <p>This module will connect to Geckoboard and other monitoring dashboards for IT systems and Zendesk totals. No dashboards are configured yet.</p>
        </div>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
