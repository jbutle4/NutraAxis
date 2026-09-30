<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-performance');

$id = (int) ($_GET['id'] ?? 0);
$selfHref = '/marketing/performance/response.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $note = (string) ($_POST['note'] ?? '');
    $result = match ($action) {
        'label'   => mkt_response_set_triage($id, (string) ($_POST['label'] ?? ''), $note),
        'clear'   => mkt_response_clear($id, (string) ($_POST['decision'] ?? ''), $note),
        'replied' => mkt_response_record_reply($id, $note),
        'close'   => mkt_response_close($id, $note),
        'reopen'  => mkt_response_reopen($id),
        'retriage' => (static function () use ($id): array {
            $run = process_execute('engagement-triage', ['response_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            mkt_response_notify_escalations();
            return !empty($run['ok']) ? ['ok' => true] : ['ok' => false, 'error' => (string) ($run['error'] ?? 'Triage failed.')];
        })(),
        default   => ['ok' => false, 'error' => 'Unknown action.'],
    };
    $messages = [
        'label' => 'Label saved.', 'clear' => 'Compliance decision recorded.', 'replied' => 'Reply recorded.',
        'close' => 'Closed.', 'reopen' => 'Reopened.', 'retriage' => 'AI triage re-run.',
    ];
    marketing_redirect('/marketing/performance/response.php', $result['ok']
        ? ['id' => $id, 'notice' => ($messages[$action] ?? 'Saved.') . (!empty($result['escalated']) ? ' Sent to compliance.' : '')]
        : ['id' => $id, 'error' => $result['error']]);
}

$row = mkt_response_get($id);
if ($row === null) {
    marketing_redirect('/marketing/performance/inbox.php', ['error' => 'Response not found.']);
}

$canUpdate = marketing_can_update();
$canCompliance = mkt_can_compliance_review();
$info = mkt_response_triage_info($row['Triage']);
$channels = mkt_response_channels();
$status = (string) $row['Status'];
$overdue = $status === 'escalated' && $row['DueAtText'] && $row['DueAtText'] < gmdate('Y-m-d H:i:s');

$pageTitle = 'Response #' . $id . ' | NutraAxis Operations';
$pageDescription = 'Response triage, compliance review and reply.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
$by = static fn(?string $name, ?string $at): string => $at ? htmlspecialchars(mkt_cal_format($at, 'M j, g:i A')) . ($name ? ' by ' . htmlspecialchars($name) : '') : '';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/performance/inbox.php',
          'back_label' => 'Back to Response Inbox',
          'category'   => 'Marketing & Research',
          'title'      => 'Response #' . $id,
          'lead'       => (MKT_RESPONSE_KINDS[$row['Kind']] ?? $row['Kind']) . ' on ' . ($channels[$row['Channel']] ?? $row['Channel']) . ', received ' . mkt_cal_format($row['ReceivedAtText'], 'D M j, g:i A T') . '.',
          'permission' => auth_module_permission_label('marketing-performance'),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

      <?php if ($status === 'escalated'): ?>
      <div class="status-banner status-banner-approval">
        <div>
          <strong><?= htmlspecialchars($info['label']) ?> — with compliance</strong>
          <p>
            <?= $overdue ? '<strong style="color:var(--danger)">Overdue.</strong> Was due ' : 'Due ' ?><?= htmlspecialchars(mkt_cal_format($row['DueAtText'], 'D M j, g:i A T')) ?>.
            <?= htmlspecialchars($info['advice']) ?>
          </p>
          <?php if (!$row['EscalationNotifiedAt']): ?><p class="form-hint">Compliance reviewers have not been emailed yet (no reviewer email, or mail is not configured) — the task is on the Tasks page.</p><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="detail-card">
        <p style="white-space:pre-wrap;font-size:1.02rem;margin:0 0 0.75rem"><?= htmlspecialchars((string) $row['BodyText']) ?></p>
        <dl class="detail-list detail-list-inline">
          <dt>Label</dt>
          <dd>
            <?= mkt_response_triage_badge($row['Triage']) ?>
            <?php if ($row['Triage']): ?>
            <span class="form-hint"><?= match ((string) $row['TriageSource']) { 'ai' => 'AI' . ($row['TriageConfidence'] !== null ? ', ' . round((float) $row['TriageConfidence'] * 100) . '% sure' : ''), 'rule' => 'keyword rule', default => 'set by hand' } ?><?= $row['TriagedByName'] ? ' — ' . htmlspecialchars((string) $row['TriagedByName']) : '' ?></span>
            <?php endif; ?>
          </dd>
          <?php if ($row['TriageReason']): ?><dt>Why</dt><dd><?= htmlspecialchars((string) $row['TriageReason']) ?></dd><?php endif; ?>
          <?php if ($row['Triage'] && $status !== 'escalated'): ?><dt>What to do</dt><dd><?= htmlspecialchars($info['advice']) ?></dd><?php endif; ?>
          <?php if ($row['SuggestedAction']): ?><dt>AI suggestion</dt><dd><?= htmlspecialchars((string) $row['SuggestedAction']) ?></dd><?php endif; ?>
          <?php if ($row['TriageError'] && $status === 'new'): ?><dt>Triage error</dt><dd style="color:var(--danger)"><?= htmlspecialchars((string) $row['TriageError']) ?> (attempt <?= (int) $row['TriageAttempts'] ?> of 3)</dd><?php endif; ?>
          <dt>Status</dt><dd><?= htmlspecialchars(MKT_RESPONSE_STATUSES[$status] ?? $status) ?></dd>
          <dt>In response to</dt>
          <dd>
            <?php if ($row['AssetID']): ?>
            <a href="/marketing/campaigns/asset.php?id=<?= (int) $row['AssetID'] ?>">Asset #<?= (int) $row['AssetID'] ?> <?= htmlspecialchars(mb_strimwidth(trim((string) ($row['AssetTitle'] ?: $row['AssetSubject'])), 0, 80, '…')) ?></a>
            — <a href="/marketing/campaigns/campaign.php?id=<?= (int) $row['CampaignID'] ?>"><?= htmlspecialchars((string) $row['CampaignName']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </dd>
          <dt>Original</dt><dd><?= $row['ExternalUrl'] ? '<a href="' . htmlspecialchars((string) $row['ExternalUrl']) . '" target="_blank" rel="noopener noreferrer">Open on the platform</a>' : '—' ?></dd>
          <dt>Added</dt><dd><?= $by($row['CreatedByName'], $row['CreatedAt']) ?><?= (int) $row['RedactionCount'] ? ' <span class="form-hint">(' . (int) $row['RedactionCount'] . ' handle/email/phone removed)</span>' : '' ?></dd>
          <?php if ($row['ComplianceAt']): ?><dt>Compliance</dt><dd><?= htmlspecialchars((string) $row['ComplianceNote']) ?> <span class="form-hint">— <?= $by($row['ComplianceByName'], $row['ComplianceAt']) ?></span></dd><?php endif; ?>
          <?php if ($row['RepliedAt']): ?><dt>Replied</dt><dd><?= $by($row['RepliedByName'], $row['RepliedAt']) ?><?= $row['ReplyNote'] ? ' — ' . htmlspecialchars((string) $row['ReplyNote']) : '' ?></dd><?php endif; ?>
          <?php if ($row['ClosedAt']): ?><dt>Closed</dt><dd><?= $by($row['ClosedByName'], $row['ClosedAt']) ?><?= !$row['RepliedAt'] && $row['ReplyNote'] ? ' — ' . htmlspecialchars((string) $row['ReplyNote']) : '' ?></dd><?php endif; ?>
        </dl>
      </div>

      <?php if ($status === 'escalated' && $canCompliance && $canUpdate): ?>
      <h2 class="hub-section-title">Compliance decision</h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="clear" />
        <?php marketing_render_field_guide_link('response-compliance'); ?>
        <div class="form-grid">
          <div class="form-group">
            <label for="decision">Decision</label>
            <select class="form-input" id="decision" name="decision" required>
              <option value="reply">Cleared to reply (guidance below)</option>
              <option value="close">No public reply — close it</option>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="note">Decision and guidance</label>
            <textarea class="form-input" id="note" name="note" rows="3" maxlength="2000" required placeholder="What may be said (approved wording or 'please ask your practitioner'), or why there is no reply. For a possible adverse event, record whether it was reported."></textarea>
          </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn-primary">Record decision</button></div>
      </form>
      <?php elseif ($status === 'escalated'): ?>
      <p class="form-hint">Only a user whose role has Marketing Compliance Review and Marketing Update access can clear this. Do not reply until they do.</p>
      <?php endif; ?>

      <?php if ($canUpdate && $status === 'open'): ?>
      <h2 class="hub-section-title">Reply</h2>
      <?php if ($row['ComplianceNote']): ?><p><strong>Compliance guidance:</strong> <?= htmlspecialchars((string) $row['ComplianceNote']) ?></p><?php endif; ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="replied" />
        <?php marketing_render_field_guide_link('response-reply'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="reply_note">What we replied</label>
            <textarea class="form-input" id="reply_note" name="note" rows="2" maxlength="2000" placeholder="Optional — a short summary or the reply text (no names)"></textarea>
          </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn-primary">I replied on the platform</button></div>
      </form>
      <?php endif; ?>

      <?php if ($canUpdate): ?>
      <h2 class="hub-section-title">Other actions</h2>
      <?php if (in_array($status, ['new', 'open', 'escalated'], true)): ?>
      <?php marketing_render_field_guide_link('response-actions'); ?>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="margin-bottom:0.75rem">
        <input type="hidden" name="action" value="label" />
        <label for="label">Label</label>
        <select class="form-input" id="label" name="label">
          <?php foreach (MKT_RESPONSE_TRIAGE as $key => $triage): ?>
          <?php if ($status === 'escalated' && !in_array($key, MKT_RESPONSE_ESCALATE, true)) { continue; } ?>
          <option value="<?= htmlspecialchars($key) ?>"<?= $row['Triage'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($triage[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <input class="form-input" name="note" maxlength="1000" placeholder="Why (optional)" aria-label="Why" />
        <button type="submit" class="btn-secondary">Set label</button>
      </form>
      <?php endif; ?>
      <?php if (in_array($status, ['new', 'open'], true)): ?>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
        <input type="hidden" name="action" value="retriage" />
        <button type="submit" class="btn-text">Re-run AI triage</button>
      </form>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="display:inline-flex">
        <input type="hidden" name="action" value="close" />
        <input class="form-input" name="note" maxlength="500" placeholder="Reason (e.g. spam hidden)" aria-label="Reason" />
        <button type="submit" class="btn-text">Close without reply</button>
      </form>
      <?php elseif (in_array($status, ['replied', 'closed'], true)): ?>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="reopen" />
        <button type="submit" class="btn-secondary">Reopen</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
