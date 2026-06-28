<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';
require dirname(__DIR__) . '/includes/catalog-attachments.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

catalog_require_read();

$skuId = (int) ($_GET['id'] ?? 0);
$sku = $skuId > 0 ? catalog_get_sku($skuId) : null;

if ($sku === null) {
    header('Location: /product-catalog/', true, 302);
    exit;
}

$activeSlug = 'product-catalog';
$notice = $_GET['notice'] ?? null;
$error = isset($_GET['error']) ? qbo_humanize_error((string) $_GET['error']) : null;
$warning = $_GET['warning'] ?? null;
$attachments = catalog_list_attachments($skuId);
$qboSyncBlockers = catalog_can_update() ? catalog_qbo_sync_blockers($sku) : [];

$pageTitle = $sku['SKUCode'] . ' | Product Catalog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      ob_start();
      ?>
          <?php if (catalog_can_update()): ?>
          <a class="btn-primary" href="/product-catalog/edit.php?id=<?= $skuId ?>">Edit</a>
          <?php if (qbo_is_connected()): ?>
          <form method="post" action="/product-catalog/sync-qbo.php" class="inline-form" onsubmit="return confirm('Sync this SKU to QuickBooks as a product item?');">
            <input type="hidden" name="sku_id" value="<?= $skuId ?>" />
            <button type="submit" class="btn-secondary">Sync to QuickBooks</button>
          </form>
          <?php else: ?>
          <a class="btn-secondary" href="/accounting/">Connect QuickBooks</a>
          <?php endif; ?>
          <?php endif; ?>
          <?php if (catalog_can_delete()): ?>
          <form method="post" action="/product-catalog/delete.php" class="inline-form" onsubmit="return confirm('Delete this SKU from the catalog?');">
            <input type="hidden" name="sku_id" value="<?= $skuId ?>" />
            <button type="submit" class="btn-text btn-text-danger">Delete</button>
          </form>
          <?php endif; ?>
      <?php
      $listToolbar = trim(ob_get_clean());
      $catalogLead = '<span class="status-badge ' . catalog_status_class($sku['SKUStatus']) . '">' . htmlspecialchars($sku['SKUStatus']) . '</span> · ' . htmlspecialchars($sku['SKUCode']) . ' · ' . htmlspecialchars($sku['Brand']);
      render_list_page_header([
          'back_href'  => '/product-catalog/',
          'back_label' => 'Back to SKU Master',
          'category'   => 'SKU',
          'title'      => $sku['ProductName'],
          'lead'       => $catalogLead,
          'lead_html'  => true,
      ]);
      ?>

      <?php if ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">SKU saved successfully.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php elseif ($notice === 'qbo_synced'): ?>
      <div class="admin-notice is-success" role="status">SKU synced to QuickBooks successfully.</div>
      <?php elseif ($notice === 'qbo_reconciled'): ?>
      <div class="admin-notice is-success" role="status">QuickBooks item linked successfully.</div>
      <?php endif; ?>
      <?php if ($warning !== null): ?>
      <div class="admin-notice is-warning" role="status"><?= htmlspecialchars($warning) ?></div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($qboSyncBlockers !== []): ?>
      <div class="admin-notice is-warning" role="status">
        <strong>QuickBooks sync is not ready.</strong>
        Set the following on the
        <a href="/product-catalog/edit.php?id=<?= $skuId ?>#qbo-settings">Edit SKU</a>
        page under <em>QuickBooks inventory item</em>:
        <ul class="notice-list">
          <?php foreach ($qboSyncBlockers as $blocker): ?>
          <li><?= htmlspecialchars($blocker) ?></li>
          <?php endforeach; ?>
        </ul>
        Account pickers use the cached chart of accounts from
        <a href="/accounting/chart-of-accounts.php">Accounting → Chart of Accounts</a>
        (run the QBO COA sync job if lists are empty).
      </div>
      <?php endif; ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Product details</h2>
          <dl class="detail-list">
            <div><dt>SKU code</dt><dd><?= htmlspecialchars($sku['SKUCode']) ?></dd></div>
            <div><dt>Brand</dt><dd><?= htmlspecialchars($sku['Brand']) ?></dd></div>
            <div><dt>Manufacturer</dt><dd><?= htmlspecialchars($sku['Manufacturer']) ?></dd></div>
            <div>
              <dt>Supplier</dt>
              <dd>
                <?php if (!empty($sku['SupplierID']) && !empty($sku['SupplierName'])): ?>
                <a href="/supplier-management/view.php?id=<?= (int) $sku['SupplierID'] ?>"><?= htmlspecialchars($sku['SupplierName']) ?></a>
                <?php if (!empty($sku['SupplierCode'])): ?>
                (<?= htmlspecialchars($sku['SupplierCode']) ?>)
                <?php endif; ?>
                <?php else: ?>
                —
                <?php endif; ?>
              </dd>
            </div>
            <div><dt>Primary category</dt><dd><?= htmlspecialchars($sku['PrimaryTherapeuticCategory']) ?></dd></div>
            <div><dt>Secondary category</dt><dd><?= htmlspecialchars($sku['SecondaryCategory'] ?? '—') ?></dd></div>
            <div><dt>Label selection</dt><dd><?= htmlspecialchars($sku['LabelSelection'] ?? '—') ?></dd></div>
            <div><dt>Launch date</dt><dd><?= htmlspecialchars(catalog_format_date($sku['LaunchDate'])) ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(admin_format_datetime($sku['ModifiedDate'])) ?><?= !empty($sku['ModifiedByName']) ? ' by ' . htmlspecialchars($sku['ModifiedByName']) : '' ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Packaging &amp; identifiers</h2>
          <dl class="detail-list">
            <div><dt>Capsule count</dt><dd><?= $sku['CapsuleCount'] !== null ? (int) $sku['CapsuleCount'] : '—' ?></dd></div>
            <div><dt>Servings per container</dt><dd><?= $sku['ServingCount'] !== null ? (int) $sku['ServingCount'] : '—' ?></dd></div>
            <div><dt>Bottle size</dt><dd><?= htmlspecialchars($sku['BottleSize'] ?? '—') ?></dd></div>
            <div><dt>GTIN-14</dt><dd><?= htmlspecialchars($sku['GTIN14'] ?? '—') ?></dd></div>
            <div><dt>UPC (GTIN-12)</dt><dd><?= htmlspecialchars($sku['UPC'] ?? '—') ?></dd></div>
            <div><dt>SKU case barcode</dt><dd><?= htmlspecialchars($sku['SKUCaseBarcode'] ?? '—') ?></dd></div>
            <div><dt>Product each weight</dt><dd><?= htmlspecialchars(catalog_format_weight($sku['ProductEachWeightLbs'] ?? null)) ?></dd></div>
            <div><dt>Product case weight</dt><dd><?= htmlspecialchars(catalog_format_weight($sku['ProductCaseWeightLbs'] ?? null)) ?></dd></div>
            <div><dt>Supplement facts panel</dt><dd><?= htmlspecialchars($sku['SupplementFactsPanel'] ?? '—') ?></dd></div>
            <div><dt>Non-GMO certified</dt><dd><?= !empty($sku['NonGMOCertified']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>Allergen statement</dt><dd><?= htmlspecialchars(catalog_format_allergens($sku['AllergenStatement'] ?? null)) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>QuickBooks</h2>
          <dl class="detail-list">
            <div><dt>Sync status</dt><dd><?= htmlspecialchars(catalog_qbo_sync_status_label((string) ($sku['QBO_SyncStatus'] ?? 'NotSynced'))) ?></dd></div>
            <div><dt>QBO item ID</dt><dd><?= htmlspecialchars($sku['QBO_ItemID'] ?? '—') ?></dd></div>
            <div><dt>Display name</dt><dd><?= htmlspecialchars($sku['QBO_DisplayName'] ?? catalog_build_qbo_item_name($sku) ?: '—') ?></dd></div>
            <div><dt>Taxable</dt><dd><?= !array_key_exists('QBO_Taxable', $sku) || !empty($sku['QBO_Taxable']) ? 'Yes' : 'No' ?></dd></div>
            <div><dt>Income account</dt><dd><?= htmlspecialchars(trim(($sku['QBO_IncomeAccountRefName'] ?? '') . ($sku['QBO_IncomeAccountRefValue'] ?? '' ? ' (' . $sku['QBO_IncomeAccountRefValue'] . ')' : '')) ?: '—') ?></dd></div>
            <div><dt>COGS account</dt><dd><?= htmlspecialchars(trim(($sku['QBO_ExpenseAccountRefName'] ?? '') . ($sku['QBO_ExpenseAccountRefValue'] ?? '' ? ' (' . $sku['QBO_ExpenseAccountRefValue'] . ')' : '')) ?: '—') ?></dd></div>
            <div><dt>Inventory asset account</dt><dd><?= htmlspecialchars(trim(($sku['QBO_AssetAccountRefName'] ?? '') . ($sku['QBO_AssetAccountRefValue'] ?? '' ? ' (' . $sku['QBO_AssetAccountRefValue'] . ')' : '')) ?: '—') ?></dd></div>
            <div><dt>Purchase description</dt><dd><?= htmlspecialchars($sku['QBO_PurchaseDesc'] ?? '—') ?></dd></div>
            <div><dt>Last synced</dt><dd><?= !empty($sku['QBO_SyncedAt']) ? htmlspecialchars(admin_format_datetime($sku['QBO_SyncedAt'])) : '—' ?></dd></div>
            <?php if (!empty($sku['QBO_SyncError'])): ?>
            <div><dt>Sync error</dt><dd><?= htmlspecialchars(qbo_humanize_error((string) $sku['QBO_SyncError'])) ?></dd></div>
            <?php endif; ?>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Pricing</h2>
          <dl class="detail-list">
            <div><dt>COGS</dt><dd><?= htmlspecialchars(catalog_format_money($sku['COGS'])) ?></dd></div>
            <div><dt>Wholesale price</dt><dd><?= htmlspecialchars(catalog_format_money($sku['WholesalePrice'])) ?></dd></div>
            <div><dt>MSRP</dt><dd><?= htmlspecialchars(catalog_format_money($sku['MSRP'])) ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Documents &amp; notes</h2>
          <dl class="detail-list">
            <div>
              <dt>SFP link</dt>
              <dd>
                <?php if (!empty($sku['SFPLink'])): ?>
                <?php
                  $sfpLabel = trim((string) ($sku['SupplementFactsPanel'] ?? ''));
                  if ($sfpLabel === '') {
                      $sfpLabel = catalog_link_display_label($sku['SFPLink'], 'supplement facts');
                  }
                ?>
                <a class="detail-external-link" href="<?= htmlspecialchars($sku['SFPLink']) ?>" target="_blank" rel="noopener noreferrer">View <?= htmlspecialchars($sfpLabel) ?></a>
                <?php else: ?>
                —
                <?php endif; ?>
              </dd>
            </div>
            <div>
              <dt>Label (print-ready) link</dt>
              <dd>
                <?php if (!empty($sku['LabelPrintReadyLink'])): ?>
                <?php $labelLinkName = catalog_link_display_label($sku['LabelPrintReadyLink'], 'product label'); ?>
                <a class="detail-external-link" href="<?= htmlspecialchars($sku['LabelPrintReadyLink']) ?>" target="_blank" rel="noopener noreferrer">View <?= htmlspecialchars($labelLinkName) ?></a>
                <?php else: ?>
                —
                <?php endif; ?>
              </dd>
            </div>
          </dl>
          <?php if (!empty($sku['Formulation'])): ?>
          <h3 class="production-line-header">Formulation</h3>
          <p><?= nl2br(htmlspecialchars($sku['Formulation'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($sku['Product'])): ?>
          <h3 class="production-line-header">Product</h3>
          <p><?= nl2br(htmlspecialchars($sku['Product'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($sku['Claims'])): ?>
          <h3 class="production-line-header">Claims</h3>
          <p><?= nl2br(htmlspecialchars($sku['Claims'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($sku['Directions'])): ?>
          <h3 class="production-line-header">Directions</h3>
          <p><?= nl2br(htmlspecialchars($sku['Directions'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($sku['CertsOnLabel'])): ?>
          <h3 class="production-line-header">Certs on label</h3>
          <p><?= nl2br(htmlspecialchars($sku['CertsOnLabel'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($sku['Notes'])): ?>
          <h3 class="production-line-header">Notes</h3>
          <p><?= nl2br(htmlspecialchars($sku['Notes'])) ?></p>
          <?php endif; ?>
        </section>
      </div>

      <?php
        $showUploadForm = catalog_can_update();
        $uploadReturnPath = '/product-catalog/view.php?id=' . $skuId;
        $uploadNotice = $notice;
        require dirname(__DIR__) . '/includes/catalog-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
