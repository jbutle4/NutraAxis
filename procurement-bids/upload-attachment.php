<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /procurement-bids/', true, 302);
    exit;
}

$bidId = (int) ($_POST['bid_id'] ?? 0);
$kind = trim((string) ($_POST['attachment_kind'] ?? 'Estimate'));
$result = bid_estimate_save_attachment($bidId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /procurement-bids/bid-edit.php?id=' . $bidId . '&notice=attachment', true, 302);
    exit;
}

$bid = $bidId > 0 ? bid_estimate_get($bidId) : null;
$backHref = $bid !== null
    ? '/procurement-bids/bid-edit.php?id=' . $bidId
    : '/procurement-bids/';

$pageTitle = 'Upload Bid Attachment | Procurement';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error'] ?? 'Upload failed.') ?></div>
      <a class="btn-secondary" href="<?= htmlspecialchars($backHref) ?>">Back</a>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
