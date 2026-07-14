<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/product-enrichment.php';

product_enrichment_require_read();

$activeSlug = 'product-enrichment';
$id = (int) ($_GET['id'] ?? 0);
$record = product_enrichment_get($id);

if ($record === null) {
    http_response_code(404);
    exit('Product enrichment record not found.');
}

$notice = $_GET['notice'] ?? null;
$sku = (string) ($record['SKUCode'] ?? '');
$pageTitle = 'Product Enrichment Detail | NutraAxis Operations';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/product-enrichment/',
          'back_label' => 'Back to Product Page Enrichment',
          'category'   => 'Products',
          'title'      => htmlspecialchars((string) ($record['ProductName'] ?? $sku)),
          'lead'       => 'SKU ' . htmlspecialchars($sku),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Product enrichment created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Product enrichment updated successfully.</div>
      <?php endif; ?>

      <div class="module-actions">
        <?php if (product_enrichment_can_update()): ?>
        <a class="btn-primary" href="/product-enrichment/edit.php?id=<?= $id ?>">Edit</a>
        <?php endif; ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(product_enrichment_admin_pdf_url($id)) ?>" target="_blank" rel="noopener noreferrer">Open PDF</a>
        <?php if (!empty($record['Publish'])): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(product_enrichment_public_api_url($sku)) ?>" target="_blank" rel="noopener noreferrer">View public API</a>
        <?php endif; ?>
      </div>

      <div class="detail-grid">
        <div class="detail-card">
          <h2>Enrichment details</h2>
          <dl class="detail-list">
            <div><dt>SKU</dt><dd><code><?= htmlspecialchars($sku) ?></code></dd></div>
            <div><dt>Product</dt><dd><?= htmlspecialchars((string) ($record['ProductName'] ?? '—')) ?></dd></div>
            <div><dt>PDF link text</dt><dd><?= htmlspecialchars((string) ($record['PdfLinkText'] ?? '—')) ?></dd></div>
            <div><dt>Publish</dt><dd><?= !empty($record['Publish']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>File</dt><dd><?= htmlspecialchars((string) ($record['FileName'] ?? '—')) ?></dd></div>
            <div><dt>Public PDF URL</dt><dd><code><?= htmlspecialchars(product_enrichment_public_pdf_url($sku)) ?></code></dd></div>
            <div><dt>Admin PDF URL</dt><dd><code><?= htmlspecialchars(product_enrichment_admin_pdf_url($id)) ?></code></dd></div>
            <div><dt>Public API URL</dt><dd><code><?= htmlspecialchars(product_enrichment_public_api_url($sku)) ?></code></dd></div>
          </dl>
        </div>

        <div class="detail-card">
          <h2>Audit</h2>
          <dl class="detail-list">
            <div><dt>Created</dt><dd><?= htmlspecialchars(admin_format_datetime($record['CreateDate'] ?? null)) ?><?= !empty($record['CreatedByName']) ? ' · ' . htmlspecialchars((string) $record['CreatedByName']) : '' ?></dd></div>
            <div><dt>Modified</dt><dd><?= htmlspecialchars(admin_format_datetime($record['ModifiedDate'] ?? null)) ?><?= !empty($record['ModifiedByName']) ? ' · ' . htmlspecialchars((string) $record['ModifiedByName']) : '' ?></dd></div>
            <?php if (trim((string) ($record['Notes'] ?? '')) !== ''): ?>
            <div><dt>Notes</dt><dd><?= nl2br(htmlspecialchars((string) $record['Notes'])) ?></dd></div>
            <?php endif; ?>
          </dl>
        </div>
      </div>

      <?php if (trim((string) ($record['EnrichmentHtml'] ?? '')) !== ''): ?>
      <div class="detail-card">
        <h2>Rendered preview</h2>
        <div class="detail-preview-frame">
          <?= product_enrichment_render_html($record, product_enrichment_admin_pdf_url($id)) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (product_enrichment_can_delete()): ?>
      <form class="module-actions" method="post" action="/product-enrichment/delete.php" onsubmit="return confirm('Delete this enrichment record permanently?');">
        <input type="hidden" name="id" value="<?= $id ?>" />
        <button type="submit" class="btn-secondary">Delete enrichment</button>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
