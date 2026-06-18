<?php
$host = $_SERVER['HTTP_HOST'] ?? 'nutraaxisweb.azurewebsites.net';
$base = 'https://' . $host;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NutraAxis Portal</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
    h1 { font-size: 1.25rem; }
    .ok { color: #0a7; font-weight: 600; }
    .bad { color: #c00; font-weight: 600; }
    ul { padding-left: 1.25rem; }
    a { color: #0b5; }
    pre { background: #f4f4f4; padding: 0.75rem; overflow: auto; font-size: 0.85rem; }
    .hint { color: #555; font-size: 0.9rem; }
  </style>
</head>
<body>
  <h1>NutraAxis Operations Portal</h1>
  <p class="ok">This page loaded over HTTPS.</p>

  <p><strong>Operations Dashboard</strong> — try in this order:</p>
  <ul>
    <li><a href="<?= htmlspecialchars($base) ?>/dash.php">dash.php</a> (preferred)</li>
    <li><a href="<?= htmlspecialchars($base) ?>/test-dash.php">test-dash.php</a> (connectivity test)</li>
    <li><a href="<?= htmlspecialchars($base) ?>/ops-dashboard.php">ops-dashboard.php</a></li>
    <li><a href="<?= htmlspecialchars($base) ?>/operations-dashboard/index.php">operations-dashboard/index.php</a></li>
  </ul>

  <p>Other:</p>
  <ul>
    <li><a href="<?= htmlspecialchars($base) ?>/">Portal home</a></li>
    <li><a href="<?= htmlspecialchars($base) ?>/accs-inventory-reporting/">ACCS Inventory</a></li>
  </ul>

  <p class="hint">Browser check (from your Mac, not the server):</p>
  <pre id="probe">Running…</pre>

  <script>
    (async () => {
      const paths = ['/test-dash.php', '/dash.php', '/ops-dashboard.php'];
      const lines = [];
      for (const path of paths) {
        try {
          const res = await fetch(path, { redirect: 'manual', cache: 'no-store' });
          lines.push(path + ' → HTTP ' + res.status);
        } catch (err) {
          lines.push(path + ' → ERROR ' + err);
        }
      }
      document.getElementById('probe').textContent = lines.join('\n');
    })();
  </script>
</body>
</html>
