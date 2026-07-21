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
    $activeSlug = 'accounting';
    $pageTitle = 'QuickBooks Connection Failed | Accounting';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="admin-notice is-error is-detail" role="alert">QuickBooks authorization was denied or failed.</div><div class="module-actions"><a class="btn-secondary" href="/accounting/">Back to Accounting</a></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

$stateCheck = qbo_validate_oauth_state($state);
if ($code === '' || $realmId === '' || !$stateCheck['ok'] || $stateCheck['env'] === null) {
    header('Location: /accounting/', true, 302);
    exit;
}

$env = qbo_normalize_environment($stateCheck['env']);
$result = qbo_exchange_code($code, $realmId, $env);
if ($result['ok']) {
    header('Location: /accounting/?notice=connected&env=' . rawurlencode($env), true, 302);
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
