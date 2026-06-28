<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/accounting.php';
require dirname(__DIR__) . '/includes/quickbooks.php';

accounting_require_update();

$code = trim((string) ($_GET['code'] ?? ''));
$realmId = trim((string) ($_GET['realmId'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));

if ($error !== '') {
    $errorDescription = trim((string) ($_GET['error_description'] ?? ''));
    $activeSlug = 'accounting';
    $pageTitle = 'QuickBooks Connection Failed | Accounting';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner">';
    echo '<div class="admin-notice is-error is-detail" role="alert">';
    echo 'QuickBooks authorization was denied or failed.';
    if ($errorDescription !== '') {
        echo ' ' . htmlspecialchars($errorDescription);
    } elseif ($error === 'access_denied') {
        echo ' The user declined access or closed the Intuit sign-in window.';
    }
    echo '</div>';
    if (qbo_uses_sandbox_oauth() && stripos($errorDescription, 'sandbox') !== false) {
        echo '<div class="admin-notice is-error is-detail" role="alert">';
        echo 'This usually means the Operations site is still using <strong>Sandbox (Development)</strong> OAuth keys. ';
        echo 'Production QuickBooks companies only appear when the app uses Production keys and <code>QBO_ENVIRONMENT=production</code>.';
        echo '</div>';
    }
    echo '<div class="module-actions"><a class="btn-secondary" href="/accounting/">Back to Accounting</a></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if ($code === '' || $realmId === '' || !qbo_validate_oauth_state($state)) {
    header('Location: /accounting/', true, 302);
    exit;
}

$result = qbo_exchange_code($code, $realmId);
if ($result['ok']) {
    header('Location: /accounting/?notice=connected', true, 302);
    exit;
}

$activeSlug = 'accounting';
$pageTitle = 'QuickBooks Connection Failed | Accounting';
require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/accounting/">Back to Accounting</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
