<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-topics.php';

auth_require_module_read('research-topics');

$activeSlug = 'research-topics';
$listHref = '/marketing/topics/';
$id = (int) ($_GET['id'] ?? 0);
$topic = $id > 0 ? mkt_topic_get($id) : null;
if ($topic === null) {
    marketing_redirect($listHref, ['error' => 'Topic not found.']);
}
$selfHref = '/marketing/topics/view.php?id=' . $id;
$canEdit = marketing_can_update() && $topic['Status'] !== 'merged';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $result = ['ok' => true];
    $notice = null;

    if ($action === 'save' || $action === 'save_accept') {
        $result = mkt_topic_save($id, $_POST);
        if ($result['ok'] && $action === 'save_accept') {
            $result = mkt_topic_decide($id, 'accepted', (string) ($_POST['decision_note'] ?? ''));
        }
        $notice = $action === 'save_accept' ? 'Topic accepted.' : 'Topic saved.';
    } elseif (in_array($action, ['park', 'reject', 'reopen'], true)) {
        $decision = ['park' => 'parked', 'reject' => 'rejected', 'reopen' => 'proposed'][$action];
        $result = mkt_topic_decide($id, $decision, (string) ($_POST['decision_note'] ?? ''));
        $notice = 'Topic ' . strtolower(MKT_TOPIC_STATUSES[$decision]) . '.';
    } elseif ($action === 'evidence') {
        mkt_topic_set_evidence($id, (int) ($_POST['item_id'] ?? 0), !empty($_POST['is_evidence']));
        $notice = 'Evidence updated.';
    } elseif ($action === 'remove_item') {
        mkt_topic_remove_item($id, (int) ($_POST['item_id'] ?? 0));
        $notice = 'Item removed from topic; it returns to the pool for the next clustering run.';
    } elseif ($action === 'link_claim') {
        $result = mkt_topic_link_claim($id, (int) ($_POST['claim_id'] ?? 0));
        $notice = 'Claim linked.';
    } elseif ($action === 'unlink_claim') {
        mkt_topic_unlink_claim($id, (int) ($_POST['claim_id'] ?? 0));
        $notice = 'Claim unlinked.';
    } elseif ($action === 'merge') {
        $targetId = (int) ($_POST['target_id'] ?? 0);
        $result = mkt_topic_merge($id, $targetId);
        if ($result['ok']) {
            marketing_redirect('/marketing/topics/view.php', ['id' => $targetId, 'notice' => 'Topic #' . $id . ' merged into this topic.']);
        }
    }

    if ($result['ok']) {
        marketing_redirect('/marketing/topics/view.php', ['id' => $id, 'notice' => $notice ?? 'Saved.']);
    }
    $error = $result['error'];
    $topic = mkt_topic_get($id);
}

$items = mkt_topic_items($id);
$claims = mkt_topic_claims($id);
$interests = mkt_interests_list();
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');

$form = [
    'title'            => (string) ($_POST['title'] ?? $topic['Title']),
    'summary'          => (string) ($_POST['summary'] ?? $topic['Summary'] ?? ''),
    'why_it_matters'   => (string) ($_POST['why_it_matters'] ?? $topic['WhyItMatters'] ?? ''),
    'interest_id'      => (string) ($_POST['interest_id'] ?? $topic['InterestID'] ?? ''),
    'therapeutic_area' => (string) ($_POST['therapeutic_area'] ?? $topic['TherapeuticArea'] ?? ''),
    'angle'            => (string) ($_POST['angle'] ?? $topic['Angle'] ?? ''),
    'audience'         => (string) ($_POST['audience'] ?? $topic['Audience'] ?? ''),
    'avoid_notes'      => (string) ($_POST['avoid_notes'] ?? $topic['AvoidNotes'] ?? ''),
];

// Offer approved claims for products the topic's items were tagged with first, then the rest.
$taggedProducts = [];
foreach ($items as $item) {
    foreach (mkt_item_tags($item['TagsJson'] ?? null)['products'] as $product) {
        $taggedProducts[$product] = true;
    }
}
$linkedIds = array_flip(array_map('intval', array_column($claims, 'ClaimID')));
$claimOptions = ['suggested' => [], 'other' => []];
if ($canEdit) {
    foreach (mkt_claims_approved() as $claim) {
        if (isset($linkedIds[(int) $claim['ClaimID']])) {
            continue;
        }
        $bucket = isset($taggedProducts[(string) ($claim['ProductName'] ?? '')]) ? 'suggested' : 'other';
        $claimOptions[$bucket][(int) $claim['ClaimID']] = ($claim['ProductName'] ?? 'General') . ' — ' . mb_strimwidth((string) $claim['ClaimText'], 0, 140, '…');
    }
}
$mergeTargets = $canEdit ? array_filter(
    mkt_topics_list(['statuses' => ['proposed', 'accepted', 'parked'], 'order' => 'recent'], 150),
    static fn(array $row): bool => (int) $row['TopicID'] !== $id
) : [];

$pageTitle = $topic['Title'] . ' | Topic Synthesis | NutraAxis Operations';
$pageDescription = 'Topic review: brief, supporting items, and linked claims.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$e = static fn(string $key): string => htmlspecialchars($form[$key]);
$status = (string) $topic['Status'];
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $listHref . ($status === 'accepted' ? '?tab=accepted' : ''),
          'back_label' => 'Back to Topic Synthesis',
          'category'   => 'Marketing & Research',
          'title'      => (string) $topic['Title'],
          'lead'       => (string) ($topic['WhyItMatters'] ?? ''),
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      ?>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Status</dt><dd><?= mkt_render_badge($status === 'accepted' ? 'active' : $status, ['active' => 'Accepted'] + MKT_TOPIC_STATUSES) ?><?= !empty($topic['IsEmerging']) ? ' <span class="status-badge status-submitted">Emerging</span>' : '' ?></dd>
          <?php if ($status === 'merged' && !empty($topic['MergedIntoTopicID'])): ?>
          <dt>Merged into</dt><dd><a href="/marketing/topics/view.php?id=<?= (int) $topic['MergedIntoTopicID'] ?>"><?= htmlspecialchars((string) $topic['MergedIntoTitle']) ?></a></dd>
          <?php endif; ?>
          <dt>Trend</dt><dd><?= htmlspecialchars(number_format((float) $topic['TrendScore'], 1)) ?> — <?= (int) $topic['ItemCount'] ?> items (<?= (int) $topic['Items7d'] ?> in the last 7 days, <?= (int) $topic['ItemsPrior7d'] ?> the week before), <?= (int) $topic['SourceDiversity'] ?> sources, <?= (int) $topic['EvidenceCount'] ?> peer-reviewed / regulatory</dd>
          <dt>Items span</dt><dd><?= htmlspecialchars(marketing_format_date($topic['FirstItemAt'] ?? null)) ?> – <?= htmlspecialchars(marketing_format_date($topic['LastItemAt'] ?? null)) ?></dd>
          <dt>Proposed</dt><dd><?= htmlspecialchars(marketing_format_datetime($topic['CreatedAt'] ?? null)) ?><?= !empty($topic['ClusterLogID']) ? ' (clustering run #' . (int) $topic['ClusterLogID'] . ')' : '' ?></dd>
          <?php if (!empty($topic['DecidedAt'])): ?>
          <dt>Decided</dt><dd><?= htmlspecialchars(marketing_format_datetime($topic['DecidedAt'])) ?><?= !empty($topic['DecisionNote']) ? ' — ' . htmlspecialchars((string) $topic['DecisionNote']) : '' ?></dd>
          <?php endif; ?>
        </dl>
      </div>

      <?php if (!empty($topic['IsEmerging'])): ?>
      <div class="status-banner">
        <div>
          <strong>Emerging theme — not covered by a watched interest</strong>
          <p>Suggested interest: <strong><?= htmlspecialchars((string) ($topic['SuggestedInterestName'] ?? '')) ?></strong><?php
              $terms = json_decode((string) ($topic['SuggestedTermsJson'] ?? ''), true);
              echo is_array($terms) && $terms !== [] ? ' — terms: ' . htmlspecialchars(implode(', ', $terms)) : '';
          ?></p>
        </div>
        <?php if (marketing_can_create()): ?><div><a class="btn-secondary" href="/marketing/interests/edit.php?from_topic=<?= $id ?>">Create interest</a></div><?php endif; ?>
      </div>
      <?php endif; ?>

      <h2 class="hub-section-title">Brief</h2>
      <?php if ($canEdit): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <div class="form-grid">
          <div class="form-group form-grid-full"><label for="title">Title</label><input class="form-input" id="title" name="title" required maxlength="300" value="<?= $e('title') ?>" /></div>
          <div class="form-group form-grid-full"><label for="summary">Summary</label><textarea class="form-input" id="summary" name="summary" rows="3" maxlength="2000"><?= $e('summary') ?></textarea></div>
          <div class="form-group form-grid-full"><label for="why_it_matters">Why it matters</label><input class="form-input" id="why_it_matters" name="why_it_matters" maxlength="1000" value="<?= $e('why_it_matters') ?>" /></div>
          <div class="form-group">
            <label for="interest_id">Interest</label>
            <select class="form-input" id="interest_id" name="interest_id">
              <option value="">—</option>
              <?php foreach ($interests as $interest): ?>
              <option value="<?= (int) $interest['InterestID'] ?>" <?= $form['interest_id'] === (string) $interest['InterestID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $interest['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="therapeutic_area">Therapeutic area</label>
            <select class="form-input" id="therapeutic_area" name="therapeutic_area">
              <option value="">—</option>
              <?php foreach ($areas as $area): ?>
              <option value="<?= htmlspecialchars($area) ?>" <?= $form['therapeutic_area'] === $area ? 'selected' : '' ?>><?= htmlspecialchars($area) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full"><label for="angle">Angle</label><textarea class="form-input" id="angle" name="angle" rows="3" maxlength="2000" placeholder="The specific point of view our content takes, e.g. 'What the 2026 berberine trials mean for practitioners already using GLP-1s — tolerability and timing, not weight-loss promises.'"><?= $e('angle') ?></textarea></div>
          <div class="form-group">
            <label for="audience">Audience</label>
            <select class="form-input" id="audience" name="audience">
              <option value="">—</option>
              <?php foreach (MKT_CLAIM_AUDIENCES as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $form['audience'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full"><label for="avoid_notes">Avoid</label><input class="form-input" id="avoid_notes" name="avoid_notes" maxlength="1000" value="<?= $e('avoid_notes') ?>" placeholder="Claims, comparisons or framings the content must not use" /></div>
          <div class="form-group form-grid-full"><label for="decision_note">Decision note</label><input class="form-input" id="decision_note" name="decision_note" maxlength="500" placeholder="Optional — recorded with accept / park / reject" /></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-secondary" name="action" value="save">Save brief</button>
          <?php if ($status !== 'accepted'): ?>
          <button type="submit" class="btn-primary" name="action" value="save_accept">Save and accept</button>
          <?php endif; ?>
          <?php if ($status === 'proposed' || $status === 'accepted'): ?>
          <button type="submit" class="btn-secondary" name="action" value="park" formnovalidate>Park</button>
          <button type="submit" class="btn-secondary" name="action" value="reject" formnovalidate>Reject</button>
          <?php else: ?>
          <button type="submit" class="btn-secondary" name="action" value="reopen" formnovalidate>Reopen</button>
          <?php endif; ?>
        </div>
      </form>
      <?php else: ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Summary</dt><dd><?= nl2br(htmlspecialchars((string) ($topic['Summary'] ?? ''))) ?></dd>
          <dt>Interest</dt><dd><?= htmlspecialchars((string) ($topic['InterestName'] ?? '—')) ?></dd>
          <dt>Therapeutic area</dt><dd><?= htmlspecialchars((string) ($topic['TherapeuticArea'] ?? '—')) ?></dd>
          <dt>Angle</dt><dd><?= nl2br(htmlspecialchars((string) ($topic['Angle'] ?? '—'))) ?></dd>
          <dt>Audience</dt><dd><?= htmlspecialchars(MKT_CLAIM_AUDIENCES[(string) ($topic['Audience'] ?? '')] ?? '—') ?></dd>
          <dt>Avoid</dt><dd><?= htmlspecialchars((string) ($topic['AvoidNotes'] ?? '—')) ?></dd>
        </dl>
      </div>
      <?php endif; ?>

      <h2 class="hub-section-title">Items (<?= count($items) ?>)</h2>
      <p class="form-hint">Mark the items content should cite as evidence. Removing an item returns it to the pool for the next clustering run.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Item</th><th>Evidence type</th><th>Relevance</th><th>Published</th><th>Evidence</th><?php if ($canEdit): ?><th>Actions</th><?php endif; ?></tr></thead>
          <tbody>
            <?php if ($items === []): ?>
            <tr><td colspan="<?= $canEdit ? 6 : 5 ?>">No items.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
            <?php
            $study = json_decode((string) ($item['StudyJson'] ?? ''), true);
            $study = is_array($study) ? array_filter($study, static fn($v): bool => $v !== null && $v !== '') : [];
            $tags = mkt_item_tags($item['TagsJson'] ?? null);
            ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars((string) $item['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $item['Title']) ?></a>
                <div class="form-hint"><?= htmlspecialchars((string) ($item['SourceName'] ?? $item['Domain'] ?? '')) ?><?= !empty($item['AiSummary']) ? ' — ' . htmlspecialchars((string) $item['AiSummary']) : '' ?></div>
                <?php if ($tags['products'] !== []): ?><div class="form-hint">Products: <?= htmlspecialchars(implode(', ', $tags['products'])) ?></div><?php endif; ?>
                <?php if ($study !== []): ?>
                <details><summary class="form-hint">Study facts</summary>
                  <dl class="detail-list detail-list-inline">
                    <?php foreach ($study as $key => $value): ?>
                    <dt><?= htmlspecialchars(ucfirst((string) $key)) ?></dt><dd><?= htmlspecialchars(is_scalar($value) ? (string) $value : json_encode($value)) ?></dd>
                    <?php endforeach; ?>
                  </dl>
                </details>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars(MKT_ITEM_EVIDENCE_TYPES[(string) ($item['EvidenceType'] ?? '')] ?? '—') ?></td>
              <td><?= $item['RelevanceMax'] !== null ? htmlspecialchars(number_format((float) $item['RelevanceMax'], 2)) : '—' ?></td>
              <td><?= htmlspecialchars(marketing_format_date($item['PublishedAt'] ?? $item['FetchedAt'] ?? null)) ?></td>
              <td>
                <?php if ($canEdit): ?>
                <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
                  <input type="hidden" name="action" value="evidence" />
                  <input type="hidden" name="item_id" value="<?= (int) $item['ItemID'] ?>" />
                  <input type="hidden" name="is_evidence" value="<?= empty($item['IsEvidence']) ? '1' : '' ?>" />
                  <button type="submit" class="btn-text"><?= !empty($item['IsEvidence']) ? 'Evidence ✓ (unmark)' : 'Mark as evidence' ?></button>
                </form>
                <?php else: ?>
                <?= !empty($item['IsEvidence']) ? 'Evidence' : '' ?>
                <?php endif; ?>
              </td>
              <?php if ($canEdit): ?>
              <td>
                <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
                  <input type="hidden" name="action" value="remove_item" />
                  <input type="hidden" name="item_id" value="<?= (int) $item['ItemID'] ?>" />
                  <button type="submit" class="btn-text">Remove</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Linked claims (<?= count($claims) ?>)</h2>
      <p class="form-hint">Approved Claims Matrix wording that content on this topic may use. Products tagged on the items are suggested first.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Product</th><th>Claim wording</th><th>Evidence tier</th><th>Status</th><?php if ($canEdit): ?><th>Actions</th><?php endif; ?></tr></thead>
          <tbody>
            <?php if ($claims === []): ?>
            <tr><td colspan="<?= $canEdit ? 5 : 4 ?>">No claims linked.</td></tr>
            <?php endif; ?>
            <?php foreach ($claims as $claim): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($claim['ProductName'] ?? 'General')) ?></td>
              <td><?= nl2br(htmlspecialchars((string) $claim['ClaimText'])) ?></td>
              <td><?= htmlspecialchars(MKT_EVIDENCE_TIERS[(string) $claim['EvidenceTier']] ?? (string) $claim['EvidenceTier']) ?></td>
              <td><?= mkt_render_badge((string) $claim['Status'] === 'approved' ? 'active' : (string) $claim['Status'], ['active' => 'Approved'] + MKT_CLAIM_STATUSES) ?></td>
              <?php if ($canEdit): ?>
              <td>
                <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
                  <input type="hidden" name="action" value="unlink_claim" />
                  <input type="hidden" name="claim_id" value="<?= (int) $claim['ClaimID'] ?>" />
                  <button type="submit" class="btn-text">Unlink</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($canEdit && ($claimOptions['suggested'] !== [] || $claimOptions['other'] !== [])): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="link_claim" />
        <div class="form-group">
          <label for="claim_id">Link claim</label>
          <select class="form-input" id="claim_id" name="claim_id" required>
            <option value="">Choose an approved claim…</option>
            <?php foreach (['suggested' => 'Tagged products', 'other' => 'Other products'] as $bucket => $label): ?>
              <?php if ($claimOptions[$bucket] !== []): ?>
              <optgroup label="<?= htmlspecialchars($label) ?>">
                <?php foreach ($claimOptions[$bucket] as $claimId => $text): ?>
                <option value="<?= (int) $claimId ?>"><?= htmlspecialchars($text) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions"><button type="submit" class="btn-secondary">Link claim</button></div>
      </form>
      <?php endif; ?>

      <?php if ($canEdit && $mergeTargets !== []): ?>
      <h2 class="hub-section-title">Merge</h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>" onsubmit="return confirm('Merge this topic into the selected topic? Its items and claim links move there.');">
        <input type="hidden" name="action" value="merge" />
        <div class="form-group">
          <label for="target_id">Merge this topic into</label>
          <select class="form-input" id="target_id" name="target_id" required>
            <option value="">Choose a topic…</option>
            <?php foreach ($mergeTargets as $row): ?>
            <option value="<?= (int) $row['TopicID'] ?>">#<?= (int) $row['TopicID'] ?> <?= htmlspecialchars((string) $row['Title']) ?> (<?= htmlspecialchars(MKT_TOPIC_STATUSES[(string) $row['Status']] ?? '') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions"><button type="submit" class="btn-secondary">Merge</button></div>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
