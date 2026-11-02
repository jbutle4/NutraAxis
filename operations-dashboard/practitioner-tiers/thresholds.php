<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/practitioner-tier.php';

practitioner_tier_require_read();

$activeSlug = 'practitioner-tiers';
$env = practitioner_tier_environment_from_request('production');
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    practitioner_tier_require_update();
    $env = practitioner_tier_normalize_environment($_POST['env'] ?? null) ?? $env;
    $tierCode = trim((string) ($_POST['tier_code'] ?? ''));
    $min = (float) ($_POST['min_revenue'] ?? 0);
    $maxRaw = trim((string) ($_POST['max_revenue'] ?? ''));
    $max = $maxRaw === '' ? null : (float) $maxRaw;
    $stageGroupId = (int) ($_POST['stage_group_id'] ?? 0);
    $prodGroupId = (int) ($_POST['prod_group_id'] ?? 0);
    $result = practitioner_tier_update_threshold($tierCode, $min, $max, $stageGroupId, $prodGroupId);
    if ($result['ok']) {
        $notice = 'Threshold updated for ' . $tierCode . '.';
    } else {
        $error = $result['error'] ?? 'Update failed.';
    }
}

$thresholds = practitioner_tier_list_thresholds();
$pageTitle = 'Practitioner Tier Thresholds | NutraAxis Operations';
$pageDescription = 'Edit revenue thresholds and Stage/Prod Magento group IDs.';

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
          'title'      => 'Practitioner Tier Thresholds',
          'lead'       => 'Shared threshold table. Stage and Production Magento customer group IDs are stored per tier.',
          'permission' => permission_label(auth_permission_value(PRACTITIONER_TIER_PERMISSION_COLUMN)),
      ]);
      ?>

      <?php if ($notice !== ''): ?>
      <div class="admin-notice is-success" role="status"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <div class="admin-notice" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php foreach ($thresholds as $tier): ?>
      <section class="detail-card detail-card--wide">
        <h2><?= htmlspecialchars((string) $tier['DisplayName']) ?> <span class="muted">(<?= htmlspecialchars((string) $tier['TierCode']) ?>)</span></h2>
        <?php if (practitioner_tier_can_update()): ?>
        <form class="admin-form" method="post" action="/operations-dashboard/practitioner-tiers/thresholds.php">
          <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
          <input type="hidden" name="tier_code" value="<?= htmlspecialchars((string) $tier['TierCode']) ?>" />
          <div class="audit-filter-grid">
            <div>
              <label>Min revenue</label>
              <input class="form-input" type="number" step="0.01" name="min_revenue" value="<?= htmlspecialchars((string) $tier['MinRevenue']) ?>" required />
            </div>
            <div>
              <label>Max revenue (blank = open-ended)</label>
              <input class="form-input" type="number" step="0.01" name="max_revenue" value="<?= htmlspecialchars((string) ($tier['MaxRevenue'] ?? '')) ?>" />
            </div>
            <div>
              <label>Stage group ID</label>
              <input class="form-input" type="number" name="stage_group_id" value="<?= (int) $tier['StageGroupId'] ?>" required />
            </div>
            <div>
              <label>Prod group ID</label>
              <input class="form-input" type="number" name="prod_group_id" value="<?= (int) $tier['ProdGroupId'] ?>" required />
            </div>
          </div>
          <button class="btn-primary" type="submit">Save</button>
        </form>
        <?php else: ?>
        <p>
          <?= practitioner_tier_format_money((float) $tier['MinRevenue']) ?>
          –
          <?= $tier['MaxRevenue'] === null || $tier['MaxRevenue'] === '' ? '∞' : practitioner_tier_format_money((float) $tier['MaxRevenue']) ?>
          · Stage group <?= (int) $tier['StageGroupId'] ?>
          · Prod group <?= (int) $tier['ProdGroupId'] ?>
        </p>
        <?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
