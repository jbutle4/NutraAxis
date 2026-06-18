<?php
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Function App Test';

// Function App base URL and key from environment
$functionBaseUrl = rtrim((string) env('AZURE_FUNCTION_APP_URL', ''), '/');
$functionKey     = (string) env('AZURE_FUNCTION_APP_KEY', '');

$result   = null;
$error    = null;
$rawJson  = null;
$duration = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? 'NutraAxis'));

    if ($functionBaseUrl === '') {
        $error = 'AZURE_FUNCTION_APP_URL is not set in App Settings.';
    } else {
        $url = $functionBaseUrl . '/api/ping?' . http_build_query([
            'code' => $functionKey,
            'name' => $name,
        ]);

        $start = microtime(true);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $duration = round((microtime(true) - $start) * 1000);

        if ($curlErr) {
            $error = 'Could not reach Function App: ' . $curlErr;
        } elseif ($status >= 400) {
            $error = 'Function App returned HTTP ' . $status . '.';
            $rawJson = $body;
        } else {
            $decoded = json_decode($body, true);
            if ($decoded === null) {
                $error = 'Function App returned unexpected response.';
                $rawJson = $body;
            } else {
                $result  = $decoded;
                $rawJson = json_encode($decoded, JSON_PRETTY_PRINT);
            }
        }
    }
}

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="module-page">
  <div class="module-page-header">
    <h1>Function App — ping test</h1>
    <p class="module-page-description">Calls the <code>ping</code> HttpTrigger on <?= htmlspecialchars(function_app_display_name_for_url($functionBaseUrl)) ?> and shows the response.</p>
  </div>

  <div class="card" style="max-width:560px;">
    <form method="POST">
      <div class="form-group">
        <label for="name">Name to send</label>
        <input type="text" id="name" name="name" class="form-control"
               value="<?= htmlspecialchars((string) ($_POST['name'] ?? 'NutraAxis')) ?>"
               placeholder="NutraAxis" />
      </div>

      <div class="form-group" style="margin-top:8px;">
        <label>Function URL</label>
        <input type="text" class="form-control" readonly
               value="<?= htmlspecialchars($functionBaseUrl !== '' ? $functionBaseUrl . '/api/ping' : '(AZURE_FUNCTION_APP_URL not set)') ?>"
               style="color:var(--text-muted);font-family:monospace;font-size:13px;" />
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top:16px;">
        Call ping function
      </button>
    </form>
  </div>

  <?php if ($error !== null): ?>
  <div class="alert alert-danger" style="max-width:560px;margin-top:1.5rem;">
    <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    <?php if ($rawJson !== null): ?>
      <pre style="margin-top:8px;font-size:12px;white-space:pre-wrap;"><?= htmlspecialchars($rawJson) ?></pre>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($result !== null): ?>
  <div class="card" style="max-width:560px;margin-top:1.5rem;border-left:4px solid var(--color-success, #2e7d32);">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <span style="font-size:18px;">✓</span>
      <strong>Function responded in <?= $duration ?>ms</strong>
    </div>
    <table class="data-table" style="font-size:13px;">
      <tr><th style="width:140px;">ok</th><td><?= $result['ok'] ? 'true' : 'false' ?></td></tr>
      <tr><th>message</th><td><?= htmlspecialchars((string) ($result['message'] ?? '—')) ?></td></tr>
      <tr><th>timestamp</th><td><?= htmlspecialchars((string) ($result['timestamp'] ?? '—')) ?></td></tr>
      <tr><th>environment</th><td><?= htmlspecialchars((string) ($result['environment'] ?? '—')) ?></td></tr>
    </table>
    <details style="margin-top:12px;">
      <summary style="font-size:12px;color:var(--text-muted);cursor:pointer;">Raw JSON</summary>
      <pre style="margin-top:8px;font-size:12px;background:var(--bg-secondary);padding:10px;border-radius:6px;white-space:pre-wrap;"><?= htmlspecialchars($rawJson ?? '') ?></pre>
    </details>
  </div>
  <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
