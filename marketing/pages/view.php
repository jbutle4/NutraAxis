<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-issues.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-pages');

$activeSlug = 'marketing-pages';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$page = mkt_page_get($id);
if ($page === null) {
    marketing_redirect('/marketing/pages/', ['error' => 'Page not found.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $back = static function (array $result, string $notice, string $anchor = '') use ($id): never {
        $query = !empty($result['ok'])
            ? ['id' => $id, 'notice' => (string) ($result['message'] ?? $notice)]
            : ['id' => $id, 'error' => (string) ($result['error'] ?? 'Action failed.')];
        header('Location: /marketing/pages/view.php?' . http_build_query($query) . ($anchor !== '' ? '#' . $anchor : ''));
        exit;
    };
    switch ((string) ($_POST['action'] ?? '')) {
        case 'recrawl':
            $back(process_execute('seo-crawl', ['page_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Page recrawled.');
        case 'exclude':
            $back(mkt_page_set_excluded($id, true), 'Page excluded — it stays out of crawls and the inventory totals.');
        case 'include':
            $back(mkt_page_set_excluded($id, false), 'Page included — it is crawled with the rest of the site.');
        case 'keyword_add':
            $back(mkt_page_keyword_add($id, (string) ($_POST['keyword'] ?? ''), (string) ($_POST['role'] ?? 'primary'), (string) ($_POST['source'] ?? 'manual')), 'Keyword mapped.', 'keywords');
        case 'keyword_remove':
            $back(mkt_page_keyword_remove($id, (int) ($_POST['page_keyword_id'] ?? 0)), 'Keyword removed.', 'keywords');
    }
    $back(['ok' => false, 'error' => 'Unknown action.'], '');
}

$canUpdate = marketing_can_update();
$crawls = mkt_page_crawls($id);
$keywords = mkt_page_keywords($id);
$queries = mkt_page_search_queries($id);
$metrics = mkt_page_metrics($id);
$issues = mkt_page_issue_list($page['Issues']);
$tracked = array_column(mkt_page_open_issues($id), null, 'Code');
$gscWindow = mkt_analytics_window('MktGscDaily');
$ga4Window = mkt_analytics_window('MktGa4Daily');

$pageTitle = $page['Path'] . ' | Page Inventory | NutraAxis Operations';
$pageDescription = 'Crawl health, issues, keywords and search performance for one page.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$post = static fn(string $action): string => '<input type="hidden" name="action" value="' . $action . '" /><input type="hidden" name="id" value="' . $id . '" />';
$delta = static function ($current, $prior): string {
    $current = (float) $current;
    $prior = (float) $prior;
    if ($prior == 0.0) {
        return $current > 0 ? ' <span class="form-hint">(new)</span>' : '';
    }
    $pct = ($current - $prior) / $prior * 100;
    return ' <span class="form-hint">(' . ($pct >= 0 ? '+' : '') . number_format($pct, 0) . '% vs prior 28 days)</span>';
};
$changeLabels = ['StatusCode' => 'Status', 'Title' => 'Title', 'MetaDescription' => 'Meta description', 'H1' => 'H1', 'Canonical' => 'Canonical', 'Robots' => 'Robots', 'content' => 'Body text'];
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/pages/',
          'back_label' => 'Back to Page Inventory',
          'category'   => 'Marketing & Research',
          'title'      => (string) $page['Path'],
          'lead'       => (MKT_PAGE_TYPES[$page['PageType']] ?? $page['PageType']) . ' page · ' . (MKT_PAGE_STATUSES[$page['Status']] ?? $page['Status']),
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>URL</dt><dd><a href="<?= htmlspecialchars((string) $page['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $page['Url']) ?></a></dd>
          <dt>Source</dt><dd><?= htmlspecialchars(MKT_PAGE_SOURCES[$page['Source']] ?? (string) $page['Source']) ?><?= (int) $page['InSitemap'] ? ' · in sitemap' : ' · not in sitemap' ?></dd>
          <?php if ($page['ContentID']): ?><dt>Content</dt><dd><a href="/marketing/content/view.php?id=<?= (int) $page['ContentID'] ?>"><?= htmlspecialchars((string) $page['ContentTitle']) ?></a></dd><?php endif; ?>
          <dt>Last crawl</dt><dd><?= $page['LastCrawledAt'] ? htmlspecialchars(marketing_format_datetime((string) $page['LastCrawledAt'])) . ' · HTTP ' . (int) $page['LastStatusCode'] : 'Not crawled yet' ?><?= $page['FinalUrl'] && $page['FinalUrl'] !== $page['Url'] ? ' · final URL <code>' . htmlspecialchars((string) $page['FinalUrl']) . '</code>' : '' ?></dd>
          <dt>Issues</dt><dd><?= mkt_page_issue_badges($page['Issues']) ?: 'None' ?></dd>
          <dt>H1</dt><dd><?= htmlspecialchars((string) ($page['H1'] ?? '—')) ?><?= (int) $page['H1Count'] > 1 ? ' <span class="form-hint">(' . (int) $page['H1Count'] . ' H1s)</span>' : '' ?></dd>
          <dt>Canonical</dt><dd><?= $page['Canonical'] ? '<code>' . htmlspecialchars((string) $page['Canonical']) . '</code>' : '—' ?></dd>
          <?php if ($page['Robots']): ?><dt>Robots</dt><dd><code><?= htmlspecialchars((string) $page['Robots']) ?></code></dd><?php endif; ?>
          <dt>Content</dt><dd><?= $page['WordCount'] !== null ? number_format((int) $page['WordCount']) . ' words' : '—' ?> · <?= (int) $page['InternalLinks'] ?> internal / <?= (int) $page['ExternalLinks'] ?> external links<?= $page['HasStructuredData'] !== null ? ' · structured data ' . ((int) $page['HasStructuredData'] ? 'yes' : 'no') : '' ?></dd>
          <?php if ($page['PageType'] === 'product'): ?><dt></dt><dd class="form-hint">Product details load in the browser after the page opens, so word and link counts only reflect the page shell.</dd><?php endif; ?>
        </dl>
        <?php if ($canUpdate): ?>
        <div class="form-actions">
          <form method="post" action="/marketing/pages/view.php" style="display:inline"><?= $post('recrawl') ?><button type="submit" class="btn-secondary">Recrawl now</button></form>
          <?php if ($page['Status'] === 'excluded'): ?>
          <form method="post" action="/marketing/pages/view.php" style="display:inline"><?= $post('include') ?><button type="submit" class="btn-secondary">Include in inventory</button></form>
          <?php else: ?>
          <form method="post" action="/marketing/pages/view.php" style="display:inline" onsubmit="return confirm('Exclude this page from crawls and inventory totals?');"><?= $post('exclude') ?><button type="submit" class="btn-secondary">Exclude</button></form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($issues !== [] || $tracked !== []): ?>
      <h2 class="hub-section-title">What to fix</h2>
      <ul>
        <?php foreach ($issues as $code): ?>
        <?php $info = mkt_page_issue_info($code); ?>
        <li>
          <strong><?= htmlspecialchars($info['label']) ?>.</strong> <?= htmlspecialchars($info['advice']) ?>
          <?php if ($code === 'legacy_brand' && $page['LegacyTerms']): ?><span class="form-hint">Found: <?= htmlspecialchars((string) $page['LegacyTerms']) ?>.</span><?php endif; ?>
          <?php if ($code === 'missing_alt'): ?><span class="form-hint"><?= (int) $page['ImagesMissingAlt'] ?> image<?= (int) $page['ImagesMissingAlt'] === 1 ? '' : 's' ?>.</span><?php endif; ?>
          <?php if (isset($tracked[$code])): ?><a href="/marketing/issues/view.php?id=<?= (int) $tracked[$code]['IssueID'] ?>">Issue: <?= htmlspecialchars(MKT_ISSUE_STATUSES[$tracked[$code]['Status']] ?? (string) $tracked[$code]['Status']) ?></a><?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php foreach (array_diff_key($tracked, array_flip($issues)) as $code => $row): ?>
        <li><strong><?= htmlspecialchars((string) $row['Title']) ?>.</strong> <?= htmlspecialchars((string) ($row['Detail'] ?? '')) ?> <a href="/marketing/issues/view.php?id=<?= (int) $row['IssueID'] ?>">Issue: <?= htmlspecialchars(MKT_ISSUE_STATUSES[$row['Status']] ?? (string) $row['Status']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <h2 class="hub-section-title">In Google results</h2>
      <div class="mkt-serp" style="margin-bottom:0.75rem">
        <div class="mkt-serp-url"><?= htmlspecialchars((string) $page['Url']) ?></div>
        <div class="mkt-serp-title"><?= htmlspecialchars((string) ($page['Title'] ?? '(no title)')) ?></div>
        <div class="mkt-serp-desc"><?= htmlspecialchars((string) ($page['MetaDescription'] ?? '(no meta description — Google picks text from the page)')) ?></div>
        <div class="form-hint">Title <?= mb_strlen((string) $page['Title']) ?>/<?= (int) marketing_setting('seo.title_max', '60') ?> · description <?= mb_strlen((string) $page['MetaDescription']) ?>/<?= (int) marketing_setting('seo.meta_max', '160') ?></div>
      </div>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Search clicks</dt><dd><?= number_format((int) $metrics['current']['Clicks']) ?><?= $delta($metrics['current']['Clicks'], $metrics['prior']['Clicks']) ?></dd>
          <dt>Impressions</dt><dd><?= number_format((int) $metrics['current']['Impressions']) ?><?= $delta($metrics['current']['Impressions'], $metrics['prior']['Impressions']) ?></dd>
          <dt>Avg position</dt><dd><?= $metrics['current']['Position'] !== null ? htmlspecialchars((string) $metrics['current']['Position']) : '—' ?></dd>
          <dt>Sessions landing here</dt><dd><?= number_format((int) $metrics['current']['Sessions']) ?><?= $delta($metrics['current']['Sessions'], $metrics['prior']['Sessions']) ?> · <?= number_format((int) $metrics['current']['EngagedSessions']) ?> engaged · <?= number_format((float) $metrics['current']['KeyEvents'], 0) ?> key events</dd>
          <dt class="is-wide">Window</dt><dd class="form-hint">Last 28 days of data — Search Console <?= $gscWindow ? htmlspecialchars(marketing_format_date($gscWindow[0]) . ' – ' . marketing_format_date($gscWindow[1])) : 'no data yet' ?>, GA4 <?= $ga4Window ? htmlspecialchars(marketing_format_date($ga4Window[0]) . ' – ' . marketing_format_date($ga4Window[1])) : 'no data yet' ?>.</dd>
        </dl>
      </div>

      <h2 class="hub-section-title" id="keywords">Target keywords</h2>
      <?php if ($keywords === []): ?>
      <p class="form-hint">No keywords mapped to this page yet.</p>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Keyword</th><th>Role</th><th>Source</th><th>Added</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($keywords as $k): ?>
            <tr>
              <td><?= htmlspecialchars((string) $k['Keyword']) ?><?= (int) $k['OtherPrimaryPages'] > 0 && $k['Role'] === 'primary' ? ' <span class="status-badge status-submitted" title="Another active page also targets this keyword as primary">Also primary on ' . (int) $k['OtherPrimaryPages'] . ' other page' . ((int) $k['OtherPrimaryPages'] === 1 ? '' : 's') . '</span>' : '' ?></td>
              <td><?= htmlspecialchars(ucfirst((string) $k['Role'])) ?></td>
              <td><?= htmlspecialchars(['manual' => 'Added by hand', 'content' => 'Content Pipeline', 'search' => 'From search queries'][$k['Source']] ?? (string) $k['Source']) ?></td>
              <td><?= htmlspecialchars(marketing_format_date((string) $k['CreatedAt'])) ?><?= $k['CreatedByName'] ? ' · ' . htmlspecialchars((string) $k['CreatedByName']) : '' ?></td>
              <td><?php if ($canUpdate): ?><form method="post" action="/marketing/pages/view.php" style="display:inline"><?= $post('keyword_remove') ?><input type="hidden" name="page_keyword_id" value="<?= (int) $k['PageKeywordID'] ?>" /><button type="submit" class="btn-text">Remove</button></form><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php if ($canUpdate): ?>
      <?php marketing_render_field_guide_link('page-keyword'); ?>
      <form class="mkt-inline-form" method="post" action="/marketing/pages/view.php">
        <?= $post('keyword_add') ?>
        <label for="keyword">Add keyword</label>
        <input class="form-input" id="keyword" name="keyword" maxlength="200" required placeholder="e.g. berberine for metabolic health" />
        <select class="form-input" name="role" aria-label="Role"><option value="primary">Primary</option><option value="secondary">Secondary</option></select>
        <button type="submit" class="btn-secondary">Add</button>
      </form>
      <?php endif; ?>

      <h2 class="hub-section-title">Search queries (last 90 days)</h2>
      <?php if ($queries === []): ?>
      <p class="form-hint">Google has not shown this page for any query yet<?= $gscWindow === null ? ' (Search Console data has not loaded yet)' : '' ?>. Search Console hides very rare queries, so page totals can be higher than the sum below.</p>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Avg position</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($queries as $q): ?>
            <tr>
              <td><?= htmlspecialchars((string) $q['Query']) ?></td>
              <td><?= number_format((int) $q['Clicks']) ?></td>
              <td><?= number_format((int) $q['Impressions']) ?></td>
              <td><?= (int) $q['Impressions'] > 0 ? number_format((int) $q['Clicks'] / (int) $q['Impressions'] * 100, 1) . '%' : '—' ?></td>
              <td><?= htmlspecialchars((string) ($q['Position'] ?? '—')) ?></td>
              <td>
                <?php if ((int) $q['Mapped']): ?><span class="form-hint">Mapped</span>
                <?php elseif ($canUpdate): ?>
                <form method="post" action="/marketing/pages/view.php" style="display:inline">
                  <?= $post('keyword_add') ?><input type="hidden" name="keyword" value="<?= htmlspecialchars((string) $q['Query']) ?>" /><input type="hidden" name="role" value="secondary" /><input type="hidden" name="source" value="search" />
                  <button type="submit" class="btn-text">Map as secondary</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <h2 class="hub-section-title">Crawl history</h2>
      <?php if ($crawls === []): ?>
      <p class="form-hint">Not crawled yet.</p>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Crawled</th><th>Status</th><th>Response</th><th>Title</th><th>Words</th><th>Issues</th><th>Changed</th></tr></thead>
          <tbody>
            <?php foreach ($crawls as $c): ?>
            <tr>
              <td><?= htmlspecialchars(marketing_format_datetime((string) $c['CrawledAt'])) ?></td>
              <td><?= $c['StatusCode'] ? (int) $c['StatusCode'] : htmlspecialchars((string) ($c['ErrorMessage'] ?? 'error')) ?></td>
              <td><?= $c['ResponseMs'] !== null ? number_format((int) $c['ResponseMs']) . ' ms' : '—' ?></td>
              <td><?= htmlspecialchars((string) ($c['Title'] ?? '—')) ?></td>
              <td><?= $c['WordCount'] !== null ? number_format((int) $c['WordCount']) : '—' ?></td>
              <td><?= mkt_page_issue_badges($c['Issues']) ?: '—' ?></td>
              <td><?= $c['Changes'] ? htmlspecialchars(implode(', ', array_map(static fn(string $f): string => $changeLabels[$f] ?? $f, explode(',', (string) $c['Changes'])))) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
