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
      <?php
      render_list_page_header([
          'back_href'  => '/labeling-operations/templates/',
          'back_label' => 'Back to Label Templates',
          'category'   => 'Label Template',
          'title'      => $template['LabelName'],
          'lead'       => $template['LabelScope'] . ' label for SKU ' . $template['SKU'] . '.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

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
