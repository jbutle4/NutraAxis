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
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Monitoring',
          'title'      => 'System Performance Dashboard',
          'lead'       => 'Central view for IT system health, commerce platform metrics, and support volume totals.',
          'permission' => auth_module_permission_label('system-performance-dashboard'),
      ]); ?>

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
