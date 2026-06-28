<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/labeling.php';

label_require_read();

$activeSlug = 'labeling-operations';
$activeLabelSection = 'templates';
$scopeFilter = $_GET['scope'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'scope' => $scopeFilter !== '' ? $scopeFilter : null,
    'q'     => $search !== '' ? $search : null,
] + table_sort_state(LABEL_TEMPLATE_LIST_SORT_COLUMNS, 'scope', 'asc', $_GET);
$templates = label_list_templates($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = label_page_title('Label Templates');
$pageDescription = 'Track labels for each customer and SKU.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = label_can_create() ? '<a class="btn-primary" href="/labeling-operations/templates/new.php">New Template</a>' : '';
      render_list_page_header([
          'back_href'  => '/labeling-operations/',
          'back_label' => 'Back to ' . label_module_title(),
          'category'   => 'Label Templates',
          'title'      => 'Customer & SKU Labels',
          'lead'       => 'Track approved label definitions for each customer SKU and internal label catalog entries.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/labeling-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Label template created successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/labeling-operations/templates/">
        <?php table_sort_hidden_inputs($listFilters, 'scope', 'asc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="scope">Scope</label>
            <select class="form-input" id="scope" name="scope">
              <option value="">All scopes</option>
              <?php foreach (LABEL_SCOPES as $scope): ?>
              <option value="<?= htmlspecialchars($scope) ?>" <?= $scopeFilter === $scope ? 'selected' : '' ?>><?= htmlspecialchars($scope) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Customer, SKU, or label name" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/labeling-operations/templates/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                LABEL_TEMPLATE_LIST_SORT_COLUMNS,
                '/labeling-operations/templates',
                $listFilters,
                ['scope', 'q'],
                [],
                'scope',
                'asc',
                '',
                'View'
            ); ?>
          </thead>
          <tbody>
            <?php if ($templates === []): ?>
            <tr><td colspan="7">No label templates found.</td></tr>
            <?php else: ?>
            <?php foreach ($templates as $template): ?>
            <tr>
              <td><?= htmlspecialchars($template['LabelScope']) ?></td>
              <td><?= htmlspecialchars($template['CustomerName'] ?? '—') ?></td>
              <td><?= htmlspecialchars($template['SKU']) ?></td>
              <td><?= htmlspecialchars($template['LabelName']) ?></td>
              <td><?= htmlspecialchars($template['CurrentVersionNo'] ?? '—') ?></td>
              <td><?= htmlspecialchars($template['TemplateStatus']) ?></td>
              <?php table_actions_cell([
                  ['href' => '/labeling-operations/templates/view.php?id=' . (int) $template['TemplateID'], 'label' => 'View'],
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
