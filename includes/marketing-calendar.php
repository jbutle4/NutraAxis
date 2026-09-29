<?php

require_once __DIR__ . '/marketing-campaigns.php';

const MKT_CAL_TIMEZONE = 'America/Chicago';
const MKT_CAL_SCHEDULABLE = ['approved', 'scheduled'];

/* ---------- Time helpers (stored UTC, entered and shown in Central time) ---------- */

function mkt_cal_tz(): DateTimeZone
{
    return new DateTimeZone(MKT_CAL_TIMEZONE);
}

/**
 * Central-time input ("Y-m-d H:i", "Y-m-d\TH:i" from datetime-local) to a UTC "Y-m-d H:i:s" string.
 */
function mkt_cal_local_to_utc(string $local): ?string
{
    $local = trim(str_replace('T', ' ', $local));
    foreach (['Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $local, mkt_cal_tz());
        if ($dt !== false && $dt->format($format) === $local) {
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function mkt_cal_local(?string $utc): ?DateTimeImmutable
{
    if ($utc === null || trim($utc) === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(mkt_cal_tz());
    } catch (Throwable) {
        return null;
    }
}

function mkt_cal_format(?string $utc, string $format = 'D M j, g:i A'): string
{
    $dt = mkt_cal_local($utc);

    return $dt !== null ? $dt->format($format) : '—';
}

/** Value for a datetime-local input. */
function mkt_cal_input_value(?string $utc): string
{
    return mkt_cal_format($utc, 'Y-m-d\TH:i') === '—' ? '' : mkt_cal_format($utc, 'Y-m-d\TH:i');
}

function mkt_cal_now_local(): DateTimeImmutable
{
    return new DateTimeImmutable('now', mkt_cal_tz());
}

/**
 * @return array<string, string> channel => "HH:MM" (Central)
 */
function mkt_cal_default_times(): array
{
    $times = [];
    foreach (marketing_setting_lines('calendar.default_times') as $line) {
        [$channel, $time] = array_pad(array_map('trim', explode('|', $line)), 2, '');
        if ($channel !== '' && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time)) {
            $times[$channel] = str_pad($time, 5, '0', STR_PAD_LEFT);
        }
    }

    return $times;
}

/* ---------- Queries ---------- */

function mkt_cal_select(): string
{
    return <<<SQL
        SELECT a.AssetID, a.CampaignID, a.Channel, a.SequenceNo, a.Title, a.Subject, a.PreviewText, a.Body, a.Hashtags,
               a.Status, a.ScheduledAt, a.ScheduledBy, a.ExternalPostID, a.ExternalPostUrl, a.LoadedAt, a.LoadedBy,
               a.PostedAt, a.PostedBy, a.ClaimsScore, a.EditorialAt, a.UpdatedAt,
               c.Name AS CampaignName, c.Slug, c.CtaUrl, c.PartCount, c.CadenceDays, c.Format
        FROM dbo.MktAsset a
        INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
    SQL;
}

/**
 * Scheduled and posted assets whose calendar time (posted time once posted) falls in [from, to) UTC.
 */
function mkt_cal_assets_between(string $fromUtc, string $toUtc): array
{
    $stmt = db()->prepare(mkt_cal_select() . <<<SQL
        WHERE a.Status IN (N'scheduled', N'posted')
          AND COALESCE(a.PostedAt, a.ScheduledAt) >= :from AND COALESCE(a.PostedAt, a.ScheduledAt) < :to
        ORDER BY COALESCE(a.PostedAt, a.ScheduledAt), a.Channel
    SQL);
    $stmt->execute(['from' => $fromUtc, 'to' => $toUtc]);

    return $stmt->fetchAll();
}

function mkt_cal_ready(?int $campaignId = null): array
{
    $sql = mkt_cal_select() . " WHERE a.Status = N'approved'" . ($campaignId ? ' AND a.CampaignID = :c' : '') . ' ORDER BY c.Name, a.CampaignID, a.SequenceNo, a.Channel';
    $stmt = db()->prepare($sql);
    $stmt->execute($campaignId ? ['c' => $campaignId] : []);

    return $stmt->fetchAll();
}

/** Scheduled but not yet loaded into GoHighLevel. */
function mkt_cal_needs_loading(): array
{
    return db()->query(mkt_cal_select() . " WHERE a.Status = N'scheduled' AND a.ExternalPostID IS NULL ORDER BY a.ScheduledAt")->fetchAll();
}

/** Scheduled time has passed but nobody marked the asset posted. */
function mkt_cal_past_due(): array
{
    return db()->query(mkt_cal_select() . " WHERE a.Status = N'scheduled' AND a.ScheduledAt < SYSUTCDATETIME() ORDER BY a.ScheduledAt")->fetchAll();
}

function mkt_cal_posted(int $limit = 100): array
{
    return db()->query(mkt_cal_select() . " WHERE a.Status = N'posted' ORDER BY a.PostedAt DESC OFFSET 0 ROWS FETCH NEXT " . max(1, $limit) . ' ROWS ONLY')->fetchAll();
}

function mkt_cal_counts(): array
{
    $row = db()->query(<<<SQL
        SELECT
            SUM(CASE WHEN Status = N'approved' THEN 1 ELSE 0 END) AS Ready,
            SUM(CASE WHEN Status = N'scheduled' AND ScheduledAt >= SYSUTCDATETIME() AND ScheduledAt < DATEADD(day, 7, SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS Next7,
            SUM(CASE WHEN Status = N'scheduled' AND ExternalPostID IS NULL THEN 1 ELSE 0 END) AS NeedsLoading,
            SUM(CASE WHEN Status = N'scheduled' AND ScheduledAt < SYSUTCDATETIME() THEN 1 ELSE 0 END) AS PastDue,
            SUM(CASE WHEN Status = N'scheduled' AND (ExternalPostID IS NULL OR ScheduledAt < SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS Todo,
            SUM(CASE WHEN Status = N'posted' AND PostedAt >= DATEADD(day, -30, SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS Posted30
        FROM dbo.MktAsset
    SQL)->fetch();

    return array_map('intval', $row ?: []);
}

/**
 * Warnings per asset: same channel closer than calendar.min_gap_hours, and series parts out of order on a channel.
 *
 * @return array<int, list<string>>
 */
function mkt_cal_conflicts(): array
{
    $gapHours = max(0.0, (float) marketing_setting('calendar.min_gap_hours', '20'));
    $rows = db()->query(<<<SQL
        SELECT AssetID, CampaignID, Channel, SequenceNo, ScheduledAt
        FROM dbo.MktAsset
        WHERE Status = N'scheduled' AND ScheduledAt IS NOT NULL
        ORDER BY Channel, ScheduledAt
    SQL)->fetchAll();
    $warnings = [];
    $previous = [];
    foreach ($rows as $row) {
        $channel = (string) $row['Channel'];
        $at = strtotime((string) $row['ScheduledAt'] . ' UTC');
        if ($gapHours > 0 && isset($previous[$channel]) && ($at - $previous[$channel]['at']) < $gapHours * 3600) {
            $hours = round(($at - $previous[$channel]['at']) / 3600, 1);
            $warnings[(int) $row['AssetID']][] = 'Only ' . $hours . ' h after asset #' . $previous[$channel]['id'] . ' on the same channel.';
        }
        $previous[$channel] = ['at' => $at, 'id' => (int) $row['AssetID']];
    }
    $bySeries = [];
    foreach ($rows as $row) {
        $bySeries[$row['CampaignID'] . '|' . $row['Channel']][] = $row;
    }
    foreach ($bySeries as $parts) {
        usort($parts, static fn(array $a, array $b): int => (int) $a['SequenceNo'] <=> (int) $b['SequenceNo']);
        for ($i = 1; $i < count($parts); $i++) {
            if (strtotime((string) $parts[$i]['ScheduledAt']) <= strtotime((string) $parts[$i - 1]['ScheduledAt'])) {
                $warnings[(int) $parts[$i]['AssetID']][] = 'Part ' . (int) $parts[$i]['SequenceNo'] . ' is scheduled before part ' . (int) $parts[$i - 1]['SequenceNo'] . '.';
            }
        }
    }

    return $warnings;
}

/* ---------- Actions ---------- */

function mkt_cal_log(int $assetId, string $decision, ?string $note = null): void
{
    mkt_asset_log($assetId, 'system', $decision, $note, mkt_asset_get($assetId));
}

/**
 * Put an approved asset on the calendar, or move a scheduled one. Returns reload_ghl = true when the asset was
 * already loaded into GoHighLevel, so the coordinator knows to move it there too.
 */
function mkt_asset_schedule(int $id, string $localDatetime): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null) {
        return ['ok' => false, 'error' => 'Asset not found.'];
    }
    if (!in_array($asset['Status'], MKT_CAL_SCHEDULABLE, true)) {
        return ['ok' => false, 'error' => 'Only approved assets can be scheduled — this one is ' . strtolower(MKT_ASSET_STATUSES[$asset['Status']] ?? $asset['Status']) . '.'];
    }
    $utc = mkt_cal_local_to_utc($localDatetime);
    if ($utc === null) {
        return ['ok' => false, 'error' => 'Enter a date and time.'];
    }
    $moved = $asset['Status'] === 'scheduled';
    db()->prepare("UPDATE dbo.MktAsset SET Status = N'scheduled', ScheduledAt = :at, ScheduledBy = :u WHERE AssetID = :id")
        ->execute(['at' => $utc, 'u' => marketing_user_id(), 'id' => $id]);
    $reload = $moved && !empty($asset['ExternalPostID']);
    mkt_cal_log($id, $moved ? 'rescheduled' : 'scheduled', mkt_cal_format($utc, 'M j, Y g:i A T') . ($reload ? ' — already in GoHighLevel as ' . $asset['ExternalPostID'] . '; move it there too.' : ''));

    return ['ok' => true, 'reload_ghl' => $reload, 'past' => strtotime($utc . ' UTC') < time()];
}

/**
 * Take an asset off the calendar (back to approved). A GoHighLevel ID is cleared and noted so it can be deleted there.
 */
function mkt_asset_unschedule(int $id): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null || $asset['Status'] !== 'scheduled') {
        return ['ok' => false, 'error' => 'Only scheduled assets can be unscheduled.'];
    }
    db()->prepare("UPDATE dbo.MktAsset SET Status = N'approved', ScheduledAt = NULL, ScheduledBy = NULL, ExternalPostID = NULL, LoadedAt = NULL, LoadedBy = NULL WHERE AssetID = :id")
        ->execute(['id' => $id]);
    $ghl = (string) ($asset['ExternalPostID'] ?? '');
    mkt_cal_log($id, 'unscheduled', $ghl !== '' ? 'Was loaded in GoHighLevel as ' . $ghl . ' — delete it there.' : null);

    return ['ok' => true, 'ghl_id' => $ghl];
}

function mkt_asset_record_loaded(int $id, string $externalId): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null || $asset['Status'] !== 'scheduled') {
        return ['ok' => false, 'error' => 'Schedule the asset before recording its GoHighLevel ID.'];
    }
    $externalId = trim($externalId);
    if ($externalId === '') {
        return ['ok' => false, 'error' => 'Enter the GoHighLevel post or campaign ID.'];
    }
    db()->prepare('UPDATE dbo.MktAsset SET ExternalPostID = :ext, LoadedAt = SYSUTCDATETIME(), LoadedBy = :u WHERE AssetID = :id')
        ->execute(['ext' => mb_substr($externalId, 0, 200), 'u' => marketing_user_id(), 'id' => $id]);
    mkt_cal_log($id, 'loaded', 'GoHighLevel ID ' . $externalId);

    return ['ok' => true];
}

function mkt_cal_valid_url(string $url): bool
{
    return $url === '' || (preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false);
}

/**
 * Mark a scheduled asset posted. Posted time defaults to the scheduled time; the live URL is optional (email has none).
 */
function mkt_asset_mark_posted(int $id, string $postedLocal = '', string $url = '', string $externalId = ''): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null || $asset['Status'] !== 'scheduled') {
        return ['ok' => false, 'error' => 'Only scheduled assets can be marked posted.'];
    }
    $url = trim($url);
    if (!mkt_cal_valid_url($url)) {
        return ['ok' => false, 'error' => 'The post URL must start with http:// or https://.'];
    }
    $postedUtc = trim($postedLocal) !== '' ? mkt_cal_local_to_utc($postedLocal) : (string) $asset['ScheduledAt'];
    if ($postedUtc === null) {
        return ['ok' => false, 'error' => 'Enter a valid posted date and time.'];
    }
    if (strtotime($postedUtc . ' UTC') > time() + 300) {
        return ['ok' => false, 'error' => 'The posted time is in the future — mark it posted once it is live.'];
    }
    $externalId = trim($externalId) !== '' ? mb_substr(trim($externalId), 0, 200) : ($asset['ExternalPostID'] ?? null);
    db()->prepare(<<<SQL
        UPDATE dbo.MktAsset
        SET Status = N'posted', PostedAt = :at, PostedBy = :u, ExternalPostUrl = :url, ExternalPostID = :ext
        WHERE AssetID = :id
    SQL)->execute(['at' => $postedUtc, 'u' => marketing_user_id(), 'url' => $url !== '' ? mb_substr($url, 0, 1000) : null, 'ext' => $externalId, 'id' => $id]);
    mkt_cal_log($id, 'posted', $url !== '' ? $url : null);

    return ['ok' => true];
}

/** Correct the live URL or GoHighLevel ID on a posted asset. */
function mkt_asset_update_posted(int $id, string $url, string $externalId): array
{
    $asset = mkt_asset_get($id);
    if ($asset === null || $asset['Status'] !== 'posted') {
        return ['ok' => false, 'error' => 'Only posted assets can be updated here.'];
    }
    $url = trim($url);
    if (!mkt_cal_valid_url($url)) {
        return ['ok' => false, 'error' => 'The post URL must start with http:// or https://.'];
    }
    $data = ['ExternalPostUrl' => $url !== '' ? mb_substr($url, 0, 1000) : null, 'ExternalPostID' => trim($externalId) !== '' ? mb_substr(trim($externalId), 0, 200) : null];
    db()->prepare('UPDATE dbo.MktAsset SET ExternalPostUrl = :ExternalPostUrl, ExternalPostID = :ExternalPostID WHERE AssetID = :id')->execute($data + ['id' => $id]);
    $built = audit_build_update('MktAsset', 'AssetID', $id, $data, array_intersect_key($asset, $data));
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/**
 * Lay out every approved, unscheduled asset of a campaign: part N goes on start date + (N − 1) × cadence days,
 * at the channel's default time unless a time is given.
 */
function mkt_campaign_schedule(int $campaignId, string $startDate, string $time = ''): array
{
    $campaign = mkt_campaign_get($campaignId);
    if ($campaign === null) {
        return ['ok' => false, 'error' => 'Campaign not found.'];
    }
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', trim($startDate), mkt_cal_tz());
    if ($start === false || $start->format('Y-m-d') !== trim($startDate)) {
        return ['ok' => false, 'error' => 'Choose a start date.'];
    }
    $time = trim($time);
    if ($time !== '' && !preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time)) {
        return ['ok' => false, 'error' => 'Enter the time as HH:MM.'];
    }
    $time = $time !== '' ? str_pad($time, 5, '0', STR_PAD_LEFT) : '';
    $defaults = mkt_cal_default_times();
    $cadence = max(1, (int) ($campaign['CadenceDays'] ?? 1));
    $scheduled = 0;
    $notReady = 0;
    foreach (mkt_campaign_assets($campaignId) as $asset) {
        if ($asset['Status'] !== 'approved') {
            $notReady += in_array($asset['Status'], ['draft', 'in_review', 'changes_requested'], true) ? 1 : 0;
            continue;
        }
        $day = $start->modify('+' . (((int) $asset['SequenceNo'] - 1) * $cadence) . ' days');
        $at = $day->format('Y-m-d') . ' ' . ($time !== '' ? $time : ($defaults[(string) $asset['Channel']] ?? '09:00'));
        if (mkt_asset_schedule((int) $asset['AssetID'], $at)['ok']) {
            $scheduled++;
        }
    }
    if ($scheduled === 0) {
        return ['ok' => false, 'error' => 'No approved, unscheduled assets in this campaign.'];
    }

    return ['ok' => true, 'scheduled' => $scheduled, 'not_ready' => $notReady];
}

/* ---------- Export ---------- */

/**
 * CSV rows for loading into GoHighLevel by hand: one row per scheduled asset in the range, text with the tracked link.
 *
 * @return list<array<string, string>>
 */
function mkt_cal_export_rows(string $fromUtc, string $toUtc): array
{
    $channels = mkt_channels();
    $rows = [];
    foreach (mkt_cal_assets_between($fromUtc, $toUtc) as $asset) {
        if ($asset['Status'] !== 'scheduled') {
            continue;
        }
        $campaign = ['Slug' => $asset['Slug'], 'CtaUrl' => $asset['CtaUrl']];
        $rows[] = [
            'asset_id'       => (string) $asset['AssetID'],
            'date'           => mkt_cal_format($asset['ScheduledAt'], 'Y-m-d'),
            'time_central'   => mkt_cal_format($asset['ScheduledAt'], 'H:i'),
            'channel'        => $channels[(string) $asset['Channel']]['label'] ?? (string) $asset['Channel'],
            'campaign'       => (string) $asset['CampaignName'],
            'part'           => (int) $asset['SequenceNo'] . ' of ' . (int) $asset['PartCount'],
            'subject'        => (string) ($asset['Subject'] ?? ''),
            'preview_text'   => (string) ($asset['PreviewText'] ?? ''),
            'text'           => mkt_asset_copy_text($asset, $campaign),
            'tracked_link'   => mkt_asset_tracked_url($asset, $campaign),
            'ghl_id'         => (string) ($asset['ExternalPostID'] ?? ''),
        ];
    }

    return $rows;
}
