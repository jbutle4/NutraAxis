<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /procurement-bids/', true, 302);
    exit;
}

$bidId = (int) ($_POST['bid_id'] ?? 0);
$result = bid_award_estimate($bidId);

if ($result['ok']) {
    $bid = bid_estimate_get($bidId);
    $initiativeId = $bid !== null ? (int) $bid['InitiativeID'] : 0;
    $params = [
        'id'         => $initiativeId,
        'notice'     => 'awarded',
        'invoice_id' => (int) ($result['invoice_id'] ?? 0),
    ];
    header('Location: /procurement-bids/view.php?' . http_build_query($params), true, 302);
    exit;
}

$bid = $bidId > 0 ? bid_estimate_get($bidId) : null;
$backHref = $bid !== null
    ? '/procurement-bids/view.php?id=' . (int) $bid['InitiativeID']
    : '/procurement-bids/';

$pageTitle = 'Award Bid | Procurement';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error'] ?? 'Unable to award bid.') ?></div>
      <a class="btn-secondary" href="<?= htmlspecialchars($backHref) ?>">Back</a>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
