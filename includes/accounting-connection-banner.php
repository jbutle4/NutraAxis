<?php
$configError = qbo_config_error();
$connection = qbo_get_connection();
$configuredEnv = qbo_environment();
$label = qbo_environment_label($configuredEnv);
?>
      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif ($connection === null): ?>
      <div class="status-banner">
        <div>
          <strong>QuickBooks <?= htmlspecialchars($label) ?> not connected</strong>
          <p>Connect the <?= htmlspecialchars(strtolower($label)) ?> company from the Accounting hub to load this page.</p>
        </div>
        <?php if (accounting_can_update()): ?>
        <a class="btn-primary" href="/accounting/">Open Accounting hub</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="status-banner">
        <div>
          <strong>Connected to <?= htmlspecialchars((string) ($connection['CompanyName'] ?? 'QuickBooks company')) ?> (<?= htmlspecialchars($label) ?>)</strong>
          <p>Realm <?= htmlspecialchars((string) $connection['RealmID']) ?> · read-only QuickBooks Online views for this environment</p>
        </div>
      </div>
      <?php endif; ?>
