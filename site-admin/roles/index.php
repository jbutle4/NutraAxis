<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_read('roles');

$activeAdminSection = 'roles';
$canCreate = auth_can_create(ADMIN_PERMISSION_COLUMNS['roles']);
$canUpdate = auth_can_update(ADMIN_PERMISSION_COLUMNS['roles']);
$canDelete = auth_can_delete(ADMIN_PERMISSION_COLUMNS['roles']);
$listFilters = table_sort_state(ADMIN_ROLES_LIST_SORT_COLUMNS, 'role', 'asc', $_GET);
$roles = admin_list_roles($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Roles | Site Admin | NutraAxis Operations';
$pageDescription = 'Manage NutraAxis Operations roles and permissions.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      $listToolbar = $canCreate ? '<a class="btn-primary" href="/site-admin/roles/new.php">New Role</a>' : '';
      render_list_page_header([
          'back_href'  => '/site-admin/',
          'back_label' => 'Back to Site Admin',
          'category'   => 'Site Admin',
          'title'      => 'Roles',
          'lead'       => 'Role definitions and CRUD permissions per module and admin area.',
          'permission' => permission_label(auth_permission_value('RoleAdmin')),
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Role created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Role updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Role deleted successfully.</div>
      <?php endif; ?>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <?php
              foreach (ADMIN_ROLES_LIST_SORT_COLUMNS as $column => $label) {
                  table_sort_render_th(
                      $column,
                      $label,
                      '/site-admin/roles',
                      ADMIN_ROLES_LIST_SORT_COLUMNS,
                      $listFilters,
                      [],
                      [],
                      'role',
                      'asc'
                  );
              }
              ?>
              <th>Permissions</th>
              <th><?= htmlspecialchars(table_actions_header($canUpdate ? ['View', 'Edit'] : ['View'])) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roles as $role): ?>
            <tr>
              <td><strong><?= htmlspecialchars($role['RoleName']) ?></strong></td>
              <td><?= htmlspecialchars($role['RoleDesc'] ?? '—') ?></td>
              <td class="admin-perm-summary"><?= htmlspecialchars(admin_role_permission_summary($role)) ?></td>
              <?php
              $roleActions = $canUpdate
                  ? [
                      ['href' => '/site-admin/roles/view.php?id=' . (int) $role['RoleID'], 'label' => 'View'],
                      ['href' => '/site-admin/roles/edit.php?id=' . (int) $role['RoleID'], 'label' => 'Edit'],
                  ]
                  : [['href' => '/site-admin/roles/view.php?id=' . (int) $role['RoleID'], 'label' => 'View']];
              if ($canDelete) {
                  $roleActions[] = [
                      'html' => table_action_delete_form(
                          '/site-admin/roles/delete.php',
                          ['role_id' => (int) $role['RoleID']],
                          'Delete this role? This cannot be undone.'
                      ),
                  ];
              }
              table_actions_cell($roleActions);
              ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
