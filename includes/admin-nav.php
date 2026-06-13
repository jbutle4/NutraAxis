<?php
/** @var string $activeAdminSection users|roles|audit|home */
$activeAdminSection = $activeAdminSection ?? 'home';
$canUsers = auth_can_read(ADMIN_PERMISSION_COLUMNS['users']);
$canRoles = auth_can_read(ADMIN_PERMISSION_COLUMNS['roles']);
$canAudit = $canUsers;
?>
<nav class="admin-nav" aria-label="Site Admin">
  <a href="/site-admin/" class="<?= $activeAdminSection === 'home' ? 'is-active' : '' ?>">Overview</a>
  <?php if ($canUsers): ?>
  <a href="/site-admin/users/" class="<?= $activeAdminSection === 'users' ? 'is-active' : '' ?>">Users</a>
  <?php endif; ?>
  <?php if ($canRoles): ?>
  <a href="/site-admin/roles/" class="<?= $activeAdminSection === 'roles' ? 'is-active' : '' ?>">Roles</a>
  <?php endif; ?>
  <?php if ($canAudit): ?>
  <a href="/site-admin/audit-log/" class="<?= $activeAdminSection === 'audit' ? 'is-active' : '' ?>">Audit Log</a>
  <?php endif; ?>
</nav>
