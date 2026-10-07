<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-backlinks.php';

auth_require_module_read('marketing-backlinks');

$activeSlug = 'marketing-backlinks';
$baseHref = '/marketing/backlinks/';
$error = null;

if (($_GET['export'] ?? '') === 'disavow') {
    mkt_bl_disavow_export();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $back = (string) ($_POST['tab'] ?? 'links');
    switch ($action) {
        case 'import':
            marketing_require_update();
            [$text, $error] = mkt_import_text($_FILES['file'] ?? null, (string) ($_POST['paste'] ?? ''));
            if ($error === null) {
                $result = mkt_bl_import($text, !empty($_POST['full_list']));
                if ($result['ok']) {
                    marketing_redirect($baseHref, ['tab' => 'import', 'notice' => $result['message']]);
                }
                $error = $result['error'];
            }
            break;
        case 'gap_dismiss':
        case 'gap_restore':
            marketing_require_update();
            mkt_bl_gap_dismiss((string) ($_POST['domain'] ?? ''), $action === 'gap_dismiss');
            marketing_redirect($baseHref, ['tab' => 'gap', 'show' => (string) ($_POST['show'] ?? 'open'), 'notice' => $action === 'gap_dismiss' ? 'Domain dismissed.' : 'Domain restored.']);
        case 'prospect_from_gap':
        case 'prospect_from_lost':
            marketing_require_create();
            $domain = mkt_domain_of((string) ($_POST['domain'] ?? ''));
            $opportunity = $action === 'prospect_from_gap'
                ? 'Links to ' . trim((string) ($_POST['summary'] ?? 'competitors')) . ' but not to us'
                : 'Reclaim a lost link from ' . mb_substr((string) ($_POST['source_url'] ?? $domain), 0, 250);
            $result = mkt_prospect_create([
                'domain' => $domain, 'origin' => $action === 'prospect_from_gap' ? 'gap' : 'lost', 'opportunity' => mb_substr($opportunity, 0, 300),
                'target_url' => (string) ($_POST['target_url'] ?? ''),
            ]);
            if ($result['ok'] || isset($result['id'])) {
                marketing_redirect('/marketing/backlinks/prospect.php', ['id' => $result['id'], 'notice' => $result['ok'] ? 'Prospect added — fill in the contact and draft the pitch.' : $result['error']]);
            }
            $error = $result['error'];
            break;
        case 'disavow_add':
            marketing_require_update();
            $domains = preg_split('/[\s,]+/', (string) ($_POST['domains'] ?? '') . "\n" . (string) ($_POST['domain'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $added = mkt_bl_disavow_add($domains, (string) ($_POST['reason'] ?? ''));
            marketing_redirect($baseHref, ['tab' => $back, 'notice' => $added . ' domain' . ($added === 1 ? '' : 's') . ' added to the disavow list.']);
        case 'disavow_spam':
            marketing_require_update();
            $added = mkt_bl_disavow_add_spam();
            marketing_redirect($baseHref, ['tab' => 'disavow', 'notice' => $added . ' likely-spam domain' . ($added === 1 ? '' : 's') . ' added to the disavow list.']);
        case 'disavow_remove':
            marketing_require_update();
            mkt_bl_disavow_remove((string) ($_POST['domain'] ?? ''));
            marketing_redirect($baseHref, ['tab' => 'disavow', 'notice' => 'Removed from the disavow list.']);
    }
}

$summary = mkt_bl_summary();
$openProspects = $summary['Prospects']['to_contact'] + $summary['Prospects']['contacted'] + $summary['Prospects']['replied'];
$tabs = [
    'links'    => 'Link profile',
    'gap'      => 'Link gap (' . (int) $summary['GapOpen'] . ')',
    'outreach' => 'Outreach (' . $openProspects . ')',
    'disavow'  => 'Disavow (' . (int) $summary['Disavowed'] . ')',
    'import'   => 'Import',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'links';
if ($error !== null && ($_POST['action'] ?? '') === 'import') {
    $tab = 'import';
}
$spam = (int) $summary['SpamThreshold'];
$siteUrl = rtrim((string) marketing_setting('pages.site_url', 'https://www.' . mkt_our_host()), '/');
$ourPath = static function (?string $url): string {
    $path = (string) (parse_url((string) $url, PHP_URL_PATH) ?: '/');

    return htmlspecialchars($path . (($q = parse_url((string) $url, PHP_URL_QUERY)) ? '?' . $q : ''));
};
$pBadge = static fn(string $s): string => mkt_render_badge($s, MKT_PROSPECT_STATUSES);
$canUpdate = marketing_can_update();
$canCreate = marketing_can_create();

$pageTitle = 'Backlinks & Outreach | NutraAxis Operations';
$pageDescription = 'Link profile, link gap and outreach tracking.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Backlinks & Outreach',
          'lead'       => 'Who links to ' . mkt_our_host() . ', which sites link to competitors but not to us, and the outreach to win those links. Link data comes from OpenRush or Semrush reports you import. Pitches are drafted here and sent by a person from their own mailbox — nothing is emailed from the portal.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      marketing_render_tabs($baseHref, $tabs, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong><?= number_format((int) $summary['Domains']) ?> referring domain<?= (int) $summary['Domains'] === 1 ? '' : 's' ?> · <?= number_format((int) $summary['Links']) ?> live link<?= (int) $summary['Links'] === 1 ? '' : 's' ?></strong>
          <p>
            <?= (int) $summary['New28'] ?> new and <?= (int) $summary['Lost28'] ?> lost in the last 28 days<?php if ((int) $summary['Links'] > 0): ?> · <?= round(100 * (int) $summary['Followed'] / max(1, (int) $summary['Links'])) ?>% followed<?php endif; ?>
            <?php if ((int) $summary['SpamDomains'] > 0): ?> · <a href="<?= htmlspecialchars($baseHref) ?>?tab=links&amp;status=spam"><?= (int) $summary['SpamDomains'] ?> likely-spam domain<?= (int) $summary['SpamDomains'] === 1 ? '' : 's' ?> not yet disavowed</a><?php endif; ?>
          </p>
          <p class="form-hint">
            Last backlinks import: <?= $summary['LastLinks'] ? htmlspecialchars(marketing_format_datetime($summary['LastLinks'])) : 'none yet' ?>. Last link gap import: <?= $summary['LastGap'] ? htmlspecialchars(marketing_format_datetime($summary['LastGap'])) : 'none yet' ?>.
            Outreach: <?php foreach (MKT_PROSPECT_STATUSES as $key => $label): ?><?= $summary['Prospects'][$key] ?> <?= htmlspecialchars(mb_strtolower($label)) ?><?= $key !== 'declined' ? ' · ' : '.' ?><?php endforeach; ?>
          </p>
        </div>
      </div>

<?php if ($tab === 'links'): ?>
      <?php
      $filters = ['status' => array_key_exists((string) ($_GET['status'] ?? ''), MKT_LINK_FILTERS) ? (string) $_GET['status'] : 'active', 'q' => trim((string) ($_GET['q'] ?? ''))];
      $links = mkt_bl_links($filters);
      ?>
      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="links" />
        <div class="audit-filter-grid">
          <div>
            <label for="f_status">Show</label>
            <select class="form-input" id="f_status" name="status">
              <?php foreach (MKT_LINK_FILTERS as $key => $label): ?>
              <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Domain, URL, anchor" /></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Referring page</th><th>Spam score</th><th>Authority</th><th>Our page</th><th>Anchor text</th><th>Followed</th><th>First seen</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($links === []): ?>
            <tr><td colspan="9"><?= (int) $summary['Links'] === 0 && $filters['status'] === 'active' ? 'No links imported yet — use the Import tab.' : 'No links match these filters.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($links as $l): ?>
            <?php $isSpam = $l['SpamScore'] !== null && (int) $l['SpamScore'] >= $spam; ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $l['SourceDomain']) ?></strong><br><span class="form-hint mkt-break"><?= htmlspecialchars(mb_strimwidth((string) $l['SourceUrl'], 0, 90, '…')) ?></span></td>
              <td><?= $l['SpamScore'] !== null ? (int) $l['SpamScore'] : '<span class="form-hint">—</span>' ?><?= $isSpam ? ' <span class="status-badge status-cancelled">Likely spam</span>' : '' ?></td>
              <td><?= $l['Authority'] !== null ? (int) $l['Authority'] : '<span class="form-hint">—</span>' ?></td>
              <td><a href="<?= htmlspecialchars((string) $l['TargetUrl']) ?>" target="_blank" rel="noopener"><?= $ourPath($l['TargetUrl']) ?></a></td>
              <td class="mkt-break"><?= $l['Anchor'] !== null ? '“' . htmlspecialchars(mb_strimwidth((string) $l['Anchor'], 0, 80, '…')) . '”' : '<span class="form-hint">—</span>' ?></td>
              <td><?= $l['Followed'] === null ? '—' : ((int) $l['Followed'] === 1 ? 'Yes' : 'No') ?></td>
              <td><?= htmlspecialchars(marketing_format_date($l['FirstSeenIso'])) ?></td>
              <td>
                <?= $l['Status'] === 'lost' ? '<span class="status-badge status-cancelled">Lost ' . htmlspecialchars(marketing_format_date($l['LostAtIso'])) . '</span>' : '<span class="status-badge status-approved">Live</span>' ?>
                <?= (int) $l['Disavowed'] === 1 ? ' <span class="status-badge status-draft">Disavowed</span>' : '' ?>
              </td>
              <td>
                <?php if ($l['Status'] === 'lost' && $canCreate && $l['ProspectID'] === null && !$isSpam): ?>
                <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                  <input type="hidden" name="action" value="prospect_from_lost" /><input type="hidden" name="domain" value="<?= htmlspecialchars((string) $l['SourceDomain']) ?>" />
                  <input type="hidden" name="source_url" value="<?= htmlspecialchars((string) $l['SourceUrl']) ?>" /><input type="hidden" name="target_url" value="<?= htmlspecialchars((string) $l['TargetUrl']) ?>" />
                  <button type="submit" class="btn-text">Reclaim</button>
                </form>
                <?php elseif ($l['ProspectID'] !== null): ?>
                <a href="/marketing/backlinks/prospect.php?id=<?= (int) $l['ProspectID'] ?>">Prospect</a>
                <?php endif; ?>
                <?php if ((int) $l['Disavowed'] === 0 && $canUpdate): ?>
                <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline" onsubmit="return confirm('Add <?= htmlspecialchars((string) $l['SourceDomain']) ?> to the disavow list?');">
                  <input type="hidden" name="action" value="disavow_add" /><input type="hidden" name="tab" value="links" />
                  <input type="hidden" name="domain" value="<?= htmlspecialchars((string) $l['SourceDomain']) ?>" /><input type="hidden" name="reason" value="<?= $isSpam ? 'Spam score ' . (int) $l['SpamScore'] : 'Added from the link profile' ?>" />
                  <button type="submit" class="btn-text">Disavow</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">Spam score (0–100) and authority come from the import; links from domains scoring <?= $spam ?> or more (Settings: backlinks.spam_threshold) are flagged as likely spam. A link reported lost, or missing from a later full import, is marked Lost; <strong>Reclaim</strong> adds the site to Outreach.</p>

<?php elseif ($tab === 'gap'): ?>
      <?php
      $gapFilters = ['show' => in_array($_GET['show'] ?? '', ['open', 'dismissed', 'earned', 'all'], true) ? (string) $_GET['show'] : 'open', 'min_authority' => (int) ($_GET['min_authority'] ?? 0), 'q' => trim((string) ($_GET['q'] ?? ''))];
      $gap = mkt_bl_gap($gapFilters);
      ?>
      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="gap" />
        <div class="audit-filter-grid">
          <div>
            <label for="g_show">Show</label>
            <select class="form-input" id="g_show" name="show">
              <?php foreach (['open' => 'Open (not linking to us)', 'earned' => 'Already link to us', 'dismissed' => 'Dismissed', 'all' => 'All'] as $key => $label): ?>
              <option value="<?= $key ?>" <?= $gapFilters['show'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label for="g_min">Minimum authority</label><input class="form-input" type="number" min="0" max="100" id="g_min" name="min_authority" value="<?= $gapFilters['min_authority'] ?: '' ?>" /></div>
          <div class="audit-filter-wide"><label for="g_q">Search</label><input class="form-input" type="search" id="g_q" name="q" value="<?= htmlspecialchars($gapFilters['q']) ?>" placeholder="Domain or competitor" /></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=gap">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Domain</th><th>Authority</th><th>Spam score</th><th>Competitors linked</th><th>First seen</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($gap === []): ?>
            <tr><td colspan="6">No link-gap domains here. Import an OpenRush link gap or a Semrush Backlink Gap export.</td></tr>
            <?php endif; ?>
            <?php foreach ($gap as $g): ?>
            <?php $linkSummary = (int) $g['CompetitorCount'] . ' competitor' . ((int) $g['CompetitorCount'] === 1 ? '' : 's') . ($g['Competitors'] ? ' (' . $g['Competitors'] . ')' : ''); ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $g['Domain']) ?></strong><?= (int) $g['Earned'] === 1 ? ' <span class="status-badge status-approved">Links to us</span>' : '' ?></td>
              <td><?= $g['Authority'] !== null ? (int) $g['Authority'] : '<span class="form-hint">—</span>' ?></td>
              <td><?= $g['SpamScore'] !== null ? (int) $g['SpamScore'] : '<span class="form-hint">—</span>' ?></td>
              <td><?= htmlspecialchars($linkSummary) ?></td>
              <td><?= htmlspecialchars(marketing_format_date($g['FirstSeenIso'])) ?></td>
              <td>
                <?php if ($g['ProspectID'] !== null): ?>
                <a href="/marketing/backlinks/prospect.php?id=<?= (int) $g['ProspectID'] ?>">In outreach</a> <?= $pBadge((string) $g['ProspectStatus']) ?>
                <?php elseif ((int) $g['Earned'] === 0): ?>
                  <?php if ($canCreate): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                    <input type="hidden" name="action" value="prospect_from_gap" /><input type="hidden" name="domain" value="<?= htmlspecialchars((string) $g['Domain']) ?>" />
                    <input type="hidden" name="summary" value="<?= htmlspecialchars($linkSummary) ?>" />
                    <button type="submit" class="btn-secondary">Add to outreach</button>
                  </form>
                  <?php endif; ?>
                  <?php if ($canUpdate): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                    <input type="hidden" name="action" value="<?= (int) $g['Dismissed'] === 1 ? 'gap_restore' : 'gap_dismiss' ?>" /><input type="hidden" name="domain" value="<?= htmlspecialchars((string) $g['Domain']) ?>" />
                    <input type="hidden" name="show" value="<?= htmlspecialchars($gapFilters['show']) ?>" />
                    <button type="submit" class="btn-text"><?= (int) $g['Dismissed'] === 1 ? 'Restore' : 'Dismiss' ?></button>
                  </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">Sites that link to competitors in Settings but not to us, most competitors first, then by authority (0–100). Dismiss the ones that are not a fit (tool directories, unrelated sites). Only the linking domain, its scores and competitor names are kept — not the competitors’ pages.</p>

<?php elseif ($tab === 'outreach'): ?>
      <?php
      $pFilters = ['status' => (string) ($_GET['status'] ?? 'open'), 'owner' => (int) ($_GET['owner'] ?? 0), 'q' => trim((string) ($_GET['q'] ?? ''))];
      $prospects = mkt_prospects_list($pFilters);
      $today = mkt_local_date();
      ?>
      <div class="mkt-outreach-counts">
        <?php foreach (MKT_PROSPECT_STATUSES as $key => $label): ?>
        <a href="<?= htmlspecialchars($baseHref) ?>?tab=outreach&amp;status=<?= $key ?>"><?= $pBadge($key) ?> <?= $summary['Prospects'][$key] ?></a>
        <?php endforeach; ?>
      </div>
      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="outreach" />
        <div class="audit-filter-grid">
          <div>
            <label for="p_status">Status</label>
            <select class="form-input" id="p_status" name="status">
              <option value="open" <?= $pFilters['status'] === 'open' ? 'selected' : '' ?>>Open (to contact, contacted, replied)</option>
              <?php foreach (MKT_PROSPECT_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $pFilters['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
              <option value="all" <?= $pFilters['status'] === 'all' ? 'selected' : '' ?>>All</option>
            </select>
          </div>
          <div class="audit-filter-wide"><label for="p_q">Search</label><input class="form-input" type="search" id="p_q" name="q" value="<?= htmlspecialchars($pFilters['q']) ?>" placeholder="Domain, opportunity or contact" /></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <?php if ($canCreate): ?><a class="btn-secondary" href="/marketing/backlinks/prospect.php?new=1">New prospect</a><?php endif; ?>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Site</th><th>Opportunity</th><th>Our page</th><th>Contact</th><th>Owner</th><th>Status</th><th>Next follow-up</th><th>Last sent</th></tr></thead>
          <tbody>
            <?php if ($prospects === []): ?>
            <tr><td colspan="8">No prospects here yet. Add them from the Link gap, a lost link, or New prospect.</td></tr>
            <?php endif; ?>
            <?php foreach ($prospects as $p): ?>
            <tr>
              <td><a class="table-name-link" href="/marketing/backlinks/prospect.php?id=<?= (int) $p['ProspectID'] ?>"><?= htmlspecialchars((string) $p['Domain']) ?></a><br><span class="form-hint"><?= htmlspecialchars(MKT_PROSPECT_ORIGINS[$p['Origin']] ?? '') ?></span></td>
              <td><?= htmlspecialchars((string) $p['Opportunity']) ?></td>
              <td><?= $p['TargetUrl'] ? $ourPath($p['TargetUrl']) : '<span class="form-hint">—</span>' ?></td>
              <td><?= htmlspecialchars((string) ($p['ContactName'] ?? '')) ?: '<span class="form-hint">—</span>' ?></td>
              <td><?= htmlspecialchars((string) ($p['OwnerName'] ?? '')) ?></td>
              <td><?= $pBadge((string) $p['Status']) ?></td>
              <td><?php if ($p['NextFollowUpIso']): ?><?= $p['NextFollowUpIso'] <= $today ? '<span class="mkt-task-overdue">' . htmlspecialchars(marketing_format_date($p['NextFollowUpIso'])) . '</span>' : htmlspecialchars(marketing_format_date($p['NextFollowUpIso'])) ?><?php else: ?><span class="form-hint">—</span><?php endif; ?></td>
              <td><?= $p['LastSentAt'] ? htmlspecialchars(marketing_format_date($p['LastSentAt'])) : '<span class="form-hint">—</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">Follow-ups that fall due become tasks for the prospect’s owner in the Task queue (Settings: outreach.follow_up_days after Log as sent). When a later backlinks import finds a live link from the site, the prospect is marked <strong>Link won</strong> automatically.</p>

<?php elseif ($tab === 'disavow'): ?>
      <?php $disavow = mkt_bl_disavow_list(); ?>
      <div class="status-banner">
        <div>
          <strong>Disavow list</strong>
          <p>Domains you want Google to ignore when judging links to <?= htmlspecialchars(mkt_our_host()) ?>. Export the file and upload it yourself in Google Search Console’s disavow links tool — the portal never submits it.</p>
          <p class="form-hint">Only disavow links you are confident are spammy or manipulative (such as paid link networks). Google ignores most spam on its own, so this is a precaution.</p>
        </div>
        <div>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?export=disavow">Export disavow file</a>
          <?php if ($canUpdate && (int) $summary['SpamDomains'] > 0): ?>
          <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline" onsubmit="return confirm('Add all <?= (int) $summary['SpamDomains'] ?> likely-spam domains to the disavow list?');">
            <input type="hidden" name="action" value="disavow_spam" />
            <button type="submit" class="btn-secondary">Add <?= (int) $summary['SpamDomains'] ?> likely-spam domain<?= (int) $summary['SpamDomains'] === 1 ? '' : 's' ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($canUpdate): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="action" value="disavow_add" /><input type="hidden" name="tab" value="disavow" />
        <?php marketing_render_field_guide_link('backlink-disavow'); ?>
        <div class="form-grid">
          <div class="form-group"><label for="d_domains">Domains</label><textarea class="form-input" id="d_domains" name="domains" rows="3" placeholder="One per line, e.g. spammy-links.example"></textarea></div>
          <div class="form-group"><label for="d_reason">Reason</label><input class="form-input" id="d_reason" name="reason" maxlength="300" placeholder="e.g. Paid link network" /></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn-secondary">Add to disavow list</button></div>
      </form>
      <?php endif; ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Domain</th><th>Spam score</th><th>Live links</th><th>Reason</th><th>Added</th><th>By</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($disavow === []): ?>
            <tr><td colspan="7">The disavow list is empty.</td></tr>
            <?php endif; ?>
            <?php foreach ($disavow as $d): ?>
            <tr>
              <td><strong><?= htmlspecialchars((string) $d['Domain']) ?></strong></td>
              <td><?= $d['SpamScore'] !== null ? (int) $d['SpamScore'] : '<span class="form-hint">—</span>' ?></td>
              <td><?= (int) $d['LiveLinks'] ?></td>
              <td><?= htmlspecialchars((string) ($d['Reason'] ?? '')) ?></td>
              <td><?= htmlspecialchars(marketing_format_date($d['CreatedAt'])) ?></td>
              <td><?= htmlspecialchars((string) ($d['CreatedByName'] ?? '')) ?></td>
              <td>
                <?php if ($canUpdate): ?>
                <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                  <input type="hidden" name="action" value="disavow_remove" /><input type="hidden" name="domain" value="<?= htmlspecialchars((string) $d['Domain']) ?>" />
                  <button type="submit" class="btn-text">Remove</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php else: ?>
      <?php $imports = mkt_bl_imports(); ?>
      <?php if ($canUpdate): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import" />
        <?php marketing_render_field_guide_link('backlink-import'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="i_paste">Paste results</label>
            <div>
              <textarea class="form-input" id="i_paste" name="paste" rows="6" placeholder="Paste an OpenRush backlinks or link gap response"><?= htmlspecialchars((string) ($_POST['paste'] ?? '')) ?></textarea>
              <p class="form-hint">OpenRush: inspect_backlinks for <?= htmlspecialchars(mkt_our_host()) ?> with the backlinks view (status live, or lost for reclaim work), or compare_backlink_gap for <?= htmlspecialchars(mkt_our_host()) ?> against the competitors. Semrush: upload a Backlinks export or a Backlink Gap export as CSV. The type is detected automatically.</p>
            </div>
          </div>
          <div class="form-group"><label for="i_file">Or file</label><input class="form-input" type="file" id="i_file" name="file" accept=".csv,.json,.txt,text/csv,application/json" /></div>
          <div class="form-group">
            <label for="i_full">Full list</label>
            <label><input type="checkbox" id="i_full" name="full_list" value="1" /> This is our complete live backlink list — mark links not in it as lost</label>
          </div>
        </div>
        <p class="form-hint">Payloads for any other site are refused. For the link gap only the linking domain, its scores and which competitors it links to are kept.</p>
        <div class="form-actions"><button type="submit" class="btn-primary">Import</button></div>
      </form>
      <?php endif; ?>
      <h2 class="hub-section-title">Import history</h2>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Imported</th><th>Source</th><th>Type</th><th>Rows</th><th>New</th><th>Lost</th><th>Links won</th><th>Skipped</th><th>By</th></tr></thead>
        <tbody>
          <?php if ($imports === []): ?>
          <tr><td colspan="9">No imports yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($imports as $i): ?>
          <tr>
            <td><?= htmlspecialchars(marketing_format_datetime($i['CreatedAt'])) ?></td>
            <td><?= $i['Source'] === 'openrush' ? 'OpenRush' : 'Semrush' ?></td>
            <td><?= $i['Kind'] === 'gap' ? 'Link gap' : 'Backlinks' ?></td>
            <td><?= (int) $i['RowsRead'] ?></td>
            <td><?= (int) $i['NewCount'] ?></td>
            <td><?= $i['Kind'] === 'gap' ? '—' : (int) $i['LostCount'] ?></td>
            <td><?= $i['Kind'] === 'gap' ? '—' : (int) $i['WonCount'] ?></td>
            <td><?= (int) $i['Skipped'] ?></td>
            <td><?= htmlspecialchars((string) ($i['CreatedByName'] ?? '')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
