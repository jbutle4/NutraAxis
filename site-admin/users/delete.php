<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_delete('users');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /site-admin/users/', true, 302);
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$result = admin_delete_user($userId);

if ($result['ok']) {
    header('Location: /site-admin/users/?notice=deleted', true, 302);
    exit;
}

$pageTitle = 'Delete User | Site Admin | NutraAxis Operations';
$activeAdminSection = 'users';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/site-admin/users/">Back to Users</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
