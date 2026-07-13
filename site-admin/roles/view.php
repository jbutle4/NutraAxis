<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_read('roles');

$roleId = (int) ($_GET['id'] ?? 0);
$role = admin_get_role($roleId);

if ($role === null) {
    http_response_code(404);
    $pageTitle = 'Role Not Found | Site Admin';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Role not found</h1><div class="module-actions"><a class="btn-secondary" href="/site-admin/roles/">Back to Roles</a></div></div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

$activeAdminSection = 'roles';
$pageTitle = htmlspecialchars($role['RoleName']) . ' | Roles | Site Admin';
$pageDescription = 'View role permissions for NutraAxis Operations.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = auth_can_update(ADMIN_PERMISSION_COLUMNS['roles'])
          ? '<a class="btn-primary" href="/site-admin/roles/edit.php?id=' . $roleId . '">Edit Role</a><a class="btn-secondary" href="/site-admin/roles/">Back to Roles</a>'
          : '<a class="btn-secondary" href="/site-admin/roles/">Back to Roles</a>';
      render_list_page_header([
          'back_href'  => '/site-admin/roles/',
          'back_label' => 'Back to Roles',
          'category'   => 'Site Admin',
          'title'      => $role['RoleName'],
          'lead'       => $role['RoleDesc'] ?? 'No description provided.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <?php $editable = false; require dirname(__DIR__, 2) . '/includes/admin-permission-grid.php'; ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
