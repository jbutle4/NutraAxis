<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';

auth_require_site_admin();

$activeAdminSection = 'home';
$canUsers = auth_can_read(ADMIN_PERMISSION_COLUMNS['users']);
$canRoles = auth_can_read(ADMIN_PERMISSION_COLUMNS['roles']);
$canAudit = $canUsers;

$pageTitle = 'Site Admin | NutraAxis Operations';
$pageDescription = 'Manage NutraAxis Operations users and roles.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Administration',
          'title'      => 'Site Admin',
          'lead'       => 'User and role management for NutraAxis Operations.',
      ]); ?>

      <?php require dirname(__DIR__) . '/includes/admin-nav.php'; ?>

      <div class="capability-grid">
        <?php if ($canUsers): ?>
        <a class="capability-card capability-card-link" href="/site-admin/users/">
          <h3>User Administration</h3>
          <p>Create, view, update, and deactivate Operations portal accounts.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(auth_permission_value('UserAdmin'))) ?></p>
          <span class="function-link">
            Manage Users
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </span>
        </a>
        <?php endif; ?>

        <?php if ($canRoles): ?>
        <a class="capability-card capability-card-link" href="/site-admin/roles/">
          <h3>Role Administration</h3>
          <p>Define roles and assign CRUD permissions per module and admin area.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(auth_permission_value('RoleAdmin'))) ?></p>
          <span class="function-link">
            Manage Roles
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </span>
        </a>
        <?php endif; ?>

        <?php if ($canAudit): ?>
        <a class="capability-card capability-card-link" href="/site-admin/audit-log/">
          <h3>Audit Change Log</h3>
          <p>Review insert, update, and delete activity and roll back changes when needed.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(auth_permission_value('UserAdmin'))) ?></p>
          <span class="function-link">
            View Audit Log
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </span>
        </a>
        <?php endif; ?>
      </div>

      <div class="module-actions">
        <a class="btn-secondary" href="/">All Applications</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
