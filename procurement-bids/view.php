<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_read();

$initiativeId = (int) ($_GET['id'] ?? 0);
$initiative = $initiativeId > 0 ? bid_initiative_get($initiativeId) : null;
if ($initiative === null) {
    http_response_code(404);
    exit('Initiative not found.');
}

$activeSlug = 'procurement-bids';
$bids = bid_estimate_list_for_initiative($initiativeId);
$notice = $_GET['notice'] ?? null;
$canAward = bid_can_update() && !in_array((string) $initiative['Status'], ['Cancelled', 'Closed'], true);

$pageTitle = $initiative['InitiativeNumber'] . ' | Initiatives & Bids';
$pageDescription = 'Initiative details and supplier bid estimates.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/procurement-bids/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Back to Initiatives & Bids
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Procurement</div>
          <h1><?= htmlspecialchars($initiative['InitiativeNumber']) ?> — <?= htmlspecialchars($initiative['Title']) ?></h1>
          <p class="page-lead"><?= $initiative['Description'] !== null && $initiative['Description'] !== '' ? nl2br(htmlspecialchars((string) $initiative['Description'])) : 'No description provided.' ?></p>
          <p class="permission-note">
            Status:
            <span class="status-badge <?= bid_initiative_status_class((string) $initiative['Status']) ?>"><?= htmlspecialchars($initiative['Status']) ?></span>
            · Owner: <?= htmlspecialchars($initiative['OwnerName'] ?? '—') ?>
            · Category: <?= htmlspecialchars($initiative['Category'] ?? '—') ?>
            · Budget: <?= $initiative['BudgetAmount'] !== null ? htmlspecialchars(accounting_format_money($initiative['BudgetAmount'])) : '—' ?>
          </p>
        </div>
        <div class="admin-actions">
          <?php if (bid_can_update()): ?>
          <a class="btn-secondary" href="/procurement-bids/edit.php?id=<?= $initiativeId ?>">Edit Initiative</a>
          <?php endif; ?>
          <?php if (bid_can_create() && !in_array((string) $initiative['Status'], ['Awarded', 'Cancelled', 'Closed'], true)): ?>
          <a class="btn-primary" href="/procurement-bids/bid-new.php?initiative_id=<?= $initiativeId ?>">Add Bid</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Initiative created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Changes saved.</div>
      <?php elseif ($notice === 'bid_created'): ?>
      <div class="admin-notice is-success" role="status">Bid added successfully.</div>
      <?php elseif ($notice === 'bid_updated'): ?>
      <div class="admin-notice is-success" role="status">Bid updated successfully.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded.</div>
      <?php elseif ($notice === 'awarded'): ?>
      <div class="admin-notice is-success" role="status">
        Bid awarded. Draft/estimate supplier invoice
        <?php if (!empty($_GET['invoice_id'])): ?>
        <a href="/accounting/supplier-invoices/view.php?id=<?= (int) $_GET['invoice_id'] ?>">#<?= (int) $_GET['invoice_id'] ?></a>
        <?php endif; ?>
        was created. Create a payment request only after goods or services are delivered.
      </div>
      <?php endif; ?>

      <section class="detail-card">
        <h2>Bids / Estimates</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Vendor</th>
                <th>Amount</th>
                <th>Submitted</th>
                <th>Status</th>
                <th>Files</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($bids === []): ?>
              <tr><td colspan="6">No bids yet. Add supplier estimates to compare and award.</td></tr>
              <?php else: ?>
              <?php foreach ($bids as $bid): ?>
              <tr>
                <td>
                  <?= htmlspecialchars($bid['VendorName']) ?>
                  <?php if (!empty($bid['SupplierCode'])): ?>
                  <div class="form-hint"><?= htmlspecialchars($bid['SupplierCode']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(accounting_format_money($bid['BidAmount'])) ?> <?= htmlspecialchars($bid['CurrencyCode'] ?? 'USD') ?></td>
                <td><?= htmlspecialchars(supplier_invoice_normalize_form_date($bid['SubmittedDate'] ?? null) ?: '—') ?></td>
                <td><span class="status-badge <?= bid_estimate_status_class((string) $bid['Status']) ?>"><?= htmlspecialchars($bid['Status']) ?></span></td>
                <td><?= (int) $bid['AttachmentCount'] ?></td>
                <td class="table-actions">
                  <a class="btn-text" href="/procurement-bids/bid-edit.php?id=<?= (int) $bid['BidEstimateID'] ?>">Open</a>
                  <?php if ($canAward && (string) $bid['Status'] !== 'Selected' && (string) $bid['Status'] !== 'Withdrawn'): ?>
                  <form method="post" action="/procurement-bids/award.php" class="inline-form" onsubmit="return confirm('Award this bid? This creates or links a Supplier and a Draft/Estimate Supplier Invoice. No payment request will be created.');">
                    <input type="hidden" name="bid_id" value="<?= (int) $bid['BidEstimateID'] ?>" />
                    <button type="submit" class="btn-text">Select / Award</button>
                  </form>
                  <?php endif; ?>
                  <?php if (!empty($bid['AwardedSupplierInvoiceID'])): ?>
                  <a class="btn-text" href="/accounting/supplier-invoices/view.php?id=<?= (int) $bid['AwardedSupplierInvoiceID'] ?>">Invoice</a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
