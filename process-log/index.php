<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/process-log.php';
require dirname(__DIR__) . '/includes/process-runner.php';

auth_require_module_read('process-log');

$activeSlug = 'process-log';
$canRerun = auth_can_update(MODULE_PERMISSION_COLUMNS['process-log']);

$filters = [
    'process_code' => trim($_GET['process_code'] ?? ''),
    'status'       => trim($_GET['status'] ?? ''),
    'limit'        => 100,
];

$logs = process_log_list($filters);
$registry = process_registry();
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;

$pageTitle = 'Process Log | NutraAxis Operations';
$pageDescription = 'Scheduled job execution history, results, and manual reruns.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <a class="breadcrumb" href="/operations-dashboard/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Dashboard
      </a>

      <div class="page-hero">
        <div class="page-hero-head">
          <div class="module-icon"><?= icon_svg('dashboard', 28) ?></div>
        </div>
        <div class="section-label">Operations</div>
        <h1>Process Log</h1>
        <p class="page-lead">Execution history for background jobs on Azure Function App Nutra-forecast-tool. Failed runs schedule Service Bus retries (2, 4, 8 minutes) before being marked abandoned.</p>
        <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('process-log')) ?></p>
      </div>

      <?php if ($notice === 'rerun_success'): ?>
      <div class="admin-notice is-success" role="status">Process rerun completed successfully.</div>
      <?php elseif ($notice === 'rerun_failed'): ?>
      <div class="admin-notice is-error" role="alert">Process rerun failed. See the latest log entry for details.</div>
      <?php elseif ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/process-log/">
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
          <a class="btn-secondary" href="/process-log/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap admin-table-wrap--process-log">
        <table class="admin-table admin-table--process-log">
          <thead>
            <tr>
              <th>Log ID</th>
              <th>Process</th>
              <th>Started</th>
              <th>Finished</th>
              <th>Duration</th>
              <th>Attempts</th>
              <th>Next Retry</th>
              <th>Trigger</th>
              <th>Status</th>
              <th>Result</th>
              <?php if ($canRerun): ?>
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
                <form method="post" action="/process-log/rerun.php">
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
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Process</th>
                <th>Description</th>
                <th>Azure Function</th>
                <th>Schedule</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registry as $entry): ?>
              <tr>
                <td><?= htmlspecialchars($entry['name']) ?></td>
                <td><?= htmlspecialchars($entry['description']) ?></td>
                <td><code><?= htmlspecialchars($entry['function_name']) ?></code></td>
                <td><?= htmlspecialchars($entry['schedule']) ?></td>
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
