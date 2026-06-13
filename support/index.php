<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/support.php';
require dirname(__DIR__) . '/includes/zendesk.php';

support_require_read();

$activeSlug = 'support';
$listFilters = support_list_filters();
$statusFilter = $listFilters['status'];
$search = $listFilters['q'];
$page = $listFilters['page'];
$notice = $_GET['notice'] ?? null;
$configError = zendesk_config_error();
$listResult = ['ok' => true, 'tickets' => [], 'users' => [], 'count' => 0, 'page' => 1, 'has_next' => false, 'has_prev' => false, 'error' => null];

if ($configError === null) {
    $listResult = zendesk_list_tickets([
        'status' => $statusFilter !== '' ? $statusFilter : null,
        'q'      => $search !== '' ? $search : null,
        'page'   => $page,
        'sort'   => $listFilters['sort'],
        'dir'    => $listFilters['dir'],
    ]);
}

$pageTitle = 'Support Tickets | Support';
$pageDescription = 'View and manage Zendesk support tickets from NutraAxis Operations.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Support</div>
          <h1>Zendesk Tickets</h1>
          <p class="page-lead">
            <?php if (support_is_agent()): ?>
            Browse all Zendesk tickets, reply, and manage status and priority.
            <?php elseif (support_can_create()): ?>
            View tickets submitted under your email and create new support requests.
            <?php else: ?>
            View tickets submitted under your NutraAxis email. Editing and replies require agent access.
            <?php endif; ?>
          </p>
          <p class="permission-note">Role access: <?= htmlspecialchars(permission_label(support_permission_value())) ?> · <?= htmlspecialchars(support_access_mode_label()) ?></p>
        </div>
        <?php if (support_can_create() && $configError === null): ?>
        <a class="btn-primary" href="/support/new.php">New Ticket</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Support ticket created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Ticket updated successfully.</div>
      <?php elseif ($notice === 'comment'): ?>
      <div class="admin-notice is-success" role="status">Reply posted successfully.</div>
      <?php endif; ?>

      <?php if ($configError !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($configError) ?></div>
      <?php elseif (!$listResult['ok']): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($listResult['error']) ?></div>
      <?php endif; ?>

      <?php if ($configError === null): ?>
      <form class="po-filter audit-filter" method="get" action="/support/">
        <?php table_sort_hidden_inputs($listFilters, 'updated', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (SUPPORT_TICKET_STATUSES as $value => $label): ?>
              <option value="<?= htmlspecialchars($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Subject, description, or ticket ID" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/support/">Clear</a>
        </div>
      </form>

      <?php if ($listResult['ok']): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                SUPPORT_LIST_SORT_COLUMNS,
                '/support',
                $listFilters,
                ['status', 'q'],
                SUPPORT_LIST_SORT_NUMERIC,
                'updated',
                'desc',
                'updated',
                'View'
            ); ?>
          </thead>
          <tbody>
            <?php if (($listResult['tickets'] ?? []) === []): ?>
            <tr>
              <td colspan="7">No tickets found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($listResult['tickets'] as $ticket): ?>
            <tr>
              <td>#<?= (int) $ticket['id'] ?></td>
              <td><?= htmlspecialchars(zendesk_ticket_subject($ticket)) ?></td>
              <td><?= htmlspecialchars(zendesk_ticket_requester_label($ticket, $listResult['users'] ?? [])) ?></td>
              <td><span class="status-badge <?= support_status_class((string) ($ticket['status'] ?? '')) ?>"><?= htmlspecialchars(support_status_label((string) ($ticket['status'] ?? ''))) ?></span></td>
              <td><?= htmlspecialchars(support_priority_label((string) ($ticket['priority'] ?? 'normal'))) ?></td>
              <td><?= htmlspecialchars(zendesk_format_datetime($ticket['updated_at'] ?? null)) ?></td>
              <?php table_actions_cell([
                  ['href' => '/support/view.php?id=' . (int) $ticket['id'], 'label' => 'View'],
              ]); ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($listResult['has_prev'] || $listResult['has_next']): ?>
      <div class="module-actions">
        <?php if ($listResult['has_prev']): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(support_list_page_href($listFilters, $page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($listResult['has_next']): ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(support_list_page_href($listFilters, $page + 1)) ?>">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
