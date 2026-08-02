<?php
/**
 * Internal operations queue for provider onboarding review.
 * Public provider pages are under /provider-signup/ (marketing UI).
 */
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

provider_signup_require_read();

$activeSlug = 'signup-review';
$listFilters = provider_signup_list_filters_from_request();
$statusFilter = (string) ($listFilters['status'] ?? '');
$search = (string) ($listFilters['q'] ?? '');
$page = (int) ($listFilters['page'] ?? 1);
$listResult = provider_signup_list_applications_page($listFilters);
$applications = $listResult['rows'];
$pendingCount = provider_signup_count_by_status(PROVIDER_SIGNUP_STATUS_DRAFT);
$notice = $_GET['notice'] ?? null;
$listQueryKeys = ['status', 'q', 'page'];
$configStepColumns = [
    'clinic'         => 'step_clinic',
    'admin'          => 'step_admin',
    'shared_catalog' => 'step_catalog',
    'catalog_assign' => 'step_assign',
    'roles'          => 'step_roles',
];
$tableColspan = count(PROVIDER_SIGNUP_LIST_SORT_COLUMNS) + 1;

$pageTitle = 'Provider Signup Management | NutraAxis Operations';
$pageDescription = 'Review and approve NutraAxis provider signup applications.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Operations',
          'title'      => 'Provider Signup Management',
          'lead'       => 'Review draft provider applications, validate NPI and banking data, approve applications, and create ACCS companies.',
          'permission' => permission_label(provider_signup_permission_value()),
      ]);
      if (provider_signup_can_update()) {
          render_list_page_toolbar(
              '<a class="btn-primary" href="/operations-dashboard/signup-review/new.php">Create new clinic</a>'
          );
      }
      ?>

      <?php if ($notice === 'commented'): ?>
      <div class="admin-notice is-success" role="status">Comment added and provider notified.</div>
      <?php elseif ($notice === 'returned'): ?>
      <div class="admin-notice is-success" role="status">Application sent back to provider for edits.</div>
      <?php elseif ($notice === 'reopened'): ?>
      <div class="admin-notice is-success" role="status">Application reopened as draft.</div>
      <?php elseif ($notice === 'approved'): ?>
      <div class="admin-notice is-success" role="status">Application approved. Open it to create the ACCS company.</div>
      <?php elseif ($notice === 'provisioned'): ?>
      <div class="admin-notice is-success" role="status">ACCS company created and provider notified.</div>
      <?php elseif ($notice === 'rejected'): ?>
      <div class="admin-notice is-success" role="status">Application rejected.</div>
      <?php elseif ($notice === 'npi_validated'): ?>
      <div class="admin-notice is-success" role="status">NPI validation refreshed.</div>
      <?php endif; ?>

      <?php if (!empty($_GET['warn'])): ?>
      <div class="admin-notice" role="status"><?= htmlspecialchars((string) $_GET['warn']) ?></div>
      <?php endif; ?>

      <?php if ($pendingCount > 0): ?>
      <div class="status-banner status-banner-approval">
        <div>
          <strong><?= $pendingCount === 1 ? '1 draft application is' : $pendingCount . ' draft applications are' ?> awaiting review</strong>
          <p>Provider applications in draft need operations validation and approval.</p>
        </div>
        <a class="btn-primary" href="/operations-dashboard/signup-review/?status=<?= rawurlencode(PROVIDER_SIGNUP_STATUS_DRAFT) ?>">Review Drafts</a>
      </div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/operations-dashboard/signup-review/">
        <?php table_sort_hidden_inputs($listFilters, 'submitted', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Filter by status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (PROVIDER_SIGNUP_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Omni Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ID, practice, email, admin, NPI, ACCS IDs, or roles summary" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/operations-dashboard/signup-review/">Clear</a>
        </div>
      </form>

      <p class="form-hint page-list-summary">
        Showing <?= $listResult['total'] === 0 ? 0 : (($page - 1) * $listResult['per_page'] + 1) ?>
        –<?= min($page * $listResult['per_page'], $listResult['total']) ?>
        of <?= (int) $listResult['total'] ?> applications
        <?php if ($search !== ''): ?> matching “<?= htmlspecialchars($search) ?>”<?php endif; ?>
      </p>

      <div class="admin-table-wrap admin-table-wrap--signup-review">
        <table class="admin-table admin-table--signup-review">
          <thead>
            <?php
            table_sort_render_head_row(
                PROVIDER_SIGNUP_LIST_SORT_COLUMNS,
                '/operations-dashboard/signup-review',
                $listFilters,
                $listQueryKeys,
                ['id', 'step_clinic', 'step_admin', 'step_catalog', 'step_assign', 'step_roles', 'config_complete'],
                'submitted',
                'desc',
                'submitted',
                'View'
            );
            ?>
          </thead>
          <tbody>
            <?php if ($applications === []): ?>
            <tr>
              <td colspan="<?= (int) $tableColspan ?>">No provider applications found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($applications as $row): ?>
            <tr>
              <td><?= (int) $row['ApplicationID'] ?></td>
              <td><?= htmlspecialchars((string) ($row['CompanyName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['ProviderEmail'] ?? '')) ?></td>
              <td><span class="<?= htmlspecialchars(provider_signup_status_badge_class((string) $row['Status'])) ?>"><?= htmlspecialchars((string) $row['Status']) ?></span></td>
              <td><?= htmlspecialchars(provider_signup_format_datetime($row['CreatedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars(provider_signup_format_datetime($row['SubmittedAt'] ?? null)) ?></td>
              <?php foreach ($configStepColumns as $stepKey => $sortKey): ?>
              <?php $stepCell = provider_signup_config_step_table_cell($row, $stepKey); ?>
              <td class="config-step-col" title="<?= htmlspecialchars($stepCell['title']) ?>">
                <span class="status-badge <?= $stepCell['done'] ? 'status-approved' : 'status-draft' ?>"><?= htmlspecialchars($stepCell['label']) ?></span>
              </td>
              <?php endforeach; ?>
              <td class="config-step-col" title="<?= !empty($row['AccsConfigurationComplete']) ? 'All clinic configuration steps complete' : 'Configuration in progress' ?>">
                <span class="status-badge <?= !empty($row['AccsConfigurationComplete']) ? 'status-approved' : 'status-draft' ?>">
                  <?= !empty($row['AccsConfigurationComplete']) ? 'Yes' : '—' ?>
                </span>
              </td>
              <td><a href="/operations-dashboard/signup-review/view.php?id=<?= (int) $row['ApplicationID'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($listResult['has_prev'] || $listResult['has_next']): ?>
      <div class="module-actions page-list-pagination">
        <?php if ($listResult['has_prev']): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(provider_signup_list_page_href($listFilters, $page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <span class="page-list-pagination-label">Page <?= (int) $page ?></span>
        <?php if ($listResult['has_next']): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(provider_signup_list_page_href($listFilters, $page + 1)) ?>">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
