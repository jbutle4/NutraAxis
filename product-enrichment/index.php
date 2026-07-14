<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_read();

$activeSlug = 'product-enrichment';
$publishedFilter = $_GET['published'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'published' => $publishedFilter !== '' ? $publishedFilter : null,
    'q'         => $search !== '' ? $search : null,
] + table_sort_state(PRODUCT_ENRICHMENT_LIST_SORT_COLUMNS, 'sku', 'asc', $_GET);
$records = product_enrichment_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Product Page Enrichment | NutraAxis Operations';
$pageDescription = 'Manage product page HTML and information sheet PDFs for nutraaxislabs.com.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = '';
      if (product_enrichment_can_create()) {
          $listToolbar .= '<a class="btn-primary" href="/product-enrichment/new.php">New enrichment</a>';
      }
      if (product_enrichment_can_update()) {
          $listToolbar .= ($listToolbar !== '' ? ' ' : '')
              . '<form class="inline-form" method="post" action="/product-enrichment/import-from-site.php" style="display:inline-block;margin-left:8px;"'
              . ' onsubmit="return confirm(\'Import enrichment HTML and PDFs from the live site for all default SKUs?\');">'
              . '<input type="hidden" name="publish" value="1" />'
              . '<button type="submit" class="btn-secondary">Import from live site</button>'
              . '</form>';
      }
      render_list_page_header([
          'back_href'  => '/product-master/',
          'back_label' => 'Back to Product Master',
          'category'   => 'Products',
          'title'      => 'Product Page Enrichment',
          'lead'       => 'Manage PDP enrichment HTML and information sheet PDFs served dynamically on nutraaxislabs.com product pages.',
          'permission' => permission_label(product_enrichment_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Product enrichment created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Product enrichment updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Product enrichment deleted successfully.</div>
      <?php elseif ($notice === 'imported'): ?>
      <div class="admin-notice is-success" role="status">Imported enrichment HTML and PDFs from nutraaxislabs.com.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/product-enrichment/">
        <?php table_sort_hidden_inputs($listFilters, 'sku', 'asc'); ?>
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
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="SKU, product, or link text" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/product-enrichment/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                PRODUCT_ENRICHMENT_LIST_SORT_COLUMNS,
                '/product-enrichment',
                $listFilters,
                ['published', 'q'],
                [],
                'sku',
                'asc',
                '',
                table_actions_header(product_enrichment_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($records === []): ?>
            <tr><td colspan="6">No product enrichment records match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($records as $record): ?>
            <tr>
              <td><code><?= htmlspecialchars((string) $record['SKUCode']) ?></code></td>
              <td><?= htmlspecialchars((string) ($record['ProductName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($record['PdfLinkText'] ?? '—')) ?></td>
              <td>
                <?php if (!empty($record['Publish'])): ?>
                <span class="status-badge status-approved">Yes</span>
                <?php else: ?>
                <span class="status-badge status-draft">No</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars(admin_format_datetime($record['ModifiedDate'] ?? null)) ?></td>
              <?php table_view_edit_cell(
                  '/product-enrichment/view.php?id=' . (int) $record['ProductEnrichmentID'],
                  '/product-enrichment/edit.php?id=' . (int) $record['ProductEnrichmentID'],
                  product_enrichment_can_update()
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
