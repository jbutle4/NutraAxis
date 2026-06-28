<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/admin.php';
require dirname(__DIR__, 2) . '/includes/alert-messages.php';

auth_require_admin_update('users');

$userId = (int) ($_GET['id'] ?? 0);
$user = admin_get_user($userId);

if ($user === null) {
    http_response_code(404);
    $pageTitle = 'User Not Found | Site Admin';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>User not found</h1><div class="module-actions"><a class="btn-secondary" href="/site-admin/users/">Back to Users</a></div></div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

$activeAdminSection = 'users';
$error = null;
$form = [
    'user_name'          => $user['UserName'],
    'user_login'         => $user['UserLogin'],
    'user_password'      => '',
    'user_assigned_role' => (string) $user['UserAssignedRole'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'user_name'          => $_POST['user_name'] ?? '',
        'user_login'         => $_POST['user_login'] ?? '',
        'user_password'      => $_POST['user_password'] ?? '',
        'user_assigned_role' => $_POST['user_assigned_role'] ?? '',
    ];

    $result = admin_save_user($form, $userId);
    if ($result['ok']) {
        try {
            $subscriptionRows = is_array($_POST['alert_subscription'] ?? null) ? $_POST['alert_subscription'] : [];
            $newAlertId = (int) ($_POST['new_alert_id'] ?? 0);
            $newAddressType = (string) ($_POST['new_address_type'] ?? 'TO');
            alert_save_user_subscription_changes($userId, $subscriptionRows, $newAlertId > 0 ? $newAlertId : null, $newAddressType);
        } catch (Throwable $e) {
            header('Location: /site-admin/users/edit.php?id=' . $userId . '&error=' . rawurlencode('User saved, but alert subscriptions could not be updated.'), true, 302);
            exit;
        }

        header('Location: /site-admin/users/edit.php?id=' . $userId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$error = $error ?? ($_GET['error'] ?? null);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Edit User | Site Admin | NutraAxis Operations';
$pageDescription = 'Update NutraAxis Operations user account.';

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
          'title'      => 'Edit User',
          'lead'       => 'Update account details for ' . $user['UserName'] . '.',
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/admin-nav.php'; ?>

      <?php if ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">User and alert subscriptions saved successfully.</div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="/site-admin/users/edit.php?id=<?= $userId ?>">
        <div class="form-group">
          <label for="user_name">Full name</label>
          <input class="form-input" type="text" id="user_name" name="user_name" value="<?= htmlspecialchars($form['user_name']) ?>" required />
        </div>

        <div class="form-group">
          <label for="user_login">Email</label>
          <input class="form-input" type="email" id="user_login" name="user_login" value="<?= htmlspecialchars($form['user_login']) ?>" required />
        </div>

        <div class="form-group">
          <label for="user_password">New password</label>
          <input class="form-input" type="password" id="user_password" name="user_password" />
          <p class="form-hint">Leave blank to keep the current password.</p>
        </div>

        <div class="form-group">
          <label for="user_assigned_role">Role</label>
          <select class="form-input" id="user_assigned_role" name="user_assigned_role" required>
            <?php foreach (admin_role_options((int) $form['user_assigned_role']) as $role): ?>
            <option value="<?= $role['id'] ?>" <?= $role['selected'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="form-hint">Approval authorizations come from the assigned role. Configure PO Approval, T&amp;E Approval, T&amp;E Processing, QBO Insert Approval, and Payment Approval in Site Admin → Roles.</p>
        </div>

        <dl class="account-details admin-meta">
          <div>
            <dt>Created</dt>
            <dd><?= htmlspecialchars(admin_format_datetime($user['CreateDate'])) ?></dd>
          </div>
          <div>
            <dt>Last login</dt>
            <dd><?= htmlspecialchars(admin_format_datetime($user['LastLoginDate'])) ?></dd>
          </div>
        </dl>

        <?php
          require dirname(__DIR__, 2) . '/includes/admin-user-alert-subscriptions.php';
        ?>

        <div class="module-actions">
          <button class="btn-primary" type="submit">Save Changes</button>
          <a class="btn-secondary" href="/site-admin/users/">Cancel</a>
        </div>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
