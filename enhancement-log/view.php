<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';

enhancement_log_require_read();

$logId = (int) ($_GET['id'] ?? 0);
$entry = $logId > 0 ? enhancement_log_get($logId) : null;

if ($entry === null) {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$activeSlug = 'enhancement-log';
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Enhancement #' . $logId . ' | Enhancement Log';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/enhancement-log/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Enhancement Log
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Operations</div>
          <h1><?= htmlspecialchars((string) $entry['EnhancementTitle']) ?></h1>
          <p class="page-lead">
            Log #<?= (int) $entry['EnhancementLogID'] ?>
            · <span class="status-badge <?= enhancement_log_status_class((string) $entry['RequestStatus']) ?>">
              <?= htmlspecialchars(enhancement_log_status_label((string) $entry['RequestStatus'])) ?>
            </span>
          </p>
        </div>
        <?php if (enhancement_log_can_update()): ?>
        <a class="btn-primary" href="/enhancement-log/edit.php?id=<?= $logId ?>">Edit</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Enhancement log entry created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Enhancement log entry updated successfully.</div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Request</h2>
          <dl class="detail-list">
            <div><dt>Requested by</dt><dd><?= htmlspecialchars((string) ($entry['RequestedBy'] ?? '—')) ?></dd></div>
            <div><dt>Request date</dt><dd><?= htmlspecialchars(enhancement_log_format_date((string) ($entry['RequestDate'] ?? ''))) ?></dd></div>
            <div><dt>Due date</dt><dd><?= htmlspecialchars(enhancement_log_format_date((string) ($entry['ReqDueDate'] ?? ''))) ?></dd></div>
            <div><dt>Created</dt><dd><?= htmlspecialchars(enhancement_log_format_datetime((string) ($entry['CreateDate'] ?? ''))) ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(enhancement_log_format_datetime((string) ($entry['ModifiedDate'] ?? ''))) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Description</h2>
          <dl class="detail-list">
            <div>
              <dt>Enhancement description</dt>
              <dd class="is-prose"><?= trim((string) ($entry['EnhDesc'] ?? '')) !== '' ? htmlspecialchars((string) $entry['EnhDesc']) : '—' ?></dd>
            </div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Notes</h2>
          <dl class="detail-list">
            <div>
              <dt>Request notes</dt>
              <dd class="is-prose"><?= trim((string) ($entry['ReqNotes'] ?? '')) !== '' ? htmlspecialchars((string) $entry['ReqNotes']) : '—' ?></dd>
            </div>
          </dl>
        </section>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
