<?php

require_once __DIR__ . '/marketing-topics.php';

const MKT_CAMPAIGN_FORMATS = ['post' => 'Single post', 'series' => 'Series', 'email' => 'Email'];
const MKT_CAMPAIGN_STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'];
const MKT_ASSET_STATUSES = [
    'draft'             => 'Draft',
    'in_review'         => 'In review',
    'changes_requested' => 'Changes requested',
    'approved'          => 'Approved',
    'scheduled'         => 'Scheduled',
    'posted'            => 'Posted',
    'archived'          => 'Archived',
];
const MKT_GATE_STATUSES = [
    'not_required'      => 'Not required',
    'pending'           => 'Pending',
    'approved'          => 'Approved',
    'changes_requested' => 'Changes requested',
];
const MKT_ASSET_EDITABLE = ['draft', 'changes_requested'];
const MKT_ASSET_CONTENT_FIELDS = [
    'title' => 'Title', 'subject' => 'Subject', 'preview_text' => 'PreviewText', 'body' => 'Body',
    'hashtags' => 'Hashtags', 'cta_text' => 'CtaText', 'media_notes' => 'MediaNotes',
];
const MKT_SERIES_MAX_PARTS = 10;
const MKT_EMAIL_MAX_PARTS = 6;

/* ---------- Channels, roles, helpers ---------- */

/**
 * @return array<string, array{key: string, label: string, medium: string, max: int, guidance: string}>
 */
function mkt_channels(): array
{
    $channels = [];
    foreach (marketing_setting_lines('campaign.channels') as $line) {
        [$key, $label, $medium, $max, $guidance] = array_pad(array_map('trim', explode('|', $line)), 5, '');
        if ($key === '') {
            continue;
        }
        $channels[$key] = ['key' => $key, 'label' => $label ?: $key, 'medium' => $medium === 'email' ? 'email' : 'social', 'max' => (int) $max, 'guidance' => $guidance];
    }

    return $channels;
}

function mkt_compliance_reviewer_logins(): array
{
    return array_map('strtolower', marketing_setting_lines('review.compliance_reviewers'));
}

/**
 * Compliance reviewers are named in review.compliance_reviewers and need Marketing update access.
 */
function mkt_can_compliance_review(): bool
{
    $login = strtolower(trim((string) (auth_user()['UserLogin'] ?? '')));

    return $login !== '' && marketing_can_update() && in_array($login, mkt_compliance_reviewer_logins(), true);
}

function mkt_can_editorial_review(): bool
{
    return marketing_can_admin();
}

function mkt_compliance_reviewers(): array
{
    $logins = mkt_compliance_reviewer_logins();
    if ($logins === []) {
        return [];
    }
    $params = [];
    $in = [];
    foreach ($logins as $i => $login) {
        $in[] = ':l' . $i;
        $params['l' . $i] = $login;
    }
    $stmt = db()->prepare('SELECT u.UserID, u.UserName, u.UserLogin, r.Marketing FROM dbo.[User] u LEFT JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole WHERE LOWER(u.UserLogin) IN (' . implode(', ', $in) . ') ORDER BY u.UserName');
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_slugify(string $text, int $max = 60): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '', '-'));
    if ($slug === '') {
        return 'campaign';
    }
    if (strlen($slug) > $max) {
        $cut = substr($slug, 0, $max + 1);
        $slug = str_contains($cut, '-') ? substr($cut, 0, (int) strrpos($cut, '-')) : substr($slug, 0, $max);
    }

    return rtrim($slug, '-');
}

function mkt_unique_campaign_slug(string $base): string
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM dbo.MktCampaign WHERE Slug = :s');
    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute(['s' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = substr($base, 0, 70) . '-' . $n;
    }
}

function mkt_user_names(array $userIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if ($ids === []) {
        return [];
    }

    return array_column(db()->query('SELECT UserID, UserName FROM dbo.[User] WHERE UserID IN (' . implode(',', $ids) . ')')->fetchAll(), 'UserName', 'UserID');
}

/* ---------- Campaigns ---------- */

function mkt_campaigns_list(array $filters = []): array
{
    $where = [];
    $params = [];
    if (($filters['status'] ?? '') !== '' && array_key_exists((string) $filters['status'], MKT_CAMPAIGN_STATUSES)) {
        $where[] = 'c.Status = :status';
        $params['status'] = (string) $filters['status'];
    } else {
        $where[] = "c.Status <> N'archived'";
    }
    if (!empty($filters['topic_id'])) {
        $where[] = 'c.TopicID = :topic';
        $params['topic'] = (int) $filters['topic_id'];
    }
    $stmt = db()->prepare(<<<SQL
        SELECT c.*, t.Title AS TopicTitle,
               (SELECT COUNT(*) FROM dbo.MktAsset a WHERE a.CampaignID = c.CampaignID) AS AssetCount,
               (SELECT COUNT(*) FROM dbo.MktAsset a WHERE a.CampaignID = c.CampaignID AND a.Status IN (N'draft', N'changes_requested')) AS DraftCount,
               (SELECT COUNT(*) FROM dbo.MktAsset a WHERE a.CampaignID = c.CampaignID AND a.Status = N'in_review') AS ReviewCount,
               (SELECT COUNT(*) FROM dbo.MktAsset a WHERE a.CampaignID = c.CampaignID AND a.Status IN (N'approved', N'scheduled', N'posted')) AS ApprovedCount
        FROM dbo.MktCampaign c
        LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
    SQL . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY c.UpdatedAt DESC');
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_campaign_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT c.*, t.Title AS TopicTitle, t.Angle AS TopicAngle, t.Status AS TopicStatus, t.AvoidNotes AS TopicAvoid
        FROM dbo.MktCampaign c
        LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
        WHERE c.CampaignID = :id
    SQL);
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

/**
 * New campaign from an accepted topic. Parts: post = 1, series 2–10, email 1–6.
 */
function mkt_campaign_create(array $input): array
{
    $topic = mkt_topic_get((int) ($input['topic_id'] ?? 0));
    if ($topic === null || $topic['Status'] !== 'accepted') {
        return ['ok' => false, 'error' => 'Choose an accepted topic — campaigns are generated from its angle.'];
    }
    $format = array_key_exists((string) ($input['format'] ?? ''), MKT_CAMPAIGN_FORMATS) ? (string) $input['format'] : 'post';
    $known = mkt_channels();
    if ($format === 'email') {
        $channels = isset($known['email']) ? ['email'] : [];
    } else {
        $channels = array_values(array_filter((array) ($input['channels'] ?? []), static fn($c): bool => isset($known[(string) $c]) && $known[(string) $c]['medium'] === 'social'));
    }
    if ($channels === []) {
        return ['ok' => false, 'error' => $format === 'email' ? 'The email channel is missing from campaign.channels.' : 'Choose at least one social channel.'];
    }
    $parts = match ($format) {
        'series' => max(2, min(MKT_SERIES_MAX_PARTS, (int) ($input['part_count'] ?? 5))),
        'email'  => max(1, min(MKT_EMAIL_MAX_PARTS, (int) ($input['part_count'] ?? 1))),
        default  => 1,
    };
    $ctaUrl = trim((string) ($input['cta_url'] ?? '')) ?: (string) marketing_setting('campaign.default_cta_url', (string) marketing_setting('brand.site_url', ''));
    if (!preg_match('#^https?://#i', $ctaUrl) || filter_var($ctaUrl, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'error' => 'Enter a valid http(s) call-to-action URL.'];
    }
    $audience = (string) ($input['audience'] ?? '');
    if (!array_key_exists($audience, MKT_CLAIM_AUDIENCES)) {
        $audience = array_key_exists((string) ($topic['Audience'] ?? ''), MKT_CLAIM_AUDIENCES) ? (string) $topic['Audience'] : 'both';
    }
    $name = trim((string) ($input['name'] ?? '')) ?: mb_substr((string) $topic['Title'], 0, 150) . ' — ' . MKT_CAMPAIGN_FORMATS[$format];
    $userId = marketing_user_id();
    $data = [
        'TopicID'     => (int) $topic['TopicID'],
        'Name'        => mb_substr($name, 0, 200),
        'Slug'        => mkt_unique_campaign_slug(mkt_slugify((string) $topic['Title'], 48) . '-' . $format),
        'Format'      => $format,
        'Channels'    => implode(',', $channels),
        'Audience'    => $audience,
        'PartCount'   => $parts,
        'CadenceDays' => $parts > 1 ? max(1, min(30, (int) ($input['cadence_days'] ?? ($format === 'email' ? 3 : 2)))) : null,
        'CtaUrl'      => mb_substr($ctaUrl, 0, 1000),
        'CtaText'     => trim((string) ($input['cta_text'] ?? '')) !== '' ? mb_substr(trim((string) $input['cta_text']), 0, 200) : null,
        'Brief'       => trim((string) ($input['brief'] ?? '')) !== '' ? mb_substr(trim((string) $input['brief']), 0, 2000) : null,
        'CreatedBy'   => $userId,
        'UpdatedBy'   => $userId,
    ];
    $cols = array_keys($data);
    $stmt = db()->prepare('INSERT INTO dbo.MktCampaign (' . implode(', ', $cols) . ') OUTPUT INSERTED.CampaignID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
    $stmt->execute($data);
    $id = db_fetch_inserted_int($stmt, 'inserted_id');
    $built = audit_build_insert('MktCampaign', $data, 'CampaignID', $id);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'id' => $id];
}

/**
 * Name, CTA and brief can change any time; the CTA URL feeds every asset's tracked link.
 */
function mkt_campaign_update(int $id, array $input): array
{
    $before = mkt_campaign_get($id);
    if ($before === null) {
        return ['ok' => false, 'error' => 'Campaign not found.'];
    }
    $ctaUrl = trim((string) ($input['cta_url'] ?? $before['CtaUrl']));
    if (!preg_match('#^https?://#i', $ctaUrl) || filter_var($ctaUrl, FILTER_VALIDATE_URL) === false) {
        return ['ok' => false, 'error' => 'Enter a valid http(s) call-to-action URL.'];
    }
    $status = (string) ($input['status'] ?? $before['Status']);
    $data = [
        'Name'      => trim((string) ($input['name'] ?? '')) !== '' ? mb_substr(trim((string) $input['name']), 0, 200) : (string) $before['Name'],
        'CtaUrl'    => mb_substr($ctaUrl, 0, 1000),
        'CtaText'   => trim((string) ($input['cta_text'] ?? '')) !== '' ? mb_substr(trim((string) $input['cta_text']), 0, 200) : null,
        'Brief'     => trim((string) ($input['brief'] ?? '')) !== '' ? mb_substr(trim((string) $input['brief']), 0, 2000) : null,
        'Status'    => array_key_exists($status, MKT_CAMPAIGN_STATUSES) ? $status : (string) $before['Status'],
        'UpdatedBy' => marketing_user_id(),
    ];
    $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
    db()->prepare("UPDATE dbo.MktCampaign SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE CampaignID = :id")->execute($data + ['id' => $id]);
    $built = audit_build_update('MktCampaign', 'CampaignID', $id, $data, array_intersect_key($before, $data));
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/* ---------- Assets ---------- */

function mkt_campaign_assets(int $campaignId): array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktAsset WHERE CampaignID = :id ORDER BY SequenceNo, Channel');
    $stmt->execute(['id' => $campaignId]);

    return $stmt->fetchAll();
}

function mkt_asset_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktAsset WHERE AssetID = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

function mkt_asset_reviews(int $assetId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT r.*, u.UserName FROM dbo.MktAssetReview r
        LEFT JOIN dbo.[User] u ON u.UserID = r.UserID
        WHERE r.AssetID = :id ORDER BY r.ReviewID DESC
    SQL);
    $stmt->execute(['id' => $assetId]);

    return $stmt->fetchAll();
}

function mkt_asset_check(array $asset): ?array
{
    $check = json_decode((string) ($asset['ClaimsCheckJson'] ?? ''), true);

    return is_array($check) ? $check : null;
}

function mkt_asset_check_current(array $asset): bool
{
    return $asset['ClaimsCheckedVersion'] !== null && (int) $asset['ClaimsCheckedVersion'] === (int) $asset['ContentVersion'];
}

function mkt_asset_passes(array $asset): bool
{
    return mkt_asset_check_current($asset) && $asset['ClaimsScore'] !== null
        && (float) $asset['ClaimsScore'] >= (float) marketing_setting('claims.min_score', '7');
}

/**
 * CTA URL with utm_source = channel, utm_medium = social|email, utm_campaign = campaign slug, utm_content = asset id.
 */
function mkt_asset_tracked_url(array $asset, array $campaign): string
{
    $channels = mkt_channels();
    $channel = (string) $asset['Channel'];
    $utm = http_build_query([
        'utm_source'   => $channel,
        'utm_medium'   => $channels[$channel]['medium'] ?? 'social',
        'utm_campaign' => (string) $campaign['Slug'],
        'utm_content'  => (string) $asset['AssetID'],
    ]);
    $url = (string) $campaign['CtaUrl'];
    $fragment = '';
    if (($hash = strpos($url, '#')) !== false) {
        $fragment = substr($url, $hash);
        $url = substr($url, 0, $hash);
    }
    if (parse_url($url, PHP_URL_PATH) === null && !str_contains($url, '?')) {
        $url .= '/';
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . $utm . $fragment;
}

/**
 * Copy-ready text: [LINK] replaced by the tracked URL, hashtags appended for social channels.
 */
function mkt_asset_copy_text(array $asset, array $campaign): string
{
    $text = str_replace('[LINK]', mkt_asset_tracked_url($asset, $campaign), (string) $asset['Body']);
    if (!empty($asset['Hashtags']) && $asset['Channel'] !== 'email') {
        $text .= "\n\n" . $asset['Hashtags'];
    }

    return $text;
}

function mkt_asset_log(int $assetId, string $gate, string $decision, ?string $note, ?array $asset = null): void
{
    db()->prepare(<<<SQL
        INSERT INTO dbo.MktAssetReview (AssetID, Gate, Decision, Note, ContentVersion, ClaimsScore, UserID)
        VALUES (:id, :gate, :decision, :note, :version, :score, :user)
    SQL)->execute([
        'id'       => $assetId,
        'gate'     => $gate,
        'decision' => $decision,
        'note'     => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null,
        'version'  => $asset['ContentVersion'] ?? null,
        'score'    => $asset['ClaimsScore'] ?? null,
        'user'     => marketing_user_id(),
    ]);
}

/**
 * Save asset content. Any content change bumps ContentVersion (the claims check must run again); editing an
 * asset that is in review or approved sends it back to draft and clears both gates.
 *
 * @return array{ok: bool, error?: string, changed?: bool, reset?: bool}
 */
function mkt_asset_save(int $id, array $input): array
{
    $before = mkt_asset_get($id);
    if ($before === null) {
        return ['ok' => false, 'error' => 'Asset not found.'];
    }
    if (in_array($before['Status'], ['scheduled', 'posted', 'archived'], true)) {
        return ['ok' => false, 'error' => 'Scheduled, posted or archived assets cannot be edited.'];
    }
    $data = [];
    foreach (MKT_ASSET_CONTENT_FIELDS as $key => $col) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $value = trim(str_replace("\r\n", "\n", (string) $input[$key]));
        $max = match ($col) { 'Body' => 20000, 'MediaNotes' => 1000, 'Hashtags' => 500, 'CtaText' => 200, default => 300 };
        $data[$col] = $value !== '' ? mb_substr($value, 0, $max) : null;
    }
    if (array_key_exists('Body', $data) && $data['Body'] === null) {
        return ['ok' => false, 'error' => 'The body cannot be empty.'];
    }
    $changed = false;
    foreach ($data as $col => $value) {
        if ((string) ($before[$col] ?? '') !== (string) ($value ?? '')) {
            $changed = true;
            break;
        }
    }
    if (!$changed) {
        return ['ok' => true, 'changed' => false, 'reset' => false];
    }
    $reset = !in_array($before['Status'], MKT_ASSET_EDITABLE, true);
    $sets = array_map(static fn(string $c): string => "$c = :$c", array_keys($data));
    $sets[] = 'ContentVersion = ContentVersion + 1';
    $sets[] = 'UpdatedBy = :UpdatedBy';
    $sets[] = 'UpdatedAt = SYSUTCDATETIME()';
    if ($reset) {
        $sets[] = "Status = N'draft', ComplianceStatus = NULL, ComplianceBy = NULL, ComplianceAt = NULL, EditorialStatus = NULL, EditorialBy = NULL, EditorialAt = NULL, SubmittedBy = NULL, SubmittedAt = NULL";
    }
    db()->prepare('UPDATE dbo.MktAsset SET ' . implode(', ', $sets) . ' WHERE AssetID = :id')
        ->execute($data + ['UpdatedBy' => marketing_user_id(), 'id' => $id]);
    $built = audit_build_update('MktAsset', 'AssetID', $id, $data, array_intersect_key($before, $data));
    audit_log_change($built['change'], $built['reverse']);
    if ($reset) {
        mkt_asset_log($id, 'system', 'returned_to_draft', 'Edited while ' . (MKT_ASSET_STATUSES[$before['Status']] ?? $before['Status']) . ' — both reviews cleared.', $before);
    }

    return ['ok' => true, 'changed' => true, 'reset' => $reset];
}

/**
 * Submit for review: needs a current claims check at or above claims.min_score and the [LINK] placeholder.
 * Compliance review is required when the check found claims, efficacy/condition references or flag terms.
 */
function mkt_asset_submit(int $id): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null) {
        return ['ok' => false, 'error' => 'Asset not found.'];
    }
    if (!in_array($asset['Status'], MKT_ASSET_EDITABLE, true)) {
        return ['ok' => false, 'error' => 'Only drafts or assets with changes requested can be submitted.'];
    }
    if (!mkt_asset_check_current($asset)) {
        return ['ok' => false, 'error' => 'Run the claims check on the current version before submitting.'];
    }
    if (!mkt_asset_passes($asset)) {
        return ['ok' => false, 'error' => 'Claims score ' . $asset['ClaimsScore'] . ' is below the minimum of ' . marketing_setting('claims.min_score', '7') . ' — revise the asset first.'];
    }
    if (!str_contains((string) $asset['Body'], '[LINK]')) {
        return ['ok' => false, 'error' => 'Add the [LINK] placeholder where the tracked link belongs.'];
    }
    $needsCompliance = marketing_setting('review.compliance_mode', 'claims') === 'all' || !empty($asset['NeedsCompliance']);
    db()->prepare(<<<SQL
        UPDATE dbo.MktAsset
        SET Status = N'in_review', ComplianceStatus = :compliance, ComplianceBy = NULL, ComplianceAt = NULL,
            EditorialStatus = N'pending', EditorialBy = NULL, EditorialAt = NULL,
            SubmittedBy = :u, SubmittedAt = SYSUTCDATETIME()
        WHERE AssetID = :id
    SQL)->execute(['compliance' => $needsCompliance ? 'pending' : 'not_required', 'u' => marketing_user_id(), 'id' => $id]);
    mkt_asset_log($id, 'submit', 'submitted', $needsCompliance ? 'Compliance review required.' : 'No claims found — compliance review not required.', $asset);

    return ['ok' => true, 'compliance' => $needsCompliance];
}

/**
 * Record a gate decision. Compliance clears first; editorial approves after. Reviewers cannot act on an asset
 * they last edited or submitted, and requesting changes needs a note.
 */
function mkt_asset_review(int $id, string $gate, string $decision, ?string $note = null): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null) {
        return ['ok' => false, 'error' => 'Asset not found.'];
    }
    if (!in_array($gate, ['compliance', 'editorial'], true) || !in_array($decision, ['approved', 'changes_requested'], true)) {
        return ['ok' => false, 'error' => 'Unknown review action.'];
    }
    if ($asset['Status'] !== 'in_review') {
        return ['ok' => false, 'error' => 'This asset is not in review.'];
    }
    $userId = marketing_user_id();
    if ($userId !== null && in_array($userId, [(int) ($asset['UpdatedBy'] ?? 0), (int) ($asset['SubmittedBy'] ?? 0)], true)) {
        return ['ok' => false, 'error' => 'You edited or submitted this asset — another reviewer must review it.'];
    }
    if ($decision === 'changes_requested' && trim((string) $note) === '') {
        return ['ok' => false, 'error' => 'Say what needs to change.'];
    }
    if ($gate === 'compliance') {
        if (!mkt_can_compliance_review()) {
            return ['ok' => false, 'error' => 'Only named compliance reviewers can clear compliance.'];
        }
        if ($asset['ComplianceStatus'] !== 'pending') {
            return ['ok' => false, 'error' => 'Compliance review is not pending on this asset.'];
        }
    } else {
        if (!mkt_can_editorial_review()) {
            return ['ok' => false, 'error' => 'Editorial approval requires full Marketing access.'];
        }
        if ($asset['EditorialStatus'] !== 'pending') {
            return ['ok' => false, 'error' => 'Editorial review is not pending on this asset.'];
        }
        if ($decision === 'approved' && !in_array($asset['ComplianceStatus'], ['approved', 'not_required'], true)) {
            return ['ok' => false, 'error' => 'Compliance must clear this asset before editorial approval.'];
        }
    }

    $prefix = $gate === 'compliance' ? 'Compliance' : 'Editorial';
    $editorialDone = $gate === 'editorial' ? $decision === 'approved' : $asset['EditorialStatus'] === 'approved';
    $complianceDone = $gate === 'compliance' ? $decision === 'approved' : in_array($asset['ComplianceStatus'], ['approved', 'not_required'], true);
    $status = $decision === 'changes_requested' ? 'changes_requested' : ($editorialDone && $complianceDone ? 'approved' : 'in_review');
    db()->prepare("UPDATE dbo.MktAsset SET {$prefix}Status = :d, {$prefix}By = :u, {$prefix}At = SYSUTCDATETIME(), Status = :s WHERE AssetID = :id")
        ->execute(['d' => $decision, 'u' => $userId, 's' => $status, 'id' => $id]);
    mkt_asset_log($id, $gate, $decision, $note, $asset);

    return ['ok' => true, 'status' => $status];
}

function mkt_asset_archive(int $id): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null) {
        return ['ok' => false, 'error' => 'Asset not found.'];
    }
    if ($asset['Status'] === 'posted') {
        return ['ok' => false, 'error' => 'Posted assets stay on record.'];
    }
    db()->prepare("UPDATE dbo.MktAsset SET Status = N'archived' WHERE AssetID = :id")->execute(['id' => $id]);
    mkt_asset_log($id, 'system', 'archived', null, $asset);

    return ['ok' => true];
}

/**
 * Review queue: compliance-pending assets for named reviewers; compliance-cleared, editorial-pending assets for editors.
 */
function mkt_review_queue(): array
{
    return db()->query(<<<SQL
        SELECT a.AssetID, a.CampaignID, a.Channel, a.SequenceNo, a.Title, a.ClaimsScore, a.ComplianceStatus, a.EditorialStatus,
               a.SubmittedAt, a.SubmittedBy, a.UpdatedBy, c.Name AS CampaignName, u.UserName AS SubmittedByName
        FROM dbo.MktAsset a
        INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
        LEFT JOIN dbo.[User] u ON u.UserID = a.SubmittedBy
        WHERE a.Status = N'in_review'
        ORDER BY a.SubmittedAt
    SQL)->fetchAll();
}

function mkt_asset_status_counts(): array
{
    $counts = array_fill_keys(array_keys(MKT_ASSET_STATUSES), 0);
    foreach (db()->query('SELECT Status, COUNT(*) AS n FROM dbo.MktAsset GROUP BY Status')->fetchAll() as $row) {
        $counts[(string) $row['Status']] = (int) $row['n'];
    }
    $counts['compliance_pending'] = (int) db()->query("SELECT COUNT(*) FROM dbo.MktAsset WHERE Status = N'in_review' AND ComplianceStatus = N'pending'")->fetchColumn();
    $counts['editorial_ready'] = (int) db()->query("SELECT COUNT(*) FROM dbo.MktAsset WHERE Status = N'in_review' AND EditorialStatus = N'pending' AND ComplianceStatus IN (N'approved', N'not_required')")->fetchColumn();

    return $counts;
}

function mkt_asset_badge(array $asset): string
{
    $status = (string) $asset['Status'];
    $class = match ($status) {
        'approved', 'scheduled', 'posted' => 'active',
        'changes_requested'               => 'failed',
        'in_review'                       => 'running',
        default                           => 'draft',
    };

    return mkt_render_badge($class, [$class => MKT_ASSET_STATUSES[$status] ?? $status]);
}

function mkt_score_badge(array $asset): string
{
    if ($asset['ClaimsScore'] === null) {
        return '<span class="form-hint">Not checked</span>';
    }
    $current = mkt_asset_check_current($asset);
    $class = !$current ? 'draft' : (mkt_asset_passes($asset) ? 'active' : 'failed');
    $label = number_format((float) $asset['ClaimsScore'], 1) . ' / 10' . ($current ? '' : ' (stale)');

    return mkt_render_badge($class, [$class => $label]);
}
