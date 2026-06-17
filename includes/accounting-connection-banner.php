<?php
$configError = qbo_config_error();
$connection = qbo_get_connection();
?>
      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif ($connection === null): ?>
      <div class="status-banner">
        <div>
          <strong>QuickBooks not connected</strong>
          <p>Connect a QuickBooks Online company to load AP, AR, purchase orders, inventory, suppliers, and chart of accounts.</p>
          <?php if (accounting_can_update()): ?>
          <p class="permission-note">OAuth redirect URI (must match Intuit app exactly): <code><?= htmlspecialchars(qbo_redirect_uri()) ?></code></p>
          <?php endif; ?>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/connect.php">Connect QuickBooks</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Connected to <?= htmlspecialchars((string) ($connection['CompanyName'] ?? 'QuickBooks company')) ?></strong>
          <p>Realm <?= htmlspecialchars((string) $connection['RealmID']) ?> · <?= htmlspecialchars((string) $connection['Environment']) ?> environment · read-only views from QuickBooks Online</p>
          <?php if (data_profile_is_production() && qbo_environment() === 'sandbox'): ?>
          <p class="permission-note">QuickBooks production credentials are not configured yet — this production page is using the sandbox company.</p>
          <?php endif; ?>
        </div>
        <?php if (accounting_can_update()): ?>
        <form method="post" action="/accounting/disconnect.php" onsubmit="return confirm('Disconnect QuickBooks from Operations?');">
          <button type="submit" class="btn-secondary">Disconnect</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
