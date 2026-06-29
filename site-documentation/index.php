<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/site-documentation.php';

site_documentation_require_read();

$activeSlug = 'site-documentation';
$moduleSections = site_documentation_module_sections();
$scheduledProcesses = site_documentation_scheduled_processes();
$processMonitoring = site_documentation_process_monitoring();
$dataProfileOverview = site_documentation_data_profile_overview();
$pageDataSources = site_documentation_page_data_sources();
$azureSql = site_documentation_azure_sql();
$functionApps = site_documentation_function_apps();
$serviceBus = site_documentation_service_bus();

$pageTitle = 'Site Documentation | NutraAxis Operations';
$pageDescription = 'Support reference for NutraAxis Operations pages and scheduled background processes.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/operations-dashboard/',
          'back_label' => 'Back to Operations Dashboard',
          'category'   => 'Support',
          'title'      => 'Site Documentation',
          'lead'       => 'Reference guide to NutraAxis Operations modules, pages, and scheduled background processes.',
          'permission' => auth_module_permission_label('site-documentation'),
      ]); ?>

      <section class="detail-card site-doc-section">
        <h2>Getting started</h2>
        <p>NutraAxis Operations is the internal PHP portal hosted on Azure App Service. Background jobs run on Azure Function App <strong>Nutra-forecast-tool</strong>; this site provides the UI, Process Log, and manual reruns. Sign in with your NutraAxis email to access modules assigned to your role. Permissions are managed under Site Admin → Roles.</p>
        <ul class="site-doc-list">
          <li><strong>Home:</strong> <a href="/">/</a> — application launcher filtered by your role</li>
          <li><strong>Operations Dashboard:</strong> <a href="/operations-dashboard/">/operations-dashboard/</a> — shortcuts to Microsoft 365, commerce tools, and internal utilities</li>
          <li><strong>Process Log:</strong> <a href="/process-log/">/process-log/</a> — scheduled job history and manual reruns</li>
        </ul>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Data source profiles</h2>
        <p>Pages that read Jazz OMS or Adobe Commerce use a <strong>data profile</strong> (<code>includes/data-profile.php</code>) to pick production vs UAT credentials. Portal workflow data always uses Azure SQL <code>nutraaxis</code>.</p>
        <?php foreach ($dataProfileOverview as $item): ?>
        <div class="site-doc-note">
          <h3><?= htmlspecialchars($item['title']) ?></h3>
          <p><?= htmlspecialchars($item['body']) ?></p>
        </div>
        <?php endforeach; ?>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Application modules</h2>
        <p>Each module below maps to a landing page or workflow area in the portal. Product Master, Inventory, Procurement, Inbound & Receiving, and Sales Reporting include production and UAT variants where external APIs differ.</p>

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
                  <th>Data sources</th>
                  <th>Description</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($section['children'] as $child): ?>
                <tr>
                  <td><a href="<?= htmlspecialchars((string) $child['href']) ?>"><?= htmlspecialchars($child['title']) ?></a></td>
                  <td><code><?= htmlspecialchars((string) $child['href']) ?></code></td>
                  <td><?= htmlspecialchars((string) ($child['data_source'] ?? 'Portal SQL: nutraaxis')) ?></td>
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
        <h2>Page data source reference</h2>
        <p>Complete mapping of portal paths to SQL, Jazz OMS, and Adobe Commerce (ACCS) targets.</p>
        <details class="site-doc-expand">
          <summary>Show all page data sources</summary>
          <div class="admin-table-wrap">
            <table class="admin-table site-doc-table">
              <thead>
                <tr>
                  <th>Page</th>
                  <th>Path</th>
                  <th>Tier</th>
                  <th>SQL</th>
                  <th>Jazz</th>
                  <th>ACCS</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pageDataSources as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['title']) ?></td>
                  <td><code><?= htmlspecialchars($row['path']) ?></code></td>
                  <td><?= htmlspecialchars($row['tier']) ?></td>
                  <td><?= htmlspecialchars($row['sql']) ?></td>
                  <td><?= htmlspecialchars($row['jazz']) ?></td>
                  <td><?= htmlspecialchars($row['accs']) ?></td>
                  <td><?= htmlspecialchars($row['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Azure SQL</h2>
        <p><?= htmlspecialchars($azureSql['summary']) ?></p>
        <details class="site-doc-expand">
          <summary>Connection details and migrations</summary>
          <div class="admin-table-wrap">
            <table class="admin-table site-doc-table">
              <thead>
                <tr>
                  <th>Database</th>
                  <th>Role</th>
                  <th>Used by</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($azureSql['databases'] as $db): ?>
                <tr>
                  <td><code><?= htmlspecialchars($db['name']) ?></code></td>
                  <td><?= htmlspecialchars($db['role']) ?></td>
                  <td><?= htmlspecialchars($db['used_by']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <ul class="site-doc-list">
            <?php foreach ($azureSql['connection'] as $label => $value): ?>
            <li><strong><?= htmlspecialchars($label) ?>:</strong> <?= htmlspecialchars($value) ?></li>
            <?php endforeach; ?>
            <li><strong>Migrations:</strong> <?= htmlspecialchars($azureSql['migrations']) ?></li>
          </ul>
        </details>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Azure Function Apps</h2>
        <p>Background jobs run on Azure Functions, not the PHP App Service. <?= htmlspecialchars($functionApps['source']) ?></p>
        <div class="admin-table-wrap">
          <table class="admin-table site-doc-table">
            <thead>
              <tr>
                <th>App</th>
                <th>Role</th>
                <th>Portal env keys</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($functionApps['apps'] as $app): ?>
              <tr>
                <td><strong><?= htmlspecialchars($app['name']) ?></strong></td>
                <td><?= htmlspecialchars($app['role']) ?></td>
                <td><code><?= htmlspecialchars($app['url_key']) ?></code>, <code><?= htmlspecialchars($app['key_key']) ?></code></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <details class="site-doc-expand">
          <summary>All functions (timers, HTTP, Service Bus)</summary>
          <div class="admin-table-wrap">
            <table class="admin-table site-doc-table">
              <thead>
                <tr>
                  <th>Function</th>
                  <th>Trigger</th>
                  <th>Data / output</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($functionApps['functions'] as $fn): ?>
                <tr>
                  <td><code><?= htmlspecialchars($fn['name']) ?></code></td>
                  <td><?= htmlspecialchars($fn['trigger']) ?></td>
                  <td><?= htmlspecialchars($fn['data']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </details>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Azure Service Bus</h2>
        <p><?= htmlspecialchars($serviceBus['summary']) ?></p>
        <details class="site-doc-expand">
          <summary>Retry flow and configuration</summary>
          <ol class="site-doc-list">
            <?php foreach ($serviceBus['flow'] as $step): ?>
            <li><?= htmlspecialchars($step) ?></li>
            <?php endforeach; ?>
          </ol>
          <ul class="site-doc-list">
            <?php foreach ($serviceBus['env_keys'] as $key => $desc): ?>
            <li><strong><code><?= htmlspecialchars($key) ?></code>:</strong> <?= htmlspecialchars($desc) ?></li>
            <?php endforeach; ?>
            <li><strong>Message type:</strong> <?= htmlspecialchars($serviceBus['message_type']) ?></li>
          </ul>
        </details>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Scheduled background processes</h2>
        <p>Scheduled jobs run on Azure Function App <strong>Nutra-forecast-tool</strong> (timer triggers and Service Bus). All runs are logged to <code>ProcessExecutionLog</code>. WebJobs are retired under <code>App_Data/Disabled_jobs/</code>.</p>

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
          <li><strong>Jazz Inventory Snapshot</strong> (Sunday noon) captures facility-level on-hand quantities in <code>InventoryBalance</code>.</li>
          <li><strong>Staging DB Sync</strong> (daily 3:00 AM) incrementally copies production SQL changes into the staging database.</li>
        </ol>
      </section>

      <section class="detail-card site-doc-section">
        <h2>Process monitoring and errors</h2>
        <details class="site-doc-expand">
          <summary>Show monitoring and email details</summary>
        <?php foreach ($processMonitoring as $item): ?>
        <div class="site-doc-note">
          <h3><?= htmlspecialchars($item['title']) ?></h3>
          <p><?= htmlspecialchars($item['body']) ?></p>
        </div>
        <?php endforeach; ?>
        </details>
      </section>

      <div class="module-actions">
        <a class="btn-secondary" href="/process-log/">Open Process Log</a>
        <a class="btn-secondary" href="/operations-dashboard/">Operations Dashboard</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
