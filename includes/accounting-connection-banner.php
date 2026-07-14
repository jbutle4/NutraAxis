<?php
$configError = qbo_config_error();
$connection = qbo_get_connection();
$configuredEnv = qbo_environment();
$connectedEnv = is_array($connection)
    ? strtolower(trim((string) ($connection['Environment'] ?? '')))
    : '';
$envMismatch = $connection !== null
    && $connectedEnv !== ''
    && $connectedEnv !== strtolower($configuredEnv);
?>
      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif ($connection === null): ?>
      <div class="status-banner">
        <div>
          <strong>QuickBooks not connected</strong>
          <p>Connect a QuickBooks Online company to load AP, AR, purchase orders, inventory, suppliers, and chart of accounts.</p>
          <p class="permission-note">Configured QBO environment: <code><?= htmlspecialchars($configuredEnv) ?></code> (set <code>QBO_ENVIRONMENT=production</code> on the production App Service).</p>
          <?php if (accounting_can_update()): ?>
          <p class="permission-note">OAuth redirect URI (must match Intuit app exactly): <code><?= htmlspecialchars(qbo_redirect_uri()) ?></code></p>
          <?php endif; ?>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/connect.php">Connect QuickBooks</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <?php if ($envMismatch): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        QuickBooks connection environment mismatch: connected as
        <strong><?= htmlspecialchars((string) $connection['Environment']) ?></strong>,
        but application settings expect
        <strong><?= htmlspecialchars($configuredEnv) ?></strong>.
        Disconnect and reconnect so OAuth uses the correct Intuit company.
      </div>
      <?php endif; ?>
      <div class="status-banner">
        <div>
          <strong>Connected to <?= htmlspecialchars((string) ($connection['CompanyName'] ?? 'QuickBooks company')) ?></strong>
          <p>Realm <?= htmlspecialchars((string) $connection['RealmID']) ?> · <?= htmlspecialchars((string) $connection['Environment']) ?> environment · app setting <?= htmlspecialchars($configuredEnv) ?> · read-only views from QuickBooks Online</p>
        </div>
        <?php if (accounting_can_update()): ?>
        <form method="post" action="/accounting/disconnect.php" onsubmit="return confirm('Disconnect QuickBooks from Operations?');">
          <button type="submit" class="btn-secondary">Disconnect</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
