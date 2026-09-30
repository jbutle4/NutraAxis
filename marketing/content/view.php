<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';
require dirname(__DIR__, 2) . '/includes/marketing-blog.php';

auth_require_module_read('marketing-content');

$activeSlug = 'marketing-content';
$id = (int) ($_GET['id'] ?? 0);
$content = $id > 0 ? mkt_content_get($id) : null;
if ($content === null) {
    marketing_redirect('/marketing/content/', ['error' => 'Content not found.']);
}
$selfHref = '/marketing/content/view.php?id=' . $id;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $anchor = (string) ($_POST['anchor'] ?? '');
    $back = static function (array $result, string $notice) use ($id, $anchor): never {
        mkt_tasks_sync();
        $query = !empty($result['ok'])
            ? ['id' => $id, 'notice' => (string) ($result['message'] ?? $notice)]
            : ['id' => $id, 'error' => (string) ($result['error'] ?? 'Action failed.')];
        header('Location: /marketing/content/view.php?' . http_build_query($query) . ($anchor !== '' ? '#' . rawurlencode($anchor) : ''));
        exit;
    };
    $job = static fn(string $code, array $params = []): array => process_execute($code, ['content_id' => $id] + $params, PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());

    switch ($action) {
        case 'brief_ai':
            $back($job('content-brief'), 'Brief written.');
        case 'brief_save':
            $result = mkt_content_save_brief($id, (string) ($_POST['brief'] ?? ''));
            $back($result, !empty($result['changed']) ? 'Brief saved.' : 'No changes.');
        case 'brief_approve':
            $back(mkt_content_approve_brief($id), 'Brief approved — the piece is ready to draft.');
        case 'brief_reopen':
            $back(mkt_content_reopen_brief($id), 'Brief reopened for editing.');
        case 'draft_ai':
            $back($job('content-draft'), 'Draft written.');
        case 'save_version':
            $result = mkt_content_save_version($id, $_POST);
            if ($result['ok'] && !empty($result['changed'])) {
                $check = $job('content-claims-check');
                $back(['ok' => true], ($result['reset'] ? 'Saved as a new version — the piece went back to draft and its reviews were cleared. ' : 'Saved as a new version. ')
                    . (!empty($check['ok']) ? 'Claims check re-run.' : 'Claims check failed: ' . (string) ($check['error'] ?? 'unknown error')));
            }
            $back($result, 'No changes.');
        case 'check':
            $back($job('content-claims-check'), 'Claims check complete.');
        case 'revise':
            $back($job('content-revise', ['instruction' => (string) ($_POST['instruction'] ?? '')]), 'AI revision saved and re-checked.');
        case 'submit':
            $result = mkt_content_submit($id);
            $back($result, !empty($result['compliance']) ? 'Submitted — waiting on compliance review.' : 'Submitted — the claims check found nothing that needs compliance (a perfect score, no claims, flag terms or health references), so it goes straight to editorial review.');
        case 'review':
            $result = mkt_content_review($id, (string) ($_POST['gate'] ?? ''), (string) ($_POST['decision'] ?? ''), (string) ($_POST['note'] ?? ''));
            $back($result, match ($result['stage'] ?? '') {
                'approved'  => 'Approved — ready to publish.',
                'editorial' => 'Compliance cleared — waiting on editorial review.',
                default     => 'Changes requested — the piece went back to the writer.',
            });
        case 'blog_publish':
            $result = mkt_blog_publish($id, $_POST);
            $back($result, match ($result['decision'] ?? '') {
                'blog_updated'     => 'Blog post updated with the approved version.',
                'blog_republished' => 'Back on the blog.',
                default            => 'Published to the blog.',
            });
        case 'blog_unpublish':
            $back(mkt_blog_unpublish($id, (string) ($_POST['note'] ?? '')), 'Taken off the blog. The piece is back to Approved.');
        case 'publish':
        case 'update_url':
            if (mkt_blog_is_live(mkt_blog_for_content($id))) {
                $back(['ok' => false, 'error' => 'This piece is live on the blog. Use Update blog post or Unpublish instead.'], '');
            }
            $result = $action === 'publish'
                ? mkt_content_publish($id, (string) ($_POST['url'] ?? ''))
                : mkt_content_update_url($id, (string) ($_POST['url'] ?? ''));
            if ($result['ok']) {
                $verify = $job('seo-verify-published');
                $back(['ok' => true], ($action === 'publish' ? 'Marked published. ' : 'Live URL updated. ')
                    . (!empty($verify['ok']) ? 'Live check: ' . (string) ($verify['message'] ?? 'done') : 'Live check could not run: ' . (string) ($verify['error'] ?? 'unknown error')));
            }
            $back($result, '');
        case 'monitoring':
            $back(mkt_content_set_stage($id, 'monitoring'), 'Moved to monitoring.');
        case 'details':
            $result = mkt_content_update($id, $_POST);
            $back($result, !empty($result['changed']) ? 'Details saved.' : 'No changes.');
        case 'archive':
            if (mkt_blog_is_live(mkt_blog_for_content($id))) {
                $back(['ok' => false, 'error' => 'This piece is still live on the blog. Unpublish it first.'], '');
            }
            $result = mkt_content_set_stage($id, 'archived');
            if ($result['ok']) {
                mkt_tasks_sync();
                marketing_redirect('/marketing/content/', ['notice' => 'Piece archived.']);
            }
            $back($result, '');
    }
    $error = 'Unknown action.';
}

$stage = (string) $content['Stage'];
$canUpdate = marketing_can_update();
$types = mkt_content_types();
$current = $content['CurrentVersionID'] ? mkt_content_version_get((int) $content['CurrentVersionID']) : null;
$versions = mkt_content_versions($id);
$reviews = mkt_content_reviews($id);
$shownNo = (int) ($_GET['version'] ?? 0);
$shown = $shownNo > 0 ? mkt_content_version_by_no($id, $shownNo) : $current;
$isCurrentShown = $shown !== null && $current !== null && (int) $shown['VersionID'] === (int) $current['VersionID'];
$diffNo = (int) ($_GET['diff'] ?? 0);
$diffNew = $diffNo > 1 ? mkt_content_version_by_no($id, $diffNo) : null;
$diffOld = $diffNew !== null ? mkt_content_version_by_no($id, $diffNo - 1) : null;
$check = mkt_content_check($shown);
$usedClaims = mkt_content_claims($shown['ClaimIdsJson'] ?? null);
$gate = mkt_content_pending_gate($content);
$ownWork = $gate !== null && mkt_content_own_work($content);
$canReview = $gate !== null && !$ownWork && ($gate === 'compliance' ? mkt_can_compliance_review() : mkt_can_editorial_review());
$briefOpen = in_array($stage, ['idea', 'brief'], true) && $content['BriefApprovedAt'] === null;
$canEdit = $canUpdate && in_array($stage, MKT_CONTENT_EDITABLE, true);
$names = mkt_user_names([$content['BriefBy'] ?? 0, $content['BriefApprovedBy'] ?? 0, $content['ComplianceBy'] ?? 0, $content['EditorialBy'] ?? 0, $content['PublishedBy'] ?? 0, $content['SubmittedBy'] ?? 0]);
$by = static fn(string $col): string => isset($names[(int) ($content[$col] ?? 0)]) ? ' by ' . htmlspecialchars((string) $names[(int) $content[$col]]) : '';
$siteUrl = rtrim((string) marketing_setting('brand.site_url', ''), '/');
$html = $shown !== null ? mkt_markdown_html((string) $shown['Body']) : '';
$minScore = (float) marketing_setting('claims.min_score', '7');
$blog = mkt_blog_for_content($id);
$blogLive = mkt_blog_is_live($blog);
$canPublishBlog = mkt_blog_can_publish();

$pageTitle = $content['Title'] . ' | Content Pipeline | NutraAxis Operations';
$pageDescription = 'Brief, versions, claims check, compliance and editorial review for a long-form piece.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$classLabels = ['approved' => 'Approved claim', 'evidence' => 'Cited finding', 'unapproved' => 'Unapproved claim', 'disease' => 'Disease claim'];
$gateLabels = ['brief' => 'Brief', 'submit' => 'Submitted', 'compliance' => 'Compliance', 'editorial' => 'Editorial', 'publish' => 'Publish', 'system' => 'System'];
$post = static fn(string $action, string $anchor = ''): string => '<input type="hidden" name="action" value="' . $action . '" />' . ($anchor !== '' ? '<input type="hidden" name="anchor" value="' . $anchor . '" />' : '');
$flow = ['idea', 'brief', 'draft', 'compliance_review', 'editorial', 'approved', 'published', 'monitoring'];
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/content/',
          'back_label' => 'Back to Content Pipeline',
          'category'   => 'Marketing & Research',
          'title'      => (string) $content['Title'],
          'lead'       => mkt_content_type_label((string) $content['ContentType']) . ' · ' . (MKT_CONTENT_STAGES[$stage] ?? $stage) . ($current ? ' · version ' . (int) $current['VersionNo'] : ''),
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      ?>

      <p class="form-hint">
        <?php foreach ($flow as $i => $step): ?>
        <?= $i > 0 ? ' → ' : '' ?><?= $step === $stage ? '<strong>' . htmlspecialchars(MKT_CONTENT_STAGES[$step]) . '</strong>' : htmlspecialchars(MKT_CONTENT_STAGES[$step]) ?>
        <?php endforeach; ?>
      </p>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Stage</dt><dd><?= mkt_content_badge($stage) ?><?= $stage === 'draft' && ($content['ComplianceStatus'] === 'changes_requested' || $content['EditorialStatus'] === 'changes_requested') ? ' <strong>Changes requested</strong> — see the review history.' : '' ?></dd>
          <?php if (!empty($content['TopicID'])): ?><dt>Topic</dt><dd><a href="/marketing/topics/view.php?id=<?= (int) $content['TopicID'] ?>"><?= htmlspecialchars((string) $content['TopicTitle']) ?></a><?= $content['TopicStatus'] !== 'accepted' ? ' <span class="form-hint">(' . htmlspecialchars((string) $content['TopicStatus']) . ')</span>' : '' ?></dd><?php endif; ?>
          <?php if (!empty($content['ProductID'])): ?><dt>Product</dt><dd><?= htmlspecialchars((string) $content['ProductName']) ?></dd><?php endif; ?>
          <dt>Keywords</dt><dd><?= htmlspecialchars((string) ($content['PrimaryKeyword'] ?? '—')) ?><?= $content['SecondaryKeywords'] ? ' <span class="form-hint">+ ' . htmlspecialchars((string) $content['SecondaryKeywords']) . '</span>' : '' ?></dd>
          <dt>Audience</dt><dd><?= htmlspecialchars(MKT_CLAIM_AUDIENCES[(string) $content['Audience']] ?? (string) $content['Audience']) ?> · target <?= number_format((int) ($content['TargetWords'] ?: ($types[(string) $content['ContentType']]['words'] ?? 1200))) ?> words</dd>
          <dt>Owner</dt><dd><?= htmlspecialchars((string) ($content['OwnerName'] ?? '—')) ?><?= $content['DueDate'] ? ' · due ' . htmlspecialchars(marketing_format_date((string) $content['DueDate'])) : '' ?></dd>
          <?php if ($content['BriefApprovedAt']): ?><dt>Brief</dt><dd>Approved <?= htmlspecialchars(marketing_format_datetime($content['BriefApprovedAt'])) ?><?= $by('BriefApprovedBy') ?></dd><?php endif; ?>
          <?php if ($content['ComplianceStatus']): ?><dt>Compliance</dt><dd><?= htmlspecialchars(MKT_GATE_STATUSES[(string) $content['ComplianceStatus']] ?? '') ?><?= $content['ComplianceAt'] ? ' — ' . htmlspecialchars(marketing_format_datetime($content['ComplianceAt'])) . $by('ComplianceBy') : '' ?></dd><?php endif; ?>
          <?php if ($content['EditorialStatus']): ?><dt>Editorial</dt><dd><?= htmlspecialchars(MKT_GATE_STATUSES[(string) $content['EditorialStatus']] ?? '') ?><?= $content['EditorialAt'] ? ' — ' . htmlspecialchars(marketing_format_datetime($content['EditorialAt'])) . $by('EditorialBy') : '' ?></dd><?php endif; ?>
          <?php if ($blog !== null): ?><dt>Blog</dt><dd><?= $blogLive ? mkt_render_badge('active', ['active' => 'Live']) . ' version ' . (int) $blog['VersionNo'] . ' · since ' . htmlspecialchars(marketing_format_date((string) $blog['FirstPublishedAt'])) : mkt_render_badge('paused', ['paused' => 'Unpublished']) . ' ' . htmlspecialchars(marketing_format_datetime($blog['UnpublishedAt'] ?? null)) ?></dd><?php endif; ?>
          <?php if ($content['TargetUrl'] && !$content['PublishedUrl']): ?><dt class="is-wide">Planned URL</dt><dd><code><?= htmlspecialchars((string) $content['TargetUrl']) ?></code></dd><?php endif; ?>
          <?php if ($content['PublishedUrl']): ?><dt class="is-wide">Live URL</dt><dd><a href="<?= htmlspecialchars((string) $content['PublishedUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $content['PublishedUrl']) ?></a> — published <?= htmlspecialchars(marketing_format_datetime($content['PublishedAt'])) ?><?= $by('PublishedBy') ?></dd><?php endif; ?>
          <?php if ($content['Notes']): ?><dt class="is-wide">Notes</dt><dd><?= nl2br(htmlspecialchars((string) $content['Notes'])) ?></dd><?php endif; ?>
        </dl>
      </div>

      <?php /* ---------- Brief ---------- */ ?>
      <h2 class="hub-section-title" id="brief">Brief</h2>
      <?php if ($briefOpen): ?>
        <?php if ($canUpdate): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="margin-bottom:0.75rem">
          <?= $post('brief_ai', 'brief') ?>
          <button type="submit" class="btn-<?= trim((string) $content['BriefText']) === '' ? 'primary' : 'secondary' ?>" <?= trim((string) $content['BriefText']) !== '' ? 'onclick="return confirm(\'Replace the current brief with a new AI brief?\');"' : '' ?>><?= trim((string) $content['BriefText']) === '' ? 'Write the brief with AI' : 'Regenerate with AI' ?></button>
          <span class="form-hint">Uses the topic's evidence, the keyword, the product and the approved claims. Under a minute, a few cents.</span>
        </form>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
          <?= $post('brief_save', 'brief') ?>
          <?php marketing_render_field_guide_link('content-brief'); ?>
          <div class="form-group form-grid-full form-group--stacked">
            <label for="brief_text">Brief (Markdown)</label>
            <textarea class="form-input" id="brief_text" name="brief" rows="18" placeholder="Write the brief yourself, or generate it with AI above."><?= htmlspecialchars((string) ($content['BriefText'] ?? '')) ?></textarea>
            <?php if ($content['BriefAt']): ?><p class="form-hint">Last written <?= htmlspecialchars(marketing_format_datetime($content['BriefAt'])) ?><?= $by('BriefBy') ?>.</p><?php endif; ?>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-secondary">Save brief</button>
            <?php if ($stage === 'brief' && trim((string) $content['BriefText']) !== ''): ?>
            <button type="submit" class="btn-primary" name="action" value="brief_approve">Approve brief</button>
            <?php endif; ?>
          </div>
          <p class="form-hint">Approving locks the brief and moves the piece to drafting. Save any edits first — approval uses the saved brief.</p>
        </form>
        <?php elseif (trim((string) $content['BriefText']) !== ''): ?>
        <div class="mkt-article"><?= mkt_markdown_html((string) $content['BriefText']) ?></div>
        <?php else: ?>
        <p class="form-hint">No brief yet.</p>
        <?php endif; ?>
      <?php else: ?>
        <details>
          <summary>Approved brief<?= $content['BriefApprovedAt'] ? ' — ' . htmlspecialchars(marketing_format_datetime($content['BriefApprovedAt'])) . $by('BriefApprovedBy') : '' ?></summary>
          <div class="mkt-article" style="margin-top:0.5rem"><?= mkt_markdown_html((string) ($content['BriefText'] ?? '')) ?></div>
          <?php if ($canUpdate && $stage === 'draft'): ?>
          <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="margin-top:0.5rem">
            <?= $post('brief_reopen', 'brief') ?>
            <button type="submit" class="btn-text">Reopen the brief</button>
            <span class="form-hint">— existing versions are kept.</span>
          </form>
          <?php endif; ?>
        </details>
      <?php endif; ?>

      <?php /* ---------- Draft actions ---------- */ ?>
      <?php if ($canUpdate && $stage === 'draft'): ?>
      <h2 class="hub-section-title" id="draft">Draft</h2>
      <div class="form-actions">
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <?= $post('draft_ai', 'draft') ?>
          <button type="submit" class="btn-<?= $current === null ? 'primary' : 'secondary' ?>" <?= $current !== null ? 'onclick="return confirm(\'Write a fresh AI draft from the brief? It is saved as a new version; earlier versions are kept.\');"' : '' ?>><?= $current === null ? 'Write the draft with AI' : 'New AI draft from the brief' ?></button>
        </form>
        <?php if ($current !== null && $current['ClaimsCheckedAt'] === null): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline"><?= $post('check', 'claims') ?><button type="submit" class="btn-secondary">Run claims check</button></form>
        <?php endif; ?>
        <?php if ($current !== null): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <?= $post('submit') ?>
          <button type="submit" class="btn-primary" <?= mkt_content_passes($current) ? '' : 'disabled title="Needs a claims check at or above the minimum score"' ?>>Submit version <?= (int) $current['VersionNo'] ?> for review</button>
        </form>
        <?php endif; ?>
      </div>
      <p class="form-hint">
        <?= $current === null ? 'The AI draft follows the approved brief (Sonnet, one to two minutes, usually under 20 cents) and is claims-checked automatically. Or write it yourself in the editor below.' : 'Submitting needs a claims score of at least ' . htmlspecialchars(number_format($minScore, 1)) . ' on the current version.' ?>
      </p>
      <?php if ($current !== null): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <?= $post('revise', 'claims') ?>
        <?php marketing_render_field_guide_link('content-revise'); ?>
        <div class="form-group form-grid-full"><label for="instruction">AI revise</label><textarea class="form-input" id="instruction" name="instruction" rows="2" maxlength="2000" placeholder="Optional — blank fixes every claims-check finding and the latest reviewer note. e.g. 'Tighten the intro, add a section on dosing evidence.'"></textarea></div>
        <div class="form-actions"><button type="submit" class="btn-secondary">Revise with AI</button><span class="form-hint">Saved as a new version and re-checked.</span></div>
      </form>
      <?php endif; ?>
      <?php endif; ?>

      <?php /* ---------- Review ---------- */ ?>
      <?php if ($canReview): ?>
      <h2 class="hub-section-title" id="review"><?= $gate === 'compliance' ? 'Compliance review' : 'Editorial review' ?></h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <?= $post('review') ?>
        <input type="hidden" name="gate" value="<?= $gate ?>" />
        <?php marketing_render_field_guide_link('content-review'); ?>
        <p class="form-hint">
          <?= $gate === 'compliance'
              ? 'Check every statement against the approved claims: no disease claims, no guarantees, findings attributed to their source, and the disclaimer where a ‡ claim is used. The claims-check findings are below.'
              : 'Accuracy, voice, structure, search intent and the call to action. Compliance ' . ($content['ComplianceStatus'] === 'not_required' ? 'was not required (the claims check found no claims, flag terms or health references).' : 'has cleared this version.') ?>
          You are reviewing version <?= (int) ($current['VersionNo'] ?? 0) ?>.
        </p>
        <div class="form-group form-grid-full"><label for="note">Note</label><textarea class="form-input" id="note" name="note" rows="3" maxlength="2000" placeholder="Required when requesting changes"></textarea></div>
        <div class="form-actions">
          <button type="submit" class="btn-primary" name="decision" value="approved"><?= $gate === 'compliance' ? 'Clear compliance' : 'Approve' ?></button>
          <button type="submit" class="btn-secondary" name="decision" value="changes_requested">Request changes</button>
        </div>
      </form>
      <?php elseif ($gate !== null): ?>
      <p class="form-hint" id="review">
        Waiting on <?= $gate === 'compliance' ? 'compliance review by a Marketing Compliance Reviewer' : 'editorial review by a Marketing admin' ?>.
        <?= $ownWork ? 'You wrote or submitted this version, so someone else must review it.' : '' ?>
      </p>
      <?php endif; ?>

      <?php /* ---------- Publish ---------- */ ?>
      <?php if ($stage === 'approved' && $canUpdate): ?>
      <h2 class="hub-section-title" id="publish">Publish</h2>
      <?php if ($canPublishBlog): ?>
      <h3><?= $blogLive ? 'Update the blog post' : ($blog !== null ? 'Put back on the blog' : 'Publish to the blog') ?></h3>
      <p class="form-hint">
        <?= $blogLive
            ? 'The blog is showing version ' . (int) $blog['VersionNo'] . '. Publishing replaces it with approved version ' . (int) ($current['VersionNo'] ?? 0) . ' right away.'
            : 'Puts approved version ' . (int) ($current['VersionNo'] ?? 0) . ' on <a href="' . htmlspecialchars(blog_page_url()) . '" target="_blank" rel="noopener">' . htmlspecialchars(preg_replace('#^https?://#', '', blog_page_url())) . '</a> as soon as you click. Check the blog preview below first.' ?>
      </p>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <?= $post('blog_publish', 'publish') ?>
        <?php marketing_render_field_guide_link('content-blog-publish'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="blog_slug">Post address</label>
            <input class="form-input" id="blog_slug" name="slug" required maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= htmlspecialchars((string) ($blog['Slug'] ?? mkt_blog_suggest_slug((string) ($current['Title'] ?? $content['Title']), $id))) ?>" />
          </div>
          <p class="form-hint form-grid-full">Live link: <code><?= htmlspecialchars(blog_page_url()) ?>?post=</code><em>address</em>. 3–160 characters: lowercase letters, numbers and single hyphens, not starting or ending with a hyphen. Must not be used by another post.<?= $blog !== null ? ' Changing it breaks links already shared.' : '' ?></p>
          <div class="form-group form-grid-full"><label for="blog_excerpt">History summary</label><textarea class="form-input" id="blog_excerpt" name="excerpt" rows="2" maxlength="<?= MKT_BLOG_EXCERPT_MAX ?>"><?= htmlspecialchars((string) ($blog['Excerpt'] ?? ($current !== null ? mkt_blog_default_excerpt($current) : ''))) ?></textarea></div>
          <div class="form-group"><label for="blog_author">Byline</label><input class="form-input" id="blog_author" name="author" maxlength="150" value="<?= htmlspecialchars((string) ($blog['AuthorName'] ?? marketing_setting('blog.author', 'NutraAxis Team'))) ?>" /></div>
          <div class="form-group"><label for="blog_hero">Header image URL</label><input class="form-input" type="url" id="blog_hero" name="hero_image_url" maxlength="1000" value="<?= htmlspecialchars((string) ($blog['HeroImageUrl'] ?? '')) ?>" placeholder="Optional — https://…" /></div>
          <div class="form-group form-grid-full"><label for="blog_hero_alt">Image description</label><input class="form-input" id="blog_hero_alt" name="hero_image_alt" maxlength="300" value="<?= htmlspecialchars((string) ($blog['HeroImageAlt'] ?? '')) ?>" placeholder="What the image shows, for screen readers (defaults to the title)" /></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary" onclick="return confirm('<?= $blogLive ? 'Replace the live blog post with this approved version?' : 'Publish this approved version to the public blog now?' ?>');"><?= $blogLive ? 'Update blog post' : 'Publish to blog' ?></button>
        </div>
      </form>
      <?php else: ?>
      <p class="form-hint">Publishing to the blog needs full Marketing access (the same as editorial approval). Ask a Marketing admin.</p>
      <?php endif; ?>
      <?php if (!$blogLive): ?>
      <h3>Or publish somewhere else</h3>
      <p class="form-hint">Put version <?= (int) ($current['VersionNo'] ?? 0) ?> live on the site yourself (copy the HTML or Markdown below), then record the live URL here.</p>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form">
        <?= $post('publish') ?>
        <?php marketing_render_field_guide_link('content-live-url'); ?>
        <label for="url">Live URL</label>
        <input class="form-input" type="url" id="url" name="url" required maxlength="1000" style="min-width:24rem" value="<?= htmlspecialchars((string) ($content['PublishedUrl'] ?: $content['TargetUrl'] ?: '')) ?>" placeholder="<?= htmlspecialchars($siteUrl) ?>/…" />
        <button type="submit" class="btn-secondary">Mark published</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($blogLive && $canUpdate): ?>
      <?php if ($stage !== 'approved'): ?><h2 class="hub-section-title" id="publish">Blog post</h2><?php else: ?><h3>Blog post</h3><?php endif; ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Showing</dt><dd>Version <?= (int) $blog['VersionNo'] ?><?= $current !== null && (int) $current['VersionID'] !== (int) $blog['VersionID'] ? ' <span class="form-hint">— the current version ' . (int) $current['VersionNo'] . ' is not on the blog until it is approved and published</span>' : '' ?></dd>
          <dt>Byline</dt><dd><?= htmlspecialchars((string) ($blog['AuthorName'] ?? '—')) ?></dd>
          <dt>First published</dt><dd><?= htmlspecialchars(marketing_format_datetime((string) $blog['FirstPublishedAt'])) ?></dd>
          <dt>Last published</dt><dd><?= htmlspecialchars(marketing_format_datetime((string) $blog['PublishedAt'])) ?></dd>
          <dt class="is-wide">Live link</dt><dd><a href="<?= htmlspecialchars(blog_post_url((string) $blog['Slug'])) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(blog_post_url((string) $blog['Slug'])) ?></a></dd>
        </dl>
      </div>
      <form class="mkt-inline-form" method="post" action="<?= htmlspecialchars($selfHref) ?>" style="margin-bottom:0.5rem">
        <?= $post('blog_unpublish', 'publish') ?>
        <?php marketing_render_field_guide_link('content-blog-unpublish'); ?>
        <input class="form-input" name="note" maxlength="2000" style="min-width:20rem" placeholder="Reason (optional, kept in the review history)" aria-label="Reason for unpublishing" />
        <button type="submit" class="btn-secondary" onclick="return confirm('Take this post off the public blog now?');">Unpublish from blog</button>
      </form>
      <?php if ($stage === 'published'): ?>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>"><?= $post('monitoring') ?><button type="submit" class="btn-text">Move to monitoring</button> <span class="form-hint">— Page Inventory cannot track single blog posts; add /our-blog there to follow the whole blog's traffic as one page.</span></form>
      <?php endif; ?>
      <?php elseif (in_array($stage, ['published', 'monitoring'], true) && $canUpdate): ?>
      <h2 class="hub-section-title" id="publish">Live page</h2>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="margin-bottom:0.5rem">
        <?= $post('update_url') ?>
        <?php marketing_render_field_guide_link('content-live-url'); ?>
        <input class="form-input" type="url" name="url" required maxlength="1000" style="min-width:24rem" value="<?= htmlspecialchars((string) $content['PublishedUrl']) ?>" aria-label="Live URL" />
        <button type="submit" class="btn-secondary">Update URL</button>
      </form>
      <?php if ($stage === 'published'): ?>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>"><?= $post('monitoring') ?><button type="submit" class="btn-text">Move to monitoring</button> <span class="form-hint">— once it is indexed; search performance joins here when Search Console is connected.</span></form>
      <?php endif; ?>
      <?php endif; ?>

      <?php /* ---------- Version preview ---------- */ ?>
      <?php if ($diffNew !== null && $diffOld !== null): ?>
      <h2 class="hub-section-title" id="diff">Changes from version <?= (int) $diffOld['VersionNo'] ?> to <?= (int) $diffNew['VersionNo'] ?></h2>
      <p class="form-hint"><a href="<?= htmlspecialchars($selfHref) ?>#versions">Back to the current version</a> · paragraph-level: green added, red removed, grey unchanged.</p>
      <?php
      $metaChanges = [];
      foreach (['Title' => 'Title', 'MetaTitle' => 'Meta title', 'MetaDescription' => 'Meta description'] as $col => $label) {
          if ((string) $diffOld[$col] !== (string) $diffNew[$col]) {
              $metaChanges[] = [$label, (string) $diffOld[$col], (string) $diffNew[$col]];
          }
      }
      ?>
      <?php if ($metaChanges !== []): ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <?php foreach ($metaChanges as [$label, $old, $new]): ?>
          <dt><?= htmlspecialchars($label) ?></dt><dd><span style="text-decoration:line-through;color:var(--text-muted)"><?= htmlspecialchars($old) ?></span><br><?= htmlspecialchars($new) ?></dd>
          <?php endforeach; ?>
        </dl>
      </div>
      <?php endif; ?>
      <div class="mkt-diff mkt-article">
        <?php foreach (mkt_content_diff((string) $diffOld['Body'], (string) $diffNew['Body']) as $op): ?>
        <p class="is-<?= $op['op'] ?>"><?= htmlspecialchars($op['text']) ?></p>
        <?php endforeach; ?>
      </div>
      <?php elseif ($shown !== null): ?>
      <h2 class="hub-section-title" id="preview">
        <?= $isCurrentShown ? 'Current version' : 'Version' ?> <?= (int) $shown['VersionNo'] ?>
        <span class="form-hint">— <?= htmlspecialchars(MKT_CONTENT_SOURCES[(string) $shown['Source']] ?? (string) $shown['Source']) ?>, <?= number_format((int) $shown['WordCount']) ?> words</span>
      </h2>
      <?php if (!$isCurrentShown): ?><p class="admin-notice is-error">This is an earlier version. <a href="<?= htmlspecialchars($selfHref) ?>">Show the current version</a>.</p><?php endif; ?>
      <div class="mkt-serp" style="margin-bottom:0.75rem">
        <div class="mkt-serp-url"><?= htmlspecialchars((string) ($content['PublishedUrl'] ?: $content['TargetUrl'] ?: $siteUrl . '/…')) ?></div>
        <div class="mkt-serp-title"><?= htmlspecialchars((string) ($shown['MetaTitle'] ?: $shown['Title'])) ?></div>
        <div class="mkt-serp-desc"><?= htmlspecialchars((string) ($shown['MetaDescription'] ?? '')) ?></div>
        <div class="form-hint">Meta title <?= mb_strlen((string) $shown['MetaTitle']) ?>/<?= MKT_CONTENT_META_TITLE_MAX ?> · description <?= mb_strlen((string) $shown['MetaDescription']) ?>/<?= MKT_CONTENT_META_DESCRIPTION_MAX ?></div>
      </div>
      <div class="mkt-article">
        <h1 style="font-size:1.6rem;margin-top:0"><?= htmlspecialchars((string) $shown['Title']) ?></h1>
        <?= $html ?>
      </div>
      <p class="form-hint">
        <?= in_array($stage, ['approved', 'published', 'monitoring'], true) && $isCurrentShown ? '' : '<strong>Not approved — do not publish.</strong>' ?>
        <button type="button" class="btn-text" data-copy-from="copy-html">Copy HTML</button>
        <button type="button" class="btn-text" data-copy-from="copy-md">Copy Markdown</button>
      </p>
      <textarea id="copy-html" hidden><?= htmlspecialchars($html) ?></textarea>
      <textarea id="copy-md" hidden><?= htmlspecialchars((string) $shown['Body']) ?></textarea>

      <details id="blog-preview" <?= $content['ContentType'] === 'blog' || $stage === 'approved' ? 'open' : '' ?>>
        <summary class="hub-section-title" style="cursor:pointer">Blog preview — version <?= (int) $shown['VersionNo'] ?> as it would appear on the blog</summary>
        <link rel="stylesheet" href="/blog/blog.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/blog/blog.css') ?>" />
        <div class="na-blog-post" style="margin-top:0.75rem;max-width:760px">
          <h2 class="na-blog-post-title"><?= htmlspecialchars((string) $shown['Title']) ?></h2>
          <div class="na-blog-meta">
            <span><?= htmlspecialchars($blog !== null ? blog_format_date((string) $blog['FirstPublishedAt']) : blog_format_date(gmdate('Y-m-d H:i:s'))) ?></span>
            <span>By <?= htmlspecialchars((string) ($blog['AuthorName'] ?? marketing_setting('blog.author', 'NutraAxis Team'))) ?></span>
            <span><?= max(1, (int) round((int) $shown['WordCount'] / BLOG_WORDS_PER_MINUTE)) ?> min read</span>
          </div>
          <div class="na-blog-article"><?= mkt_blog_html((string) $shown['Body']) ?></div>
        </div>
      </details>

      <h2 class="hub-section-title" id="claims">Claims check — version <?= (int) $shown['VersionNo'] ?></h2>
      <?php if ($check === null): ?>
      <p class="form-hint">Not checked yet.</p>
      <?php else: ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Score</dt><dd><?= mkt_content_score_badge($shown) ?> AI <?= htmlspecialchars(number_format((float) ($check['ai_score'] ?? $check['score']), 1)) ?><?= ($check['flag_terms'] ?? []) !== [] && (float) ($check['flag_penalty'] ?? 1) > 0 ? ', minus ' . count($check['flag_terms']) . ' for flag terms' : '' ?> · minimum <?= htmlspecialchars(number_format($minScore, 1)) ?></dd>
          <dt>Summary</dt><dd><?= htmlspecialchars((string) ($check['summary'] ?? '')) ?></dd>
          <?php if (($check['flag_terms'] ?? []) !== []): ?><dt>Flag terms</dt><dd><?= htmlspecialchars(implode(', ', $check['flag_terms'])) ?> <span class="form-hint">— compliance checks each use in context</span></dd><?php endif; ?>
          <dt>References</dt><dd><?= !empty($check['references_efficacy']) ? 'Efficacy · ' : '' ?><?= !empty($check['references_condition']) ? 'Health condition · ' : '' ?>Disclaimer <?= !empty($check['disclaimer_ok']) ? 'OK' : '<strong>missing or wrong</strong>' ?></dd>
          <dt>Compliance</dt><dd><?= !empty($shown['NeedsCompliance']) ? 'Required' : 'Not required' ?><?= marketing_setting('review.compliance_mode', 'claims') === 'all' ? ' (every piece is reviewed)' : '' ?></dd>
          <?php if ($usedClaims !== []): ?>
          <dt>Claims used</dt><dd><?= implode('<br>', array_map(static fn(array $c): string => htmlspecialchars(($c['ProductName'] ?? 'General') . ': ' . $c['ClaimText'] . ($c['RequiresDisclaimer'] ? ' ‡' : '')), $usedClaims)) ?></dd>
          <?php endif; ?>
          <dt>Checked</dt><dd><?= htmlspecialchars(marketing_format_datetime($shown['ClaimsCheckedAt'] ?? null)) ?></dd>
        </dl>
        <?php if (($check['issues'] ?? []) !== []): ?>
        <h3>Issues</h3>
        <ul><?php foreach ($check['issues'] as $issue): ?><li><?= htmlspecialchars((string) $issue) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
      <?php if (($check['statements'] ?? []) !== []): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Statement</th><th>Class</th><th>Note</th></tr></thead>
          <tbody>
            <?php foreach ($check['statements'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['text']) ?></td>
              <td><?= mkt_render_badge(in_array($row['class'], ['unapproved', 'disease'], true) ? 'failed' : 'active', ['failed' => $classLabels[$row['class']] ?? $row['class'], 'active' => $classLabels[$row['class']] ?? $row['class']]) ?></td>
              <td><?= htmlspecialchars((string) ($row['note'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

      <?php /* ---------- Editor ---------- */ ?>
      <?php if ($canEdit && ($diffNew === null)): ?>
      <?php $editFrom = $current ?? ['Title' => $content['Title'], 'MetaTitle' => '', 'MetaDescription' => '', 'Body' => '']; ?>
      <details id="edit" <?= $stage === 'draft' ? 'open' : '' ?>>
        <summary class="hub-section-title" style="cursor:pointer"><?= $current === null ? 'Write the draft yourself' : 'Edit — saves version ' . ((int) ($versions[0]['VersionNo'] ?? 0) + 1) ?></summary>
        <?php if ($stage !== 'draft'): ?>
        <p class="admin-notice is-error">This piece is <?= htmlspecialchars(strtolower(MKT_CONTENT_STAGES[$stage])) ?>. Saving a change sends it back to draft and clears its reviews<?= in_array($stage, ['published', 'monitoring'], true) ? '; the live URL is kept so the update can be re-published over it' : '' ?>.</p>
        <?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
          <?= $post('save_version', 'claims') ?>
          <?php marketing_render_field_guide_link('content-version'); ?>
          <div class="form-grid">
            <div class="form-group form-grid-full"><label for="v_title">Page title</label><input class="form-input" id="v_title" name="title" required maxlength="300" value="<?= htmlspecialchars((string) $editFrom['Title']) ?>" /></div>
            <div class="form-group form-grid-full"><label for="v_meta_title">Meta title</label><input class="form-input" id="v_meta_title" name="meta_title" maxlength="200" value="<?= htmlspecialchars((string) ($editFrom['MetaTitle'] ?? '')) ?>" placeholder="Up to <?= MKT_CONTENT_META_TITLE_MAX ?> characters" /></div>
            <div class="form-group form-grid-full"><label for="v_meta_description">Meta description</label><input class="form-input" id="v_meta_description" name="meta_description" maxlength="400" value="<?= htmlspecialchars((string) ($editFrom['MetaDescription'] ?? '')) ?>" placeholder="Up to <?= MKT_CONTENT_META_DESCRIPTION_MAX ?> characters" /></div>
            <div class="form-group form-grid-full form-group--stacked">
              <label for="v_body">Body (Markdown)</label>
              <textarea class="form-input" id="v_body" name="body" rows="24" required style="font-family:ui-monospace,monospace;font-size:0.85rem"><?= htmlspecialchars((string) $editFrom['Body']) ?></textarea>
              <p class="form-hint">Use <code>##</code> / <code>###</code> headings, <code>-</code> lists, <code>**bold**</code>, <code>*italic*</code> and <code>[text](https://…)</code> links. No H1 — the page title is the H1.</p>
            </div>
            <div class="form-group form-grid-full"><label for="v_note">Change note</label><input class="form-input" id="v_note" name="note" maxlength="500" placeholder="Optional — what changed and why" /></div>
          </div>
          <div class="form-actions"><button type="submit" class="btn-primary">Save as new version and re-check</button></div>
        </form>
      </details>
      <?php endif; ?>

      <?php /* ---------- Versions ---------- */ ?>
      <h2 class="hub-section-title" id="versions">Versions</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Version</th><th>Source</th><th>Title</th><th>Words</th><th>Claims score</th><th>By</th><th>When</th><th>Compare</th></tr></thead>
          <tbody>
            <?php if ($versions === []): ?>
            <tr><td colspan="8">No versions yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($versions as $row): ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars($selfHref) ?>&amp;version=<?= (int) $row['VersionNo'] ?>#preview">v<?= (int) $row['VersionNo'] ?></a>
                <?= (int) $row['VersionID'] === (int) $content['CurrentVersionID'] ? ' <span class="form-hint">current</span>' : '' ?>
                <?= (int) $row['VersionID'] === (int) $content['SubmittedVersionID'] ? ' <span class="form-hint">submitted</span>' : '' ?>
              </td>
              <td><?= htmlspecialchars(MKT_CONTENT_SOURCES[(string) $row['Source']] ?? (string) $row['Source']) ?><?= $row['Note'] ? '<div class="form-hint">' . htmlspecialchars(mb_strimwidth((string) $row['Note'], 0, 140, '…')) . '</div>' : '' ?></td>
              <td><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 80, '…')) ?></td>
              <td><?= number_format((int) $row['WordCount']) ?></td>
              <td><?= mkt_content_score_badge($row) ?></td>
              <td><?= htmlspecialchars((string) ($row['CreatedByName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($row['CreatedAt'] ?? null)) ?></td>
              <td><?= (int) $row['VersionNo'] > 1 ? '<a href="' . htmlspecialchars($selfHref) . '&amp;diff=' . (int) $row['VersionNo'] . '#diff">vs v' . ((int) $row['VersionNo'] - 1) . '</a>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Review history</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>When</th><th>Step</th><th>Decision</th><th>By</th><th>Version</th><th>Note</th></tr></thead>
          <tbody>
            <?php if ($reviews === []): ?>
            <tr><td colspan="6">No activity yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($reviews as $row): ?>
            <tr>
              <td><?= htmlspecialchars(marketing_format_datetime($row['CreatedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars($gateLabels[(string) $row['Gate']] ?? (string) $row['Gate']) ?></td>
              <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $row['Decision']))) ?></td>
              <td><?= htmlspecialchars((string) ($row['UserName'] ?? '—')) ?></td>
              <td><?= $row['VersionNo'] !== null ? 'v' . (int) $row['VersionNo'] : '—' ?></td>
              <td><?= nl2br(htmlspecialchars((string) ($row['Note'] ?? ''))) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php /* ---------- Details ---------- */ ?>
      <?php if ($canUpdate && $stage !== 'archived'): ?>
      <?php
      $locked = $content['BriefApprovedAt'] !== null;
      $accepted = $locked ? [] : mkt_topics_list(['statuses' => ['accepted'], 'order' => 'recent'], 200);
      $products = $locked ? [] : mkt_products_list();
      $keywords = $locked ? [] : mkt_keywords_list();
      $owners = mkt_content_owner_options();
      ?>
      <details id="details">
        <summary class="hub-section-title" style="cursor:pointer">Details</summary>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
          <?= $post('details', 'details') ?>
          <?php marketing_render_field_guide_link('content-details'); ?>
          <div class="form-grid">
            <div class="form-group form-grid-full"><label for="d_title">Working title</label><input class="form-input" id="d_title" name="title" required maxlength="300" value="<?= htmlspecialchars((string) $content['Title']) ?>" /></div>
            <?php if (!$locked): ?>
            <div class="form-group">
              <label for="d_type">Type</label>
              <select class="form-input" id="d_type" name="content_type">
                <?php foreach ($types as $key => $type): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $content['ContentType'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($type['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="d_audience">Audience</label>
              <select class="form-input" id="d_audience" name="audience">
                <?php foreach (MKT_CLAIM_AUDIENCES as $key => $label): ?>
                <option value="<?= $key ?>" <?= $content['Audience'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group form-grid-full">
              <label for="d_topic">Topic</label>
              <select class="form-input" id="d_topic" name="topic_id">
                <option value="">None</option>
                <?php if ($content['TopicID'] && !in_array((int) $content['TopicID'], array_map('intval', array_column($accepted, 'TopicID')), true)): ?>
                <option value="<?= (int) $content['TopicID'] ?>" selected>#<?= (int) $content['TopicID'] ?> <?= htmlspecialchars(mb_strimwidth((string) $content['TopicTitle'], 0, 140, '…')) ?></option>
                <?php endif; ?>
                <?php foreach ($accepted as $row): ?>
                <option value="<?= (int) $row['TopicID'] ?>" <?= (int) $content['TopicID'] === (int) $row['TopicID'] ? 'selected' : '' ?>>#<?= (int) $row['TopicID'] ?> <?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 140, '…')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="d_product">Product</label>
              <select class="form-input" id="d_product" name="product_id">
                <option value="">None</option>
                <?php foreach ($products as $row): ?>
                <option value="<?= (int) $row['ProductID'] ?>" <?= (int) $content['ProductID'] === (int) $row['ProductID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['Name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="d_keyword">Keyword list</label>
              <select class="form-input" id="d_keyword" name="keyword_id">
                <option value="">None</option>
                <?php foreach ($keywords as $row): ?>
                <option value="<?= (int) $row['KeywordID'] ?>" <?= (int) $content['KeywordID'] === (int) $row['KeywordID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['Keyword']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label for="d_primary">Primary keyword</label><input class="form-input" id="d_primary" name="primary_keyword" maxlength="200" value="<?= htmlspecialchars((string) ($content['PrimaryKeyword'] ?? '')) ?>" /></div>
            <div class="form-group"><label for="d_secondary">Secondary keywords</label><input class="form-input" id="d_secondary" name="secondary_keywords" maxlength="1000" value="<?= htmlspecialchars((string) ($content['SecondaryKeywords'] ?? '')) ?>" /></div>
            <div class="form-group"><label for="d_words">Target words</label><input class="form-input" type="number" id="d_words" name="target_words" min="200" max="5000" value="<?= htmlspecialchars((string) ($content['TargetWords'] ?? '')) ?>" /></div>
            <?php else: ?>
            <p class="form-hint form-grid-full">Type, topic, product, keywords, audience and length are locked because the brief is approved. Reopen the brief (at the draft stage) to change them.</p>
            <?php endif; ?>
            <div class="form-group">
              <label for="d_owner">Owner</label>
              <select class="form-input" id="d_owner" name="owner_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($owners as $row): ?>
                <option value="<?= (int) $row['UserID'] ?>" <?= (int) $content['OwnerUserID'] === (int) $row['UserID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['UserName']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label for="d_due">Due</label><input class="form-input" type="date" id="d_due" name="due_date" value="<?= htmlspecialchars($content['DueDate'] ? substr((string) $content['DueDate'], 0, 10) : '') ?>" /></div>
            <div class="form-group form-grid-full"><label for="d_url">Planned URL</label><input class="form-input" type="url" id="d_url" name="target_url" maxlength="1000" value="<?= htmlspecialchars((string) ($content['TargetUrl'] ?? '')) ?>" /></div>
            <div class="form-group form-grid-full"><label for="d_notes">Notes</label><textarea class="form-input" id="d_notes" name="notes" rows="3" maxlength="2000"><?= htmlspecialchars((string) ($content['Notes'] ?? '')) ?></textarea></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary">Save details</button>
            <?php if (!in_array($stage, ['published', 'monitoring'], true)): ?>
            <button type="submit" class="btn-secondary" name="action" value="archive" formnovalidate onclick="return confirm('Archive this piece?');">Archive</button>
            <?php endif; ?>
          </div>
        </form>
      </details>
      <?php endif; ?>
    </div>
  </main>
  <script>
    document.querySelectorAll('[data-copy-from]').forEach((button) => {
      button.addEventListener('click', () => {
        const source = document.getElementById(button.dataset.copyFrom);
        navigator.clipboard.writeText(source.value).then(() => { button.textContent = 'Copied'; });
      });
    });
  </script>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
