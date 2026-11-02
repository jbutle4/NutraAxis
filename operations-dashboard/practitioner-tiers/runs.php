<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/practitioner-tier.php';

practitioner_tier_require_read();

$activeSlug = 'practitioner-tiers';
$env = practitioner_tier_environment_from_request('production');
$runId = (int) ($_GET['run_id'] ?? 0);
$notice = trim((string) ($_GET['notice'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$runs = practitioner_tier_list_runs($env, 40);
$details = $runId > 0 ? practitioner_tier_list_run_details($runId) : [];

$pageTitle = 'Practitioner Tier Runs | NutraAxis Operations';
$pageDescription = 'History of practitioner tier reclassification runs.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main page-main--fluid">
    <div class="container page-inner page-inner--full">
      <?php
      render_list_page_header([
          'back_href'  => '/operations-dashboard/practitioner-tiers/?env=' . rawurlencode($env),
          'back_label' => 'Back to clinic list',
          'category'   => 'Operations',
          'title'      => 'Practitioner Tier Runs',
          'lead'       => 'Manual and monthly reclassification history for ' . htmlspecialchars(practitioner_tier_environment_label($env)) . '.',
          'permission' => permission_label(auth_permission_value(PRACTITIONER_TIER_PERMISSION_COLUMN)),
      ]);
      ?>

      <?php if ($notice !== ''): ?>
      <div class="admin-notice is-success" role="status"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <div class="admin-notice" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/operations-dashboard/practitioner-tiers/runs.php">
        <div class="audit-filter-grid">
          <div>
            <label for="env">Environment</label>
            <select class="form-input" id="env" name="env">
              <option value="production" <?= $env === 'production' ? 'selected' : '' ?>>Production</option>
              <option value="stage" <?= $env === 'stage' ? 'selected' : '' ?>>Stage</option>
            </select>
          </div>
        </div>
        <div class="audit-filter-actions">
          <button class="btn-primary" type="submit">Apply</button>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Run</th>
              <th>Started</th>
              <th>Trigger</th>
              <th>Status</th>
              <th>Scanned</th>
              <th>Updated</th>
              <th>Skipped</th>
              <th>Failed</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($runs === []): ?>
            <tr><td colspan="9">No runs yet for this environment.</td></tr>
            <?php endif; ?>
            <?php foreach ($runs as $run): ?>
            <tr>
              <td>#<?= (int) $run['RunLogID'] ?></td>
              <td><?= htmlspecialchars((string) $run['StartedAt']) ?></td>
              <td><?= htmlspecialchars((string) $run['TriggerType']) ?><?php if (!empty($run['TriggeredBy'])): ?> <span class="muted">(<?= htmlspecialchars((string) $run['TriggeredBy']) ?>)</span><?php endif; ?></td>
              <td><?= htmlspecialchars((string) $run['Status']) ?></td>
              <td><?= (int) $run['CompaniesScanned'] ?></td>
              <td><?= (int) $run['CompaniesUpdated'] ?></td>
              <td><?= (int) $run['CompaniesSkipped'] ?></td>
              <td><?= (int) $run['CompaniesFailed'] ?></td>
              <td><a href="/operations-dashboard/practitioner-tiers/runs.php?env=<?= rawurlencode($env) ?>&amp;run_id=<?= (int) $run['RunLogID'] ?>">Details</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($runId > 0): ?>
      <section class="detail-card detail-card--wide" style="margin-top:1.5rem;">
        <h2>Run #<?= $runId ?> details</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Company</th>
                <th>YTD</th>
                <th>Provider / Patient</th>
                <th>From → To</th>
                <th>Tier</th>
                <th>Status</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($details === []): ?>
              <tr><td colspan="7">No detail rows.</td></tr>
              <?php endif; ?>
              <?php foreach ($details as $detail): ?>
              <tr>
                <td>#<?= (int) $detail['AccsCompanyId'] ?> <?= htmlspecialchars((string) ($detail['CompanyName'] ?? '')) ?></td>
                <td><?= htmlspecialchars(practitioner_tier_format_money((float) ($detail['YtdRevenue'] ?? 0))) ?></td>
                <td><?= htmlspecialchars(practitioner_tier_format_money((float) ($detail['ProviderRevenue'] ?? 0))) ?> / <?= htmlspecialchars(practitioner_tier_format_money((float) ($detail['PatientRevenue'] ?? 0))) ?></td>
                <td><?= (int) ($detail['FromGroupId'] ?? 0) ?> → <?= (int) ($detail['ToGroupId'] ?? 0) ?></td>
                <td><?= htmlspecialchars((string) ($detail['SuggestedTierCode'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($detail['ActionStatus'] ?? '')) ?><?php if (!empty($detail['SkipReason'])): ?> <span class="muted">(<?= htmlspecialchars((string) $detail['SkipReason']) ?>)</span><?php endif; ?></td>
                <td><?= htmlspecialchars((string) ($detail['DetailMessage'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
