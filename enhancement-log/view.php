<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';
require dirname(__DIR__) . '/includes/enhancement-log-attachments.php';

enhancement_log_require_read();

$logId = (int) ($_GET['id'] ?? 0);
$entry = $logId > 0 ? enhancement_log_get($logId) : null;

if ($entry === null) {
    header('Location: /enhancement-log/', true, 302);
    exit;
}

$activeSlug = 'enhancement-log';
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Backlog Item #' . $logId . ' | IT Product Backlog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      ob_start();
      if (enhancement_log_can_update()):
      ?>
      <a class="btn-primary" href="/enhancement-log/edit.php?id=<?= $logId ?>">Edit</a>
      <?php endif;
      $pageToolbar = ob_get_clean();

      render_list_page_header([
          'back_href'  => '/enhancement-log/',
          'back_label' => 'Back to IT Product Backlog',
          'category'   => 'Operations',
          'title'      => (string) $entry['EnhancementTitle'],
          'lead'       => 'Log #' . (int) $entry['EnhancementLogID'] . ' · <span class="status-badge ' . enhancement_log_status_class((string) $entry['RequestStatus']) . '">' . htmlspecialchars(enhancement_log_status_label((string) $entry['RequestStatus'])) . '</span>',
          'lead_html'  => true,
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Backlog item created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Backlog item updated successfully.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Screenshot uploaded successfully.</div>
      <?php elseif ($notice === 'attachment_deleted'): ?>
      <div class="admin-notice is-success" role="status">Screenshot deleted successfully.</div>
      <?php endif; ?>

      <?php render_list_page_toolbar($pageToolbar); ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Request</h2>
          <dl class="detail-list detail-list-inline">
            <div><dt>Type</dt><dd><?= htmlspecialchars((string) ($entry['EnhType'] ?? '—')) ?></dd></div>
            <div><dt>IT product</dt><dd><?= htmlspecialchars((string) ($entry['ITProduct'] ?? '—')) ?></dd></div>
            <div><dt>Priority</dt><dd><?= htmlspecialchars((string) ($entry['Priority'] ?? '—')) ?></dd></div>
            <div><dt>Impact</dt><dd><?= htmlspecialchars((string) ($entry['Impact'] ?? '—')) ?></dd></div>
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
              <dt>Description</dt>
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

      <?php
        $showUploadForm = enh_log_can_add_attachments();
        require dirname(__DIR__) . '/includes/enhancement-log-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
