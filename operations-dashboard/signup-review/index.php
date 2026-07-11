<?php
/**
 * Internal operations queue for provider onboarding review.
 * Public provider pages are under /provider-signup/ (marketing UI).
 */
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

provider_signup_require_read();

$activeSlug = 'signup-review';
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
] + table_sort_state(PROVIDER_SIGNUP_LIST_SORT_COLUMNS, 'submitted', 'desc', $_GET);
$applications = provider_signup_list_applications($listFilters);
$pendingCount = provider_signup_count_by_status(PROVIDER_SIGNUP_STATUS_DRAFT);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Provider Signup Management | NutraAxis Operations';
$pageDescription = 'Review and approve NutraAxis provider signup applications.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Operations',
          'title'      => 'Provider Signup Management',
          'lead'       => 'Review draft provider applications, validate NPI and banking data, approve for Clinic Store activation.',
          'permission' => permission_label(provider_signup_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'commented'): ?>
      <div class="admin-notice is-success" role="status">Comment added and provider notified.</div>
      <?php elseif ($notice === 'returned'): ?>
      <div class="admin-notice is-success" role="status">Application returned to provider for updates.</div>
      <?php elseif ($notice === 'approved'): ?>
      <div class="admin-notice is-success" role="status">Application approved.</div>
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

      <form class="po-filter page-list-filters" method="get" action="/operations-dashboard/signup-review/">
        <?php table_sort_hidden_inputs($listFilters, 'submitted', 'desc'); ?>
        <label for="status">Filter by status</label>
        <select class="form-input" id="status" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (PROVIDER_SIGNUP_STATUSES as $status): ?>
          <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php
            table_sort_render_head_row(
                PROVIDER_SIGNUP_LIST_SORT_COLUMNS,
                '/operations-dashboard/signup-review',
                $listFilters,
                ['status'],
                [],
                'submitted',
                'desc',
                'submitted',
                'Actions'
            );
            ?>
          </thead>
          <tbody>
            <?php if ($applications === []): ?>
            <tr>
              <td colspan="6">No provider applications found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($applications as $row): ?>
            <tr>
              <td><?= (int) $row['ApplicationID'] ?></td>
              <td><?= htmlspecialchars((string) ($row['CompanyName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($row['ProviderEmail'] ?? '')) ?></td>
              <td><span class="<?= htmlspecialchars(provider_signup_status_badge_class((string) $row['Status'])) ?>"><?= htmlspecialchars((string) $row['Status']) ?></span></td>
              <td><?= htmlspecialchars(provider_signup_format_datetime($row['SubmittedAt'] ?? null)) ?></td>
              <td><a href="/operations-dashboard/signup-review/view.php?id=<?= (int) $row['ApplicationID'] ?>">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
