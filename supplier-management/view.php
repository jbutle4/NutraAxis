<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/supplier.php';

supplier_require_read();

$supplierId = (int) ($_GET['id'] ?? 0);
$supplier = $supplierId > 0 ? supplier_get($supplierId) : null;

if ($supplier === null) {
    header('Location: /supplier-management/', true, 302);
    exit;
}

$activeSlug = 'supplier-management';
$notice = $_GET['notice'] ?? null;
$error = $_GET['error'] ?? null;
$isActive = !empty($supplier['IsActive']);
$supplierPurchaseOrders = supplier_list_purchase_orders($supplierId);

$pageTitle = $supplier['SupplierName'] . ' | Supplier Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/supplier-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Supplier Management
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Supplier</div>
          <h1><?= htmlspecialchars($supplier['SupplierName']) ?></h1>
          <p class="page-lead">
            <span class="status-badge <?= supplier_status_class($isActive) ?>"><?= htmlspecialchars(supplier_status_label($isActive)) ?></span>
            · <?= htmlspecialchars($supplier['SupplierCode'] ?? '—') ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php if (supplier_can_update()): ?>
          <a class="btn-primary" href="/supplier-management/edit.php?id=<?= $supplierId ?>">Edit</a>
          <?php endif; ?>
          <?php if (supplier_can_delete()): ?>
          <form method="post" action="/supplier-management/status.php" class="inline-form" onsubmit="return confirm('<?= $isActive ? 'Deactivate this supplier? It will no longer appear in new PO supplier lists.' : 'Reactivate this supplier?' ?>');">
            <input type="hidden" name="supplier_id" value="<?= $supplierId ?>" />
            <input type="hidden" name="is_active" value="<?= $isActive ? '0' : '1' ?>" />
            <button type="submit" class="btn-text <?= $isActive ? 'btn-text-danger' : '' ?>"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'created' || $notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Supplier saved successfully.</div>
      <?php endif; ?>
      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="detail-grid">
        <section class="detail-card">
          <h2>Supplier details</h2>
          <dl class="detail-list">
            <div><dt>Supplier code</dt><dd><?= htmlspecialchars($supplier['SupplierCode'] ?? '—') ?></dd></div>
            <div><dt>Supplier type</dt><dd><?= htmlspecialchars($supplier['SupplierType'] ?? '—') ?></dd></div>
            <div><dt>Address</dt><dd><?= htmlspecialchars($supplier['Address'] ?? '—') ?></dd></div>
            <div><dt>Purchase orders</dt><dd><?= (int) $supplier['POCount'] ?></dd></div>
            <div><dt>Last modified</dt><dd><?= htmlspecialchars(admin_format_datetime($supplier['ModifiedDate'])) ?><?= !empty($supplier['ModifiedByName']) ? ' by ' . htmlspecialchars($supplier['ModifiedByName']) : '' ?></dd></div>
          </dl>
        </section>

        <section class="detail-card">
          <h2>Contact</h2>
          <dl class="detail-list">
            <div><dt>Contact name</dt><dd><?= htmlspecialchars($supplier['ContactName'] ?? '—') ?></dd></div>
            <div><dt>Email</dt><dd><?= htmlspecialchars($supplier['ContactEmail'] ?? '—') ?></dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($supplier['ContactPhone'] ?? '—') ?></dd></div>
          </dl>
        </section>

        <?php if (!empty($supplier['Notes'])): ?>
        <section class="detail-card">
          <h2>Notes</h2>
          <p><?= nl2br(htmlspecialchars($supplier['Notes'])) ?></p>
        </section>
        <?php endif; ?>
      </div>

      <?php require dirname(__DIR__) . '/includes/supplier-po-report.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
