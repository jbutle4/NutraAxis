<?php
require dirname(__DIR__) . '/includes/init.php';

if (auth_is_logged_in()) {
    $redirect = auth_safe_redirect($_GET['redirect'] ?? '/');
    header('Location: ' . $redirect, true, 302);
    exit;
}

$error = null;
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = auth_safe_redirect($_POST['redirect'] ?? $_GET['redirect'] ?? '/');

    $result = auth_attempt_login($login, $password);
    if ($result['ok']) {
        header('Location: ' . $redirect, true, 302);
        exit;
    }

    $error = $result['error'];
}

$redirect = auth_safe_redirect($_GET['redirect'] ?? '/');
$pageTitle = 'Log In | NutraAxis Operations';
$pageDescription = 'Sign in to access NutraAxis Operations applications.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="auth-card">
        <div class="section-label">Sign In</div>
        <h1>Log in to Operations</h1>
        <p class="page-lead">Use your NutraAxis email and password to access internal applications.</p>

        <?php if ($error !== null): ?>
        <div class="auth-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="/login/">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>" />

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

          <div class="form-group">
            <div class="form-label-row">
              <label for="password">Password</label>
              <a class="auth-inline-link" href="/login/forgot-password/">Forgot password?</a>
            </div>
            <input
              class="form-input"
              type="password"
              id="password"
              name="password"
              autocomplete="current-password"
              required
            />
          </div>

          <button class="btn-primary auth-submit" type="submit">Log In</button>
        </form>

        <p class="auth-footnote">
          <a href="/">Back to Operations Home</a>
        </p>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
