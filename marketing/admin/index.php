<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-admin');
marketing_require_admin();

$activeSlug = 'marketing-admin';
$baseHref = '/marketing/admin/';
$tabs = ['jobs' => 'Jobs', 'usage' => 'AI & API Usage', 'settings' => 'Settings'];
$tab = (string) ($_GET['tab'] ?? 'jobs');
if (!isset($tabs[$tab])) {
    $tab = 'jobs';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'run') {
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!in_array($code, marketing_job_codes(), true)) {
            marketing_redirect($baseHref, ['tab' => 'jobs', 'error' => 'Unknown job.']);
        }
        $result = process_execute($code, [], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
        marketing_redirect($baseHref, !empty($result['ok'])
            ? ['tab' => 'jobs', 'notice' => (string) ($result['message'] ?? 'Job completed.')]
            : ['tab' => 'jobs', 'error' => (string) ($result['error'] ?? 'Job failed.')]);
    }

    if ($action === 'save_settings') {
        $values = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
        $changed = marketing_settings_save($values);
        marketing_redirect($baseHref, ['tab' => 'settings', 'notice' => $changed . ' setting(s) updated.']);
    }
}

$pageTitle = 'Admin & Jobs | NutraAxis Operations';
$pageDescription = 'Marketing & Research job runs, AI and API usage, and settings.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Admin & Jobs',
          'lead'       => 'Scheduled job runs, AI and API spend, and the settings that drive harvesting and generation.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_tabs($baseHref, $tabs, $tab);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

<?php if ($tab === 'jobs'): ?>
      <?php $lastRuns = marketing_jobs_last_runs(); ?>
      <h2 class="hub-section-title">Jobs</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Job</th><th>Schedule</th><th>Last run</th><th>Status</th><th>Result</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach (marketing_process_registry() as $code => $job): ?>
            <?php $last = $lastRuns[$code] ?? null; ?>
            <tr>
              <td><strong><?= htmlspecialchars($job['name']) ?></strong><br><span class="form-hint"><?= htmlspecialchars($code) ?> — <?= htmlspecialchars($job['description']) ?></span></td>
              <td><?= htmlspecialchars($job['schedule']) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($last['StartedAt'] ?? null)) ?></td>
              <td>
                <?php if ($last !== null): ?>
                <span class="status-badge <?= htmlspecialchars(process_log_status_class((string) $last['Status'])) ?>"><?= htmlspecialchars(process_log_status_label((string) $last['Status'])) ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= htmlspecialchars($last !== null ? process_log_result_text($last) : '') ?></td>
              <td>
                <?php if (empty($job['on_demand'])): ?>
                <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="table-action-form">
                  <input type="hidden" name="action" value="run" />
                  <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>" />
                  <button type="submit" class="btn-secondary">Run now</button>
                </form>
                <?php else: ?>
                <span class="form-hint">Runs from Campaign Studio</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Recent runs</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Log ID</th><th>Job</th><th>Started</th><th>Duration</th><th>Trigger</th><th>Status</th><th>Result</th></tr>
          </thead>
          <tbody>
            <?php $runs = marketing_jobs_recent(50); ?>
            <?php if ($runs === []): ?>
            <tr><td colspan="7">No runs yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($runs as $run): ?>
            <tr>
              <td><?= (int) $run['ProcessExecutionLogID'] ?></td>
              <td><?= htmlspecialchars((string) $run['ProcessName']) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($run['StartedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars(process_log_duration_label($run['StartedAt'] ?? null, $run['FinishedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) $run['TriggerType']) ?><?= !empty($run['TriggeredByUserName']) ? ' · ' . htmlspecialchars((string) $run['TriggeredByUserName']) : '' ?></td>
              <td><span class="status-badge <?= htmlspecialchars(process_log_status_class((string) $run['Status'])) ?>"><?= htmlspecialchars(process_log_status_label((string) $run['Status'])) ?></span></td>
              <td><?= htmlspecialchars(process_log_result_text($run)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php elseif ($tab === 'usage'): ?>
      <?php
      $monthCost = marketing_ai_cost_month_to_date();
      $budget = (float) marketing_setting('ai.monthly_budget_usd', '0');
      $summary = marketing_usage_month_summary();
      ?>
      <div class="status-banner">
        <div>
          <strong>AI spend this month: <?= htmlspecialchars(marketing_format_usd($monthCost)) ?><?= $budget > 0 ? ' of ' . htmlspecialchars(marketing_format_usd($budget)) . ' budget' : '' ?></strong>
          <p>Scheduled jobs stop submitting AI work when month-to-date AI cost reaches the budget (Settings → <code>ai.monthly_budget_usd</code>). Costs are estimates from token counts and <code>ai.pricing</code>.</p>
        </div>
      </div>

      <h2 class="hub-section-title">Month to date by provider and operation</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Provider</th><th>Operation</th><th>Mode</th><th>Calls</th><th>Failures</th><th>Input tokens</th><th>Output tokens</th><th>Units</th><th>Cost</th></tr>
          </thead>
          <tbody>
            <?php if ($summary === []): ?>
            <tr><td colspan="9">No API usage recorded this month.</td></tr>
            <?php endif; ?>
            <?php foreach ($summary as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['Provider']) ?></td>
              <td><?= htmlspecialchars((string) $row['Operation']) ?></td>
              <td><?= htmlspecialchars((string) $row['Mode']) ?></td>
              <td><?= number_format((int) $row['Calls']) ?></td>
              <td><?= number_format((int) $row['Failures']) ?></td>
              <td><?= number_format((int) $row['InputTokens']) ?></td>
              <td><?= number_format((int) $row['OutputTokens']) ?></td>
              <td><?= number_format((int) $row['Units']) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd((float) $row['CostUsd'], 4)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Recent calls</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>When</th><th>Provider</th><th>Operation</th><th>Prompt</th><th>Model</th><th>Tokens in / out</th><th>Cost</th><th>Result</th></tr>
          </thead>
          <tbody>
            <?php $recent = marketing_usage_recent(100); ?>
            <?php if ($recent === []): ?>
            <tr><td colspan="8">No calls yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $row): ?>
            <tr>
              <td><?= htmlspecialchars(marketing_format_datetime($row['CreatedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) $row['Provider']) ?> <span class="form-hint"><?= htmlspecialchars((string) $row['Mode']) ?></span></td>
              <td><?= htmlspecialchars((string) $row['Operation']) ?></td>
              <td><?= htmlspecialchars(trim((string) ($row['PromptKey'] ?? '') . (!empty($row['PromptVersion']) ? ' v' . (int) $row['PromptVersion'] : ''))) ?: '—' ?></td>
              <td><?= htmlspecialchars((string) ($row['Model'] ?? '')) ?: '—' ?></td>
              <td><?= number_format((int) ($row['InputTokens'] ?? 0)) ?> / <?= number_format((int) ($row['OutputTokens'] ?? 0)) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd((float) ($row['CostUsd'] ?? 0), 4)) ?></td>
              <td><?= !empty($row['Ok']) ? 'OK' : htmlspecialchars('Failed: ' . (string) ($row['ErrorMessage'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php else: ?>
      <?php $settings = marketing_settings_all(); ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="action" value="save_settings" />
        <div class="form-grid">
          <?php foreach ($settings as $key => $row): ?>
          <?php
            $value = (string) ($row['SettingValue'] ?? '');
            $multiline = str_contains($value, "\n") || strlen($value) > 90 || in_array($key, ['brand.competitors', 'brand.terms', 'brand.audiences', 'brand.voice'], true);
            $fieldId = 'setting-' . preg_replace('/[^a-z0-9]+/i', '-', $key);
          ?>
          <div class="form-group form-grid-full">
            <label for="<?= htmlspecialchars($fieldId) ?>"><?= htmlspecialchars($key) ?></label>
            <div>
              <?php if ($multiline): ?>
              <textarea class="form-input" id="<?= htmlspecialchars($fieldId) ?>" name="settings[<?= htmlspecialchars($key) ?>]" rows="3"><?= htmlspecialchars($value) ?></textarea>
              <?php else: ?>
              <input class="form-input" type="text" id="<?= htmlspecialchars($fieldId) ?>" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($value) ?>" />
              <?php endif; ?>
              <?php if (!empty($row['Description'])): ?>
              <p class="form-hint"><?= htmlspecialchars((string) $row['Description']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Save settings</button>
        </div>
      </form>
      <p class="form-hint">API keys (<code>ANTHROPIC_API_KEY</code>, <code>OPENAI_API_KEY</code>, <code>GHL_PIT</code>) live in App Service / Function App settings and are never stored or shown here.</p>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
