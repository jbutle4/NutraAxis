<?php
/** @var int $userId */
$userSubscriptions = alert_list_user_subscription_rows($userId);
$availableAlerts = alert_list_available_for_user($userId);
?>
      <div class="account-card" style="margin-top: 24px;">
        <h2>Alert subscriptions</h2>
        <p class="account-card-lead">Manage outbound email subscriptions for this user. Only active subscriptions are listed. Choose To or Cc for each alert.</p>

        <?php if (!alert_tables_available()): ?>
        <p class="account-card-lead">Alert tables are not available yet. Run migrations <code>053</code> and <code>054</code>.</p>
        <?php else: ?>

        <?php if ($userSubscriptions === []): ?>
        <p class="account-card-lead">This user is not subscribed to any alerts yet.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Alert</th>
                <th>Description</th>
                <th>Address</th>
                <th>Remove</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($userSubscriptions as $subscription): ?>
              <tr>
                <td><code><?= htmlspecialchars((string) $subscription['AlertName']) ?></code></td>
                <td><?= htmlspecialchars((string) ($subscription['AlertDescription'] ?? '')) ?></td>
                <td>
                  <select class="form-input" name="alert_subscription[<?= (int) $subscription['alertSubID'] ?>][address_type]">
                    <?php foreach (ALERT_ADDRESS_TYPES as $addressType): ?>
                    <option value="<?= $addressType ?>" <?= strtoupper((string) $subscription['AddressType']) === $addressType ? 'selected' : '' ?>><?= $addressType ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <label class="permission-note">
                    <input type="checkbox" name="alert_subscription[<?= (int) $subscription['alertSubID'] ?>][remove]" value="1" />
                    Remove
                  </label>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($availableAlerts !== []): ?>
        <div class="form-grid" style="margin-top: 20px;">
          <div class="form-group">
            <label for="new_alert_id">Add subscription</label>
            <select class="form-input" id="new_alert_id" name="new_alert_id">
              <option value="">Select an alert…</option>
              <?php foreach ($availableAlerts as $alert): ?>
              <option value="<?= (int) $alert['alertID'] ?>"><?= htmlspecialchars((string) $alert['AlertName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="new_address_type">Address type</label>
            <select class="form-input" id="new_address_type" name="new_address_type">
              <?php foreach (ALERT_ADDRESS_TYPES as $addressType): ?>
              <option value="<?= $addressType ?>"><?= $addressType ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php else: ?>
        <p class="form-hint" style="margin-top: 16px;">This user is subscribed to all available alerts.</p>
        <?php endif; ?>

        <?php endif; ?>
      </div>
