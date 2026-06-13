<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/enhancement-log.php';

enhancement_log_require_create();

$activeSlug = 'enhancement-log';
$error = null;
$user = auth_user();
$defaultRequester = is_array($user) ? trim((string) ($user['UserName'] ?? '')) : '';

$form = enhancement_log_from_input([
    'request_status' => 'New',
    'requested_by'   => $defaultRequester,
    'request_date'   => (new DateTimeImmutable('now', new DateTimeZone('America/Chicago')))->format('Y-m-d'),
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = enhancement_log_from_input($_POST);
    $result = enhancement_log_save($_POST);

    if ($result['ok']) {
        header('Location: /enhancement-log/view.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Enhancement | Enhancement Log';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/enhancement-log/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Enhancement Log
      </a>

      <div class="page-hero">
        <div class="section-label">Operations</div>
        <h1>New Enhancement</h1>
        <p class="page-lead">Log a new portal enhancement request.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/enhancement-log/new.php';
        require dirname(__DIR__) . '/includes/enhancement-log-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
