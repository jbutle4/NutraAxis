<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_create();

$initiativeId = (int) ($_GET['initiative_id'] ?? $_POST['initiative_id'] ?? 0);
$initiative = $initiativeId > 0 ? bid_initiative_get($initiativeId) : null;
if ($initiative === null) {
    http_response_code(404);
    exit('Initiative not found.');
}

$activeSlug = 'procurement-bids';
$error = null;
$suppliers = supplier_list(['status' => 'active']);
$form = [
    'supplier_id'    => '',
    'vendor_name'    => '',
    'contact_name'   => '',
    'contact_email'  => '',
    'contact_phone'  => '',
    'bid_amount'     => '',
    'currency_code'  => 'USD',
    'submitted_date' => date('Y-m-d'),
    'valid_until'    => '',
    'notes'          => '',
    'status'         => 'Received',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = bid_estimate_from_input($_POST);
    $result = bid_estimate_save($initiativeId, $_POST);
    if ($result['ok']) {
        $bidId = (int) $result['id'];
        $notice = 'bid_created';
        $file = $_FILES['attachment'] ?? null;
        if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $kind = trim((string) ($_POST['attachment_kind'] ?? 'Estimate'));
            if (!array_key_exists($kind, BID_ESTIMATE_ATTACHMENT_KINDS)) {
                $kind = 'Estimate';
            }
            $uploaded = bid_estimate_save_attachment($bidId, $file, $kind);
            $notice = $uploaded['ok'] ? 'bid_created_with_file' : 'bid_created_file_failed';
        }
        header(
            'Location: /procurement-bids/bid-edit.php?id=' . $bidId . '&notice=' . rawurlencode($notice),
            true,
            302
        );
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Add Bid | ' . $initiative['InitiativeNumber'];
$pageDescription = 'Add a supplier bid/estimate to this initiative.';

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
          <h1>Add Bid — <?= htmlspecialchars($initiative['InitiativeNumber']) ?></h1>
          <p class="page-lead"><?= htmlspecialchars($initiative['Title']) ?></p>
        </div>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" enctype="multipart/form-data" action="/procurement-bids/bid-new.php?initiative_id=<?= $initiativeId ?>">
        <input type="hidden" name="initiative_id" value="<?= $initiativeId ?>" />
        <?php require dirname(__DIR__) . '/includes/bid-estimate-form.php'; ?>

        <div class="form-grid" style="margin-top: 8px;">
          <?php
            $uploadFieldId = 'attachment';
            $uploadLabel = 'Estimate / invoice file (optional)';
            $uploadTitle = 'Drop, paste, or choose file';
            $uploadHint = 'Drag a PDF or image here, paste a screenshot, or choose a file';
            $uploadFormHint = 'You can also add more files after saving.';
            require dirname(__DIR__) . '/includes/file-upload-dropzone-field.php';
          ?>
          <div class="form-group">
            <label for="attachment_kind">Attachment type</label>
            <select class="form-input" id="attachment_kind" name="attachment_kind">
              <?php foreach (BID_ESTIMATE_ATTACHMENT_KINDS as $kind => $label): ?>
              <option value="<?= htmlspecialchars($kind) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">Save Bid</button>
          <a class="btn-secondary" href="/procurement-bids/view.php?id=<?= $initiativeId ?>">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
