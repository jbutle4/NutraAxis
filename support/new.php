<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/support.php';
require dirname(__DIR__) . '/includes/zendesk.php';

support_require_create();

$activeSlug = 'support';
$error = null;
$form = [
    'subject'  => '',
    'body'     => '',
    'priority' => 'normal',
];
$configError = zendesk_config_error();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'subject'  => trim($_POST['subject'] ?? ''),
        'body'     => trim($_POST['body'] ?? ''),
        'priority' => strtolower(trim($_POST['priority'] ?? 'normal')),
    ];

    if ($configError !== null) {
        $error = $configError;
    } else {
        $result = zendesk_create_ticket($form);
        if ($result['ok']) {
            header('Location: /support/view.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
            exit;
        }
        $error = $result['error'];
    }
}

$pageTitle = 'New Ticket | Support';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/support/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Tickets
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Support</div>
          <h1>New Zendesk Ticket</h1>
          <p class="page-lead">Create a support request in Zendesk. It will be submitted under <?= htmlspecialchars(support_user_email()) ?>.</p>
        </div>
      </div>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($configError === null): ?>
      <?php
        $formAction = '/support/new.php';
        require dirname(__DIR__) . '/includes/support-ticket-form.php';
      ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
