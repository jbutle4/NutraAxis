<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_read('users');

$activeAdminSection = 'users';
$canCreate = auth_can_create(ADMIN_PERMISSION_COLUMNS['users']);
$canUpdate = auth_can_update(ADMIN_PERMISSION_COLUMNS['users']);
$canDelete = auth_can_delete(ADMIN_PERMISSION_COLUMNS['users']);
$listFilters = table_sort_state(ADMIN_USERS_LIST_SORT_COLUMNS, 'name', 'asc', $_GET);
$users = admin_list_users($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Users | Site Admin | NutraAxis Operations';
$pageDescription = 'Manage NutraAxis Operations user accounts.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/site-admin/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Site Admin
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Site Admin</div>
          <h1>Users</h1>
          <p class="page-lead">Operations portal accounts and role assignments.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(auth_permission_value('UserAdmin'))) ?></p>
        </div>
        <?php if ($canCreate): ?>
        <a class="btn-primary" href="/site-admin/users/new.php">New User</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">User created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">User updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">User deleted successfully.</div>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php
            $userActionHeader = null;
            if ($canUpdate || $canDelete) {
                $userActionHeader = table_actions_header(array_filter([
                    $canUpdate ? 'Edit' : null,
                    $canDelete ? 'Delete' : null,
                ]));
            }
            table_sort_render_head_row(
                ADMIN_USERS_LIST_SORT_COLUMNS,
                '/site-admin/users',
                $listFilters,
                [],
                [],
                'name',
                'asc',
                'last_login',
                $userActionHeader
            );
            ?>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['UserName']) ?></td>
              <td><?= htmlspecialchars($user['UserLogin']) ?></td>
              <td><?= htmlspecialchars($user['RoleName']) ?></td>
              <td><?= htmlspecialchars(admin_format_datetime($user['LastLoginDate'])) ?></td>
              <?php if ($canUpdate || $canDelete): ?>
              <?php
              $userActions = [];
              if ($canUpdate) {
                  $userActions[] = ['href' => '/site-admin/users/edit.php?id=' . (int) $user['UserID'], 'label' => 'Edit'];
              }
              if ($canDelete) {
                  $userActions[] = [
                      'html' => table_action_delete_form(
                          '/site-admin/users/delete.php',
                          ['user_id' => (int) $user['UserID']],
                          'Delete this user? This cannot be undone.'
                      ),
                  ];
              }
              table_actions_cell($userActions);
              ?>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
