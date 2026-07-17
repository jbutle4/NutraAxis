<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/coa.php';

coa_require_read();

$activeSlug = 'coa-management';
$id = (int) ($_GET['id'] ?? 0);
$record = coa_get($id);

if ($record === null) {
    http_response_code(404);
    exit('COA document not found.');
}

$notice = $_GET['notice'] ?? null;
$pageTitle = 'COA Detail | COA Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/coa-management/',
          'back_label' => 'Back to COA Management',
          'category'   => 'Quality',
          'title'      => htmlspecialchars((string) $record['ProductName']),
          'lead'       => 'Lot ' . htmlspecialchars((string) $record['LotNumber']),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">COA created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">COA updated successfully.</div>
      <?php endif; ?>

      <div class="module-actions">
        <?php if (coa_can_update()): ?>
        <a class="btn-primary" href="/coa-management/edit.php?id=<?= $id ?>">Edit</a>
        <?php endif; ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(coa_public_pdf_url($id)) ?>" target="_blank" rel="noopener noreferrer">Open PDF</a>
        <?php if (!empty($record['Publish'])): ?>
        <a class="btn-secondary" href="https://www.nutraaxislabs.com/our-coas" target="_blank" rel="noopener noreferrer">View public page</a>
        <?php endif; ?>
      </div>

      <div class="detail-grid">
        <div class="detail-card">
          <h2>Document details</h2>
          <dl class="detail-list">
            <div><dt>Product</dt><dd><?= htmlspecialchars((string) $record['ProductName']) ?></dd></div>
            <div><dt>Lot number</dt><dd><?= htmlspecialchars((string) $record['LotNumber']) ?></dd></div>
            <div><dt>Expiration date</dt><dd><?= htmlspecialchars(coa_format_date((string) ($record['ExpirationDate'] ?? ''))) ?></dd></div>
            <div><dt>Public display</dt><dd><?= htmlspecialchars(coa_format_expiration_display($record)) ?></dd></div>
            <div><dt>Publish</dt><dd><?= !empty($record['Publish']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>Sort order</dt><dd><?= (int) ($record['SortOrder'] ?? 0) ?></dd></div>
            <div><dt>File</dt><dd><?= htmlspecialchars((string) ($record['FileName'] ?? '—')) ?></dd></div>
            <div><dt>Public PDF URL</dt><dd><code><?= htmlspecialchars(coa_public_pdf_url($id)) ?></code></dd></div>
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

      <?php if (coa_can_delete()): ?>
      <form class="module-actions" method="post" action="/coa-management/delete.php" onsubmit="return confirm('Delete this COA permanently?');">
        <input type="hidden" name="id" value="<?= $id ?>" />
        <button type="submit" class="btn-secondary">Delete COA</button>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
