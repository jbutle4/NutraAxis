<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'versions';
$search = trim($_GET['q'] ?? '');
$versions = label_list_versions($search !== '' ? $search : null);

$pageTitle = label_page_title('Label Version Control');
$pageDescription = 'Track label revisions for customer and internal labels.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/labeling-operations/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to <?= htmlspecialchars(label_module_title()) ?>
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Version Control</div>
          <h1>Label Revisions</h1>
          <p class="page-lead">Full revision history across customer and internal label templates.</p>
        </div>
      </div>

      <form class="po-filter audit-filter" method="get" action="/labeling-operations/versions/">
        <div class="audit-filter-grid">
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer, SKU, label name, or version number" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Search</button>
          <a class="btn-secondary" href="/labeling-operations/versions/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Version</th>
              <th>Scope</th>
              <th>Customer</th>
              <th>SKU</th>
              <th>Label</th>
              <th>Status</th>
              <th>Revision Notes</th>
              <th>Created</th>
              <th>View</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($versions === []): ?>
            <tr><td colspan="9">No label versions found.</td></tr>
            <?php else: ?>
            <?php foreach ($versions as $version): ?>
            <tr>
              <td><?= htmlspecialchars($version['VersionNumber']) ?></td>
              <td><?= htmlspecialchars($version['LabelScope']) ?></td>
              <td><?= htmlspecialchars($version['CustomerName'] ?? '—') ?></td>
              <td><?= htmlspecialchars($version['SKU']) ?></td>
              <td><?= htmlspecialchars($version['LabelName']) ?></td>
              <td><?= htmlspecialchars($version['VersionStatus']) ?></td>
              <td><?= htmlspecialchars($version['RevisionNotes'] ?? '—') ?></td>
              <td><?= htmlspecialchars(label_format_datetime($version['CreateDate'])) ?></td>
              <?php table_actions_cell([
                  ['href' => '/labeling-operations/templates/view.php?id=' . (int) $version['TemplateID'], 'label' => 'View'],
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
