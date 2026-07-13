<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_read();

$bidId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$bid = $bidId > 0 ? bid_estimate_get($bidId) : null;
if ($bid === null) {
    http_response_code(404);
    exit('Bid not found.');
}

$initiativeId = (int) $bid['InitiativeID'];
$isSelected = (string) $bid['Status'] === 'Selected';
$canEdit = bid_can_update() && !$isSelected;
$activeSlug = 'procurement-bids';
$error = null;
$notice = $_GET['notice'] ?? null;
$suppliers = supplier_list(['status' => 'active']);
$form = bid_estimate_to_form($bid);
$attachments = bid_estimate_list_attachments($bidId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $form = bid_estimate_from_input($_POST);
    $result = bid_estimate_save($initiativeId, $_POST, $bidId);
    if ($result['ok']) {
        header('Location: /procurement-bids/bid-edit.php?id=' . $bidId . '&notice=bid_updated', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Bid | ' . $bid['InitiativeNumber'];
$pageDescription = 'Bid/estimate detail and attachments.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/procurement-bids/view.php?id=<?= $initiativeId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Back to Initiative
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Procurement</div>
          <h1><?= htmlspecialchars($bid['VendorName']) ?></h1>
          <p class="page-lead">
            <?= htmlspecialchars($bid['InitiativeNumber']) ?> — <?= htmlspecialchars($bid['InitiativeTitle']) ?>
            · <span class="status-badge <?= bid_estimate_status_class((string) $bid['Status']) ?>"><?= htmlspecialchars($bid['Status']) ?></span>
          </p>
        </div>
        <div class="admin-actions">
          <?php if ($canEdit): ?>
          <form method="post" action="/procurement-bids/award.php" onsubmit="return confirm('Award this bid? Creates/links Supplier and a Draft/Estimate Supplier Invoice. No payment request.');">
            <input type="hidden" name="bid_id" value="<?= $bidId ?>" />
            <button type="submit" class="btn-primary">Select / Award Bid</button>
          </form>
          <?php endif; ?>
          <?php if (!empty($bid['AwardedSupplierInvoiceID'])): ?>
          <a class="btn-secondary" href="/accounting/supplier-invoices/view.php?id=<?= (int) $bid['AwardedSupplierInvoiceID'] ?>">Open Draft Invoice</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($notice === 'bid_created'): ?>
      <div class="admin-notice is-success" role="status">Bid created. Upload the estimate file below if available.</div>
      <?php elseif ($notice === 'bid_updated'): ?>
      <div class="admin-notice is-success" role="status">Bid updated.</div>
      <?php elseif ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/procurement-bids/bid-edit.php?id=<?= $bidId ?>">
        <input type="hidden" name="id" value="<?= $bidId ?>" />
        <?php $isLocked = !$canEdit; require dirname(__DIR__) . '/includes/bid-estimate-form.php'; ?>
        <?php if ($canEdit): ?>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Save Bid</button>
        </div>
        <?php endif; ?>
      </form>

      <section class="detail-card" style="margin-top: 24px;">
        <h2>Estimate / quote files</h2>
        <?php if ($attachments === []): ?>
        <p class="page-lead">No files attached yet.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>File</th>
                <th>Type</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attachments as $file): ?>
              <tr>
                <td><a class="btn-text" href="/procurement-bids/attachment.php?id=<?= (int) $file['AttachmentID'] ?>"><?= htmlspecialchars($file['FileName']) ?></a></td>
                <td><?= htmlspecialchars(bid_estimate_attachment_kind_label((string) $file['AttachmentKind'])) ?></td>
                <td><?= htmlspecialchars(bid_estimate_format_file_size((int) $file['FileSizeBytes'])) ?></td>
                <td><?= htmlspecialchars(admin_format_datetime($file['UploadDate'])) ?></td>
                <td><?= htmlspecialchars((string) $file['UploadedByName']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($canEdit): ?>
        <form class="admin-form" method="post" enctype="multipart/form-data" action="/procurement-bids/upload-attachment.php" style="margin-top: 16px;">
          <input type="hidden" name="bid_id" value="<?= $bidId ?>" />
          <?php
            $uploadFieldId = 'attachment';
            $uploadLabel = 'Upload estimate / quote';
            $uploadTitle = 'Drop, paste, or choose file';
            $uploadHint = 'Drag a PDF or image here, paste a screenshot, or choose a file';
            require dirname(__DIR__) . '/includes/file-upload-dropzone-field.php';
          ?>
          <div class="form-group" style="margin-top: 12px;">
            <label for="attachment_kind">Attachment type</label>
            <select class="form-input" id="attachment_kind" name="attachment_kind">
              <?php foreach (BID_ESTIMATE_ATTACHMENT_KINDS as $kind => $label): ?>
              <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn-secondary btn-small">Upload attachment</button>
        </form>
        <?php endif; ?>
      </section>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
