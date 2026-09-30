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
                <span class="form-hint">Runs on demand from its module</span>
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
      $spend = marketing_spend_overview();
      $budget = $spend['budget'];
      $summary = marketing_usage_month_summary();
      $daily = marketing_spend_daily(30);
      $monthly = marketing_spend_monthly(6);
      $byJob = marketing_spend_by_job();
      $byPrompt = marketing_spend_by_prompt();
      $dailyMax = max(0.0001, ...array_map(static fn ($d) => $d['cost'], $daily));
      $monthlyMax = max(0.0001, $budget, ...array_map(static fn ($m) => $m['cost'], $monthly));
      $barClass = static function (?float $pct): string {
          if ($pct === null) {
              return '';
          }
          return $pct >= 100 ? ' is-over' : ($pct >= 80 ? ' is-warn' : '');
      };
      ?>
      <div class="status-banner">
        <div>
          <strong>AI spend this month: <?= htmlspecialchars(marketing_format_usd($spend['month_cost'])) ?><?= $budget > 0 ? ' of ' . htmlspecialchars(marketing_format_usd($budget)) . ' budget (' . number_format((float) $spend['pct_used'], 0) . '%)' : '' ?></strong>
          <?php if ($budget > 0): ?>
          <div class="mkt-bar mkt-bar-lg" title="Used <?= number_format((float) $spend['pct_used'], 1) ?>%, forecast <?= number_format((float) $spend['pct_forecast'], 1) ?>%">
            <span class="mkt-bar-forecast" style="width: <?= min(100, (float) $spend['pct_forecast']) ?>%"></span>
            <span class="mkt-bar-fill<?= $barClass($spend['pct_used']) ?>" style="width: <?= min(100, (float) $spend['pct_used']) ?>%"></span>
          </div>
          <?php endif; ?>
          <p>Every AI call — scheduled or on demand — is refused once month-to-date AI cost reaches the budget (Settings → <code>ai.monthly_budget_usd</code>). Months are UTC. Costs are estimates from token counts and <code>ai.pricing</code>. The lighter bar is the month-end forecast.</p>
        </div>
      </div>

      <div class="mkt-kpis">
        <div class="mkt-kpi">
          <span class="mkt-kpi-label">Today (UTC)</span>
          <span class="mkt-kpi-value"><?= htmlspecialchars(marketing_format_usd($spend['today_cost'])) ?></span>
        </div>
        <div class="mkt-kpi">
          <span class="mkt-kpi-label">Last 7 days</span>
          <span class="mkt-kpi-value"><?= htmlspecialchars(marketing_format_usd($spend['last7_cost'])) ?></span>
          <span class="mkt-kpi-prior"><?= htmlspecialchars(marketing_format_usd($spend['daily_rate'])) ?> / day</span>
        </div>
        <div class="mkt-kpi">
          <span class="mkt-kpi-label">Month-end forecast</span>
          <span class="mkt-kpi-value<?= $spend['pct_forecast'] !== null && $spend['pct_forecast'] >= 100 ? ' mkt-task-overdue' : '' ?>"><?= htmlspecialchars(marketing_format_usd($spend['forecast'])) ?></span>
          <span class="mkt-kpi-prior"><?= $spend['pct_forecast'] !== null ? number_format((float) $spend['pct_forecast'], 0) . '% of budget' : 'No budget set' ?></span>
        </div>
        <div class="mkt-kpi">
          <span class="mkt-kpi-label">Budget runway</span>
          <span class="mkt-kpi-value"><?php
            if ($budget <= 0) {
                echo '—';
            } elseif ($spend['month_cost'] >= $budget) {
                echo 'Reached';
            } elseif ($spend['days_to_budget'] === null || $spend['days_to_budget'] > $spend['days_in_month'] - $spend['elapsed_days']) {
                echo 'Lasts the month';
            } else {
                echo htmlspecialchars(number_format((float) $spend['days_to_budget'], 1)) . ' days';
            }
          ?></span>
          <span class="mkt-kpi-prior">at the 7-day rate</span>
        </div>
        <div class="mkt-kpi">
          <span class="mkt-kpi-label">Last month</span>
          <span class="mkt-kpi-value"><?= htmlspecialchars(marketing_format_usd($spend['prior_month_cost'])) ?></span>
        </div>
        <div class="mkt-kpi">
          <span class="mkt-kpi-label">AI calls this month</span>
          <span class="mkt-kpi-value"><?= number_format($spend['month_calls']) ?></span>
          <span class="mkt-kpi-prior"><?= number_format($spend['month_failures']) ?> failed</span>
        </div>
      </div>

      <h2 class="hub-section-title">Daily AI spend (last 30 days, UTC)</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Date</th><th>Calls</th><th>Failures</th><th>Cost</th><th class="mkt-bar-col">Share of busiest day</th></tr>
          </thead>
          <tbody>
            <?php foreach ($daily as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['date']) ?></td>
              <td><?= number_format($d['calls']) ?></td>
              <td><?= number_format($d['failures']) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd($d['cost'], 4)) ?></td>
              <td><div class="mkt-bar"><span class="mkt-bar-fill" style="width: <?= round($d['cost'] / $dailyMax * 100, 1) ?>%"></span></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Monthly AI spend</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Month</th><th>Calls</th><th>Failures</th><th>Cost</th><th>% of budget</th><th class="mkt-bar-col">Spend</th></tr>
          </thead>
          <tbody>
            <?php foreach ($monthly as $m): ?>
            <?php $pct = $budget > 0 ? $m['cost'] / $budget * 100 : null; ?>
            <tr>
              <td><?= htmlspecialchars($m['label']) ?></td>
              <td><?= number_format($m['calls']) ?></td>
              <td><?= number_format($m['failures']) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd($m['cost'])) ?></td>
              <td><?= $pct !== null ? number_format($pct, 0) . '%' : '—' ?></td>
              <td><div class="mkt-bar"><span class="mkt-bar-fill<?= $barClass($pct) ?>" style="width: <?= round($m['cost'] / $monthlyMax * 100, 1) ?>%"></span></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Month to date by job</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Job</th><th>Runs</th><th>AI calls</th><th>Failures</th><th>Cost</th><th>Share</th></tr>
          </thead>
          <tbody>
            <?php if ($byJob === []): ?>
            <tr><td colspan="6">No AI calls this month.</td></tr>
            <?php endif; ?>
            <?php foreach ($byJob as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['JobName']) ?></td>
              <td><?= number_format((int) $row['Runs']) ?></td>
              <td><?= number_format((int) $row['Calls']) ?></td>
              <td><?= number_format((int) $row['Failures']) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd((float) $row['CostUsd'], 4)) ?></td>
              <td><?= $spend['month_cost'] > 0 ? number_format((float) $row['CostUsd'] / $spend['month_cost'] * 100, 1) . '%' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Month to date by prompt and model</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Prompt</th><th>Model</th><th>Calls</th><th>Failures</th><th>Input tokens</th><th>Output tokens</th><th>Avg / call</th><th>Cost</th></tr>
          </thead>
          <tbody>
            <?php if ($byPrompt === []): ?>
            <tr><td colspan="8">No AI calls this month.</td></tr>
            <?php endif; ?>
            <?php foreach ($byPrompt as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['PromptKey']) ?></td>
              <td><?= htmlspecialchars((string) $row['Model']) ?: '—' ?> <span class="form-hint"><?= htmlspecialchars((string) $row['Provider']) ?></span></td>
              <td><?= number_format((int) $row['Calls']) ?></td>
              <td><?= number_format((int) $row['Failures']) ?></td>
              <td><?= number_format((int) $row['InputTokens']) ?></td>
              <td><?= number_format((int) $row['OutputTokens']) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd((int) $row['Calls'] > 0 ? (float) $row['CostUsd'] / (int) $row['Calls'] : 0, 4)) ?></td>
              <td><?= htmlspecialchars(marketing_format_usd((float) $row['CostUsd'], 4)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Month to date by provider and operation (all APIs)</h2>
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
        <?php marketing_render_field_guide_link('admin-settings'); ?>
        <div class="form-grid">
          <?php foreach ($settings as $key => $row): ?>
          <?php
            $value = (string) ($row['SettingValue'] ?? '');
            $multiline = str_contains($value, "\n") || strlen($value) > 90 || in_array($key, ['brand.competitors', 'brand.terms', 'brand.audiences', 'brand.voice', 'pages.exclude_patterns', 'pages.type_rules', 'engagement.weights', 'engagement.adverse_terms', 'brand.legacy_terms', 'brand.legacy_allow_paths', 'alerts.rules', 'alerts.recipients'], true);
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
