<?php
/**
 * Dual-connection banner for the Accounting hub.
 * Leaf QBO pages use accounting-connection-banner.php (current env only).
 */
$connections = qbo_list_connections();
$noticeEnv = qbo_normalize_environment($_GET['env'] ?? '');
$canManage = accounting_can_update();
$redirectUri = qbo_redirect_uri();
?>
      <div class="qbo-connection-grid">
        <?php foreach ([QBO_ENV_SANDBOX, QBO_ENV_PRODUCTION] as $env): ?>
        <?php
          $label = qbo_environment_label($env);
          $configError = qbo_config_error($env);
          $connection = $connections[$env] ?? null;
          $ctaHref = '/accounting/connect.php?env=' . rawurlencode($env);
        ?>
        <div class="status-banner qbo-connection-card card-tier-<?= $env === QBO_ENV_PRODUCTION ? 'production' : 'uat' ?>">
          <div>
            <strong><?= $env === QBO_ENV_PRODUCTION ? 'QuickBooks Online Production' : 'QuickBooks Online Sandbox' ?></strong>
            <?php if ($configError !== null): ?>
            <p class="permission-note"><?= htmlspecialchars($configError) ?></p>
            <?php elseif ($connection === null): ?>
            <p>Not connected. Connect this <?= htmlspecialchars(strtolower($label)) ?> company to use <?= $env === QBO_ENV_PRODUCTION ? 'Production' : 'UAT' ?> accounting pages without switching credentials.</p>
            <?php if ($canManage && $redirectUri !== ''): ?>
            <p class="permission-note">OAuth redirect URI: <code><?= htmlspecialchars($redirectUri) ?></code></p>
            <?php endif; ?>
            <?php else: ?>
            <p>Connected to <?= htmlspecialchars((string) ($connection['CompanyName'] ?? 'QuickBooks company')) ?> · Realm <?= htmlspecialchars((string) $connection['RealmID']) ?></p>
            <?php endif; ?>
          </div>
          <?php if ($canManage): ?>
            <?php if ($configError !== null): ?>
            <span class="btn-secondary" aria-disabled="true">Configure app settings</span>
            <?php elseif ($connection === null): ?>
            <a class="btn-primary" href="<?= htmlspecialchars($ctaHref) ?>">Connect to QuickBooks Online <?= htmlspecialchars($label) ?></a>
            <?php else: ?>
            <form method="post" action="/accounting/disconnect.php" onsubmit="return confirm('Disconnect QuickBooks <?= htmlspecialchars($label, ENT_QUOTES) ?> from Operations?');">
              <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
              <button type="submit" class="btn-secondary">Disconnect <?= htmlspecialchars($label) ?></button>
            </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
