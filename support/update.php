<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/support.php';
require dirname(__DIR__) . '/includes/zendesk.php';

support_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /support/', true, 302);
    exit;
}

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$result = zendesk_update_ticket($ticketId, $_POST);

if ($result['ok']) {
    header('Location: /support/view.php?id=' . $ticketId . '&notice=updated', true, 302);
    exit;
}

$activeSlug = 'support';
$pageTitle = 'Update Failed | Support';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/support/view.php?id=<?= $ticketId ?>">Back to Ticket</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
