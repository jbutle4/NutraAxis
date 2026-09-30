<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-literature.php';

auth_require_module_read('research-literature');

$activeSlug = 'research-literature';
$sourceId = (int) ($_GET['id'] ?? 0);
$selfHref = '/marketing/literature/view.php?id=' . $sourceId;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $claimId = (int) ($_POST['claim_id'] ?? 0);
    $result = match ($action) {
        'save'    => mkt_lit_source_update($sourceId, $_POST),
        'retire'  => ['ok' => mkt_lit_source_set_status($sourceId, 'retired'), 'message' => 'Retired from the library. Claims no longer count it as evidence.'],
        'restore' => ['ok' => mkt_lit_source_set_status($sourceId, 'active'), 'message' => 'Back in the library.'],
        'link'    => mkt_lit_claim_link($claimId, $sourceId) + ['message' => 'Linked as evidence for the claim.'],
        'unlink'  => (static function () use ($claimId, $sourceId): array { mkt_lit_claim_unlink($claimId, $sourceId); return ['ok' => true, 'message' => 'Link removed.']; })(),
        default   => ['ok' => false, 'error' => 'Unknown action.'],
    };
    if (!empty($result['ok'])) {
        marketing_redirect($selfHref, ['notice' => (string) ($result['message'] ?? 'Saved.')]);
    }
    $error = (string) ($result['error'] ?? 'Something went wrong.');
}

$source = mkt_lit_source_get($sourceId);
if ($source === null) {
    http_response_code(404);
    $pageTitle = 'Source not found | NutraAxis Operations';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><p>Library source not found. <a href="/marketing/literature/">Back to Literature &amp; Intelligence</a></p></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}
$study = mkt_lit_study($source['EffectiveStudyJson']);
$meta = mkt_lit_meta($source);
$tags = mkt_item_tags($source['TagsJson']);
$computedLevel = mkt_lit_row_level(['StudyJson' => $source['ItemStudyJson'], 'SourceType' => $source['SourceType']]);
$claims = mkt_lit_source_claims($source);
$topics = db()->prepare('SELECT t.TopicID, t.Title, t.Status, ti.IsEvidence FROM dbo.MktTopicItem ti JOIN dbo.MktTopic t ON t.TopicID = ti.TopicID WHERE ti.ItemID = :id ORDER BY t.TopicID DESC');
$topics->execute(['id' => (int) $source['ItemID']]);
$topics = $topics->fetchAll(PDO::FETCH_ASSOC);
$flyerRefs = db()->prepare("SELECT f.RefNumber, p.Name FROM dbo.MktLitFlyerRef f JOIN dbo.MktProduct p ON p.ProductID = f.ProductID WHERE f.SourceID = :id AND f.MatchStatus = N'matched' ORDER BY p.Name, f.RefNumber");
$flyerRefs->execute(['id' => $sourceId]);
$flyerRefs = $flyerRefs->fetchAll(PDO::FETCH_ASSOC);
$canUpdate = marketing_can_update();
$posted = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save';
$val = static function (string $key, ?string $current) use ($posted): string {
    return htmlspecialchars($posted ? (string) ($_POST[$key] ?? '') : (string) $current);
};
$addedFrom = ['feed' => 'From the research feed', 'lookup' => 'Looked up by ID', 'flyer' => 'Matched to a flyer reference'];
$citation = mkt_lit_citation($source);

$pageTitle = 'Library source | NutraAxis Operations';
$pageDescription = 'A study in the evidence library.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$postButton = static function (string $label, array $fields, string $class = 'btn-text') use ($selfHref): string {
    $html = '<form method="post" action="' . htmlspecialchars($selfHref) . '" style="display:inline">';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars((string) $name) . '" value="' . htmlspecialchars((string) $value) . '" />';
    }

    return $html . '<button type="submit" class="' . $class . '">' . htmlspecialchars($label) . '</button></form>';
};
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/literature/',
          'back_label' => 'Back to Literature & Intelligence',
          'category'   => 'Marketing & Research',
          'title'      => 'Library source',
          'lead'       => 'A study in the evidence library: what it found, which claims it backs, and where we have used it.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      ?>

      <h2 class="hub-section-title">Source <?= $source['Status'] === 'retired' ? mkt_render_badge('draft', ['draft' => 'Retired']) : '' ?></h2>
      <div class="detail-card">
        <p style="margin:0 0 .75rem;font-size:1.05rem"><strong><?= htmlspecialchars((string) $source['Title']) ?></strong></p>
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Evidence level</dt><dd><?= mkt_lit_level_badge($source['Level']) ?><?= !empty($source['LevelOverride']) ? ' <span class="form-hint">set by hand</span>' : '' ?></dd>
          <dt>Published</dt><dd><?= htmlspecialchars(trim(($meta['journal'] ?? $source['Domain']) . ', ' . substr((string) $source['PublishedAt'], 0, 4), ', ')) ?></dd>
          <dt>Links</dt><dd>
            <?php $links = []; ?>
            <?php if (!empty($meta['pmid'])) { $links[] = '<a href="https://pubmed.ncbi.nlm.nih.gov/' . htmlspecialchars((string) $meta['pmid']) . '/" target="_blank" rel="noopener">PubMed ' . htmlspecialchars((string) $meta['pmid']) . '</a>'; } ?>
            <?php if (!empty($meta['doi'])) { $links[] = '<a href="https://doi.org/' . htmlspecialchars((string) $meta['doi']) . '" target="_blank" rel="noopener">DOI</a>'; } ?>
            <?php if (!empty($meta['nct_id'])) { $links[] = '<a href="https://clinicaltrials.gov/study/' . htmlspecialchars((string) $meta['nct_id']) . '" target="_blank" rel="noopener">' . htmlspecialchars((string) $meta['nct_id']) . '</a>'; } ?>
            <?php if ($links === []) { $links[] = '<a href="' . htmlspecialchars((string) $source['Url']) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars((string) $source['Domain']) . '</a>'; } ?>
            <?= implode(' · ', $links) ?>
          </dd>
          <dt>Added</dt><dd><?= htmlspecialchars($addedFrom[$source['AddedFrom']] ?? '') ?><?= !empty($source['CreatedByName']) ? ' by ' . htmlspecialchars((string) $source['CreatedByName']) : '' ?>, <?= htmlspecialchars(mkt_lit_day($source['CreatedAt'])) ?></dd>
          <dt>Products</dt><dd><?= htmlspecialchars(implode(', ', $tags['products'])) ?: '—' ?></dd>
          <dt>Area</dt><dd><?= htmlspecialchars((string) ($source['TherapeuticArea'] ?? '—')) ?></dd>
        </dl>
        <p class="form-hint" style="margin-top:.75rem"><strong>Citation:</strong> <span class="mkt-break" id="lit_citation"><?= htmlspecialchars($citation) ?></span>
          <button type="button" class="btn-text" onclick="navigator.clipboard.writeText(document.getElementById('lit_citation').textContent).then(() => { this.textContent = 'Copied'; });">Copy</button></p>
      </div>

      <h2 class="hub-section-title">Study facts and notes</h2>
      <form class="admin-form detail-card" method="post" action="<?= htmlspecialchars($selfHref) ?>" style="max-width:920px">
        <?php marketing_render_field_guide_link('lit-source'); ?>
        <input type="hidden" name="action" value="save" />
        <div class="form-group"><label for="s_take">Takeaway</label><textarea class="form-input" id="s_take" name="takeaway" rows="2" maxlength="1000" placeholder="What it shows, for practitioners, in a sentence or two"<?= $canUpdate ? '' : ' readonly' ?>><?= $val('takeaway', $source['Takeaway']) ?></textarea></div>
        <div class="form-group"><label for="s_lim">Limitations</label><textarea class="form-input" id="s_lim" name="limitations" rows="2" maxlength="1000" placeholder="Sample size, design, funding, who it does not apply to"<?= $canUpdate ? '' : ' readonly' ?>><?= $val('limitations', $source['Limitations']) ?></textarea></div>
        <div class="form-group"><label for="s_level">Evidence level</label>
          <select class="form-input" id="s_level" name="level" style="max-width:30rem"<?= $canUpdate ? '' : ' disabled' ?>>
            <option value="">Worked out from the design (<?= htmlspecialchars(MKT_LIT_LEVELS[$computedLevel]) ?>)</option>
            <?php foreach (MKT_LIT_LEVELS as $k => $label): ?><option value="<?= $k ?>"<?= ($posted ? ($_POST['level'] ?? '') : ($source['LevelOverride'] ?? '')) === $k ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php foreach (['design' => 'Design', 'population' => 'Population', 'n' => 'Participants', 'intervention' => 'Intervention', 'dose' => 'Dose', 'duration' => 'Duration', 'outcomes' => 'Outcomes', 'result' => 'Result'] as $key => $label): ?>
        <div class="form-group"><label for="s_<?= $key ?>"><?= $label ?></label>
          <?php if (in_array($key, ['outcomes', 'result', 'population', 'intervention'], true)): ?>
          <textarea class="form-input" id="s_<?= $key ?>" name="<?= $key ?>" rows="<?= $key === 'result' ? 5 : 2 ?>" maxlength="2000"<?= $canUpdate ? '' : ' readonly' ?>><?= $val($key, isset($study[$key]) ? (string) $study[$key] : '') ?></textarea>
          <?php else: ?>
          <input class="form-input" id="s_<?= $key ?>" name="<?= $key ?>" value="<?= $val($key, isset($study[$key]) ? (string) $study[$key] : '') ?>"<?= $key === 'n' ? ' inputmode="numeric" style="max-width:10rem"' : ' maxlength="2000"' ?><?= $canUpdate ? '' : ' readonly' ?> />
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <p class="form-hint">Study facts were pulled from the abstract by AI when the item was scored<?= $source['StudyJson'] !== null ? '; you have corrected them since' : '' ?>. Correct anything wrong — your edits are kept and the evidence level follows the design unless you set it by hand.</p>
        <?php if ($canUpdate): ?>
        <button type="submit" class="btn-primary">Save</button>
        <?php endif; ?>
      </form>
      <?php if ($canUpdate): ?>
      <p><?= $source['Status'] === 'active' ? $postButton('Retire from library', ['action' => 'retire']) . ' <span class="form-hint">Retired sources stop counting as evidence for claims; the research item stays.</span>' : $postButton('Bring back into the library', ['action' => 'restore'], 'btn-secondary') ?></p>
      <?php endif; ?>

      <h2 class="hub-section-title">Claims it backs</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Claim</th><th>Product</th><th>Tier</th><th>Link</th></tr></thead>
          <tbody>
            <?php if ($claims === []): ?><tr><td colspan="4">No linked claims, and no approved claims for the products this study is tagged with.</td></tr><?php endif; ?>
            <?php foreach ($claims as $c): ?>
            <tr>
              <td><a href="/marketing/claims-matrix/?edit=<?= (int) $c['ClaimID'] ?>"><?= htmlspecialchars(mb_strimwidth((string) $c['ClaimText'], 0, 130, '…')) ?></a></td>
              <td><?= htmlspecialchars((string) ($c['ProductName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(ucfirst((string) $c['EvidenceTier'])) ?></td>
              <td>
                <?php if ($c['Link'] === 'flyer'): ?><?= mkt_render_badge('active', ['active' => 'Cited on the flyer']) ?>
                <?php elseif ($c['Link'] === 'direct'): ?><?= mkt_render_badge('active', ['active' => 'Linked']) ?><?= $canUpdate ? ' · ' . $postButton('Unlink', ['action' => 'unlink', 'claim_id' => (int) $c['ClaimID']]) : '' ?>
                <?php elseif (!$c['Fits']): ?><span class="form-hint">A <?= htmlspecialchars(strtolower(MKT_LIT_LEVELS[$source['Level']])) ?> cannot back a <?= htmlspecialchars((string) $c['EvidenceTier']) ?> claim</span>
                <?php elseif ($canUpdate && $source['Status'] === 'active'): ?><?= $postButton('Link as evidence', ['action' => 'link', 'claim_id' => (int) $c['ClaimID']]) ?>
                <?php else: ?><span class="form-hint">Suggested</span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">Suggestions come from the products the study is tagged with (label claims are left out). Clinical claims need a human study; mechanistic claims also accept lab studies and reviews.</p>

      <h2 class="hub-section-title">Used in</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Where</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($topics === [] && $flyerRefs === []): ?><tr><td colspan="2">Not in a topic or matched to a flyer reference yet.</td></tr><?php endif; ?>
            <?php foreach ($topics as $t): ?>
            <tr><td>Topic: <a href="/marketing/topics/view.php?id=<?= (int) $t['TopicID'] ?>"><?= htmlspecialchars((string) $t['Title']) ?></a><?= !empty($t['IsEvidence']) ? ' <span class="form-hint">(marked as evidence)</span>' : '' ?></td><td><?= mkt_render_badge((string) $t['Status'] === 'accepted' ? 'active' : (string) $t['Status'], ['active' => 'Accepted'] + MKT_TOPIC_STATUSES) ?></td></tr>
            <?php endforeach; ?>
            <?php foreach ($flyerRefs as $f): ?>
            <tr><td>Flyer reference: <a href="/marketing/literature/?tab=flyers&amp;product=<?= rawurlencode((string) $f['Name']) ?>"><?= htmlspecialchars((string) $f['Name']) ?> #<?= (int) $f['RefNumber'] ?></a></td><td><?= mkt_render_badge('active', ['active' => 'Matched']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
