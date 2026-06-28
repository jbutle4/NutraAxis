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
      <?php
      render_list_page_header([
          'back_href'  => '/support/',
          'back_label' => 'Back to Tickets',
          'category'   => 'Support',
          'title'      => 'New Zendesk Ticket',
          'lead'       => 'Create a support request in Zendesk. It will be submitted under ' . support_user_email() . '.',
      ]);
      ?>

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
