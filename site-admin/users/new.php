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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'user_name'          => $_POST['user_name'] ?? '',
        'user_login'         => $_POST['user_login'] ?? '',
        'user_password'      => $_POST['user_password'] ?? '',
        'user_assigned_role' => $_POST['user_assigned_role'] ?? '',
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
      <?php
      render_list_page_header([
          'back_href'  => '/site-admin/users/',
          'back_label' => 'Back to Users',
          'category'   => 'Site Admin',
          'title'      => 'New User',
          'lead'       => 'Create a new Operations portal account.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

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
          <p class="form-hint">Approval authorizations come from the assigned role. Configure PO Approval, T&amp;E Approval, T&amp;E Processing, QBO Insert Approval, and Payment Approval in Site Admin → Roles.</p>
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
