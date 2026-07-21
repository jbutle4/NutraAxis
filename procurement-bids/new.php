<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_create();

$activeSlug = 'procurement-bids';
$error = null;
$form = [
    'title'             => '',
    'description'       => '',
    'category'          => '',
    'owner_user_id'     => (string) (auth_user()['UserID'] ?? ''),
    'target_award_date' => '',
    'budget_amount'     => '',
    'status'            => 'Open for Bids',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = bid_initiative_from_input($_POST);
    $result = bid_initiative_save($_POST);
    if ($result['ok']) {
        header('Location: /procurement-bids/view.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'New Initiative | Procurement';
$pageDescription = 'Create a light RFP initiative for supplier bids.';

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
          <h1>New Initiative</h1>
          <p class="page-lead">Describe the work you are soliciting bids for. This does not create a purchase order.</p>
        </div>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/procurement-bids/new.php">
        <?php require dirname(__DIR__) . '/includes/bid-initiative-form.php'; ?>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Create Initiative</button>
          <a class="btn-secondary" href="/procurement-bids/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
