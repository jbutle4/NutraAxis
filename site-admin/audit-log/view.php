<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/audit.php';

audit_require_read();

$activeAdminSection = 'audit';
$canRollback = auth_can_update(ADMIN_PERMISSION_COLUMNS['users']);
$logId = (int) ($_GET['id'] ?? 0);
$log = $logId > 0 ? audit_get_log($logId) : null;

if ($log === null) {
    header('Location: /site-admin/audit-log/?error=' . rawurlencode('Audit log entry not found.'), true, 302);
    exit;
}

$error = $_GET['error'] ?? null;
$notice = $_GET['notice'] ?? null;
$alreadyRolledBack = $log['RolledBackDate'] !== null;

$pageTitle = 'Audit Log #' . $logId . ' | Site Admin | NutraAxis Operations';
$pageDescription = 'Review audit log entry and roll back if needed.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/site-admin/audit-log/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Audit Log
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Audit Log</div>
          <h1>Entry #<?= (int) $log['LogID'] ?></h1>
          <p class="page-lead">Recorded <?= htmlspecialchars(admin_format_datetime($log['ChangeDate'])) ?> by <?= htmlspecialchars($log['UserName']) ?>.</p>
        </div>
      </div>

      <?php if ($notice === 'rolled_back'): ?>
      <div class="admin-notice is-success" role="status">Change rolled back successfully.</div>
      <?php elseif ($error !== null && $error !== ''): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Details</h2>
          <dl class="detail-list">
            <div>
              <dt>Log ID</dt>
              <dd><?= (int) $log['LogID'] ?></dd>
            </div>
            <div>
              <dt>Change Date</dt>
              <dd><?= htmlspecialchars(admin_format_datetime($log['ChangeDate'])) ?></dd>
            </div>
            <div>
              <dt>User</dt>
              <dd><?= htmlspecialchars($log['UserName']) ?> (<?= htmlspecialchars($log['UserLogin']) ?>)</dd>
            </div>
            <div>
              <dt>User ID</dt>
              <dd><?= (int) $log['UserID'] ?></dd>
            </div>
            <div>
              <dt>Rollback Status</dt>
              <dd>
                <?php if ($alreadyRolledBack): ?>
                Rolled back on <?= htmlspecialchars(admin_format_datetime($log['RolledBackDate'])) ?>
                <?php else: ?>
                Not rolled back
                <?php endif; ?>
              </dd>
            </div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Change SQL</h2>
          <pre class="audit-sql-block"><?= htmlspecialchars((string) $log['ChangeSQL']) ?></pre>
        </section>

        <section class="detail-card">
          <h2>Reverse SQL</h2>
          <pre class="audit-sql-block"><?= htmlspecialchars((string) $log['ReverseSQL']) ?></pre>
        </section>
      </div>

      <?php if ($canRollback && !$alreadyRolledBack): ?>
      <div class="module-actions">
        <form method="post" action="/site-admin/audit-log/rollback.php" onsubmit="return confirm('Roll back this change? This will execute the reverse SQL against the database.');">
          <input type="hidden" name="log_id" value="<?= (int) $log['LogID'] ?>" />
          <button type="submit" class="btn-primary">Roll Back Change</button>
        </form>
      </div>
      <?php elseif ($alreadyRolledBack): ?>
      <p class="permission-note">This entry has already been rolled back and cannot be rolled back again.</p>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
