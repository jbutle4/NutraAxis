<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/password-reset.php';

if (auth_is_logged_in()) {
    header('Location: /', true, 302);
    exit;
}

$error = null;
$message = null;
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $result = password_reset_request($login);

    if ($result['ok']) {
        $message = $result['message'];
        $login = '';
    } else {
        $error = $result['error'];
    }
}

$pageTitle = 'Forgot Password | NutraAxis Operations';
$pageDescription = 'Request a password reset link for your NutraAxis Operations account.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="auth-card">
        <div class="section-label">Password Reset</div>
        <h1>Forgot your password?</h1>
        <p class="page-lead">Enter your NutraAxis email and we will send you a link to choose a new password.</p>

        <?php if ($message !== null): ?>
        <div class="auth-success" role="status"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="/login/forgot-password/">
          <div class="form-group">
            <label for="login">Email</label>
            <input
              class="form-input"
              type="email"
              id="login"
              name="login"
              value="<?= htmlspecialchars($login) ?>"
              autocomplete="username"
              required
              autofocus
            />
          </div>

          <button class="btn-primary auth-submit" type="submit">Send Reset Link</button>
        </form>

        <p class="auth-footnote">
          <a href="/login/">Back to Log In</a>
        </p>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
