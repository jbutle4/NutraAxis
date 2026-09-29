<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-manual.php';

auth_require_module_read('marketing-manual');

$activeSlug = 'marketing-manual';
$back = app_module_hub_back_link($activeSlug);
$access = mkt_manual_my_access();
$workflows = mkt_manual_workflows();
$pageIndex = mkt_manual_page_index();
$pageGuide = mkt_manual_pages();
$jobs = marketing_process_registry();

$linkHtml = static function (?array $link): string {
    $resolved = mkt_manual_link($link);
    if ($resolved === null) {
        return '—';
    }
    return '<a href="' . htmlspecialchars($resolved['href']) . '">' . htmlspecialchars($resolved['label']) . '</a>';
};
$list = static function (array $items): string {
    if ($items === []) {
        return '';
    }
    return '<ul>' . implode('', array_map(static fn ($item) => '<li>' . mkt_manual_text((string) $item) . '</li>', $items)) . '</ul>';
};

$toc = [
    'start'       => 'Start here',
    'roles'       => 'Roles and access',
    'rhythm'      => 'Operating rhythm (who does what, when)',
    'workflows'   => 'Step-by-step workflows',
    'pages'       => 'Page guide and how to analyze',
    'alerts'      => 'Alerts',
    'schedules'   => 'Job schedules and task deadlines',
    'compliance'  => 'Compliance rules',
    'statuses'    => 'Status glossary',
    'other'       => 'Other instructions',
    'help'        => 'Troubleshooting and help',
];

$pageTitle = 'User Manual | NutraAxis Operations';
$pageDescription = 'How to use Marketing & Research: roles, workflows, page guide, alerts and troubleshooting.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner mkt-manual">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'User Manual',
          'lead'       => 'What to do, who does it, when, and where — for every part of Marketing & Research.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      ?>

      <nav class="mkt-manual-toc" aria-label="Contents">
        <strong>Contents</strong>
        <ol>
          <?php foreach ($toc as $anchor => $label): ?>
          <li><a href="#<?= htmlspecialchars($anchor) ?>"><?= htmlspecialchars($label) ?></a><?php if ($anchor === 'workflows'): ?>
            <ol>
              <?php foreach ($workflows as $key => $wf): ?>
              <li><a href="#wf-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($wf['title']) ?></a></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
        <button type="button" class="btn-secondary mkt-manual-print" onclick="window.print()">Print / save as PDF</button>
      </nav>

      <section id="start" class="mkt-manual-section">
        <h2 class="hub-section-title">Start here</h2>
        <p>Marketing &amp; Research runs the content engine for nutraaxislabs.com: it watches topics, harvests and scores research, turns accepted topics into social posts, emails and web articles with compliance review, tracks what was posted, measures search and site performance, and audits the website for SEO problems. Scheduled jobs do the collecting and scoring; people make every decision and every post.</p>
        <h3>Golden rules</h3>
        <?= $list(mkt_manual_golden_rules()) ?>
        <div class="status-banner">
          <div>
            <strong>Your access</strong>
            <p>
              View: yes ·
              Create: <?= $access['create'] ? 'yes' : 'no' ?> ·
              Update: <?= $access['update'] ? 'yes' : 'no' ?> ·
              Delete: <?= $access['delete'] ? 'yes' : 'no' ?> ·
              Editorial approval &amp; Admin &amp; Jobs: <?= $access['admin'] ? 'yes' : 'no' ?> ·
              Compliance review: <?= $access['compliance'] ? 'yes' : 'no' ?>
            </p>
          </div>
        </div>
      </section>

      <section id="roles" class="mkt-manual-section">
        <h2 class="hub-section-title">Roles and access</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Role</th><th>Access</th><th>What they do</th></tr></thead>
            <tbody>
              <?php foreach (mkt_manual_roles() as $role): ?>
              <tr>
                <td><strong><?= htmlspecialchars($role['role']) ?></strong></td>
                <td><?= htmlspecialchars($role['access']) ?></td>
                <td><?= mkt_manual_text($role['does']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="form-hint">Task assignments use four working roles — Writer, Compliance, Editorial, Coordinator — which map onto the portal roles above. One person can hold several.</p>
      </section>

      <section id="rhythm" class="mkt-manual-section">
        <h2 class="hub-section-title">Operating rhythm</h2>
        <p>Times are Central. Expect about 8–10 hours a week for the operator once habits form.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>When</th><th>Who</th><th>What to do</th><th>Where</th></tr></thead>
            <tbody>
              <?php foreach (mkt_manual_rhythm() as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['when']) ?></td>
                <td><?= htmlspecialchars($row['who']) ?></td>
                <td><?= mkt_manual_text($row['what']) ?></td>
                <td><?= $linkHtml($row['link']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="workflows" class="mkt-manual-section">
        <h2 class="hub-section-title">Step-by-step workflows</h2>
        <?php foreach ($workflows as $key => $wf): ?>
        <div id="wf-<?= htmlspecialchars($key) ?>" class="mkt-manual-workflow">
          <h3><?= htmlspecialchars($wf['title']) ?></h3>
          <p><?= mkt_manual_text($wf['summary']) ?> <span class="form-hint">Timing: <?= htmlspecialchars($wf['cadence']) ?></span></p>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>#</th><th>Who</th><th>When</th><th>What to do</th><th>Page</th></tr></thead>
              <tbody>
                <?php foreach ($wf['steps'] as $i => $step): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($step['who']) ?></td>
                  <td><?= htmlspecialchars($step['when']) ?></td>
                  <td><?= mkt_manual_text($step['what']) ?></td>
                  <td><?= $linkHtml($step['link']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endforeach; ?>
      </section>

      <section id="pages" class="mkt-manual-section">
        <h2 class="hub-section-title">Page guide and how to analyze</h2>
        <?php foreach ($pageIndex as $slug => $page): ?>
        <?php if ($slug === $activeSlug) { continue; } ?>
        <?php $guide = $pageGuide[$slug] ?? null; ?>
        <article id="page-<?= htmlspecialchars($slug) ?>" class="mkt-manual-page">
          <h3><a href="<?= htmlspecialchars($page['href']) ?>"><?= htmlspecialchars($page['title']) ?></a><?= $page['placeholder'] ? ' <span class="status-badge status-draft">Not built yet</span>' : '' ?></h3>
          <?php if ($guide === null): ?>
          <p><?= htmlspecialchars(trim(preg_replace('/\s*Placeholder.*$/', '', $page['desc']))) ?></p>
          <p class="form-hint"><?= $page['placeholder'] ? 'Planned, not built. Don’t record work here yet.' : 'See the page itself for details.' ?></p>
          <?php else: ?>
          <dl class="detail-list detail-list-inline">
            <div><dt>Purpose</dt><dd><?= mkt_manual_text($guide['purpose']) ?></dd></div>
            <div><dt>Who uses it</dt><dd><?= htmlspecialchars($guide['who']) ?></dd></div>
            <div><dt>What you see</dt><dd><?= $list($guide['screens']) ?></dd></div>
            <?php if (!empty($guide['analyze'])): ?>
            <div><dt>How to analyze</dt><dd><?= $list($guide['analyze']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Actions</dt><dd><?= $list($guide['actions']) ?></dd></div>
          </dl>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </section>

      <section id="alerts" class="mkt-manual-section">
        <h2 class="hub-section-title">Alerts</h2>
        <p>The alert check runs daily about 06:00 and on demand (Audit &amp; Issues → Alerts → Check now). New alerts are emailed once; alerts resolve on their own when the cause is gone. Acknowledge means “seen, working on it”.</p>
        <?php $enabled = marketing_setting_lines('alerts.rules'); ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Alert</th><th>On</th><th>What it means</th><th>What to do</th></tr></thead>
            <tbody>
              <?php foreach (mkt_manual_alert_guide() as $rule => [$means, $todo]): ?>
              <tr>
                <td><strong><?= htmlspecialchars(MKT_ALERT_RULES[$rule] ?? $rule) ?></strong><br><span class="form-hint"><?= htmlspecialchars($rule) ?></span></td>
                <td><?= in_array($rule, $enabled, true) ? 'Yes' : 'No' ?></td>
                <td><?= htmlspecialchars($means) ?></td>
                <td><?= htmlspecialchars($todo) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p><a href="<?= htmlspecialchars($pageIndex['marketing-issues']['href'] ?? '/marketing/issues/') ?>?tab=alerts">Open the alerts list</a></p>
      </section>

      <section id="schedules" class="mkt-manual-section">
        <h2 class="hub-section-title">Job schedules and task deadlines</h2>
        <p>Scheduled jobs run automatically. Times are Central daylight time; from November to March they run one hour earlier. Marketing admins can rerun any scheduled job from Admin &amp; Jobs.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Job</th><th>When</th><th>What it does</th></tr></thead>
            <tbody>
              <?php foreach ($jobs as $job): ?>
              <?php if (!empty($job['on_demand'])) { continue; } ?>
              <tr>
                <td><?= htmlspecialchars($job['name']) ?></td>
                <td><?= htmlspecialchars($job['schedule']) ?></td>
                <td><?= htmlspecialchars($job['description']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <h3>On-demand jobs</h3>
        <p class="form-hint">Started by a button in their module, not by a timer.</p>
        <ul>
          <?php foreach ($jobs as $job): ?>
          <?php if (empty($job['on_demand'])) { continue; } ?>
          <li><strong><?= htmlspecialchars($job['name']) ?></strong> — <?= htmlspecialchars($job['schedule']) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php $deadlines = mkt_manual_task_deadlines(); ?>
        <?php if ($deadlines !== []): ?>
        <h3>Automatic task deadlines</h3>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Task</th><th>Days allowed</th></tr></thead>
            <tbody>
              <?php foreach ($deadlines as $row): ?>
              <tr><td><?= htmlspecialchars($row['task']) ?></td><td><?= (int) $row['days'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="form-hint">Compliance must review claims-risk and possible adverse-event responses within <?= (int) marketing_setting('engagement.escalation_hours', '24') ?> hours.</p>
        <?php endif; ?>
      </section>

      <section id="compliance" class="mkt-manual-section">
        <h2 class="hub-section-title">Compliance rules</h2>
        <p>These rules are given to every AI generation, revision and claims check, and are what reviewers check against. A claims score of at least <?= htmlspecialchars(marketing_setting('claims.min_score', '7')) ?> out of 10 is needed to submit.</p>
        <?= $list(marketing_setting_lines('campaign.rules')) ?>
        <?php $disclaimer = trim((string) marketing_setting('claims.disclaimer', '')); ?>
        <?php if ($disclaimer !== ''): ?>
        <p><strong>Disclaimer on claims-bearing copy (the claims check confirms it is present):</strong> <?= htmlspecialchars($disclaimer) ?></p>
        <?php endif; ?>
        <p class="form-hint">Flag terms (words that always trigger compliance review) and approved claims are maintained by marketing admins in Settings and the Claims Matrix.</p>
      </section>

      <section id="statuses" class="mkt-manual-section">
        <h2 class="hub-section-title">Status glossary</h2>
        <div class="mkt-manual-glossary">
          <?php foreach (mkt_manual_statuses() as $group => $statuses): ?>
          <div>
            <h3><?= htmlspecialchars($group) ?></h3>
            <p><?= htmlspecialchars(implode(' → ', array_values($statuses))) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="form-hint">Arrows show the usual order; some states (archived, ignored, rejected, cancelled) can happen at any point.</p>
      </section>

      <section id="other" class="mkt-manual-section">
        <h2 class="hub-section-title">Other instructions</h2>
        <dl class="detail-list detail-list-inline">
          <?php foreach (mkt_manual_other_instructions() as $title => $text): ?>
          <div><dt><?= htmlspecialchars($title) ?></dt><dd><?= mkt_manual_text($text) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>

      <section id="help" class="mkt-manual-section">
        <h2 class="hub-section-title">Troubleshooting and help</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>If you see</th><th>Do this</th></tr></thead>
            <tbody>
              <?php foreach (mkt_manual_troubleshooting() as [$symptom, $fix]): ?>
              <tr><td><?= htmlspecialchars($symptom) ?></td><td><?= mkt_manual_text($fix) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p><a href="#start">Back to top</a></p>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
