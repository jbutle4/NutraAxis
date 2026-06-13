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
      <a class="breadcrumb" href="/site-admin/roles/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Roles
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Site Admin</div>
        <h1><?= htmlspecialchars($role['RoleName']) ?></h1>
        <p class="page-lead"><?= htmlspecialchars($role['RoleDesc'] ?? 'No description provided.') ?></p>
      </div>

      <?php $editable = false; require dirname(__DIR__, 2) . '/includes/admin-permission-grid.php'; ?>

      <div class="module-actions">
        <?php if (auth_can_update(ADMIN_PERMISSION_COLUMNS['roles'])): ?>
        <a class="btn-primary" href="/site-admin/roles/edit.php?id=<?= $roleId ?>">Edit Role</a>
        <?php endif; ?>
        <a class="btn-secondary" href="/site-admin/roles/">Back to Roles</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
