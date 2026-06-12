<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$templateId = (int) ($_GET['id'] ?? 0);
$template = $templateId > 0 ? label_get_template($templateId) : null;

if ($template === null) {
    header('Location: /labeling-operations/templates/', true, 302);
    exit;
}

$activeSlug = 'labeling-operations';
$activeLabelSection = 'templates';
$versions = label_list_template_versions($templateId);
$notice = $_GET['notice'] ?? null;

$pageTitle = label_page_title('Label Template');

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/labeling-operations/templates/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Label Templates
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Label Template</div>
          <h1><?= htmlspecialchars($template['LabelName']) ?></h1>
          <p class="page-lead"><?= htmlspecialchars($template['LabelScope']) ?> label for SKU <?= htmlspecialchars($template['SKU']) ?>.</p>
        </div>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Label template created successfully.</div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Template Details</h2>
          <dl class="detail-list">
            <div><dt>Scope</dt><dd><?= htmlspecialchars($template['LabelScope']) ?></dd></div>
            <div><dt>Customer</dt><dd><?= htmlspecialchars($template['CustomerName'] ?? '—') ?></dd></div>
            <div><dt>SKU</dt><dd><?= htmlspecialchars($template['SKU']) ?></dd></div>
            <div><dt>Current Version</dt><dd><?= htmlspecialchars($template['CurrentVersionNo'] ?? '—') ?></dd></div>
            <div><dt>Status</dt><dd><?= htmlspecialchars($template['TemplateStatus']) ?></dd></div>
            <div><dt>Created</dt><dd><?= htmlspecialchars(label_format_datetime($template['CreateDate'])) ?> by <?= htmlspecialchars($template['CreatedByName']) ?></dd></div>
          </dl>
          <?php if (!empty($template['Notes'])): ?>
          <p><?= nl2br(htmlspecialchars($template['Notes'])) ?></p>
          <?php endif; ?>
        </section>

        <section class="detail-card">
          <h2>Version History</h2>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Version</th>
                  <th>Status</th>
                  <th>Revision Notes</th>
                  <th>Approved</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($versions === []): ?>
                <tr><td colspan="4">No versions recorded.</td></tr>
                <?php else: ?>
                <?php foreach ($versions as $version): ?>
                <tr>
                  <td><?= htmlspecialchars($version['VersionNumber']) ?></td>
                  <td><?= htmlspecialchars($version['VersionStatus']) ?></td>
                  <td><?= htmlspecialchars($version['RevisionNotes'] ?? '—') ?></td>
                  <td><?= $version['ApprovedDate'] ? htmlspecialchars(label_format_datetime($version['ApprovedDate'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
