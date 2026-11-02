<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/practitioner-tier.php';

practitioner_tier_require_read();

$activeSlug = 'practitioner-tiers';
$env = practitioner_tier_environment_from_request('production');
$actor = (string) (auth_user()['UserName'] ?? 'ops');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    practitioner_tier_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $env = practitioner_tier_normalize_environment($_POST['env'] ?? null) ?? $env;
    $companyId = (int) ($_POST['company_id'] ?? 0);

    if ($env === 'production' && empty($_POST['confirm_production']) && in_array($action, ['apply_tier', 'run_all', 'run_one'], true)) {
        header('Location: /operations-dashboard/practitioner-tiers/?env=production&error=' . rawurlencode('Production actions require confirmation.'));
        exit;
    }

    if ($action === 'run_all') {
        $result = practitioner_tier_run_environment($env, 'manual_all', $actor);
        $qs = http_build_query([
            'env'    => $env,
            'notice' => $result['ok']
                ? sprintf('Run #%d finished — updated %d, skipped %d, failed %d.', (int) $result['run_log_id'], $result['updated'], $result['skipped'], $result['failed'])
                : null,
            'error'  => $result['ok'] ? null : ($result['error'] ?? 'Run failed.'),
        ]);
        header('Location: /operations-dashboard/practitioner-tiers/runs.php?' . $qs);
        exit;
    }

    if ($action === 'run_one' && $companyId > 0) {
        $result = practitioner_tier_run_environment($env, 'manual_one', $actor, $companyId, true);
        $qs = http_build_query([
            'env'        => $env,
            'company_id' => $companyId,
            'notice'     => $result['ok'] ? 'Clinic reclassification finished.' : null,
            'error'      => $result['ok'] ? null : ($result['error'] ?? 'Run failed.'),
        ]);
        header('Location: /operations-dashboard/practitioner-tiers/view.php?' . $qs);
        exit;
    }

    if ($action === 'apply_tier' && $companyId > 0) {
        $tierCode = trim((string) ($_POST['tier_code'] ?? ''));
        $lock = !empty($_POST['lock_override']);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $threshold = practitioner_tier_threshold_by_code($tierCode);
        if ($threshold === null) {
            header('Location: /operations-dashboard/practitioner-tiers/view.php?env=' . rawurlencode($env) . '&company_id=' . $companyId . '&error=' . rawurlencode('Unknown tier.'));
            exit;
        }
        $groupId = practitioner_tier_group_id_for_environment($threshold, $env);
        $apply = practitioner_tier_apply_to_company($companyId, $env, $groupId);
        if ($lock) {
            practitioner_tier_set_override($companyId, $env, true, $groupId, $tierCode, $notes !== '' ? $notes : null, $actor);
        }
        $qs = http_build_query([
            'env'        => $env,
            'company_id' => $companyId,
            'notice'     => $apply['ok'] ? ('Applied ' . $threshold['DisplayName'] . ($lock ? ' and locked override.' : '.')) : null,
            'error'      => $apply['ok'] ? null : ($apply['error'] ?? 'Apply failed.'),
        ]);
        header('Location: /operations-dashboard/practitioner-tiers/view.php?' . $qs);
        exit;
    }

    if ($action === 'unlock' && $companyId > 0) {
        practitioner_tier_set_override($companyId, $env, false, null, null, trim((string) ($_POST['notes'] ?? '')) ?: null, $actor);
        header('Location: /operations-dashboard/practitioner-tiers/view.php?env=' . rawurlencode($env) . '&company_id=' . $companyId . '&notice=' . rawurlencode('Override unlocked.'));
        exit;
    }

    header('Location: /operations-dashboard/practitioner-tiers/?env=' . rawurlencode($env));
    exit;
}

$companyId = (int) ($_GET['company_id'] ?? 0);
if ($companyId <= 0) {
    header('Location: /operations-dashboard/practitioner-tiers/?env=' . rawurlencode($env));
    exit;
}

$companyResult = practitioner_tier_get_company($companyId, $env);
if (!$companyResult['ok'] || !is_array($companyResult['company'])) {
    header('Location: /operations-dashboard/practitioner-tiers/?env=' . rawurlencode($env) . '&error=' . rawurlencode($companyResult['error'] ?? 'Company not found.'));
    exit;
}

$snapshot = practitioner_tier_company_snapshot($companyResult['company'], $env);
$thresholds = practitioner_tier_list_thresholds();
$notice = trim((string) ($_GET['notice'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$locked = is_array($snapshot['override'] ?? null) && (int) ($snapshot['override']['IsLocked'] ?? 0) === 1;

$pageTitle = 'Clinic Tier | NutraAxis Operations';
$pageDescription = 'Clinic practitioner tier detail.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/operations-dashboard/practitioner-tiers/?env=' . rawurlencode($env),
          'back_label' => 'Back to clinic list',
          'category'   => 'Operations',
          'title'      => (string) $snapshot['company_name'],
          'lead'       => 'Environment: ' . htmlspecialchars(practitioner_tier_environment_label($env)) . ' · ACCS company #' . (int) $snapshot['company_id'],
          'permission' => permission_label(auth_permission_value(PRACTITIONER_TIER_PERMISSION_COLUMN)),
      ]);
      ?>

      <?php if ($notice !== ''): ?>
      <div class="admin-notice is-success" role="status"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <div class="admin-notice" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($snapshot['providers_error'])): ?>
      <div class="admin-notice" role="alert">Providers: <?= htmlspecialchars((string) $snapshot['providers_error']) ?></div>
      <?php endif; ?>
      <?php if (!empty($snapshot['clinic_error'])): ?>
      <div class="admin-notice" role="alert">Clinic patients: <?= htmlspecialchars((string) $snapshot['clinic_error']) ?></div>
      <?php endif; ?>

      <section class="detail-card detail-card--wide">
        <h2>YTD revenue (<?= (int) $snapshot['ytd']['year'] ?>)</h2>
        <dl class="detail-grid">
          <div><dt>Total</dt><dd><?= htmlspecialchars(practitioner_tier_format_money((float) $snapshot['total_revenue'])) ?></dd></div>
          <div><dt>Providers / clinic users</dt><dd><?= htmlspecialchars(practitioner_tier_format_money((float) $snapshot['provider_revenue'])) ?> (<?= (int) $snapshot['provider_count'] ?> customers)</dd></div>
          <div><dt>Clinic patients</dt><dd><?= htmlspecialchars(practitioner_tier_format_money((float) $snapshot['patient_revenue'])) ?> (<?= (int) $snapshot['patient_count'] ?> customers)</dd></div>
          <div><dt>Current company tier</dt><dd>
            <?php if (is_array($snapshot['current_tier'])): ?>
              <?= htmlspecialchars((string) $snapshot['current_tier']['DisplayName']) ?>
            <?php else: ?>
              Group <?= (int) $snapshot['current_group_id'] ?>
            <?php endif; ?>
          </dd></div>
          <div><dt>Suggested tier</dt><dd>
            <?php if (is_array($snapshot['suggested_tier'])): ?>
              <?= htmlspecialchars((string) $snapshot['suggested_tier']['DisplayName']) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </dd></div>
          <div><dt>Override</dt><dd><?= $locked ? 'Locked' : 'Not locked' ?></dd></div>
        </dl>
      </section>

      <section class="detail-card detail-card--wide">
        <h2>Provider users (will receive tier group)</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Group</th></tr></thead>
            <tbody>
              <?php if (($snapshot['providers'] ?? []) === []): ?>
              <tr><td colspan="4">No company hierarchy users found.</td></tr>
              <?php endif; ?>
              <?php foreach ($snapshot['providers'] as $customer): ?>
              <tr>
                <td><?= (int) ($customer['id'] ?? 0) ?></td>
                <td><?= htmlspecialchars(trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) ($customer['email'] ?? '')) ?></td>
                <td><?= (int) ($customer['group_id'] ?? 0) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php if (practitioner_tier_can_update()): ?>
      <section class="detail-card detail-card--wide">
        <h2>Manual assign<?= $env === 'production' ? ' (Production)' : '' ?></h2>
        <form class="admin-form" method="post" action="/operations-dashboard/practitioner-tiers/view.php">
          <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
          <input type="hidden" name="company_id" value="<?= (int) $companyId ?>" />
          <div class="form-group">
            <label for="tier_code">Tier</label>
            <select class="form-input" id="tier_code" name="tier_code" required>
              <?php foreach ($thresholds as $tier): ?>
              <option value="<?= htmlspecialchars((string) $tier['TierCode']) ?>">
                <?= htmlspecialchars((string) $tier['DisplayName']) ?>
                (<?= practitioner_tier_format_money((float) $tier['MinRevenue']) ?>–
                <?= $tier['MaxRevenue'] === null || $tier['MaxRevenue'] === '' ? '∞' : practitioner_tier_format_money((float) $tier['MaxRevenue']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="notes">Notes</label>
            <input class="form-input" type="text" id="notes" name="notes" maxlength="1000" />
          </div>
          <div class="form-group">
            <label><input type="checkbox" name="lock_override" value="1" /> Lock override (monthly job will not reclassify)</label>
          </div>
          <?php if ($env === 'production'): ?>
          <div class="form-group">
            <label><input type="checkbox" name="confirm_production" value="1" required /> I confirm this Production ACCS update</label>
          </div>
          <?php endif; ?>
          <button class="btn-primary" type="submit" name="action" value="apply_tier">Apply tier to company + providers</button>
        </form>

        <form class="admin-form" method="post" action="/operations-dashboard/practitioner-tiers/view.php" style="margin-top:1rem;" onsubmit="return confirm('Reclassify this clinic from YTD revenue now?');">
          <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
          <input type="hidden" name="company_id" value="<?= (int) $companyId ?>" />
          <?php if ($env === 'production'): ?>
          <div class="form-group">
            <label><input type="checkbox" name="confirm_production" value="1" required /> Confirm Production run</label>
          </div>
          <?php endif; ?>
          <button class="btn-secondary" type="submit" name="action" value="run_one">Run suggested tier now</button>
        </form>

        <?php if ($locked): ?>
        <form class="admin-form" method="post" action="/operations-dashboard/practitioner-tiers/view.php" style="margin-top:1rem;">
          <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
          <input type="hidden" name="company_id" value="<?= (int) $companyId ?>" />
          <button class="btn-secondary" type="submit" name="action" value="unlock">Unlock override</button>
        </form>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
