<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';

links_require_read();

$activeSlug = 'links-index';
$statusFilter = $_GET['status'] ?? 'active';
$categoryFilter = $_GET['category'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status'   => $statusFilter !== '' ? $statusFilter : null,
    'category' => $categoryFilter !== '' ? $categoryFilter : null,
    'q'        => $search !== '' ? $search : null,
] + table_sort_state(LINKS_LIST_SORT_COLUMNS, 'category', 'asc', $_GET);
$links = links_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Links Index | NutraAxis Operations';
$pageDescription = 'Quick access to internal tools, Microsoft 365 apps, documents, and external reference sites.';

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
          <div class="section-label">Resources</div>
          <h1>Links Index</h1>
          <p class="page-lead">Curated shortcuts to web applications, Microsoft 365 apps, documents, and external reference sites used across NutraAxis operations.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(links_permission_value())) ?></p>
        </div>
        <?php if (links_can_create()): ?>
        <a class="btn-primary" href="/links-index/new.php">New Link</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Link created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Link updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Link deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/links-index/">
        <?php table_sort_hidden_inputs($listFilters, 'category', 'asc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (LINK_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars(links_status_label($status)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="category">Category</label>
            <select class="form-input" id="category" name="category">
              <option value="">All categories</option>
              <?php foreach (LINK_CATEGORIES as $category): ?>
              <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, description, or URL" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/links-index/">Clear</a>
        </div>
      </form>

      <?php if ($links === []): ?>
      <div class="status-banner">
        <div>
          <strong>No links found</strong>
          <p><?= links_can_create() ? 'Add your first link or adjust the filters above.' : 'No links match your filters.' ?></p>
        </div>
        <?php if (links_can_create()): ?>
        <a class="btn-primary" href="/links-index/new.php">New Link</a>
        <?php endif; ?>
      </div>
      <?php else: ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                LINKS_LIST_SORT_COLUMNS,
                '/links-index',
                $listFilters,
                ['status', 'category', 'q'],
                [],
                'category',
                'asc',
                '',
                'View | Edit'
            ); ?>
          </thead>
          <tbody>
            <?php foreach ($links as $link):
                $href = links_external_url((string) $link['LinkURL']);
                $description = trim((string) ($link['LinkDescription'] ?? ''));
            ?>
            <tr>
              <td><a href="<?= htmlspecialchars($href) ?>" <?= links_external_name_attrs() ?>><?= htmlspecialchars((string) $link['LinkName']) ?></a></td>
              <td><?= htmlspecialchars((string) $link['LinkCategory']) ?></td>
              <td><span class="status-badge <?= links_status_class((string) $link['LinkStatus']) ?>"><?= htmlspecialchars(links_status_label((string) $link['LinkStatus'])) ?></span></td>
              <td><?= !empty($link['UserRegistrationRequired']) ? 'Yes' : 'No' ?></td>
              <td><?= $description !== '' ? htmlspecialchars($description) : '—' ?></td>
              <?php table_view_edit_cell(
                  '/links-index/view.php?id=' . (int) $link['LinkID'],
                  '/links-index/edit.php?id=' . (int) $link['LinkID'],
                  links_can_update()
              ); ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
