<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-pages.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-pages');

$activeSlug = 'marketing-pages';
$baseHref = '/marketing/pages/';
$tabs = [
    'pages'    => 'Pages',
    'issues'   => 'Issues',
    'changes'  => 'Changes',
    'keywords' => 'Keyword map',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'pages';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $run = static function (array $params) use ($baseHref): never {
        $result = process_execute('seo-crawl', $params, PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
        if (!empty($result['ok']) && !empty($result['page_id']) && isset($params['url'])) {
            marketing_redirect('/marketing/pages/view.php', ['id' => (int) $result['page_id'], 'notice' => (string) ($result['message'] ?? 'Page added.')]);
        }
        marketing_redirect($baseHref, !empty($result['ok'])
            ? ['notice' => (string) ($result['message'] ?? 'Crawl complete.')]
            : ['error' => (string) ($result['error'] ?? 'Crawl failed.')]);
    };
    if ($action === 'crawl') {
        $run([]);
    }
    if ($action === 'add_url') {
        $url = trim((string) ($_POST['url'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            marketing_redirect($baseHref, ['error' => 'Enter a full page URL starting with https://.']);
        }
        $run(['url' => $url]);
    }
}

$filters = [
    'type'   => (string) ($_GET['type'] ?? ''),
    'status' => (string) ($_GET['status'] ?? 'active'),
    'issue'  => (string) ($_GET['issue'] ?? ''),
    'q'      => trim((string) ($_GET['q'] ?? '')),
    'sort'   => (string) ($_GET['sort'] ?? ''),
];
$counts = mkt_page_counts();
$status = mkt_analytics_status();
$canUpdate = marketing_can_update();
$gscWindow = mkt_analytics_window('MktGscDaily');
$ga4Window = mkt_analytics_window('MktGa4Daily');

$pageTitle = 'Page Inventory | NutraAxis Operations';
$pageDescription = 'Every public page with its crawl health, issues, keyword map, and search and GA4 numbers.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$select = static function (string $name, array $options, string $current, string $id = ''): void {
    echo '<select class="form-input" id="' . htmlspecialchars($id ?: $name) . '" name="' . htmlspecialchars($name) . '">';
    foreach ($options as $key => $label) {
        echo '<option value="' . htmlspecialchars((string) $key) . '"' . ((string) $key === $current ? ' selected' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    echo '</select>';
};
$range = static fn(?array $w): string => $w === null ? 'no data yet' : marketing_format_date($w[0]) . ' – ' . marketing_format_date($w[1]);
$lastRun = static function (?array $run): string {
    if ($run === null) {
        return 'not run yet';
    }
    return marketing_format_datetime((string) $run['StartedAt']) . ' (' . strtolower((string) $run['Status']) . ')';
};
$tabLabels = $tabs;
$tabLabels['pages'] .= ' (' . $counts['active'] . ')';
$tabLabels['issues'] .= ' (' . $counts['with_issues'] . ')';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Page Inventory',
          'lead'       => 'Every public page on nutraaxislabs.com — from the sitemaps, published Content Pipeline pieces, and URLs Google shows in search — with its crawl health, SEO issues, target keywords, and Search Console and GA4 numbers.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Inventory</strong>
          <p>
            <?= number_format($counts['active']) ?> active pages ·
            <?= number_format($counts['with_issues']) ?> with issues ·
            <?= number_format($counts['errors']) ?> not loading ·
            <?= number_format($counts['excluded']) ?> excluded ·
            <?= number_format($counts['gone']) ?> gone.
            Last crawl <?= htmlspecialchars($lastRun($status['seo-crawl']['run'])) ?>.
          </p>
          <p class="form-hint">
            Search and sessions columns cover the last 28 days of data — Search Console <?= htmlspecialchars($range($gscWindow)) ?>, GA4 <?= htmlspecialchars($range($ga4Window)) ?>.
          </p>
        </div>
        <?php if ($canUpdate): ?>
        <div>
          <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
            <input type="hidden" name="action" value="crawl" />
            <button type="submit" class="btn-secondary" title="Read the sitemaps and recrawl every active page (about a minute)">Crawl site now</button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($tab === 'pages'): ?>
        <?php $pages = mkt_pages_list($filters); ?>
        <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <div class="audit-filter-grid">
            <div><label for="f_type">Type</label><?php $select('type', ['' => 'All'] + MKT_PAGE_TYPES, $filters['type'], 'f_type'); ?></div>
            <div><label for="f_status">Status</label><?php $select('status', MKT_PAGE_STATUSES + ['all' => 'All'], $filters['status'], 'f_status'); ?></div>
            <div><label for="f_issue">Issue</label><?php $select('issue', ['' => 'Any or none', 'any' => 'Has any issue'] + array_map(static fn(array $i): string => $i[0], MKT_PAGE_ISSUES), $filters['issue'], 'f_issue'); ?></div>
            <div><label for="f_sort">Sort</label><?php $select('sort', ['' => 'Type, then path', 'clicks' => 'Search clicks', 'sessions' => 'Sessions', 'issues' => 'Most issues', 'crawled' => 'Recently crawled'], $filters['sort'], 'f_sort'); ?></div>
            <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Path or title" /></div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
          </div>
        </form>

        <?php if ($pages === []): ?>
        <p class="form-hint">No pages match. <?= $counts['active'] === 0 ? 'Run "Crawl site now" to build the inventory from the sitemaps.' : '' ?></p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Page</th><th>Type</th><th>Title</th><th>Words</th><th>Issues</th><th>Search clicks</th><th>Impressions</th><th>Avg position</th><th>Sessions</th><th>Keywords</th><th>Crawled</th></tr>
            </thead>
            <tbody>
              <?php foreach ($pages as $page): ?>
              <tr>
                <td>
                  <a href="/marketing/pages/view.php?id=<?= (int) $page['PageID'] ?>"><?= htmlspecialchars((string) $page['Path']) ?></a>
                  <?php if ($page['Status'] !== 'active'): ?> <?= mkt_render_badge((string) $page['Status'], MKT_PAGE_STATUSES) ?><?php endif; ?>
                  <?php if (!(int) $page['InSitemap'] && $page['Status'] === 'active'): ?><br /><span class="form-hint">Not in sitemap (<?= htmlspecialchars(MKT_PAGE_SOURCES[$page['Source']] ?? $page['Source']) ?>)</span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(MKT_PAGE_TYPES[$page['PageType']] ?? $page['PageType']) ?></td>
                <td><?= htmlspecialchars((string) ($page['Title'] ?? '—')) ?></td>
                <td><?= $page['WordCount'] !== null ? number_format((int) $page['WordCount']) : '—' ?></td>
                <td><?= mkt_page_issue_badges($page['Issues']) ?: '<span class="form-hint">None</span>' ?></td>
                <td><?= number_format((int) $page['Clicks']) ?></td>
                <td><?= number_format((int) $page['Impressions']) ?></td>
                <td><?= $page['Position'] !== null ? htmlspecialchars((string) $page['Position']) : '—' ?></td>
                <td><?= number_format((int) $page['Sessions']) ?></td>
                <td><?= (int) $page['Keywords'] ?></td>
                <td><?= $page['LastCrawledAt'] ? htmlspecialchars(marketing_format_date((string) $page['LastCrawledAt'])) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($canUpdate): ?>
        <h2 class="hub-section-title">Add a page</h2>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="action" value="add_url" />
          <?php marketing_render_field_guide_link('page-add'); ?>
          <div class="form-group"><label for="url">Page URL</label><input class="form-input" type="url" id="url" name="url" required placeholder="https://www.nutraaxislabs.com/…" /></div>
          <p class="form-hint">For a live page missing from the sitemaps. It is added and crawled right away. Published Content Pipeline pieces are added automatically, except posts on our blog: they all share one address (/our-blog), so add that page to track the blog as a whole.</p>
          <div class="form-actions"><button type="submit" class="btn-primary">Add and crawl</button></div>
        </form>
        <?php endif; ?>

      <?php elseif ($tab === 'issues'): ?>
        <?php $issueCounts = mkt_page_issue_counts(); ?>
        <?php if ($issueCounts === []): ?>
        <p class="form-hint">No issues on active pages<?= $counts['active'] === 0 ? ' — the site has not been crawled yet' : '' ?>.</p>
        <?php else: ?>
        <p class="form-hint">Checks from the last crawl of each active page. Fixes are made in the website (Adobe document authoring); the next crawl clears an issue once it is fixed.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Issue</th><th>Severity</th><th>Pages</th><th>What to do</th></tr></thead>
            <tbody>
              <?php foreach ($issueCounts as $code => $n): ?>
              <?php $info = mkt_page_issue_info($code); ?>
              <tr>
                <td><?= mkt_page_issue_badges($code) ?></td>
                <td><?= htmlspecialchars(['error' => 'Fix now', 'warn' => 'Should fix', 'info' => 'Minor'][$info['severity']]) ?></td>
                <td><a href="<?= htmlspecialchars($baseHref . '?' . http_build_query(['issue' => $code])) ?>"><?= (int) $n ?> page<?= $n === 1 ? '' : 's' ?></a></td>
                <td><?= htmlspecialchars($info['advice']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      <?php elseif ($tab === 'changes'): ?>
        <?php $changes = mkt_page_changes(); ?>
        <?php if ($changes === []): ?>
        <p class="form-hint">No changes detected yet. Each crawl compares status, title, meta description, H1, canonical, robots and body text with the page's previous crawl.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Crawled</th><th>Page</th><th>What changed</th><th>Before</th><th>After</th></tr></thead>
            <tbody>
              <?php foreach ($changes as $change): ?>
              <?php
                $fields = array_filter(explode(',', (string) $change['Changes']));
                $labels = ['StatusCode' => 'Status', 'Title' => 'Title', 'MetaDescription' => 'Meta description', 'H1' => 'H1', 'Canonical' => 'Canonical', 'Robots' => 'Robots', 'content' => 'Body text'];
                $shown = array_values(array_intersect(['StatusCode', 'Title', 'MetaDescription', 'H1'], $fields));
              ?>
              <tr>
                <td><?= htmlspecialchars(marketing_format_datetime((string) $change['CrawledAt'])) ?></td>
                <td><a href="/marketing/pages/view.php?id=<?= (int) $change['PageID'] ?>"><?= htmlspecialchars((string) $change['Path']) ?></a></td>
                <td><?= htmlspecialchars(implode(', ', array_map(static fn(string $f): string => $labels[$f] ?? $f, $fields))) ?></td>
                <td><?php foreach ($shown as $f): ?><div><strong><?= htmlspecialchars($labels[$f]) ?>:</strong> <?= htmlspecialchars((string) ($change['Previous'][$f] ?? '—')) ?></div><?php endforeach; ?></td>
                <td><?php foreach ($shown as $f): ?><div><strong><?= htmlspecialchars($labels[$f]) ?>:</strong> <?= htmlspecialchars((string) ($change[$f] ?? '—')) ?></div><?php endforeach; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      <?php else: ?>
        <?php $map = mkt_page_keyword_map(); ?>
        <p class="form-hint">Which page targets which keyword. Published Content Pipeline pieces add their keywords automatically (not blog posts, which share the /our-blog page); add others on a page's detail screen (including from the Search Console queries it already ranks for). A keyword that is primary on two pages makes them compete with each other.</p>
        <?php if ($map === []): ?>
        <p class="form-hint">No keywords mapped yet.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Keyword</th><th>Page</th><th>Role</th><th>Source</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($map as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) $row['Keyword']) ?></td>
                <td><a href="/marketing/pages/view.php?id=<?= (int) $row['PageID'] ?>"><?= htmlspecialchars((string) $row['Path']) ?></a></td>
                <td><?= htmlspecialchars(ucfirst((string) $row['Role'])) ?></td>
                <td><?= htmlspecialchars(['manual' => 'Added by hand', 'content' => 'Content Pipeline', 'search' => 'From search queries'][$row['Source']] ?? (string) $row['Source']) ?></td>
                <td><?php if ((int) $row['PrimaryPages'] > 1 && $row['Role'] === 'primary'): ?><span class="status-badge status-submitted">Primary on <?= (int) $row['PrimaryPages'] ?> pages</span><?php endif; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
