<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/site-documentation.php';

site_documentation_require_read();

$activeSlug = 'site-documentation';
$moduleSections = site_documentation_module_sections();
$scheduledProcesses = site_documentation_scheduled_processes();
$processMonitoring = site_documentation_process_monitoring();

$pageTitle = 'Site Documentation | NutraAxis Operations';
$pageDescription = 'Support reference for NutraAxis Operations pages and scheduled background processes.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/operations-dashboard/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Dashboard
      </a>

      <div class="page-hero">
        <div class="page-hero-head">
          <div class="module-icon"><?= icon_svg('document', 28) ?></div>
        </div>
        <div class="section-label">Support</div>
        <h1>Site Documentation</h1>
        <p class="page-lead">Reference guide to NutraAxis Operations modules, pages, and scheduled background processes.</p>
        <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('site-documentation')) ?></p>
      </div>

      <section class="detail-card site-doc-section">
        <h2>Getting started</h2>
        <p>NutraAxis Operations is the internal PHP portal hosted on Azure App Service. Background jobs run on Azure Function Apps <strong><?= htmlspecialchars(function_app_uat_display_name()) ?></strong> (UAT/stage timers) and <strong><?= htmlspecialchars(function_app_prod_display_name()) ?></strong> (production webhooks); this site provides the UI, Process Log, and manual reruns. Sign in with your NutraAxis email to access modules assigned to your role. Permissions are managed under Site Admin → Roles.</p>
        <ul class="site-doc-list">
          <li><strong>Home:</strong> <a href="/">/</a> — application launcher filtered by your role</li>
          <li><strong>Operations Dashboard:</strong> <a href="/operations-dashboard/">/operations-dashboard/</a> — shortcuts to Microsoft 365, commerce tools, and internal utilities</li>
          <li><strong>Process Log:</strong> <a href="/process-log/">/process-log/</a> — scheduled job history and manual reruns</li>
        </ul>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Application modules</h2>
        <p>Each module below maps to a landing page or workflow area in the portal. Supply Chain Management includes the inventory, procurement, and demand planning sub-applications.</p>

        <?php foreach ($moduleSections as $section): ?>
        <div class="site-doc-module-group">
          <h3>
            <?php if (!empty($section['href'])): ?>
            <a href="<?= htmlspecialchars((string) $section['href']) ?>"><?= htmlspecialchars($section['title']) ?></a>
            <?php else: ?>
            <?= htmlspecialchars($section['title']) ?>
            <?php endif; ?>
          </h3>
          <p><?= htmlspecialchars($section['description']) ?></p>

          <?php if (!empty($section['children'])): ?>
          <div class="admin-table-wrap">
            <table class="admin-table site-doc-table">
              <thead>
                <tr>
                  <th>Page</th>
                  <th>Path</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($section['children'] as $child): ?>
                <tr>
                  <td><a href="<?= htmlspecialchars((string) $child['href']) ?>"><?= htmlspecialchars($child['title']) ?></a></td>
                  <td><code><?= htmlspecialchars((string) $child['href']) ?></code></td>
                  <td><?= htmlspecialchars($child['description']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php elseif (!empty($section['href'])): ?>
          <p class="site-doc-path"><code><?= htmlspecialchars((string) $section['href']) ?></code></p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Scheduled background processes</h2>
        <p>Scheduled jobs run on Azure Function Apps <strong><?= htmlspecialchars(function_app_uat_display_name()) ?></strong> and <strong><?= htmlspecialchars(function_app_prod_display_name()) ?></strong> (timer triggers and Service Bus). All runs are logged to <code>ProcessExecutionLog</code>. WebJobs are retired under <code>App_Data/Disabled_jobs/</code>.</p>

        <div class="admin-table-wrap">
          <table class="admin-table site-doc-table">
            <thead>
              <tr>
                <th>Process</th>
                <th>Schedule (Central)</th>
                <th>Azure Function</th>
                <th>Output table</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($scheduledProcesses as $process): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($process['name']) ?></strong>
                  <div class="permission-note"><?= htmlspecialchars($process['code']) ?></div>
                </td>
                <td><?= htmlspecialchars($process['schedule']) ?></td>
                <td><code><?= htmlspecialchars($process['function_name']) ?></code></td>
                <td><code><?= htmlspecialchars($process['writes_to']) ?></code></td>
                <td><?= htmlspecialchars($process['notes']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <h3 class="site-doc-subheading">Process flow</h3>
        <ol class="site-doc-list">
          <li><strong>Daily Sales Summary</strong> loads ACCS orders and writes daily SKU totals to <code>DailySalesSummary</code>.</li>
          <li><strong>Weekly chain</strong> (Sunday 1:00 AM) runs monthly rollup into <code>MonthlySalesSummary</code>, then refreshes SKU projections in <code>ForecastPlan</code>.</li>
          <li><strong>Jazz Inventory Snapshot</strong> (Sunday noon) captures facility-level on-hand quantities in <code>JazzInventorySnapshot</code>.</li>
          <li><strong>Staging DB Sync</strong> (daily 2:30 AM) incrementally copies production SQL changes into the staging database.</li>
        </ol>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Process monitoring and errors</h2>
        <?php foreach ($processMonitoring as $item): ?>
        <div class="site-doc-note">
          <h3><?= htmlspecialchars($item['title']) ?></h3>
          <p><?= htmlspecialchars($item['body']) ?></p>
        </div>
        <?php endforeach; ?>
      </section>

      <div class="module-actions">
        <a class="btn-secondary" href="/process-log/">Open Process Log</a>
        <a class="btn-secondary" href="/operations-dashboard/">Operations Dashboard</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
