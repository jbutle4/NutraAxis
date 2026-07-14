<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/coa.php';

coa_require_read();

$activeSlug = 'coa-management';
$publishedFilter = $_GET['published'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'published' => $publishedFilter !== '' ? $publishedFilter : null,
    'q'         => $search !== '' ? $search : null,
] + table_sort_state(COA_LIST_SORT_COLUMNS, 'product', 'asc', $_GET);
$records = coa_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'COA Management | NutraAxis Operations';
$pageDescription = 'Manage Certificates of Analysis published on nutraaxislabs.com.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = coa_can_create() ? '<a class="btn-primary" href="/coa-management/new.php">New COA</a>' : '';
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to home',
          'category'   => 'Supply Chain',
          'title'      => 'Manage our COAs',
          'lead'       => 'Upload COA PDFs and metadata for the public Certificates of Analysis table on nutraaxislabs.com/our-coas.',
          'permission' => permission_label(coa_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">COA created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">COA updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">COA deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/coa-management/">
        <?php table_sort_hidden_inputs($listFilters, 'product', 'asc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="published">Publish</label>
            <select class="form-input" id="published" name="published">
              <option value="">All</option>
              <option value="1" <?= $publishedFilter === '1' ? 'selected' : '' ?>>Yes</option>
              <option value="0" <?= $publishedFilter === '0' ? 'selected' : '' ?>>No</option>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Product or lot number" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/coa-management/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                COA_LIST_SORT_COLUMNS,
                '/coa-management',
                $listFilters,
                ['published', 'q'],
                COA_LIST_SORT_NUMERIC,
                'product',
                'asc',
                '',
                table_actions_header(coa_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($records === []): ?>
            <tr><td colspan="7">No COA documents match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($records as $record): ?>
            <tr>
              <td><?= htmlspecialchars((string) $record['ProductName']) ?></td>
              <td><?= htmlspecialchars((string) $record['LotNumber']) ?></td>
              <td><?= htmlspecialchars(coa_format_expiration_display($record)) ?></td>
              <td>
                <?php if (!empty($record['Publish'])): ?>
                <span class="status-badge status-approved">Yes</span>
                <?php else: ?>
                <span class="status-badge status-draft">No</span>
                <?php endif; ?>
              </td>
              <td><?= (int) ($record['SortOrder'] ?? 0) ?></td>
              <td><?= htmlspecialchars(admin_format_datetime($record['ModifiedDate'] ?? null)) ?></td>
              <?php table_view_edit_cell(
                  '/coa-management/view.php?id=' . (int) $record['CoaDocumentID'],
                  '/coa-management/edit.php?id=' . (int) $record['CoaDocumentID'],
                  coa_can_update()
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
