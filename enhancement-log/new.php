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

$pageTitle = 'New Backlog Item | IT Product Backlog';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/enhancement-log/',
          'back_label' => 'Back to IT Product Backlog',
          'category'   => 'Operations',
          'title'      => 'New Backlog Item',
          'lead'       => 'Add a new item to the IT product backlog.',
      ]);
      ?>

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
