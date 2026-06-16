<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';

enhancement_log_require_read();

$activeSlug = 'enhancement-log';
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(ENHANCEMENT_LOG_LIST_SORT_COLUMNS, 'request_date', 'desc', $_GET);
$entries = enhancement_log_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Enhancement Log | NutraAxis Operations';
$pageDescription = 'Track portal enhancement requests, status, and due dates.';

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

      <div class="admin-header">
        <div>
          <div class="section-label">Operations</div>
          <h1>Enhancement Log</h1>
          <p class="page-lead">Track enhancement requests for the NutraAxis Operations portal — title, description, status, and due dates.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label('enhancement-log')) ?></p>
        </div>
        <?php if (enhancement_log_can_create()): ?>
        <a class="btn-primary" href="/enhancement-log/new.php">New Enhancement</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Enhancement log entry created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Enhancement log entry updated successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/enhancement-log/">
        <?php table_sort_hidden_inputs($listFilters, 'request_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (ENHANCEMENT_LOG_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                <?= htmlspecialchars(enhancement_log_status_label($status)) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title, description, requester, or notes" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/enhancement-log/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                ENHANCEMENT_LOG_LIST_SORT_COLUMNS,
                '/enhancement-log',
                $listFilters,
                ['status', 'q'],
                ['id'],
                'request_date',
                'desc',
                'request_date',
                table_actions_header(enhancement_log_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($entries === []): ?>
            <tr><td colspan="7">No enhancement log entries match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($entries as $entry): ?>
            <tr>
              <td><?= (int) $entry['EnhancementLogID'] ?></td>
              <td><?= htmlspecialchars((string) $entry['EnhancementTitle']) ?></td>
              <td><?= htmlspecialchars((string) ($entry['RequestedBy'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(enhancement_log_format_date((string) ($entry['RequestDate'] ?? ''))) ?></td>
              <td>
                <span class="status-badge <?= enhancement_log_status_class((string) $entry['RequestStatus']) ?>">
                  <?= htmlspecialchars(enhancement_log_status_label((string) $entry['RequestStatus'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars(enhancement_log_format_date((string) ($entry['ReqDueDate'] ?? ''))) ?></td>
              <?php table_view_edit_cell(
                  '/enhancement-log/view.php?id=' . (int) $entry['EnhancementLogID'],
                  '/enhancement-log/edit.php?id=' . (int) $entry['EnhancementLogID'],
                  enhancement_log_can_update()
              ); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
