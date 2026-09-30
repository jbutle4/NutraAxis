<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-backlinks.php';
require dirname(__DIR__, 2) . '/includes/marketing-content.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-backlinks');

$activeSlug = 'marketing-backlinks';
$listHref = '/marketing/backlinks/?tab=outreach';
$selfBase = '/marketing/backlinks/prospect.php';
$isNew = !empty($_GET['new']) || (($_POST['action'] ?? '') === 'create');
$id = (int) ($_GET['id'] ?? $_POST['prospect_id'] ?? 0);
$prospect = $isNew ? null : mkt_prospect_get($id);
if (!$isNew && $prospect === null) {
    marketing_redirect('/marketing/backlinks/', ['tab' => 'outreach', 'notice' => 'Prospect not found.']);
}
$error = null;
$form = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $back = static fn(array $result, string $ok): never => marketing_redirect($selfBase, ['id' => $id] + (!empty($result['ok'])
        ? ['notice' => (string) ($result['message'] ?? $ok)]
        : ['error' => (string) ($result['error'] ?? 'That did not work.')]));
    if ($action === 'create') {
        marketing_require_create();
        $result = mkt_prospect_create($_POST);
        if ($result['ok']) {
            marketing_redirect($selfBase, ['id' => $result['id'], 'notice' => 'Prospect added.']);
        }
        $error = $result['error'];
    } else {
        marketing_require_update();
        switch ($action) {
            case 'details':
                $result = mkt_prospect_update($id, $_POST);
                if ($result['ok']) {
                    $back($result, 'Details saved.');
                }
                $error = $result['error'];
                break;
            case 'draft':
                if (in_array($prospect['Status'], ['won', 'declined'], true)) {
                    $back(['ok' => false, 'error' => 'Reopen the prospect before drafting a pitch.'], '');
                }
                $angle = mb_substr(trim((string) ($_POST['angle'] ?? '')), 0, 1000);
                db()->prepare('UPDATE dbo.MktProspect SET Angle = :a, UpdatedAt = SYSUTCDATETIME() WHERE ProspectID = :id')->execute(['a' => $angle ?: null, 'id' => $id]);
                $back(process_execute('outreach-draft-pitch', ['prospect_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Pitch drafted.');
            case 'pitch':
                $result = mkt_prospect_save_pitch($id, (string) ($_POST['subject'] ?? ''), (string) ($_POST['body'] ?? ''));
                if ($result['ok']) {
                    $back($result, 'Pitch saved.');
                }
                $error = $result['error'];
                break;
            case 'log':
                $type = (string) ($_POST['event'] ?? '');
                $back(mkt_prospect_log($id, $type, $_POST), match ($type) {
                    'sent'     => 'Logged as sent. Next follow-up ' . marketing_format_date(mkt_local_date('+' . mkt_prospect_follow_up_days() . ' days')) . '.',
                    'won'      => 'Marked as link won.',
                    'declined' => 'Marked as declined.',
                    'reopened' => 'Reopened.',
                    'note'     => 'Note added.',
                    default    => 'Saved.',
                });
        }
    }
}

$owners = mkt_content_owner_options();
$events = $prospect !== null ? mkt_prospect_events($id) : [];
$check = $prospect !== null ? json_decode((string) ($prospect['PitchCheckJson'] ?? ''), true) : null;
$closed = $prospect !== null && in_array($prospect['Status'], ['won', 'declined'], true);
$canUpdate = marketing_can_update();
$val = static function (string $postKey, string $col, string $default = '') use ($form, $prospect): string {
    return htmlspecialchars((string) ($form[$postKey] ?? ($prospect[$col] ?? $default)));
};
$pages = db()->query("SELECT TOP (400) Url, Path, Title FROM dbo.MktPage WHERE Status = N'active' ORDER BY Path")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = ($prospect !== null ? $prospect['Domain'] : 'New prospect') . ' | Backlinks & Outreach | NutraAxis Operations';
$pageDescription = 'Outreach prospect.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $listHref,
          'back_label' => 'Back to Outreach',
          'category'   => 'Backlinks & Outreach',
          'title'      => $prospect !== null ? 'Outreach prospect' : 'New prospect',
          'lead'       => 'A site that might link to us. Contact details stay in the portal for Marketing users and are never sent to the AI.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error ?? ($_GET['error'] ?? null));
      ?>

<?php if ($prospect !== null): ?>
      <h2 class="hub-section-title"><?= htmlspecialchars((string) $prospect['Domain']) ?> <?= mkt_render_badge((string) $prospect['Status'], MKT_PROSPECT_STATUSES) ?></h2>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Opportunity</dt><dd><?= htmlspecialchars((string) $prospect['Opportunity']) ?></dd>
          <dt>Found from</dt><dd><?= htmlspecialchars(MKT_PROSPECT_ORIGINS[$prospect['Origin']] ?? '') ?><?= $prospect['CompetitorCount'] !== null ? ' — links to ' . (int) $prospect['CompetitorCount'] . ' competitor' . ((int) $prospect['CompetitorCount'] === 1 ? '' : 's') . ($prospect['Competitors'] ? ' (' . htmlspecialchars((string) $prospect['Competitors']) . ')' : '') : '' ?></dd>
          <dt>Authority</dt><dd><?= $prospect['Authority'] !== null ? (int) $prospect['Authority'] . ' / 100' : '—' ?><?= $prospect['SpamScore'] !== null ? ' · spam score ' . (int) $prospect['SpamScore'] : '' ?></dd>
          <dt>Next follow-up</dt><dd><?= $prospect['NextFollowUpIso'] ? htmlspecialchars(marketing_format_date($prospect['NextFollowUpIso'])) : '—' ?></dd>
          <?php if ($prospect['Status'] === 'won'): ?>
          <dt>Link</dt><dd><?= $prospect['WonUrl'] ? '<a href="' . htmlspecialchars((string) $prospect['WonUrl']) . '" target="_blank" rel="noopener">' . htmlspecialchars(mb_strimwidth((string) $prospect['WonUrl'], 0, 80, '…')) . '</a>' : '—' ?> (<?= htmlspecialchars(marketing_format_date($prospect['WonAtIso'])) ?>)</dd>
          <?php endif; ?>
        </dl>
      </div>
<?php endif; ?>

      <?php if ($isNew || $canUpdate): ?>
      <h2 class="hub-section-title"><?= $isNew ? 'Details' : 'Details and contact' ?></h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfBase) ?><?= $prospect !== null ? '?id=' . $id : '' ?>">
        <input type="hidden" name="action" value="<?= $isNew ? 'create' : 'details' ?>" />
        <?php if ($prospect !== null): ?><input type="hidden" name="prospect_id" value="<?= $id ?>" /><?php endif; ?>
        <?php marketing_render_field_guide_link('prospect'); ?>
        <div class="form-grid">
          <?php if ($isNew): ?>
          <div class="form-group"><label for="p_domain">Site domain</label><input class="form-input" id="p_domain" name="domain" required maxlength="253" value="<?= htmlspecialchars((string) ($form['domain'] ?? '')) ?>" placeholder="example.com" /></div>
          <input type="hidden" name="origin" value="manual" />
          <?php endif; ?>
          <div class="form-group form-grid-full"><label for="p_opp">Opportunity</label><input class="form-input" id="p_opp" name="opportunity" required maxlength="300" value="<?= $val('opportunity', 'Opportunity') ?>" placeholder="e.g. Resource page listing practitioner supplement brands" /></div>
          <div class="form-group">
            <label for="p_target">Our page to link to</label>
            <select class="form-input" id="p_target" name="target_url">
              <option value="">Choose later</option>
              <?php $currentTarget = (string) ($form['target_url'] ?? ($prospect['TargetUrl'] ?? '')); ?>
              <?php foreach ($pages as $pg): ?>
              <option value="<?= htmlspecialchars((string) $pg['Url']) ?>" <?= $currentTarget === (string) $pg['Url'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $pg['Path']) ?><?= $pg['Title'] ? ' — ' . htmlspecialchars(mb_strimwidth((string) $pg['Title'], 0, 60, '…')) : '' ?></option>
              <?php endforeach; ?>
              <?php if ($currentTarget !== '' && !in_array($currentTarget, array_column($pages, 'Url'), true)): ?>
              <option value="<?= htmlspecialchars($currentTarget) ?>" selected><?= htmlspecialchars($currentTarget) ?></option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="p_owner">Owner</label>
            <select class="form-input" id="p_owner" name="owner_user_id">
              <?php $currentOwner = (string) ($form['owner_user_id'] ?? ($prospect['OwnerUserID'] ?? marketing_user_id())); ?>
              <?php foreach ($owners as $row): ?>
              <option value="<?= (int) $row['UserID'] ?>" <?= $currentOwner === (string) $row['UserID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['UserName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label for="p_cname">Contact name</label><input class="form-input" id="p_cname" name="contact_name" maxlength="150" value="<?= $val('contact_name', 'ContactName') ?>" /></div>
          <div class="form-group"><label for="p_crole">Contact role</label><input class="form-input" id="p_crole" name="contact_role" maxlength="150" value="<?= $val('contact_role', 'ContactRole') ?>" placeholder="e.g. Editor" /></div>
          <div class="form-group"><label for="p_cemail">Contact email</label><input class="form-input" type="email" id="p_cemail" name="contact_email" maxlength="320" value="<?= $val('contact_email', 'ContactEmail') ?>" /></div>
          <div class="form-group"><label for="p_next">Next follow-up</label><input class="form-input" type="date" id="p_next" name="next_follow_up" value="<?= $val('next_follow_up', 'NextFollowUpIso', $isNew ? mkt_local_date() : '') ?>" /></div>
          <div class="form-group form-grid-full"><label for="p_angle_d">Angle</label><input class="form-input" id="p_angle_d" name="angle" maxlength="1000" value="<?= $val('angle', 'Angle') ?>" placeholder="Why our page is useful to their readers — the AI builds the pitch around this" /></div>
        </div>
        <p class="form-hint">Contact name, role and email are visible to Marketing users only and are never sent to the AI.</p>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $isNew ? 'Add prospect' : 'Save details' ?></button>
          <?php if ($isNew): ?><a class="btn-secondary" href="<?= htmlspecialchars($listHref) ?>">Cancel</a><?php endif; ?>
        </div>
      </form>
      <?php endif; ?>

<?php if ($prospect !== null): ?>
      <h2 class="hub-section-title">Pitch</h2>
      <?php if ($canUpdate && !$closed): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfBase) ?>?id=<?= $id ?>">
        <input type="hidden" name="action" value="draft" /><input type="hidden" name="prospect_id" value="<?= $id ?>" />
        <?php marketing_render_field_guide_link('prospect-pitch'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full"><label for="p_angle">Angle for the AI</label><input class="form-input" id="p_angle" name="angle" maxlength="1000" value="<?= htmlspecialchars((string) ($prospect['Angle'] ?? '')) ?>" placeholder="Optional — e.g. New trial summary written for clinicians; their readers are practitioners" /></div>
        </div>
        <p class="form-hint">The AI sees the site’s domain, the opportunity, this angle and our page title — never the contact’s name or email. It writes [Name], [Your name] and [LINK] placeholders, makes no product claims, and the draft is claims-checked. Drafting replaces the current subject and email.</p>
        <div class="form-actions"><button type="submit" class="btn-secondary"><?= $prospect['PitchBody'] ? 'Redraft with AI' : 'Draft pitch with AI' ?></button></div>
      </form>
      <?php endif; ?>

      <?php if ($prospect['PitchBody'] || ($canUpdate && !$closed)): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfBase) ?>?id=<?= $id ?>">
        <input type="hidden" name="action" value="pitch" /><input type="hidden" name="prospect_id" value="<?= $id ?>" />
        <?php if ($prospect['PitchDraftedAt']): ?>
        <p class="form-hint">
          AI draft <?= htmlspecialchars(marketing_format_datetime($prospect['PitchDraftedAt'])) ?> ·
          <?php if ($prospect['PitchClaimsScore'] !== null): ?>
          claims score <strong><?= number_format((float) $prospect['PitchClaimsScore'], 1) ?></strong> / 10<?= is_array($check) && !empty($check['summary']) ? ' — ' . htmlspecialchars((string) $check['summary']) : '' ?>
          <?php else: ?>
          edited since the AI draft, so the claims score no longer applies — keep product claims out.
          <?php endif; ?>
        </p>
        <?php if (is_array($check) && $prospect['PitchClaimsScore'] !== null && (!empty($check['issues']) || !empty($check['flag_terms']))): ?>
        <ul class="form-hint">
          <?php foreach ((array) ($check['issues'] ?? []) as $issue): ?><li><?= htmlspecialchars((string) $issue) ?></li><?php endforeach; ?>
          <?php if (!empty($check['flag_terms'])): ?><li>Flagged terms: <?= htmlspecialchars(implode(', ', array_map('strval', (array) $check['flag_terms']))) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <?php endif; ?>
        <div class="form-grid">
          <div class="form-group form-grid-full"><label for="p_subject">Subject</label><input class="form-input" id="p_subject" name="subject" maxlength="300" value="<?= htmlspecialchars((string) ($form['subject'] ?? ($prospect['PitchSubject'] ?? ''))) ?>" <?= $canUpdate && !$closed ? '' : 'readonly' ?> /></div>
          <div class="form-group form-grid-full"><label for="p_body">Email</label><textarea class="form-input mkt-pitch-body" id="p_body" name="body" rows="10" <?= $canUpdate && !$closed ? '' : 'readonly' ?>><?= htmlspecialchars((string) ($form['body'] ?? ($prospect['PitchBody'] ?? ''))) ?></textarea></div>
        </div>
        <p class="form-hint">Copy it into your own mailbox, fill in [Name], [Your name] and [LINK], send it, then press <strong>Log as sent</strong> below.</p>
        <div class="form-actions">
          <?php if ($canUpdate && !$closed): ?><button type="submit" class="btn-secondary">Save pitch</button><?php endif; ?>
          <button type="button" class="btn-secondary" onclick="navigator.clipboard.writeText('Subject: ' + document.getElementById('p_subject').value + '\n\n' + document.getElementById('p_body').value).then(() => { this.textContent = 'Copied'; });">Copy</button>
        </div>
      </form>
      <?php endif; ?>

      <?php if ($canUpdate): ?>
      <h2 class="hub-section-title">Log outreach</h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfBase) ?>?id=<?= $id ?>">
        <input type="hidden" name="action" value="log" /><input type="hidden" name="prospect_id" value="<?= $id ?>" />
        <?php marketing_render_field_guide_link('prospect-log'); ?>
        <div class="form-grid">
          <div class="form-group">
            <label for="l_event">What happened</label>
            <select class="form-input" id="l_event" name="event">
              <?php if ($closed): ?>
              <option value="reopened">Reopen</option>
              <?php else: ?>
              <option value="sent">Sent the pitch or a follow-up</option>
              <option value="replied">They replied</option>
              <option value="won">Link won</option>
              <option value="declined">Declined / not interested</option>
              <option value="followup">Follow up on a different date</option>
              <?php endif; ?>
              <option value="note">Note only</option>
            </select>
          </div>
          <div class="form-group"><label for="l_date">Follow-up date</label><input class="form-input" type="date" id="l_date" name="next_follow_up" /></div>
          <div class="form-group form-grid-full"><label for="l_url">Linking page</label><input class="form-input" type="url" id="l_url" name="won_url" maxlength="1000" placeholder="For Link won — the page on their site that links to us (optional)" /></div>
          <div class="form-group form-grid-full"><label for="l_note">Note</label><input class="form-input" id="l_note" name="note" maxlength="2000" placeholder="e.g. what they said" /></div>
        </div>
        <p class="form-hint">Sent sets the next follow-up <?= mkt_prospect_follow_up_days() ?> days out (Settings: outreach.follow_up_days); replied defaults to 2 days unless you pick a date. When the follow-up falls due it becomes a task for the owner.</p>
        <div class="form-actions"><button type="submit" class="btn-primary">Log</button></div>
      </form>
      <?php endif; ?>

      <h2 class="hub-section-title">History</h2>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>When</th><th>What</th><th>Note</th><th>By</th></tr></thead>
        <tbody>
          <?php if ($events === []): ?>
          <tr><td colspan="4">Nothing yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($events as $e): ?>
          <tr>
            <td><?= htmlspecialchars(marketing_format_datetime($e['CreatedAt'])) ?></td>
            <td><?= htmlspecialchars(MKT_PROSPECT_EVENTS[$e['EventType']] ?? (string) $e['EventType']) ?></td>
            <td class="mkt-break"><?= htmlspecialchars((string) ($e['Note'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($e['CreatedByName'] ?? '')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
