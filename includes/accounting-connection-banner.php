<?php
$configError = qbo_config_error();
$connection = qbo_get_connection();
$oauthMode = qbo_environment_label();
$redirectUri = qbo_redirect_uri();
?>
      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif ($connection === null): ?>
      <div class="status-banner">
        <div>
          <strong>QuickBooks not connected</strong>
          <p>Connect a QuickBooks Online company to load AP, AR, purchase orders, inventory, suppliers, and chart of accounts.</p>
          <p class="permission-note">OAuth mode: <strong><?= htmlspecialchars($oauthMode) ?></strong>
            <?php if (qbo_uses_sandbox_oauth()): ?>
            — only QuickBooks <em>sandbox</em> test companies appear. Production companies require Production app keys and <code>QBO_ENVIRONMENT=production</code>.
            <?php else: ?>
            — real QuickBooks Online production companies will be listed during sign-in.
            <?php endif; ?>
          </p>
          <?php if (accounting_can_update()): ?>
          <p class="permission-note">OAuth redirect URI (must match Intuit app exactly): <code><?= htmlspecialchars($redirectUri) ?></code></p>
          <?php endif; ?>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/connect.php">Connect QuickBooks</a>
        <?php endif; ?>
      </div>
      <?php if (qbo_uses_sandbox_oauth() && accounting_can_update()): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        This site is configured for <strong>Sandbox</strong> QuickBooks OAuth. If you are connecting a live production company, Intuit will show
        <em>“There is no sandbox companies found for the user.”</em>
        Ask your administrator to set Azure App Settings to <code>QBO_ENVIRONMENT=production</code> and configure the Production Client ID and Secret from
        <a href="https://developer.intuit.com/" target="_blank" rel="noopener">developer.intuit.com</a> for the NutraAxis_Operations app.
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Connected to <?= htmlspecialchars((string) ($connection['CompanyName'] ?? 'QuickBooks company')) ?></strong>
          <p>Realm <?= htmlspecialchars((string) $connection['RealmID']) ?> · <?= htmlspecialchars((string) $connection['Environment']) ?> environment · read-only views from QuickBooks Online</p>
        </div>
        <?php if (accounting_can_update()): ?>
        <form method="post" action="/accounting/disconnect.php" onsubmit="return confirm('Disconnect QuickBooks from Operations?');">
          <button type="submit" class="btn-secondary">Disconnect</button>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>
