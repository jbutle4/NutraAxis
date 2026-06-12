<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_delete('roles');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /site-admin/roles/', true, 302);
    exit;
}

$roleId = (int) ($_POST['role_id'] ?? 0);
$result = admin_delete_role($roleId);

if ($result['ok']) {
    header('Location: /site-admin/roles/?notice=deleted', true, 302);
    exit;
}

$pageTitle = 'Delete Role | Site Admin | NutraAxis Operations';
$activeAdminSection = 'roles';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/site-admin/roles/">Back to Roles</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
