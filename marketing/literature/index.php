<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('research-literature');

$activeSlug = 'research-literature';
$baseHref = '/marketing/literature/';
$tabKeys = ['library', 'review', 'flyers', 'claims', 'regulatory', 'add'];
$tab = in_array((string) ($_GET['tab'] ?? ''), $tabKeys, true) ? (string) $_GET['tab'] : 'library';
$error = null;

$back = static function (array $result, string $tab, string $okMessage, array $query = []) use ($baseHref): never {
    marketing_redirect($baseHref, ['tab' => $tab] + $query + (!empty($result['ok'])
        ? ['notice' => (string) ($result['message'] ?? $okMessage)]
        : ['error' => (string) ($result['error'] ?? 'Something went wrong.')]));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $returnTab = in_array((string) ($_POST['tab'] ?? ''), $tabKeys, true) ? (string) $_POST['tab'] : $tab;
    $keep = array_filter(['product' => (string) ($_POST['product'] ?? ''), 'status' => (string) ($_POST['status_filter'] ?? '')]);
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $flyerRefId = (int) ($_POST['flyer_ref_id'] ?? 0);

    switch ($action) {
        case 'add_feed':
            $back(mkt_lit_add_from_feed($itemId), $returnTab, 'Added to the library.', $keep);
        case 'dismiss':
            mkt_lit_dismiss($itemId);
            $back(['ok' => true], $returnTab, 'Marked as not needed. It stays in the research feed.', $keep);
        case 'undismiss':
            mkt_lit_undismiss($itemId);
            $back(['ok' => true], $returnTab, 'Back in the review list.', $keep + ['show' => 'dismissed']);
        case 'add_lookup':
        case 'flyer_manual':
        case 'flyer_confirm':
            $identifier = mkt_lit_identifier((string) ($_POST['identifier'] ?? ''));
            if ($identifier === null) {
                $error = 'Enter a PubMed ID, DOI, or ClinicalTrials.gov number (NCT…), or paste its link.';
                $tab = $action === 'add_lookup' ? 'add' : 'flyers';
                break;
            }
            $params = ['identifier' => $identifier];
            if ($action === 'add_lookup') {
                $takeaway = trim((string) ($_POST['takeaway'] ?? ''));
                if ($takeaway !== '') {
                    $params['takeaway'] = mb_substr($takeaway, 0, 1000);
                }
                $productId = (int) ($_POST['flyer_product_id'] ?? 0);
                $number = trim((string) ($_POST['flyer_number'] ?? ''));
                if ($productId > 0 || $number !== '') {
                    mkt_lit_flyer_sync();
                    $stmt = db()->prepare('SELECT FlyerRefID, MatchStatus FROM dbo.MktLitFlyerRef WHERE ProductID = :p AND RefNumber = :n');
                    $stmt->execute(['p' => $productId, 'n' => ctype_digit($number) ? (int) $number : 0]);
                    $ref = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$ref) {
                        $error = 'That flyer has no reference with that number. Pick the product and the number it has on the flyer, or leave both empty.';
                        $tab = 'add';
                        break;
                    }
                    if ($ref['MatchStatus'] === 'matched') {
                        $error = 'That flyer reference is already matched. Undo the match on the Flyer references tab first.';
                        $tab = 'add';
                        break;
                    }
                    $flyerRefId = (int) $ref['FlyerRefID'];
                }
            }
            if ($flyerRefId > 0) {
                $params['flyer_ref_id'] = $flyerRefId;
            }
            $result = process_execute('literature-add-source', $params, PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            $back($result, $action === 'add_lookup' ? 'library' : 'flyers', 'Source added.', $action === 'add_lookup' ? [] : $keep);
        case 'flyer_match':
            mkt_lit_flyer_sync();
            $params = ((int) ($_POST['product_id'] ?? 0)) > 0 ? ['product_id' => (int) $_POST['product_id']] : [];
            if ($flyerRefId > 0) {
                mkt_lit_flyer_set($flyerRefId, 'unmatched');
                $params = ['flyer_ref_id' => $flyerRefId];
            }
            $back(process_execute('literature-flyer-match', $params, PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'flyers', 'Lookup finished.', $keep);
        case 'flyer_set':
            $status = (string) ($_POST['status'] ?? '');
            $labels = ['no_match' => 'Marked as no match. Enter the PMID or DOI by hand when you find it.', 'other' => 'Marked as not a journal article.', 'unmatched' => 'Match removed; the reference is back to not checked.'];
            $back(['ok' => mkt_lit_flyer_set($flyerRefId, $status), 'error' => 'Flyer reference not found.'], 'flyers', $labels[$status] ?? 'Saved.', $keep);
        case 'reg_set':
            $status = (string) ($_POST['status'] ?? '');
            $result = mkt_lit_regulatory_set($itemId, $status, (string) ($_POST['note'] ?? ''));
            if ($result['ok']) {
                mkt_tasks_sync();
            }
            $messages = ['reviewed' => 'Marked reviewed — no action.', 'action' => 'Marked as needing action. A Compliance task is open.', 'done' => 'Marked done. The Compliance task is closed.', 'new' => 'Reopened.'];
            $back($result, 'regulatory', $messages[$status] ?? 'Saved.', $keep);
    }
}

$canUpdate = marketing_can_update();
$filters = [
    'level'   => array_key_exists((string) ($_GET['level'] ?? ''), MKT_LIT_LEVELS) ? (string) $_GET['level'] : '',
    'product' => (string) ($_GET['product'] ?? ''),
    'area'    => (string) ($_GET['area'] ?? ''),
    'q'       => trim((string) ($_GET['q'] ?? '')),
    'status'  => (string) ($_GET['status'] ?? ''),
];

$library = mkt_lit_sources($tab === 'library' ? $filters : []);
if ($tab === 'library' && ($_GET['export'] ?? '') === 'csv') {
    mkt_lit_export_csv($library);
}
if (in_array($tab, ['flyers', 'claims'], true)) {
    mkt_lit_flyer_sync();
}
$summary = mkt_lit_summary();
$reviewAll = mkt_lit_review_queue();
$review = $tab === 'review' && ($filters['level'] . $filters['product'] . $filters['q']) !== '' ? mkt_lit_review_queue($filters) : $reviewAll;
$regCounts = mkt_lit_regulatory_counts();
$flyerCounts = mkt_lit_flyer_counts();
$coverage = in_array($tab, ['library', 'claims'], true) ? mkt_lit_claims_coverage() : [];
$coverageCounts = array_count_values(array_column($coverage, 'Coverage'));
$products = db()->query('SELECT ProductID, Name FROM dbo.MktProduct ORDER BY Name')->fetchAll(PDO::FETCH_ASSOC);
$productNames = array_column($products, 'Name', 'Name');
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');
$flyerStudies = array_sum($flyerCounts) - $flyerCounts['other'];

$tabs = [
    'library'    => 'Library (' . $summary['sources'] . ')',
    'review'     => 'To review (' . $reviewAll['total'] . ')',
    'flyers'     => 'Flyer references',
    'claims'     => 'Claims coverage',
    'regulatory' => 'Regulatory watch (' . ($regCounts['new'] + $regCounts['action']) . ')',
    'add'        => 'Add a source',
];

$pageTitle = 'Literature & Intelligence | NutraAxis Operations';
$pageDescription = 'Evidence library behind claims and content, flyer references, claim coverage, and the regulatory watch.';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$select = static function (string $name, array $options, string $current = '', string $id = ''): void {
    echo '<select class="form-input" id="' . htmlspecialchars($id ?: $name) . '" name="' . htmlspecialchars($name) . '">';
    foreach ($options as $key => $label) {
        echo '<option value="' . htmlspecialchars((string) $key) . '"' . ((string) $key === $current ? ' selected' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    echo '</select>';
};
$year = static fn(array $row): string => !empty($row['PublishedAt']) ? substr((string) $row['PublishedAt'], 0, 4) : '';
$studyLine = static function (?string $json): string {
    $study = mkt_lit_study($json);
    $parts = array_filter([
        !empty($study['n']) ? 'n = ' . number_format((int) $study['n']) : null,
        $study['dose'] ?? null,
        $study['duration'] ?? null,
    ], static fn($v): bool => $v !== null && trim((string) $v) !== '' && stripos((string) $v, 'not specified') === false && stripos((string) $v, 'not reported') === false);

    return $parts === [] ? '<span class="form-hint">—</span>' : '<span class="form-hint">' . htmlspecialchars(mb_strimwidth(implode(' · ', $parts), 0, 120, '…')) . '</span>';
};
$hidden = static function (array $fields): string {
    $html = '';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars((string) $name) . '" value="' . htmlspecialchars((string) $value) . '" />';
    }

    return $html;
};
$postButton = static function (string $label, array $fields, string $class = 'btn-text') use ($baseHref, $hidden): string {
    return '<form method="post" action="' . htmlspecialchars($baseHref) . '" style="display:inline">' . $hidden($fields)
        . '<button type="submit" class="' . $class . '">' . htmlspecialchars($label) . '</button></form>';
};
$keepFields = array_filter(['product' => $filters['product'], 'status_filter' => $filters['status']]);
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Literature & Intelligence',
          'lead'       => 'The evidence library behind our claims and content: studies promoted from the research feed or added by PMID or DOI, the reference lists from our product flyers matched to their papers, and a watch list of regulatory news.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      marketing_render_tabs($baseHref, $tabs, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong><?= number_format($summary['sources']) ?> source<?= $summary['sources'] === 1 ? '' : 's' ?> in the library</strong>
          <p>
            <?= (int) ($summary['levels']['meta'] ?? 0) ?> meta-analyses or systematic reviews · <?= (int) ($summary['levels']['rct'] ?? 0) ?> randomised trials ·
            <?= (int) ($summary['levels']['clinical'] ?? 0) ?> other human studies · <?= (int) (($summary['levels']['review'] ?? 0) + ($summary['levels']['preclinical'] ?? 0) + ($summary['levels']['registered'] ?? 0) + ($summary['levels']['other'] ?? 0)) ?> reviews, lab studies or other
          </p>
          <p class="form-hint">
            Flyer references: <?= $flyerCounts['matched'] ?> of <?= $flyerStudies ?> studies matched<?= $flyerCounts['candidates'] > 0 ? ', ' . $flyerCounts['candidates'] . ' waiting for you to confirm' : '' ?>
            <?php if ($coverage !== []): ?>· Claims: <?= (int) ($coverageCounts['matched'] ?? 0) ?> fully backed, <?= (int) ($coverageCounts['partial'] ?? 0) ?> partly, <?= (int) (($coverageCounts['none'] ?? 0) + ($coverageCounts['no_study'] ?? 0)) ?> with no study cited<?php endif; ?>
            · Regulatory: <?= $regCounts['new'] ?> new, <?= $regCounts['action'] ?> need action
          </p>
        </div>
        <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-start">
          <?php if ($canUpdate): ?><a class="btn-text" href="<?= htmlspecialchars($baseHref) ?>?tab=add">Add a source</a><?php endif; ?>
          <a class="btn-text" href="<?= htmlspecialchars($baseHref) ?>?tab=library&amp;export=csv">Export citations</a>
        </div>
      </div>

<?php if ($tab === 'library'): ?>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <div class="audit-filter-grid">
          <div><label for="f_level">Evidence level</label><?php $select('level', ['' => 'All'] + MKT_LIT_LEVELS, $filters['level'], 'f_level'); ?></div>
          <div><label for="f_product">Product</label><?php $select('product', ['' => 'All'] + $productNames, $filters['product'], 'f_product'); ?></div>
          <div><label for="f_area">Therapeutic area</label><?php $select('area', ['' => 'All'] + array_combine($areas, $areas), $filters['area'], 'f_area'); ?></div>
          <div><label for="f_status">Showing</label><?php $select('status', ['' => 'In the library', 'retired' => 'Retired'], $filters['status'], 'f_status'); ?></div>
          <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Title, journal, PMID or product" /></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Source</th><th>Evidence level</th><th>Study</th><th>Products</th><th>Area</th><th>Claims</th><th>Topics</th><th>Added</th></tr></thead>
          <tbody>
            <?php if ($library === []): ?>
            <tr><td colspan="8"><?= $summary['sources'] === 0 ? 'The library is empty. Add studies from the To review tab, confirm flyer matches, or add a paper by PMID or DOI.' : 'No sources match these filters.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($library as $row): ?>
            <?php $meta = mkt_lit_meta($row); $tags = mkt_item_tags($row['TagsJson']); ?>
            <tr>
              <td>
                <a href="/marketing/literature/view.php?id=<?= (int) $row['SourceID'] ?>"><strong><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 130, '…')) ?></strong></a>
                <div class="form-hint"><?= htmlspecialchars(trim(($meta['journal'] ?? $row['Domain']) . ' · ' . $year($row), ' ·')) ?><?= !empty($meta['pmid']) ? ' · PMID ' . htmlspecialchars((string) $meta['pmid']) : (!empty($meta['nct_id']) ? ' · ' . htmlspecialchars((string) $meta['nct_id']) : '') ?></div>
                <?php if (!empty($row['Takeaway'])): ?><div class="form-hint"><strong>Takeaway:</strong> <?= htmlspecialchars(mb_strimwidth((string) $row['Takeaway'], 0, 200, '…')) ?></div><?php endif; ?>
                <?php if (!empty($row['FlyerRefs'])): ?><div class="form-hint">Flyer reference: <?= htmlspecialchars((string) $row['FlyerRefs']) ?></div><?php endif; ?>
              </td>
              <td><?= mkt_lit_level_badge($row['Level']) ?></td>
              <td><?= $studyLine($row['EffectiveStudyJson']) ?></td>
              <td><?= htmlspecialchars(implode(', ', array_slice($tags['products'], 0, 3))) ?: '—' ?></td>
              <td><?= htmlspecialchars((string) ($row['TherapeuticArea'] ?? '—')) ?></td>
              <td><?= (int) $row['ClaimCount'] ?></td>
              <td><?= (int) $row['TopicCount'] ?></td>
              <td><?= htmlspecialchars(mkt_lit_day($row['AddedAt'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php elseif ($tab === 'review'): ?>
      <?php if (($_GET['show'] ?? '') === 'dismissed'): ?>
      <?php $dismissed = mkt_lit_dismissed(); ?>
      <p class="form-hint">Items marked not needed. They stay in the research feed and topics; bring one back to add it later. <a href="?tab=review">Back to the review list</a></p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Item</th><th>Marked</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($dismissed === []): ?><tr><td colspan="3">Nothing marked as not needed.</td></tr><?php endif; ?>
            <?php foreach ($dismissed as $row): ?>
            <tr>
              <td><a href="<?= htmlspecialchars((string) $row['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 130, '…')) ?></a><div class="form-hint"><?= htmlspecialchars((string) $row['Domain']) ?></div></td>
              <td><?= htmlspecialchars(mkt_lit_day($row['DismissedAt'])) ?><?= !empty($row['DismissedBy']) ? ' — ' . htmlspecialchars((string) $row['DismissedBy']) : '' ?></td>
              <td><?= $canUpdate ? $postButton('Bring back', ['action' => 'undismiss', 'item_id' => (int) $row['ItemID'], 'tab' => 'review']) : '' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="review" />
        <div class="audit-filter-grid">
          <div><label for="f_level">Evidence level</label><?php $select('level', ['' => 'All'] + MKT_LIT_LEVELS, $filters['level'], 'f_level'); ?></div>
          <div><label for="f_product">Product</label><?php $select('product', ['' => 'All'] + $productNames, $filters['product'], 'f_product'); ?></div>
          <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Title or AI summary" /></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=review">Clear</a>
        </div>
      </form>
      <p class="form-hint">Peer-reviewed items the research feed scored as relevant, strongest evidence first (showing <?= count($review['rows']) ?> of <?= $review['total'] ?>). Add the ones worth citing; the rest stay in the research feed. News stories about a study are listed last — add the original paper instead. <a href="?tab=review&amp;show=dismissed">See items marked not needed</a>.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Item</th><th>Evidence level</th><th>Study</th><th>Products</th><th>Relevance</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($review['rows'] === []): ?><tr><td colspan="6">Nothing waiting. New peer-reviewed items appear here once the research feed scores them.</td></tr><?php endif; ?>
            <?php foreach ($review['rows'] as $row): ?>
            <?php $tags = mkt_item_tags($row['TagsJson']); $meta = mkt_lit_meta($row); ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars((string) $row['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 130, '…')) ?></a>
                <?php if ($row['IsNews']): ?> <span class="status-badge status-submitted">News about a study</span><?php endif; ?>
                <div class="form-hint"><?= htmlspecialchars(trim(($meta['journal'] ?? $row['Domain']) . ' · ' . $year($row), ' ·')) ?><?= !empty($row['AiSummary']) ? ' — ' . htmlspecialchars((string) $row['AiSummary']) : '' ?></div>
              </td>
              <td><?= $row['Level'] !== null ? mkt_lit_level_badge($row['Level']) : '<span class="form-hint">No study facts</span>' ?></td>
              <td><?= $studyLine($row['StudyJson']) ?></td>
              <td><?= htmlspecialchars(implode(', ', array_slice($tags['products'], 0, 3))) ?: '—' ?></td>
              <td><?= htmlspecialchars(number_format((float) $row['RelevanceMax'], 2)) ?></td>
              <td style="white-space:nowrap">
                <?php if ($canUpdate): ?>
                  <?= $row['IsNews'] ? '<a href="?tab=add">Add original paper</a>' : $postButton('Add to library', ['action' => 'add_feed', 'item_id' => (int) $row['ItemID'], 'tab' => 'review'] + $keepFields) ?>
                  · <?= $postButton('Not needed', ['action' => 'dismiss', 'item_id' => (int) $row['ItemID'], 'tab' => 'review'] + $keepFields) ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

<?php elseif ($tab === 'flyers'): ?>
      <?php $flyerList = mkt_lit_flyer_list(array_key_exists($filters['status'], MKT_LIT_MATCH_STATUSES) ? $filters['status'] : ''); $noRefs = mkt_lit_products_without_refs(); ?>
      <?php if ($noRefs !== []): ?>
      <div class="status-banner">
        <div><strong>No reference list on file: <?= htmlspecialchars(implode(', ', array_column($noRefs, 'Name'))) ?></strong><p>These products' claims cite no sources. Add the flyer's references on the product in the Claims Matrix, or link library studies to the claims directly.</p></div>
        <div><?php foreach ($noRefs as $p): ?><a class="btn-text" href="/marketing/claims-matrix/product.php?id=<?= (int) $p['ProductID'] ?>">Edit <?= htmlspecialchars((string) $p['Name']) ?></a> <?php endforeach; ?></div>
      </div>
      <?php endif; ?>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="flyers" />
        <div class="audit-filter-grid">
          <div><label for="f_product">Product</label><?php $select('product', ['' => 'All'] + $productNames, $filters['product'], 'f_product'); ?></div>
          <div><label for="f_status">Match</label><?php $select('status', ['' => 'All'] + MKT_LIT_MATCH_STATUSES, $filters['status'], 'f_status'); ?></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=flyers">Clear</a>
        </div>
      </form>
      <?php marketing_render_field_guide_link('lit-flyer'); ?>
      <p class="form-hint">Each product flyer's numbered reference list, as its claims cite it. <strong>Find matches</strong> looks up each unchecked study in PubMed by first author, journal and year and lists up to five candidates; you confirm the right paper, which adds it to the library and backs every claim citing that number. Nothing is matched without you.</p>
      <?php if ($canUpdate && $flyerCounts['unmatched'] > 0): ?>
      <p><?= $postButton('Find matches for ' . $flyerCounts['unmatched'] . ' unchecked reference' . ($flyerCounts['unmatched'] === 1 ? '' : 's'), ['action' => 'flyer_match', 'tab' => 'flyers'] + $keepFields, 'btn-secondary') ?> <span class="form-hint">Up to 60 per run, about a second each.</span></p>
      <?php endif; ?>
      <?php if ($flyerList === []): ?><p class="form-hint">No flyer references match these filters.</p><?php endif; ?>
      <?php foreach ($flyerList as $product): ?>
      <?php if ($filters['product'] !== '' && $product['Name'] !== $filters['product']) { continue; } ?>
      <?php $matched = count(array_filter($product['refs'], static fn(array $r): bool => $r['MatchStatus'] === 'matched')); $studies = count(array_filter($product['refs'], static fn(array $r): bool => (bool) $r['IsStudy'])); ?>
      <h2 class="hub-section-title"><?= htmlspecialchars($product['Name']) ?> <span class="form-hint"><?= count($product['refs']) ?> references · <?= $matched ?> of <?= $studies ?> studies matched</span></h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>#</th><th>Reference (from the flyer)</th><th>Match</th><th>Cited by claims</th></tr></thead>
          <tbody>
            <?php foreach ($product['refs'] as $ref): ?>
            <?php $refFields = ['flyer_ref_id' => (int) $ref['FlyerRefID'], 'tab' => 'flyers'] + $keepFields; ?>
            <tr>
              <td><?= (int) $ref['RefNumber'] ?></td>
              <td class="mkt-break"><?= htmlspecialchars((string) $ref['RefText']) ?></td>
              <td>
                <?= mkt_render_badge(match ($ref['MatchStatus']) { 'matched' => 'active', 'candidates' => 'running', 'no_match' => 'failed', default => 'draft' }, ['active' => MKT_LIT_MATCH_STATUSES['matched'], 'running' => MKT_LIT_MATCH_STATUSES['candidates'], 'failed' => MKT_LIT_MATCH_STATUSES['no_match'], 'draft' => MKT_LIT_MATCH_STATUSES[$ref['MatchStatus']] ?? 'Not checked yet']) ?>
                <?php if ($ref['MatchStatus'] === 'matched'): ?>
                  <div class="form-hint"><a href="/marketing/literature/view.php?id=<?= (int) $ref['SourceID'] ?>"><?= htmlspecialchars(mb_strimwidth((string) ($ref['SourceTitle'] ?? 'Library source'), 0, 100, '…')) ?></a><?= $canUpdate ? ' · ' . $postButton('Undo match', ['action' => 'flyer_set', 'status' => 'unmatched'] + $refFields) : '' ?></div>
                <?php elseif ($ref['MatchStatus'] === 'candidates'): ?>
                  <?php foreach ($ref['Candidates'] as $cand): ?>
                  <div class="form-hint" style="margin-top:.4rem">
                    <a href="https://pubmed.ncbi.nlm.nih.gov/<?= htmlspecialchars((string) ($cand['pmid'] ?? '')) ?>/" target="_blank" rel="noopener"><?= htmlspecialchars(mb_strimwidth((string) ($cand['title'] ?? ''), 0, 110, '…')) ?></a>
                    — <?= htmlspecialchars(trim((string) ($cand['authors'] ?? '') . '. ' . ($cand['journal'] ?? '') . ' ' . ($cand['year'] ?? '') . (!empty($cand['volume']) ? ';' . $cand['volume'] : '') . (!empty($cand['pages']) ? ':' . $cand['pages'] : ''))) ?>
                    <?= $canUpdate ? ' · ' . $postButton('This one', ['action' => 'flyer_confirm', 'identifier' => (string) ($cand['pmid'] ?? '')] + $refFields) : '' ?>
                  </div>
                  <?php endforeach; ?>
                  <?php if ($canUpdate): ?><div class="form-hint" style="margin-top:.4rem"><?= $postButton('None of these', ['action' => 'flyer_set', 'status' => 'no_match'] + $refFields) ?></div><?php endif; ?>
                <?php elseif ($ref['MatchStatus'] === 'other'): ?>
                  <?php if ($canUpdate && (bool) $ref['IsStudy']): ?><div class="form-hint"><?= $postButton('It is an article', ['action' => 'flyer_set', 'status' => 'unmatched'] + $refFields) ?></div><?php endif; ?>
                <?php elseif ($canUpdate): ?>
                  <div class="form-hint"><?= $postButton($ref['MatchStatus'] === 'no_match' ? 'Check again' : 'Find a match', ['action' => 'flyer_match'] + $refFields) ?> · <?= $postButton('Not a journal article', ['action' => 'flyer_set', 'status' => 'other'] + $refFields) ?></div>
                <?php endif; ?>
                <?php if ($canUpdate && in_array($ref['MatchStatus'], ['candidates', 'no_match', 'unmatched'], true)): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:flex;gap:.4rem;margin-top:.4rem;align-items:center">
                    <?= $hidden(['action' => 'flyer_manual'] + $refFields) ?>
                    <input class="form-input" name="identifier" placeholder="PMID or DOI" style="max-width:11rem;padding:.3rem .5rem" aria-label="PMID or DOI for reference <?= (int) $ref['RefNumber'] ?>" />
                    <button type="submit" class="btn-text">Match</button>
                  </form>
                <?php endif; ?>
              </td>
              <td><?= $ref['Claims'] === [] ? '<span class="form-hint">—</span>' : implode(', ', array_map(static fn(int $id): string => '<a href="/marketing/claims-matrix/?edit=' . $id . '">#' . $id . '</a>', $ref['Claims'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>

<?php elseif ($tab === 'claims'): ?>
      <?php $shown = array_values(array_filter($coverage, static fn(array $c): bool => ($filters['product'] === '' || $c['ProductName'] === $filters['product']) && ($filters['status'] === '' || $c['Coverage'] === $filters['status']))); ?>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="claims" />
        <div class="audit-filter-grid">
          <div><label for="f_product">Product</label><?php $select('product', ['' => 'All'] + $productNames, $filters['product'], 'f_product'); ?></div>
          <div><label for="f_status">Coverage</label><?php $select('status', ['' => 'All'] + MKT_LIT_COVERAGE, $filters['status'], 'f_status'); ?></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=claims">Clear</a>
        </div>
      </form>
      <p class="form-hint">Every approved claim and the sources behind it, least-backed first. A claim is backed once each study its flyer references cite is matched to a paper in the library; you can also link library sources to a claim from the source's page. For information only — nothing here blocks a claim.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Claim</th><th>Product</th><th>Tier</th><th>Flyer refs</th><th>Studies matched</th><th>Linked</th><th>Best evidence</th><th>Coverage</th></tr></thead>
          <tbody>
            <?php if ($shown === []): ?><tr><td colspan="8">No approved claims match these filters.</td></tr><?php endif; ?>
            <?php foreach ($shown as $c): ?>
            <tr>
              <td><a href="/marketing/claims-matrix/?edit=<?= (int) $c['ClaimID'] ?>"><?= htmlspecialchars(mb_strimwidth((string) $c['ClaimText'], 0, 120, '…')) ?></a></td>
              <td><?= htmlspecialchars((string) ($c['ProductName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(ucfirst((string) $c['EvidenceTier'])) ?></td>
              <td><?= htmlspecialchars((string) ($c['ReferenceNumbers'] ?? '—')) ?></td>
              <td><?= $c['Studies'] > 0 ? $c['Matched'] . ' of ' . $c['Studies'] : '—' ?></td>
              <td><?= $c['Linked'] ?: '—' ?></td>
              <td><?= mkt_lit_level_badge($c['BestLevel']) ?></td>
              <td>
                <?php if ($c['Coverage'] === 'label'): ?><span class="form-hint"><?= MKT_LIT_COVERAGE['label'] ?></span>
                <?php else: ?><?= mkt_render_badge(['matched' => 'active', 'partial' => 'running', 'no_study' => 'draft', 'none' => 'failed'][$c['Coverage']], ['active' => MKT_LIT_COVERAGE['matched'], 'running' => MKT_LIT_COVERAGE['partial'], 'draft' => MKT_LIT_COVERAGE['no_study'], 'failed' => MKT_LIT_COVERAGE['none']]) ?><?php endif; ?>
                <?php if ($c['TierGap'] && $c['BestLevel'] !== null): ?><div class="form-hint">No matched human study yet for this clinical claim.</div><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php elseif ($tab === 'regulatory'): ?>
      <?php $regRows = mkt_lit_regulatory(array_key_exists($filters['status'], MKT_LIT_REG_STATUSES) ? $filters['status'] : ''); ?>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="regulatory" />
        <div class="audit-filter-grid">
          <div><label for="f_status">Status</label><?php $regOptions = ['' => 'All']; foreach (MKT_LIT_REG_STATUSES as $k => $l) { $regOptions[$k] = $l . ' (' . $regCounts[$k] . ')'; } $select('status', $regOptions, $filters['status'], 'f_status'); ?></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=regulatory">Clear</a>
        </div>
      </form>
      <?php marketing_render_field_guide_link('lit-regulatory'); ?>
      <p class="form-hint">Regulatory news the research feed picked up (FDA, FTC, state and international regulators). Mark each one reviewed, or as needing action with a note saying what should happen — that opens a Compliance task, which closes when you mark the item done.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Item</th><th>Published</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($regRows === []): ?><tr><td colspan="4">No regulatory items here.</td></tr><?php endif; ?>
            <?php foreach ($regRows as $row): ?>
            <?php $meta = mkt_lit_meta($row); $st = (string) $row['WatchStatus']; $regFields = ['item_id' => (int) $row['ItemID'], 'tab' => 'regulatory'] + $keepFields; ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars((string) $row['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 140, '…')) ?></a>
                <div class="form-hint"><?= htmlspecialchars((string) ($meta['publisher'] ?? $row['Domain'])) ?><?= !empty($row['AiSummary']) ? ' — ' . htmlspecialchars((string) $row['AiSummary']) : '' ?></div>
                <?php if (!empty($row['Note'])): ?><div class="form-hint"><strong><?= $st === 'reviewed' ? 'Note' : 'Action' ?>:</strong> <?= htmlspecialchars((string) $row['Note']) ?></div><?php endif; ?>
                <?php if ($st !== 'new' && !empty($row['WatchUpdatedBy'])): ?><div class="form-hint"><?= htmlspecialchars((string) $row['WatchUpdatedBy']) ?>, <?= htmlspecialchars(marketing_format_datetime($row['WatchUpdatedAt'])) ?></div><?php endif; ?>
              </td>
              <td><?= htmlspecialchars(marketing_format_date($row['PublishedAt'] ?? null)) ?></td>
              <td><?= mkt_render_badge(match ($st) { 'action' => 'failed', 'reviewed', 'done' => 'active', default => 'new' }, ['failed' => MKT_LIT_REG_STATUSES['action'], 'active' => MKT_LIT_REG_STATUSES[$st] ?? '', 'new' => MKT_LIT_REG_STATUSES['new']]) ?></td>
              <td>
                <?php if ($canUpdate): ?>
                  <?php if ($st === 'new'): ?>
                    <?= $postButton('Reviewed — no action', ['action' => 'reg_set', 'status' => 'reviewed'] + $regFields) ?>
                    <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:flex;gap:.4rem;margin-top:.4rem;align-items:center">
                      <?= $hidden(['action' => 'reg_set', 'status' => 'action'] + $regFields) ?>
                      <input class="form-input" name="note" maxlength="1000" required placeholder="What needs to happen?" style="min-width:14rem;padding:.3rem .5rem" aria-label="Action needed" />
                      <button type="submit" class="btn-text">Needs action</button>
                    </form>
                  <?php elseif ($st === 'action'): ?>
                    <?= $postButton('Action done', ['action' => 'reg_set', 'status' => 'done'] + $regFields) ?> · <?= $postButton('Reopen', ['action' => 'reg_set', 'status' => 'new'] + $regFields) ?>
                  <?php else: ?>
                    <?= $postButton('Reopen', ['action' => 'reg_set', 'status' => 'new'] + $regFields) ?>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php else: ?>
      <?php if ($canUpdate): ?>
      <form class="admin-form detail-card" method="post" action="<?= htmlspecialchars($baseHref) ?>" style="max-width:760px;margin-top:1rem">
        <?php marketing_render_field_guide_link('lit-add'); ?>
        <input type="hidden" name="action" value="add_lookup" />
        <input type="hidden" name="tab" value="add" />
        <p class="form-hint">Paste a PubMed ID, DOI, ClinicalTrials.gov number or link. The portal fetches the title, authors, journal and abstract from PubMed or ClinicalTrials.gov, and the AI pulls out the study facts (design, population, dose, duration, result) the same way it does for the research feed. Only the public paper is sent to the AI.</p>
        <div class="form-group"><label for="a_id">PMID, DOI, NCT or link</label><input class="form-input" id="a_id" name="identifier" required value="<?= htmlspecialchars((string) ($_POST['identifier'] ?? '')) ?>" placeholder="e.g. 42742071, 10.1097/GME.0000000000002894, NCT05166499" /></div>
        <div class="form-group"><label for="a_prod">Flyer reference (optional)</label><?php $select('flyer_product_id', ['' => 'Not from a flyer'] + array_column($products, 'Name', 'ProductID'), (string) ($_POST['flyer_product_id'] ?? ''), 'a_prod'); ?></div>
        <div class="form-group"><label for="a_num">Reference number</label><input class="form-input" id="a_num" name="flyer_number" inputmode="numeric" value="<?= htmlspecialchars((string) ($_POST['flyer_number'] ?? '')) ?>" placeholder="e.g. 7" style="max-width:10rem" /></div>
        <div class="form-group"><label for="a_note">Takeaway</label><textarea class="form-input" id="a_note" name="takeaway" rows="2" maxlength="1000" placeholder="Optional: what it shows, for practitioners, in a sentence"><?= htmlspecialchars((string) ($_POST['takeaway'] ?? '')) ?></textarea></div>
        <button type="submit" class="btn-primary">Look up and add</button>
      </form>
      <?php else: ?>
      <p class="form-hint">You need Update access to add sources.</p>
      <?php endif; ?>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
