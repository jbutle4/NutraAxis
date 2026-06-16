<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/catalog.php';

catalog_require_read();

$activeSlug = 'product-catalog';
$statusFilter = $_GET['status'] ?? '';
$brandFilter = $_GET['brand'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$search = trim($_GET['q'] ?? '');
$skus = catalog_list_skus([
    'status'   => $statusFilter !== '' ? $statusFilter : null,
    'brand'    => $brandFilter !== '' ? $brandFilter : null,
    'category' => $categoryFilter !== '' ? $categoryFilter : null,
    'q'        => $search !== '' ? $search : null,
]);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Product SKU Master | Inventory Management';
$pageDescription = 'View and manage the master product catalog and SKU reference data.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Inventory</div>
          <h1>Product SKU Master</h1>
          <p class="page-lead">Maintain SKU codes, product attributes, pricing, and catalog data used across NutraAxis operations.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(catalog_permission_value())) ?></p>
        </div>
        <?php if (catalog_can_create()): ?>
        <a class="btn-primary" href="/product-catalog/new.php">New SKU</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">SKU created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">SKU updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">SKU deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/product-catalog/">
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (CATALOG_SKU_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="brand">Brand</label>
            <select class="form-input" id="brand" name="brand">
              <option value="">All brands</option>
              <?php foreach (CATALOG_BRANDS as $brand): ?>
              <option value="<?= htmlspecialchars($brand) ?>" <?= $brandFilter === $brand ? 'selected' : '' ?>><?= htmlspecialchars($brand) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="category">Category</label>
            <select class="form-input" id="category" name="category">
              <option value="">All categories</option>
              <?php foreach (CATALOG_THERAPEUTIC_CATEGORIES as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="SKU code, product name, GTIN, or UPC" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/product-catalog/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table admin-table--catalog">
          <thead>
            <tr>
              <th>SKU code</th>
              <th>Product name</th>
              <th>UPC</th>
              <th>Brand</th>
              <th>Category</th>
              <th>Status</th>
              <th>Serving count</th>
              <th>MSRP</th>
              <th><?= htmlspecialchars(table_actions_header(catalog_can_update() ? ['View', 'Edit'] : ['View'])) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($skus === []): ?>
            <tr><td colspan="9">No SKUs match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($skus as $sku): ?>
            <tr>
              <td><?= htmlspecialchars($sku['SKUCode']) ?></td>
              <td><?= htmlspecialchars($sku['ProductName']) ?></td>
              <td><?= !empty($sku['UPC']) ? htmlspecialchars($sku['UPC']) : '—' ?></td>
              <td><?= htmlspecialchars($sku['Brand']) ?></td>
              <td><?= htmlspecialchars($sku['PrimaryTherapeuticCategory']) ?></td>
              <td><span class="status-badge <?= catalog_status_class($sku['SKUStatus']) ?>"><?= htmlspecialchars($sku['SKUStatus']) ?></span></td>
              <td><?= $sku['ServingCount'] !== null ? (int) $sku['ServingCount'] : '—' ?></td>
              <td><?= htmlspecialchars(catalog_format_money($sku['MSRP'])) ?></td>
              <?php table_view_edit_cell(
                  '/product-catalog/view.php?id=' . (int) $sku['SKUID'],
                  '/product-catalog/edit.php?id=' . (int) $sku['SKUID'],
                  catalog_can_update()
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
