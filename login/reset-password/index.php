<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/password-reset.php';

if (auth_is_logged_in()) {
    header('Location: /', true, 302);
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;
$completed = false;
$reset = $token !== '' ? password_reset_validate_token($token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = password_reset_complete(
        $token,
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['password_confirm'] ?? '')
    );

    if ($result['ok']) {
        $completed = true;
    } else {
        $error = $result['error'];
        $reset = password_reset_validate_token($token);
    }
}

$pageTitle = 'Reset Password | NutraAxis Operations';
$pageDescription = 'Choose a new password for your NutraAxis Operations account.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="auth-card">
        <div class="section-label">Password Reset</div>
        <h1>Choose a new password</h1>

        <?php if ($completed): ?>
        <div class="auth-success" role="status">Your password has been updated. You can log in with your new password.</div>
        <p class="auth-footnote">
          <a href="/login/">Go to Log In</a>
        </p>
        <?php elseif ($reset === null): ?>
        <p class="page-lead">This reset link is invalid or has expired.</p>
        <div class="auth-error" role="alert">Request a new password reset link to continue.</div>
        <p class="auth-footnote">
          <a href="/login/forgot-password/">Request a new reset link</a>
          <span aria-hidden="true"> · </span>
          <a href="/login/">Back to Log In</a>
        </p>
        <?php else: ?>
        <p class="page-lead">Set a new password for <?= htmlspecialchars((string) $reset['UserLogin']) ?>.</p>

        <?php if ($error !== null): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="/login/reset-password/">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />

          <div class="form-group">
            <label for="password">New password</label>
            <input
              class="form-input"
              type="password"
              id="password"
              name="password"
              autocomplete="new-password"
              minlength="<?= PASSWORD_RESET_MIN_LENGTH ?>"
              required
              autofocus
            />
          </div>

          <div class="form-group">
            <label for="password_confirm">Confirm new password</label>
            <input
              class="form-input"
              type="password"
              id="password_confirm"
              name="password_confirm"
              autocomplete="new-password"
              minlength="<?= PASSWORD_RESET_MIN_LENGTH ?>"
              required
            />
          </div>

          <p class="auth-hint">Use at least <?= PASSWORD_RESET_MIN_LENGTH ?> characters.</p>

          <button class="btn-primary auth-submit" type="submit">Update Password</button>
        </form>

        <p class="auth-footnote">
          <a href="/login/">Back to Log In</a>
        </p>
        <?php endif; ?>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
