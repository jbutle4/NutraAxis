<?php
require dirname(__DIR__) . '/includes/process-log-page.php';
$dataProfile = process_log_normalize_profile($dataProfile ?? 'production');
require dirname(__DIR__) . '/includes/init.php';
data_profile_set($dataProfile);
require dirname(__DIR__) . '/includes/process-log.php';
require dirname(__DIR__) . '/includes/process-runner.php';

auth_require_module_read('process-log');

$activeSlug = data_profile_is_uat() ? 'scheduled-jobs-uat' : 'process-log';
$processLogBasePath = process_log_base_path();
$otherProfile = data_profile_is_uat() ? 'production' : 'uat';
$otherProfilePath = process_log_base_path($otherProfile);
$canRerun = auth_can_update(MODULE_PERMISSION_COLUMNS['process-log']);

$filters = [
    'process_code' => trim($_GET['process_code'] ?? ''),
    'status'       => trim($_GET['status'] ?? ''),
    'limit'        => 100,
] + table_sort_state(PROCESS_LOG_LIST_SORT_COLUMNS, 'started', 'desc', $_GET);

$logs = process_log_list($filters);
$registry = process_registry();
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;

$pageTitle = (data_profile_is_uat() ? 'Process Log (UAT) | ' : 'Process Log | ') . 'NutraAxis Operations';
$pageDescription = data_profile_is_uat()
    ? 'Manual UAT / sandbox background jobs — ACCS Stage, QBO Sandbox, IMS uat ledger.'
    : 'Production background job execution history and manual runs.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="page-hero">
        <div class="page-hero-head">
          <div class="module-icon"><?= icon_svg('dashboard', 28) ?></div>
        </div>
        <div class="section-label">Operations</div>
        <h1>Process Log<?= data_profile_is_uat() ? ' (UAT)' : '' ?></h1>
        <p class="page-lead">
          <?php if (data_profile_is_uat()): ?>
          Run sandbox / test jobs on <strong><?= htmlspecialchars(process_functions_uat_app_label()) ?></strong>
          (ACCS Stage, QBO Sandbox, IMS <code>uat</code>). Use this page for procurement + inventory UAT E2E — not production ACCS or QBO.
          <?php else: ?>
          Run production-oriented jobs. ACCS order sync and employee customer provisioning route to
          <strong><?= htmlspecialchars(process_functions_prod_app_label()) ?></strong>; other jobs use
          <?= htmlspecialchars(process_functions_uat_app_label()) ?> unless configured otherwise.
          <?php endif; ?>
        </p>
        <p class="permission-note">
          Switch profile:
          <a href="<?= htmlspecialchars($otherProfilePath) ?>"><?= $otherProfile === 'uat' ? 'UAT / Sandbox processes' : 'Production processes' ?></a>
        </p>
        <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('process-log')) ?></p>
      </div>

      <?php if ($notice === 'rerun_success'): ?>
      <div class="admin-notice is-success" role="status">Process rerun completed successfully.</div>
      <?php elseif ($notice === 'rerun_failed'): ?>
      <div class="admin-notice is-error" role="alert">Process rerun failed. See the latest log entry for details.</div>
      <?php elseif ($notice === 'run_success'): ?>
      <div class="admin-notice is-success" role="status">Process run completed successfully. See the latest log entry below.</div>
      <?php elseif ($notice === 'run_failed'): ?>
      <div class="admin-notice is-error" role="alert">Process run failed. See the latest log entry for details<?= $error !== null && $error !== '' ? ': ' . htmlspecialchars($error) : '.' ?></div>
      <?php elseif ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if (data_profile_is_uat() && $canRerun): ?>
      <section class="operations-dashboard-section">
        <h2 class="operations-dashboard-section-title">UAT E2E run order</h2>
        <p class="page-lead">Typical inventory cycle on test systems after PO receipt and ACCS Stage order ship:</p>
        <ol class="process-log-e2e-steps">
          <?php foreach (process_registry_uat_e2e() as $entry): ?>
          <li>
            <strong><?= htmlspecialchars($entry['name']) ?></strong>
            <span class="permission-note">→ <?= htmlspecialchars(process_registry_resolved_app_label($entry)) ?></span>
            <form class="process-log-inline-run" method="post" action="<?= htmlspecialchars($processLogBasePath) ?>run.php">
              <input type="hidden" name="data_profile" value="uat" />
              <input type="hidden" name="process_code" value="<?= htmlspecialchars($entry['code']) ?>" />
              <button type="submit" class="btn-secondary btn-small">Run</button>
            </form>
          </li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>

      <?php if ($canRerun): ?>
      <form class="po-filter audit-filter process-log-run-form" method="post" action="<?= htmlspecialchars($processLogBasePath) ?>run.php">
        <input type="hidden" name="data_profile" value="<?= htmlspecialchars(data_profile()) ?>" />
        <div class="audit-filter-grid">
          <div class="process-log-run-field">
            <label for="run_process_code">Run process</label>
            <select class="form-input" id="run_process_code" name="process_code" required>
              <option value="">Select a registered process…</option>
              <?php foreach ($registry as $entry): ?>
              <option value="<?= htmlspecialchars($entry['code']) ?>">
                <?= htmlspecialchars($entry['name']) ?> (<?= htmlspecialchars($entry['code']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Run now</button>
        </div>
      </form>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($processLogBasePath) ?>">
        <?php table_sort_hidden_inputs($filters, 'started', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="process_code">Process</label>
            <select class="form-input" id="process_code" name="process_code">
              <option value="">All processes</option>
              <?php foreach ($registry as $entry): ?>
              <option value="<?= htmlspecialchars($entry['code']) ?>" <?= $filters['process_code'] === $entry['code'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($entry['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <option value="Success" <?= $filters['status'] === 'Success' ? 'selected' : '' ?>>Success</option>
              <option value="Failed" <?= $filters['status'] === 'Failed' ? 'selected' : '' ?>>Failed (retry pending)</option>
              <option value="Abandoned" <?= $filters['status'] === 'Abandoned' ? 'selected' : '' ?>>Abandoned</option>
              <option value="Running" <?= $filters['status'] === 'Running' ? 'selected' : '' ?>>Running</option>
            </select>
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($processLogBasePath) ?>">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap admin-table-wrap--process-log">
        <table class="admin-table admin-table--process-log">
          <thead>
            <tr>
              <?php
              foreach (PROCESS_LOG_LIST_SORT_COLUMNS as $column => $label) {
                  table_sort_render_th(
                      $column,
                      $label,
                      rtrim($processLogBasePath, '/'),
                      PROCESS_LOG_LIST_SORT_COLUMNS,
                      $filters,
                      ['process_code', 'status'],
                      PROCESS_LOG_LIST_SORT_NUMERIC,
                      'started',
                      'desc',
                      'started'
                  );
              }
              if ($canRerun): ?>
              <th>Action</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($logs === []): ?>
            <tr>
              <td colspan="<?= $canRerun ? 11 : 10 ?>">No process executions logged yet.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= (int) $log['ProcessExecutionLogID'] ?></td>
              <td>
                <strong><?= htmlspecialchars((string) $log['ProcessName']) ?></strong>
                <div class="permission-note"><?= htmlspecialchars((string) $log['ProcessCode']) ?></div>
              </td>
              <td class="process-log-datetime" title="<?= htmlspecialchars(process_log_format_datetime((string) $log['StartedAt'])) ?>"><?= htmlspecialchars(process_log_format_datetime_compact((string) $log['StartedAt'])) ?></td>
              <td class="process-log-datetime" title="<?= htmlspecialchars(process_log_format_datetime((string) ($log['FinishedAt'] ?? ''))) ?>"><?= htmlspecialchars(process_log_format_datetime_compact((string) ($log['FinishedAt'] ?? ''))) ?></td>
              <td><?= htmlspecialchars(process_log_duration_label((string) $log['StartedAt'], (string) ($log['FinishedAt'] ?? ''))) ?></td>
              <td><?= htmlspecialchars(process_log_attempt_label($log)) ?></td>
              <td class="process-log-datetime" title="<?= htmlspecialchars(process_log_format_datetime((string) ($log['NextRetryAt'] ?? ''))) ?>"><?= htmlspecialchars(process_log_format_datetime_compact((string) ($log['NextRetryAt'] ?? ''))) ?></td>
              <td>
                <?= htmlspecialchars((string) $log['TriggerType']) ?>
                <?php if (!empty($log['TriggeredByUserName'])): ?>
                <div class="permission-note"><?= htmlspecialchars((string) $log['TriggeredByUserName']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-badge <?= process_log_status_class((string) $log['Status']) ?>">
                  <?= htmlspecialchars(process_log_status_label((string) $log['Status'])) ?>
                </span>
              </td>
              <td class="process-log-result-cell">
                <?php $resultText = process_log_result_text($log); ?>
                <?php if ($resultText !== ''): ?>
                <span
                  class="process-log-result<?= empty($log['ResultMessage']) && !empty($log['ErrorMessage']) ? ' is-error' : '' ?>"
                  title="<?= htmlspecialchars($resultText) ?>"
                ><?= htmlspecialchars($resultText) ?></span>
                <?php else: ?>
                —
                <?php endif; ?>
              </td>
              <?php if ($canRerun): ?>
              <td>
                <?php if (process_log_can_rerun($log)): ?>
                <form method="post" action="<?= htmlspecialchars($processLogBasePath) ?>rerun.php">
                  <input type="hidden" name="data_profile" value="<?= htmlspecialchars(data_profile()) ?>" />
                  <input type="hidden" name="log_id" value="<?= (int) $log['ProcessExecutionLogID'] ?>" />
                  <button type="submit" class="btn-secondary btn-small">Rerun</button>
                </form>
                <?php else: ?>
                —
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <section class="operations-dashboard-section">
        <h2 class="operations-dashboard-section-title">Registered Processes</h2>
        <p class="page-lead">
          Manual runs from this page use the <?= data_profile_is_uat() ? 'UAT' : 'production' ?> profile below.
          Failed runs schedule Service Bus retries (2, 4, 8 minutes) before being marked abandoned.
        </p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Process</th>
                <th>Description</th>
                <th>Runs on</th>
                <th>Azure Function</th>
                <th>Schedule</th>
                <?php if ($canRerun): ?>
                <th>Action</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registry as $entry): ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($entry['name']) ?></strong>
                  <div class="permission-note"><?= htmlspecialchars($entry['code']) ?></div>
                </td>
                <td><?= htmlspecialchars($entry['description']) ?></td>
                <td><code><?= htmlspecialchars(process_registry_resolved_app_label($entry)) ?></code></td>
                <td><code><?= htmlspecialchars($entry['function_name']) ?></code></td>
                <td><?= htmlspecialchars($entry['schedule']) ?></td>
                <?php if ($canRerun): ?>
                <td>
                  <form method="post" action="<?= htmlspecialchars($processLogBasePath) ?>run.php">
                    <input type="hidden" name="data_profile" value="<?= htmlspecialchars(data_profile()) ?>" />
                    <input type="hidden" name="process_code" value="<?= htmlspecialchars($entry['code']) ?>" />
                    <button type="submit" class="btn-secondary btn-small">Run</button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
