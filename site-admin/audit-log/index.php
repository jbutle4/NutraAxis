<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/audit.php';

audit_require_read();

$activeAdminSection = 'audit';
$canRollback = auth_can_update(ADMIN_PERMISSION_COLUMNS['users']);

$filters = [
    'log_id'       => trim($_GET['log_id'] ?? ''),
    'user_id'      => trim($_GET['user_id'] ?? ''),
    'date_from'    => trim($_GET['date_from'] ?? ''),
    'date_to'      => trim($_GET['date_to'] ?? ''),
    'q'            => trim($_GET['q'] ?? ''),
    'rolled_back'  => trim($_GET['rolled_back'] ?? ''),
    'limit'        => 200,
] + table_sort_state(AUDIT_LIST_SORT_COLUMNS, 'change_date', 'desc', $_GET);

$logs = audit_list_logs($filters);
$users = audit_list_users_for_filter();
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;

$pageTitle = 'Audit Log | Site Admin | NutraAxis Operations';
$pageDescription = 'Review data changes and roll back when needed.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/site-admin/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Site Admin
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Site Admin</div>
          <h1>Audit Change Log</h1>
          <p class="page-lead">Insert, update, and delete activity across purchase orders, users, and roles.</p>
        </div>
      </div>

      <?php if ($notice === 'rolled_back'): ?>
      <div class="admin-notice is-success" role="status">Change rolled back successfully.</div>
      <?php elseif ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/site-admin/audit-log/">
        <?php table_sort_hidden_inputs($filters, 'change_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="log_id">Log ID</label>
            <input class="form-input" type="number" id="log_id" name="log_id" value="<?= htmlspecialchars($filters['log_id']) ?>" />
          </div>
          <div>
            <label for="user_id">User</label>
            <select class="form-input" id="user_id" name="user_id">
              <option value="">All users</option>
              <?php foreach ($users as $user): ?>
              <option value="<?= (int) $user['UserID'] ?>" <?= (string) $filters['user_id'] === (string) $user['UserID'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($user['UserName'] . ' (' . $user['UserLogin'] . ')') ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="date_from">Date from</label>
            <input class="form-input" type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" />
          </div>
          <div>
            <label for="date_to">Date to</label>
            <input class="form-input" type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" />
          </div>
          <div>
            <label for="rolled_back">Rollback status</label>
            <select class="form-input" id="rolled_back" name="rolled_back">
              <option value="">All entries</option>
              <option value="no" <?= $filters['rolled_back'] === 'no' ? 'selected' : '' ?>>Not rolled back</option>
              <option value="yes" <?= $filters['rolled_back'] === 'yes' ? 'selected' : '' ?>>Already rolled back</option>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search SQL or user</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Search ChangeSQL, ReverseSQL, user name, or login" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/site-admin/audit-log/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                AUDIT_LIST_SORT_COLUMNS,
                '/site-admin/audit-log',
                $filters,
                ['log_id', 'user_id', 'date_from', 'date_to', 'q', 'rolled_back'],
                ['log_id'],
                'change_date',
                'desc',
                'change_date',
                'View'
            ); ?>
          </thead>
          <tbody>
            <?php if ($logs === []): ?>
            <tr>
              <td colspan="6">No audit log entries match your filters.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= (int) $log['LogID'] ?></td>
              <td><?= htmlspecialchars(admin_format_datetime($log['ChangeDate'])) ?></td>
              <td><?= htmlspecialchars($log['UserName']) ?><br /><span class="text-muted"><?= htmlspecialchars($log['UserLogin']) ?></span></td>
              <td><code class="audit-sql-preview"><?= htmlspecialchars(audit_preview_sql((string) $log['ChangeSQL'])) ?></code></td>
              <td>
                <?php if ($log['RolledBackDate'] !== null): ?>
                <span class="status-pill status-pill-muted">Rolled back</span>
                <?php else: ?>
                <span class="status-pill status-pill-active">Active</span>
                <?php endif; ?>
              </td>
              <?php table_actions_cell([
                  ['href' => '/site-admin/audit-log/view.php?id=' . (int) $log['LogID'], 'label' => 'View'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
