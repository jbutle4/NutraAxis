<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_create('roles');

$activeAdminSection = 'roles';
$error = null;
$role = [
    'RoleName' => '',
    'RoleDesc' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = [
        'RoleName' => $_POST['role_name'] ?? '',
        'RoleDesc' => $_POST['role_desc'] ?? '',
    ];

    $result = admin_save_role(array_merge($_POST, [
        'role_name' => $role['RoleName'],
        'role_desc' => $role['RoleDesc'],
    ]));

    if ($result['ok']) {
        header('Location: /site-admin/roles/?notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Role | Site Admin | NutraAxis Operations';
$pageDescription = 'Create a new NutraAxis Operations role.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/site-admin/roles/',
          'back_label' => 'Back to Roles',
          'category'   => 'Site Admin',
          'title'      => 'New Role',
          'lead'       => 'Define a role and assign CRUD permissions.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/site-admin/roles/new.php">
        <div class="form-group">
          <label for="role_name">Role name</label>
          <input class="form-input" type="text" id="role_name" name="role_name" value="<?= htmlspecialchars($role['RoleName']) ?>" required />
        </div>

        <div class="form-group">
          <label for="role_desc">Description</label>
          <textarea class="form-input form-textarea" id="role_desc" name="role_desc" rows="3"><?= htmlspecialchars($role['RoleDesc']) ?></textarea>
        </div>

        <h2 class="admin-form-subhead">Permissions</h2>
        <p class="form-hint">Select Create, Read, Update, and Delete access for each area.</p>

        <?php $editable = true; require dirname(__DIR__, 2) . '/includes/admin-permission-grid.php'; ?>

        <div class="module-actions">
          <button class="btn-primary" type="submit">Create Role</button>
          <a class="btn-secondary" href="/site-admin/roles/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
