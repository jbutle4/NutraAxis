<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';

auth_require_admin_create('users');

$activeAdminSection = 'users';
$error = null;
$form = [
    'user_name'          => '',
    'user_login'         => '',
    'user_password'      => '',
    'user_assigned_role' => '',
    'is_po_approver'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'user_name'          => $_POST['user_name'] ?? '',
        'user_login'         => $_POST['user_login'] ?? '',
        'user_password'      => $_POST['user_password'] ?? '',
        'user_assigned_role' => $_POST['user_assigned_role'] ?? '',
        'is_po_approver'     => $_POST['is_po_approver'] ?? '',
    ];

    $result = admin_save_user($form);
    if ($result['ok']) {
        header('Location: /site-admin/users/?notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New User | Site Admin | NutraAxis Operations';
$pageDescription = 'Create a new NutraAxis Operations user account.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/site-admin/users/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Users
      </a>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <div class="page-hero">
        <div class="section-label">Site Admin</div>
        <h1>New User</h1>
        <p class="page-lead">Create a new Operations portal account.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/site-admin/users/new.php">
        <div class="form-group">
          <label for="user_name">Full name</label>
          <input class="form-input" type="text" id="user_name" name="user_name" value="<?= htmlspecialchars($form['user_name']) ?>" required />
        </div>

        <div class="form-group">
          <label for="user_login">Email</label>
          <input class="form-input" type="email" id="user_login" name="user_login" value="<?= htmlspecialchars($form['user_login']) ?>" required />
        </div>

        <div class="form-group">
          <label for="user_password">Password</label>
          <input class="form-input" type="password" id="user_password" name="user_password" required />
        </div>

        <div class="form-group">
          <label for="user_assigned_role">Role</label>
          <select class="form-input" id="user_assigned_role" name="user_assigned_role" required>
            <option value="">Select a role</option>
            <?php foreach (admin_role_options((int) ($form['user_assigned_role'] ?: 0)) as $role): ?>
            <option value="<?= $role['id'] ?>" <?= $role['selected'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="checkbox-label">
            <input type="checkbox" name="is_po_approver" value="1" <?= !empty($form['is_po_approver']) ? 'checked' : '' ?> />
            PO approver (can approve via email links)
          </label>
          <p class="form-hint">Designated approvers receive action links when a PO is submitted. CC subscribers receive notification only.</p>
        </div>

        <div class="module-actions">
          <button class="btn-primary" type="submit">Create User</button>
          <a class="btn-secondary" href="/site-admin/users/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
