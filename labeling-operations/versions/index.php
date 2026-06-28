<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'versions';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'q' => $search !== '' ? $search : null,
] + table_sort_state(LABEL_VERSION_LIST_SORT_COLUMNS, 'created', 'desc', $_GET);
$versions = label_list_versions($listFilters);

$pageTitle = label_page_title('Label Version Control');
$pageDescription = 'Track label revisions for customer and internal labels.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/labeling-operations/',
          'back_label' => 'Back to ' . label_module_title(),
          'category'   => 'Version Control',
          'title'      => 'Label Revisions',
          'lead'       => 'Full revision history across customer and internal label templates.',
      ]); ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/labeling-operations/versions/">
        <?php table_sort_hidden_inputs($listFilters, 'created', 'desc'); ?>
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
            <?php table_sort_render_head_row(
                LABEL_VERSION_LIST_SORT_COLUMNS,
                '/labeling-operations/versions',
                $listFilters,
                ['q'],
                [],
                'created',
                'desc',
                'created',
                'View'
            ); ?>
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
