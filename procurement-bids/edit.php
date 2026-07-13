<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/bid-initiative.php';

bid_require_update();

$initiativeId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$initiative = $initiativeId > 0 ? bid_initiative_get($initiativeId) : null;
if ($initiative === null) {
    http_response_code(404);
    exit('Initiative not found.');
}

$activeSlug = 'procurement-bids';
$error = null;
$form = bid_initiative_to_form($initiative);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = bid_initiative_from_input($_POST);
    $result = bid_initiative_save($_POST, $initiativeId);
    if ($result['ok']) {
        header('Location: /procurement-bids/view.php?id=' . $initiativeId . '&notice=updated', true, 302);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Edit Initiative | Procurement';
$pageDescription = 'Update initiative details.';

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
          <h1>Edit <?= htmlspecialchars($initiative['InitiativeNumber']) ?></h1>
          <p class="page-lead"><?= htmlspecialchars($initiative['Title']) ?></p>
        </div>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/procurement-bids/edit.php?id=<?= $initiativeId ?>">
        <input type="hidden" name="id" value="<?= $initiativeId ?>" />
        <?php require dirname(__DIR__) . '/includes/bid-initiative-form.php'; ?>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Save Changes</button>
          <a class="btn-secondary" href="/procurement-bids/view.php?id=<?= $initiativeId ?>">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
