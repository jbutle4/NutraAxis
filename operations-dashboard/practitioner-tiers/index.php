<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/practitioner-tier.php';

practitioner_tier_require_read();

$activeSlug = 'practitioner-tiers';
$env = practitioner_tier_environment_from_request('production');
$notice = trim((string) ($_GET['notice'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

$companiesResult = practitioner_tier_list_companies($env);
$rows = [];
$listError = null;
if (!$companiesResult['ok']) {
    $listError = $companiesResult['error'] ?? 'Unable to load companies.';
} else {
    foreach ($companiesResult['companies'] as $company) {
        $companyId = (int) ($company['id'] ?? 0);
        $name = (string) ($company['company_name'] ?? '');
        if ($q !== '') {
            $hay = strtolower($name . ' ' . $companyId);
            if (!str_contains($hay, strtolower($q))) {
                continue;
            }
        }
        $currentGroupId = (int) ($company['customer_group_id'] ?? 0);
        $rows[] = [
            'company_id'       => $companyId,
            'company_name'     => $name,
            'current_group_id' => $currentGroupId,
            'current_tier'     => practitioner_tier_threshold_by_group_id($currentGroupId, $env),
            'override'         => practitioner_tier_get_override($companyId, $env),
        ];
    }
    usort($rows, static function (array $a, array $b): int {
        return strcasecmp((string) $a['company_name'], (string) $b['company_name']);
    });
}

$ytd = practitioner_tier_ytd_bounds();
$pageTitle = 'Practitioner Tiers | NutraAxis Operations';
$pageDescription = 'Review clinic companies and assign Practitioner wholesale tiers.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main page-main--fluid">
    <div class="container page-inner page-inner--full">
      <?php
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Operations',
          'title'      => 'Practitioner Tiers',
          'lead'       => 'Classify clinic companies and provider users into Practitioner / Bronze–Platinum from ACCS YTD revenue (providers + clinic patients). Open a clinic for revenue split and reassignment.',
          'permission' => permission_label(auth_permission_value(PRACTITIONER_TIER_PERMISSION_COLUMN)),
      ]);
      ?>

      <div class="module-actions" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem;">
        <span class="admin-notice" style="margin:0;">Environment: <strong><?= htmlspecialchars(practitioner_tier_environment_label($env)) ?></strong></span>
        <a class="btn-secondary" href="/operations-dashboard/practitioner-tiers/thresholds.php?env=<?= rawurlencode($env) ?>">Thresholds</a>
        <a class="btn-secondary" href="/operations-dashboard/practitioner-tiers/runs.php?env=<?= rawurlencode($env) ?>">Run history</a>
        <?php if (practitioner_tier_can_update()): ?>
        <form method="post" action="/operations-dashboard/practitioner-tiers/view.php" style="display:inline;" onsubmit="return confirm('Run reclassification for all clinics in <?= htmlspecialchars(practitioner_tier_environment_label($env), ENT_QUOTES) ?>?');">
          <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>" />
          <input type="hidden" name="action" value="run_all" />
          <?php if ($env === 'production'): ?>
          <input type="hidden" name="confirm_production" value="1" />
          <?php endif; ?>
          <button class="btn-primary" type="submit">Run <?= htmlspecialchars(practitioner_tier_environment_label($env)) ?> now</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($notice !== ''): ?>
      <div class="admin-notice is-success" role="status"><?= htmlspecialchars($notice) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
      <div class="admin-notice" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($listError !== null): ?>
      <div class="admin-notice" role="alert"><?= htmlspecialchars($listError) ?></div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/operations-dashboard/practitioner-tiers/">
        <div class="audit-filter-grid">
          <div>
            <label for="env">Environment</label>
            <select class="form-input" id="env" name="env">
              <option value="production" <?= $env === 'production' ? 'selected' : '' ?>>Production</option>
              <option value="stage" <?= $env === 'stage' ? 'selected' : '' ?>>Stage</option>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search clinics</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Company name or ACCS company ID" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button class="btn-primary" type="submit">Apply</button>
          <a class="btn-secondary" href="/operations-dashboard/practitioner-tiers/?env=production">Clear</a>
        </div>
      </form>

      <p class="page-lead">YTD window (America/Chicago): <?= htmlspecialchars($ytd['start']) ?> → <?= htmlspecialchars($ytd['end']) ?> (<?= (int) $ytd['year'] ?>). Revenue is calculated on the clinic detail page and during monthly/manual runs.</p>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Company</th>
              <th>Env</th>
              <th>Current company group / tier</th>
              <th>Override</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="5">No clinics found for this environment<?= $q !== '' ? ' matching your search' : '' ?>.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
            <?php
              $currentName = is_array($row['current_tier'] ?? null)
                  ? (string) $row['current_tier']['DisplayName']
                  : ('Group ' . (int) $row['current_group_id']);
              $locked = is_array($row['override'] ?? null) && (int) ($row['override']['IsLocked'] ?? 0) === 1;
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars((string) $row['company_name']) ?></strong><br>
                <span class="muted">ACCS #<?= (int) $row['company_id'] ?></span>
              </td>
              <td><?= htmlspecialchars(practitioner_tier_environment_label($env)) ?></td>
              <td><?= htmlspecialchars($currentName) ?> <span class="muted">(<?= (int) $row['current_group_id'] ?>)</span></td>
              <td><?= $locked ? 'Locked' : '—' ?></td>
              <td><a href="/operations-dashboard/practitioner-tiers/view.php?env=<?= rawurlencode($env) ?>&amp;company_id=<?= (int) $row['company_id'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
