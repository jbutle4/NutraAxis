<?php
require_once __DIR__ . '/marketing-ranks.php';

/**
 * Backlinks & Outreach: our link profile and the competitor link gap from imported OpenRush / Semrush data, a
 * disavow list, and outreach prospects. Pitches are drafted by AI (outreach-draft-pitch job) and sent by a person
 * from their own mailbox; contact details never leave the portal.
 */
const MKT_PROSPECT_STATUSES = [
    'to_contact' => 'To contact',
    'contacted'  => 'Contacted',
    'replied'    => 'Replied',
    'won'        => 'Link won',
    'declined'   => 'Declined',
];

const MKT_PROSPECT_ORIGINS = ['gap' => 'Link gap', 'lost' => 'Lost link', 'manual' => 'Added by hand'];

const MKT_PROSPECT_EVENTS = [
    'created'  => 'Added',
    'drafted'  => 'AI draft',
    'edited'   => 'Pitch edited',
    'sent'     => 'Sent',
    'replied'  => 'Replied',
    'won'      => 'Link won',
    'declined' => 'Declined',
    'followup' => 'Follow-up set',
    'reopened' => 'Reopened',
    'note'     => 'Note',
];

const MKT_LINK_FILTERS = ['active' => 'Live', 'new' => 'New (28 days)', 'lost' => 'Lost', 'spam' => 'Likely spam', 'all' => 'All'];

function mkt_bl_spam_threshold(): int
{
    return max(1, min(100, (int) marketing_setting('backlinks.spam_threshold', '30')));
}

function mkt_bl_domain_valid(string $domain): bool
{
    return (bool) preg_match('/^(?=.{3,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain);
}

/* ---------- Summary and lists ---------- */

function mkt_bl_summary(): array
{
    $spam = mkt_bl_spam_threshold();
    $stmt = db()->prepare(<<<SQL
        SELECT
            (SELECT COUNT(DISTINCT SourceDomain) FROM dbo.MktBacklink WHERE Status = N'active') AS Domains,
            (SELECT COUNT(*) FROM dbo.MktBacklink WHERE Status = N'active') AS Links,
            (SELECT COUNT(*) FROM dbo.MktBacklink WHERE Status = N'active' AND Followed = 1) AS Followed,
            (SELECT COUNT(*) FROM dbo.MktBacklink WHERE FirstSeen >= DATEADD(DAY, -28, CAST(SYSUTCDATETIME() AS DATE))) AS New28,
            (SELECT COUNT(*) FROM dbo.MktBacklink WHERE Status = N'lost' AND LostAt >= DATEADD(DAY, -28, CAST(SYSUTCDATETIME() AS DATE))) AS Lost28,
            (SELECT COUNT(DISTINCT b.SourceDomain) FROM dbo.MktBacklink b
                WHERE b.Status = N'active' AND b.SpamScore >= :spam
                  AND NOT EXISTS (SELECT 1 FROM dbo.MktBacklinkDisavow d WHERE d.Domain = b.SourceDomain)) AS SpamDomains,
            (SELECT COUNT(*) FROM dbo.MktBacklinkDisavow) AS Disavowed,
            (SELECT COUNT(*) FROM dbo.MktBacklinkGap g WHERE g.Earned = 0 AND g.Dismissed = 0
                AND NOT EXISTS (SELECT 1 FROM dbo.MktProspect p WHERE p.Domain = g.Domain)) AS GapOpen,
            (SELECT CONVERT(varchar(19), MAX(CreatedAt), 120) FROM dbo.MktBacklinkImport WHERE Kind = N'links') AS LastLinks,
            (SELECT CONVERT(varchar(19), MAX(CreatedAt), 120) FROM dbo.MktBacklinkImport WHERE Kind = N'gap') AS LastGap
    SQL);
    $stmt->execute(['spam' => $spam]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $row['Prospects'] = array_fill_keys(array_keys(MKT_PROSPECT_STATUSES), 0);
    foreach (db()->query('SELECT Status, COUNT(*) AS N FROM dbo.MktProspect GROUP BY Status')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $row['Prospects'][(string) $r['Status']] = (int) $r['N'];
    }
    $row['SpamThreshold'] = $spam;

    return $row;
}

function mkt_bl_links(array $filters): array
{
    $where = [];
    $params = [];
    switch ($filters['status'] ?? 'active') {
        case 'active':
            $where[] = "b.Status = N'active'";
            break;
        case 'new':
            $where[] = 'b.FirstSeen >= DATEADD(DAY, -28, CAST(SYSUTCDATETIME() AS DATE))';
            break;
        case 'lost':
            $where[] = "b.Status = N'lost'";
            break;
        case 'spam':
            $where[] = "b.Status = N'active' AND b.SpamScore >= :spam";
            $params['spam'] = mkt_bl_spam_threshold();
            break;
    }
    if (trim((string) ($filters['q'] ?? '')) !== '') {
        [$like, $likeParams] = db_like_or(['b.SourceDomain', 'b.SourceUrl', 'b.Anchor', 'b.TargetUrl'], (string) $filters['q']);
        $where[] = $like;
        $params += $likeParams;
    }
    $sql = <<<SQL
        SELECT TOP (500) b.*, CONVERT(varchar(10), b.FirstSeen, 23) AS FirstSeenIso, CONVERT(varchar(10), b.LastSeen, 23) AS LastSeenIso,
               CONVERT(varchar(10), b.LostAt, 23) AS LostAtIso,
               CASE WHEN d.Domain IS NULL THEN 0 ELSE 1 END AS Disavowed, p.ProspectID
        FROM dbo.MktBacklink b
        LEFT JOIN dbo.MktBacklinkDisavow d ON d.Domain = b.SourceDomain
        LEFT JOIN dbo.MktProspect p ON p.Domain = b.SourceDomain
    SQL;
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(b.LostAt, b.FirstSeen) DESC, b.SourceDomain';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_bl_gap(array $filters): array
{
    $show = (string) ($filters['show'] ?? 'open');
    $where = match ($show) {
        'dismissed' => 'g.Dismissed = 1',
        'earned'    => 'g.Earned = 1',
        'all'       => '1 = 1',
        default     => 'g.Earned = 0 AND g.Dismissed = 0',
    };
    $params = [];
    if ((int) ($filters['min_authority'] ?? 0) > 0) {
        $where .= ' AND g.Authority >= :a';
        $params['a'] = (int) $filters['min_authority'];
    }
    if (trim((string) ($filters['q'] ?? '')) !== '') {
        [$like, $likeParams] = db_like_or(['g.Domain', 'g.Competitors'], (string) $filters['q']);
        $where .= ' AND ' . $like;
        $params += $likeParams;
    }
    $stmt = db()->prepare(<<<SQL
        SELECT TOP (500) g.*, CONVERT(varchar(10), g.FirstSeen, 23) AS FirstSeenIso, p.ProspectID, p.Status AS ProspectStatus
        FROM dbo.MktBacklinkGap g
        LEFT JOIN dbo.MktProspect p ON p.Domain = g.Domain
        WHERE {$where}
        ORDER BY g.CompetitorCount DESC, g.Authority DESC, g.Domain
    SQL);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_bl_gap_dismiss(string $domain, bool $dismiss): bool
{
    $stmt = db()->prepare('UPDATE dbo.MktBacklinkGap SET Dismissed = :d WHERE Domain = :dom');
    $stmt->execute(['d' => $dismiss ? 1 : 0, 'dom' => mkt_domain_of($domain)]);

    return $stmt->rowCount() > 0;
}

function mkt_bl_imports(int $limit = 20): array
{
    $limit = max(1, min(100, $limit));

    return db()->query(<<<SQL
        SELECT TOP ({$limit}) i.*, u.UserName AS CreatedByName
        FROM dbo.MktBacklinkImport i LEFT JOIN dbo.[User] u ON u.UserID = i.CreatedBy
        ORDER BY i.ImportID DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- Disavow ---------- */

function mkt_bl_disavow_list(): array
{
    return db()->query(<<<SQL
        SELECT d.*, u.UserName AS CreatedByName,
               (SELECT COUNT(*) FROM dbo.MktBacklink b WHERE b.SourceDomain = d.Domain AND b.Status = N'active') AS LiveLinks,
               (SELECT MAX(b.SpamScore) FROM dbo.MktBacklink b WHERE b.SourceDomain = d.Domain) AS SpamScore
        FROM dbo.MktBacklinkDisavow d LEFT JOIN dbo.[User] u ON u.UserID = d.CreatedBy
        ORDER BY d.Domain
    SQL)->fetchAll(PDO::FETCH_ASSOC);
}

/** Add domains (list of strings) to the disavow list. Returns number added. */
function mkt_bl_disavow_add(array $domains, string $reason): int
{
    $added = 0;
    $stmt = db()->prepare(<<<SQL
        IF NOT EXISTS (SELECT 1 FROM dbo.MktBacklinkDisavow WHERE Domain = :d1)
            INSERT INTO dbo.MktBacklinkDisavow (Domain, Reason, CreatedBy) VALUES (:d2, :r, :u)
    SQL);
    foreach (array_unique(array_map('mkt_domain_of', $domains)) as $domain) {
        if (!mkt_bl_domain_valid($domain) || mkt_is_our_domain($domain)) {
            continue;
        }
        $stmt->execute(['d1' => $domain, 'd2' => $domain, 'r' => mb_substr(trim($reason), 0, 300) ?: null, 'u' => marketing_user_id()]);
        $added += $stmt->rowCount() > 0 ? 1 : 0;
    }

    return $added;
}

/** Domains at or above the spam threshold with live links and not yet on the list. */
function mkt_bl_disavow_add_spam(): int
{
    $stmt = db()->prepare(<<<SQL
        SELECT DISTINCT b.SourceDomain FROM dbo.MktBacklink b
        WHERE b.Status = N'active' AND b.SpamScore >= :s
          AND NOT EXISTS (SELECT 1 FROM dbo.MktBacklinkDisavow d WHERE d.Domain = b.SourceDomain)
    SQL);
    $stmt->execute(['s' => mkt_bl_spam_threshold()]);

    return mkt_bl_disavow_add($stmt->fetchAll(PDO::FETCH_COLUMN), 'Spam score at or above ' . mkt_bl_spam_threshold());
}

function mkt_bl_disavow_remove(string $domain): bool
{
    $stmt = db()->prepare('DELETE FROM dbo.MktBacklinkDisavow WHERE Domain = :d');
    $stmt->execute(['d' => mkt_domain_of($domain)]);

    return $stmt->rowCount() > 0;
}

/** Google disavow file: one "domain:example.com" line per domain. */
function mkt_bl_disavow_export(): never
{
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="disavow-' . mkt_our_host() . '-' . mkt_local_date() . '.txt"');
    echo '# Disavow file for ' . mkt_our_host() . ' exported ' . mkt_local_date() . " from NutraAxis Operations\n";
    foreach (mkt_bl_disavow_list() as $row) {
        if (!empty($row['Reason'])) {
            echo '# ' . str_replace(["\r", "\n"], ' ', (string) $row['Reason']) . "\n";
        }
        echo 'domain:' . $row['Domain'] . "\n";
    }
    exit;
}

/* ---------- Imports ---------- */

/**
 * Import backlinks or the link gap. OpenRush JSON (inspect_backlinks with the backlinks view, or
 * compare_backlink_gap) or a Semrush CSV (Backlinks export, or Backlink Gap export). Payloads for any domain
 * other than ours are refused. $fullList marks live links missing from a backlinks import as lost.
 */
function mkt_bl_import(string $text, bool $fullList): array
{
    $payloads = mkt_openrush_payloads($text);
    if ($payloads !== null) {
        $data = $payloads[0] ?? [];
        $domain = mkt_domain_of((string) ($data['domain'] ?? ''));
        if ($domain !== '' && !mkt_is_our_domain($domain)) {
            return ['ok' => false, 'error' => sprintf('This payload is for %s. Only data for %s can be imported.', $domain, mkt_our_host())];
        }
        if (is_array($data['gap'] ?? null)) {
            $rows = [];
            foreach ($data['gap'] as $g) {
                if (!is_array($g)) {
                    continue;
                }
                $rows[] = [
                    'domain'      => (string) ($g['domain'] ?? ''),
                    'authority'   => isset($g['domain_rank']) ? (int) round(((float) $g['domain_rank']) / 10) : null,
                    'spam'        => isset($g['spam_score']) ? (int) $g['spam_score'] : null,
                    'count'       => (int) ($g['competitor_count'] ?? 0),
                    'competitors' => null,
                    'earned'      => !empty($g['earned']),
                ];
            }

            return mkt_bl_import_gap($rows, 'openrush');
        }
        if (array_key_exists('backlinks', $data)) {
            if (!is_array($data['backlinks']) || $data['backlinks'] === []) {
                return ['ok' => false, 'error' => 'This payload has no backlinks. In OpenRush, include the "backlinks" view.'];
            }
            $rows = [];
            foreach ($data['backlinks'] as $b) {
                if (!is_array($b)) {
                    continue;
                }
                $rows[] = [
                    'source'    => (string) ($b['url_from'] ?? ''),
                    'domain'    => (string) ($b['domain_from'] ?? ''),
                    'target'    => (string) ($b['url_to'] ?? ''),
                    'anchor'    => (string) ($b['anchor'] ?? ''),
                    'followed'  => isset($b['dofollow']) ? (bool) $b['dofollow'] : null,
                    'authority' => null,
                    'spam'      => isset($b['spam_score']) ? (int) $b['spam_score'] : null,
                    'first'     => mkt_iso_date($b['first_seen'] ?? null),
                    'last'      => mkt_iso_date($b['last_seen'] ?? null),
                    'lost'      => !empty($b['is_lost']) || !empty($b['lost_date']),
                    'lostAt'    => mkt_iso_date($b['lost_date'] ?? null),
                ];
            }

            return mkt_bl_import_links($rows, 'openrush', $fullList && ($data['status'] ?? 'live') === 'live');
        }

        return ['ok' => false, 'error' => 'This JSON is not an OpenRush backlinks or link gap payload.'];
    }

    $records = mkt_csv_records($text);
    $headers = array_keys($records[0] ?? []);
    if (in_array('source url', $headers, true) && in_array('target url', $headers, true)) {
        $rows = [];
        foreach ($records as $r) {
            $nofollow = strtolower(mkt_csv_pick($r, ['nofollow']));
            $lost = strtolower(mkt_csv_pick($r, ['lost link', 'lost']));
            $rows[] = [
                'source'    => mkt_csv_pick($r, ['source url']),
                'domain'    => '',
                'target'    => mkt_csv_pick($r, ['target url']),
                'anchor'    => mkt_csv_pick($r, ['anchor']),
                'followed'  => $nofollow === '' ? null : !in_array($nofollow, ['true', '1', 'yes'], true),
                'authority' => is_numeric($a = mkt_csv_pick($r, ['page ascore', 'authority score', 'ascore'])) ? (int) $a : null,
                'spam'      => null,
                'first'     => mkt_iso_date(mkt_csv_pick($r, ['first seen'])),
                'last'      => mkt_iso_date(mkt_csv_pick($r, ['last seen'])),
                'lost'      => in_array($lost, ['true', '1', 'yes'], true),
                'lostAt'    => null,
            ];
        }

        return mkt_bl_import_links($rows, 'semrush', $fullList);
    }
    if (in_array('domain', $headers, true) && array_intersect($headers, ['authority score', 'ascore']) !== []) {
        $competitors = mkt_competitor_domains();
        $compColumns = [];
        $ourColumn = null;
        foreach ($headers as $h) {
            $d = mkt_domain_of($h);
            if ($d !== '' && str_contains($d, '.')) {
                if (mkt_is_our_domain($d)) {
                    $ourColumn = $h;
                } elseif (($name = mkt_competitor_for($d, $competitors)) !== null) {
                    $compColumns[$h] = $name;
                }
            }
        }
        if ($compColumns === []) {
            return ['ok' => false, 'error' => 'No column in this Backlink Gap export matches a competitor domain in Settings (seo.competitor_domains).'];
        }
        $rows = [];
        foreach ($records as $r) {
            $names = [];
            foreach ($compColumns as $col => $name) {
                if ((int) preg_replace('/\D/', '', (string) ($r[$col] ?? '')) > 0) {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            $rows[] = [
                'domain'      => mkt_csv_pick($r, ['domain']),
                'authority'   => is_numeric($a = mkt_csv_pick($r, ['authority score', 'ascore'])) ? (int) $a : null,
                'spam'        => null,
                'count'       => count($names),
                'competitors' => $names === [] ? null : implode(', ', $names),
                'earned'      => $ourColumn !== null && (int) preg_replace('/\D/', '', (string) ($r[$ourColumn] ?? '')) > 0,
            ];
        }

        return mkt_bl_import_gap($rows, 'semrush');
    }

    return ['ok' => false, 'error' => 'Not recognised. Paste an OpenRush backlinks or link gap result, or upload a Semrush Backlinks or Backlink Gap CSV export.'];
}

function mkt_bl_import_links(array $rows, string $source, bool $markMissingLost): array
{
    $pdo = db();
    $today = mkt_local_date();
    $skipped = 0;
    $clean = [];
    foreach ($rows as $r) {
        $sourceUrl = trim($r['source']);
        $target = trim($r['target']);
        $domain = mkt_domain_of($r['domain'] !== '' ? $r['domain'] : $sourceUrl);
        if ($sourceUrl === '' || $domain === '' || !mkt_is_our_domain(mkt_domain_of($target))) {
            $skipped++;
            continue;
        }
        $hash = hash('sha256', mb_strtolower($sourceUrl) . '|' . mb_strtolower($target));
        $clean[$hash] = $r + ['hash' => $hash, 'sourceUrl' => mb_substr($sourceUrl, 0, 1000), 'targetUrl' => mb_substr($target, 0, 1000), 'sourceDomain' => mb_substr($domain, 0, 253)];
    }
    if ($clean === []) {
        return ['ok' => false, 'error' => sprintf('No links to %s found in the import (%d rows skipped).', mkt_our_host(), $skipped)];
    }

    $new = 0;
    $lost = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO dbo.MktBacklinkImport (Source, Kind, RowsRead, Skipped, CreatedBy) OUTPUT INSERTED.ImportID AS inserted_id VALUES (:s, N'links', :r, :sk, :u)");
        $stmt->execute(['s' => $source, 'r' => count($rows), 'sk' => $skipped, 'u' => marketing_user_id()]);
        $importId = db_fetch_inserted_int($stmt, 'inserted_id');
        $find = $pdo->prepare('SELECT BacklinkID, Status FROM dbo.MktBacklink WHERE LinkHash = CONVERT(BINARY(32), :h, 2)');
        $insert = $pdo->prepare(<<<SQL
            INSERT INTO dbo.MktBacklink (LinkHash, SourceUrl, SourceDomain, TargetUrl, Anchor, Followed, Authority, SpamScore, FirstSeen, LastSeen, LostAt, Status, LastImportID)
            VALUES (CONVERT(BINARY(32), :h, 2), :su, :sd, :tu, :a, :f, :au, :sp, :fs, :ls, :la, :st, :i)
        SQL);
        $update = $pdo->prepare(<<<SQL
            UPDATE dbo.MktBacklink SET Anchor = :a, Followed = :f, Authority = COALESCE(:au, Authority), SpamScore = COALESCE(:sp, SpamScore),
                FirstSeen = COALESCE(FirstSeen, :fs), LastSeen = COALESCE(:ls, LastSeen), LostAt = :la, Status = :st, LastImportID = :i, UpdatedAt = SYSUTCDATETIME()
            WHERE BacklinkID = :id
        SQL);
        foreach ($clean as $r) {
            $status = $r['lost'] ? 'lost' : 'active';
            $lostAt = $r['lost'] ? ($r['lostAt'] ?? $today) : null;
            $values = [
                'a' => $r['anchor'] !== '' ? mb_substr($r['anchor'], 0, 500) : null, 'f' => $r['followed'] === null ? null : ($r['followed'] ? 1 : 0),
                'au' => $r['authority'], 'sp' => $r['spam'], 'fs' => $r['first'] ?? $today, 'ls' => $r['last'], 'la' => $lostAt, 'st' => $status, 'i' => $importId,
            ];
            $find->execute(['h' => $r['hash']]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ($status === 'lost' && $existing['Status'] === 'active') {
                    $lost++;
                }
                $update->execute($values + ['id' => (int) $existing['BacklinkID']]);
            } else {
                $new += $status === 'active' ? 1 : 0;
                $lost += $status === 'lost' ? 1 : 0;
                $insert->execute($values + ['h' => $r['hash'], 'su' => $r['sourceUrl'], 'sd' => $r['sourceDomain'], 'tu' => $r['targetUrl']]);
            }
        }
        if ($markMissingLost) {
            $miss = $pdo->prepare("UPDATE dbo.MktBacklink SET Status = N'lost', LostAt = :d, UpdatedAt = SYSUTCDATETIME() WHERE Status = N'active' AND (LastImportID IS NULL OR LastImportID <> :i)");
            $miss->execute(['d' => $today, 'i' => $importId]);
            $lost += $miss->rowCount();
        }
        $pdo->exec("UPDATE dbo.MktBacklinkGap SET Earned = 1 WHERE Earned = 0 AND Domain IN (SELECT SourceDomain FROM dbo.MktBacklink WHERE Status = N'active')");
        $won = mkt_bl_mark_won($importId);
        $pdo->prepare('UPDATE dbo.MktBacklinkImport SET NewCount = :n, LostCount = :l, WonCount = :w WHERE ImportID = :i')
            ->execute(['n' => $new, 'l' => $lost, 'w' => $won, 'i' => $importId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['ok' => true, 'message' => sprintf('Imported %d links: %d new, %d lost%s%s.', count($clean), $new, $lost,
        $won > 0 ? sprintf(', %d outreach prospect%s marked won', $won, $won === 1 ? '' : 's') : '',
        $skipped > 0 ? sprintf(', %d rows skipped (not linking to %s)', $skipped, mkt_our_host()) : '')];
}

/** Open prospects whose domain now has a live link: mark won with the linking URL. */
function mkt_bl_mark_won(int $importId): int
{
    $rows = db()->query(<<<SQL
        SELECT p.ProspectID, (SELECT TOP (1) b.SourceUrl FROM dbo.MktBacklink b
                               WHERE b.Status = N'active' AND (b.SourceDomain = p.Domain OR b.SourceDomain LIKE N'%.' + p.Domain)
                               ORDER BY b.FirstSeen DESC) AS Url
        FROM dbo.MktProspect p
        WHERE p.Status IN (N'to_contact', N'contacted', N'replied')
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    $won = 0;
    foreach ($rows as $r) {
        if (empty($r['Url'])) {
            continue;
        }
        db()->prepare("UPDATE dbo.MktProspect SET Status = N'won', WonUrl = :u, WonAt = :d, NextFollowUp = NULL, UpdatedAt = SYSUTCDATETIME() WHERE ProspectID = :id")
            ->execute(['d' => mkt_local_date(), 'u' => (string) $r['Url'], 'id' => (int) $r['ProspectID']]);
        mkt_prospect_event((int) $r['ProspectID'], 'won', 'Link found in backlink import #' . $importId . ': ' . $r['Url']);
        $won++;
    }

    return $won;
}

function mkt_bl_import_gap(array $rows, string $source): array
{
    $pdo = db();
    $today = mkt_local_date();
    $skipped = 0;
    $clean = [];
    foreach ($rows as $r) {
        $domain = mkt_domain_of($r['domain']);
        if (!mkt_bl_domain_valid($domain) || mkt_is_our_domain($domain) || mkt_competitor_for($domain, mkt_competitor_domains()) !== null) {
            $skipped++;
            continue;
        }
        $clean[$domain] = $r;
    }
    if ($clean === []) {
        return ['ok' => false, 'error' => 'No referring domains found in the import.'];
    }

    $new = 0;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO dbo.MktBacklinkImport (Source, Kind, RowsRead, Skipped, CreatedBy) OUTPUT INSERTED.ImportID AS inserted_id VALUES (:s, N'gap', :r, :sk, :u)");
        $stmt->execute(['s' => $source, 'r' => count($rows), 'sk' => $skipped, 'u' => marketing_user_id()]);
        $importId = db_fetch_inserted_int($stmt, 'inserted_id');
        $upsert = $pdo->prepare(<<<SQL
            MERGE dbo.MktBacklinkGap AS t
            USING (SELECT :d AS Domain) AS s ON t.Domain = s.Domain
            WHEN MATCHED THEN UPDATE SET Authority = COALESCE(:a1, t.Authority), SpamScore = COALESCE(:sp1, t.SpamScore), CompetitorCount = :c1,
                Competitors = COALESCE(:n1, t.Competitors), Earned = CASE WHEN :e1 = 1 THEN 1 ELSE t.Earned END, LastSeen = :ls1, LastImportID = :i1
            WHEN NOT MATCHED THEN INSERT (Domain, Authority, SpamScore, CompetitorCount, Competitors, Earned, FirstSeen, LastSeen, LastImportID)
                VALUES (:d2, :a2, :sp2, :c2, :n2, :e2, :fs, :ls2, :i2)
            OUTPUT \$action AS MergeAction;
        SQL);
        foreach ($clean as $domain => $r) {
            $names = $r['competitors'] !== null ? mb_substr($r['competitors'], 0, 500) : null;
            $upsert->execute([
                'd' => $domain, 'a1' => $r['authority'], 'sp1' => $r['spam'], 'c1' => $r['count'], 'n1' => $names, 'e1' => $r['earned'] ? 1 : 0, 'ls1' => $today, 'i1' => $importId,
                'd2' => $domain, 'a2' => $r['authority'], 'sp2' => $r['spam'], 'c2' => $r['count'], 'n2' => $names, 'e2' => $r['earned'] ? 1 : 0, 'fs' => $today, 'ls2' => $today, 'i2' => $importId,
            ]);
            $new += $upsert->fetchColumn() === 'INSERT' ? 1 : 0;
        }
        $pdo->exec("UPDATE dbo.MktBacklinkGap SET Earned = 1 WHERE Earned = 0 AND Domain IN (SELECT SourceDomain FROM dbo.MktBacklink WHERE Status = N'active')");
        $pdo->prepare('UPDATE dbo.MktBacklinkImport SET NewCount = :n WHERE ImportID = :i')->execute(['n' => $new, 'i' => $importId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['ok' => true, 'message' => sprintf('Imported %d link-gap domains (%d new)%s.', count($clean), $new, $skipped > 0 ? sprintf(', %d rows skipped', $skipped) : '')];
}

/* ---------- Prospects ---------- */

function mkt_prospects_list(array $filters): array
{
    $where = [];
    $params = [];
    $status = (string) ($filters['status'] ?? 'open');
    if ($status === 'open') {
        $where[] = "p.Status IN (N'to_contact', N'contacted', N'replied')";
    } elseif (isset(MKT_PROSPECT_STATUSES[$status])) {
        $where[] = 'p.Status = :st';
        $params['st'] = $status;
    }
    if ((int) ($filters['owner'] ?? 0) > 0) {
        $where[] = 'p.OwnerUserID = :o';
        $params['o'] = (int) $filters['owner'];
    }
    if (trim((string) ($filters['q'] ?? '')) !== '') {
        [$like, $likeParams] = db_like_or(['p.Domain', 'p.Opportunity', 'p.ContactName'], (string) $filters['q']);
        $where[] = $like;
        $params += $likeParams;
    }
    $sql = <<<SQL
        SELECT TOP (500) p.ProspectID, p.Domain, p.Opportunity, p.Origin, p.TargetUrl, p.ContactName, p.Status, p.PitchClaimsScore, p.PitchDraftedAt,
               CONVERT(varchar(10), p.NextFollowUp, 23) AS NextFollowUpIso, CONVERT(varchar(10), p.WonAt, 23) AS WonAtIso, p.WonUrl,
               u.UserName AS OwnerName, g.Authority,
               (SELECT MAX(e.CreatedAt) FROM dbo.MktProspectEvent e WHERE e.ProspectID = p.ProspectID AND e.EventType = N'sent') AS LastSentAt
        FROM dbo.MktProspect p
        LEFT JOIN dbo.[User] u ON u.UserID = p.OwnerUserID
        LEFT JOIN dbo.MktBacklinkGap g ON g.Domain = p.Domain
    SQL;
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY CASE p.Status WHEN N'replied' THEN 0 WHEN N'to_contact' THEN 1 WHEN N'contacted' THEN 2 ELSE 3 END, p.NextFollowUp, p.Domain";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_prospect_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT p.*, CONVERT(varchar(10), p.NextFollowUp, 23) AS NextFollowUpIso, CONVERT(varchar(10), p.WonAt, 23) AS WonAtIso,
               u.UserName AS OwnerName, g.Authority, g.SpamScore, g.CompetitorCount, g.Competitors
        FROM dbo.MktProspect p
        LEFT JOIN dbo.[User] u ON u.UserID = p.OwnerUserID
        LEFT JOIN dbo.MktBacklinkGap g ON g.Domain = p.Domain
        WHERE p.ProspectID = :id
    SQL);
    $stmt->execute(['id' => $id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function mkt_prospect_events(int $id): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT e.*, u.UserName AS CreatedByName FROM dbo.MktProspectEvent e LEFT JOIN dbo.[User] u ON u.UserID = e.CreatedBy
        WHERE e.ProspectID = :id ORDER BY e.CreatedAt DESC, e.EventID DESC
    SQL);
    $stmt->execute(['id' => $id]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_prospect_event(int $id, string $type, ?string $note = null): void
{
    db()->prepare('INSERT INTO dbo.MktProspectEvent (ProspectID, EventType, Note, CreatedBy) VALUES (:p, :t, :n, :u)')
        ->execute(['p' => $id, 't' => $type, 'n' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null, 'u' => marketing_user_id()]);
}

/** Validate prospect details from a form. Returns [data, error]. */
function mkt_prospect_normalize(array $input, bool $isNew): array
{
    $data = [
        'Opportunity'  => mb_substr(trim((string) ($input['opportunity'] ?? '')), 0, 300),
        'TargetUrl'    => trim((string) ($input['target_url'] ?? '')) ?: null,
        'ContactName'  => mb_substr(trim((string) ($input['contact_name'] ?? '')), 0, 150) ?: null,
        'ContactRole'  => mb_substr(trim((string) ($input['contact_role'] ?? '')), 0, 150) ?: null,
        'ContactEmail' => mb_substr(trim((string) ($input['contact_email'] ?? '')), 0, 320) ?: null,
        'OwnerUserID'  => (int) ($input['owner_user_id'] ?? 0) ?: marketing_user_id(),
        'Angle'        => mb_substr(trim((string) ($input['angle'] ?? '')), 0, 1000) ?: null,
        'NextFollowUp' => mkt_iso_date($input['next_follow_up'] ?? null),
    ];
    if ($isNew) {
        $data['Domain'] = mkt_domain_of((string) ($input['domain'] ?? ''));
        $data['Origin'] = isset(MKT_PROSPECT_ORIGINS[$input['origin'] ?? '']) ? (string) $input['origin'] : 'manual';
        if (!mkt_bl_domain_valid($data['Domain'])) {
            return [$data, 'Enter the site’s domain, e.g. example.com.'];
        }
        if (mkt_is_our_domain($data['Domain']) || mkt_competitor_for($data['Domain'], mkt_competitor_domains()) !== null) {
            return [$data, 'That domain is ours or a competitor’s.'];
        }
    }
    if ($data['Opportunity'] === '') {
        return [$data, 'Describe the opportunity (why this site might link to us).'];
    }
    if ($data['TargetUrl'] !== null && (!filter_var($data['TargetUrl'], FILTER_VALIDATE_URL) || !mkt_is_our_domain(mkt_domain_of($data['TargetUrl'])))) {
        return [$data, 'The page to link to must be a full URL on ' . mkt_our_host() . '.'];
    }
    if ($data['ContactEmail'] !== null && !filter_var($data['ContactEmail'], FILTER_VALIDATE_EMAIL)) {
        return [$data, 'The contact email is not valid.'];
    }

    return [$data, null];
}

function mkt_prospect_create(array $input): array
{
    [$data, $error] = mkt_prospect_normalize($input, true);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error];
    }
    $dup = db()->prepare('SELECT ProspectID FROM dbo.MktProspect WHERE Domain = :d');
    $dup->execute(['d' => $data['Domain']]);
    if (($existing = $dup->fetchColumn()) !== false) {
        return ['ok' => false, 'error' => 'That domain is already a prospect.', 'id' => (int) $existing];
    }
    $data['NextFollowUp'] ??= mkt_local_date();
    $data['CreatedBy'] = marketing_user_id();
    $data['UpdatedBy'] = marketing_user_id();
    $cols = array_keys($data);
    $stmt = db()->prepare('INSERT INTO dbo.MktProspect (' . implode(', ', $cols) . ') OUTPUT INSERTED.ProspectID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
    $stmt->execute($data);
    $id = db_fetch_inserted_int($stmt, 'inserted_id');
    mkt_prospect_event($id, 'created', match ($data['Origin']) {
        'gap'   => 'Added from the link gap.',
        'lost'  => 'Added to reclaim a lost link.',
        default => 'Added by hand.',
    });

    return ['ok' => true, 'id' => $id];
}

function mkt_prospect_update(int $id, array $input): array
{
    [$data, $error] = mkt_prospect_normalize($input, false);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error];
    }
    $data['UpdatedBy'] = marketing_user_id();
    $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
    db()->prepare("UPDATE dbo.MktProspect SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE ProspectID = :id")->execute($data + ['id' => $id]);

    return ['ok' => true];
}

/** A person's edit of the pitch; the claims score applied to the AI draft is cleared. */
function mkt_prospect_save_pitch(int $id, string $subject, string $body): array
{
    $subject = mb_substr(trim($subject), 0, 300);
    $body = trim($body);
    if ($subject === '' || $body === '') {
        return ['ok' => false, 'error' => 'The pitch needs a subject and a body.'];
    }
    db()->prepare('UPDATE dbo.MktProspect SET PitchSubject = :s, PitchBody = :b, PitchClaimsScore = NULL, PitchCheckJson = NULL, UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE ProspectID = :id')
        ->execute(['s' => $subject, 'b' => $body, 'u' => marketing_user_id(), 'id' => $id]);
    mkt_prospect_event($id, 'edited');

    return ['ok' => true];
}

function mkt_prospect_follow_up_days(): int
{
    return max(1, min(60, (int) marketing_setting('outreach.follow_up_days', '7')));
}

/**
 * Record an outreach step. sent: status to contacted (unless replied), next follow-up in outreach.follow_up_days.
 * replied / declined / won set the status; followup sets a new date; reopened moves a closed prospect back to contacted.
 */
function mkt_prospect_log(int $id, string $type, array $input): array
{
    $prospect = mkt_prospect_get($id);
    if ($prospect === null) {
        return ['ok' => false, 'error' => 'Prospect not found.'];
    }
    $note = trim((string) ($input['note'] ?? ''));
    $closed = in_array($prospect['Status'], ['won', 'declined'], true);
    $sets = [];
    switch ($type) {
        case 'sent':
            if ($closed) {
                return ['ok' => false, 'error' => 'Reopen the prospect first.'];
            }
            $sets = ['Status' => $prospect['Status'] === 'replied' ? 'replied' : 'contacted', 'NextFollowUp' => mkt_local_date('+' . mkt_prospect_follow_up_days() . ' days')];
            break;
        case 'replied':
            $sets = ['Status' => 'replied', 'NextFollowUp' => mkt_iso_date($input['next_follow_up'] ?? null) ?? mkt_local_date('+2 days')];
            break;
        case 'won':
            $url = trim((string) ($input['won_url'] ?? ''));
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                return ['ok' => false, 'error' => 'The linking page must be a full URL.'];
            }
            $sets = ['Status' => 'won', 'NextFollowUp' => null, 'WonUrl' => $url ?: null, 'WonAt' => mkt_local_date()];
            $note = trim($note . ($url !== '' ? ' Link: ' . $url : ''));
            break;
        case 'declined':
            $sets = ['Status' => 'declined', 'NextFollowUp' => null];
            break;
        case 'followup':
            $date = mkt_iso_date($input['next_follow_up'] ?? null);
            if ($date === null || $closed) {
                return ['ok' => false, 'error' => $closed ? 'Reopen the prospect first.' : 'Choose the follow-up date.'];
            }
            $sets = ['NextFollowUp' => $date];
            $note = trim('For ' . marketing_format_date($date) . '. ' . $note);
            break;
        case 'reopened':
            if (!$closed) {
                return ['ok' => false, 'error' => 'The prospect is already open.'];
            }
            $sets = ['Status' => 'contacted', 'NextFollowUp' => mkt_local_date(), 'WonUrl' => null, 'WonAt' => null];
            break;
        case 'note':
            if ($note === '') {
                return ['ok' => false, 'error' => 'Write the note first.'];
            }
            break;
        default:
            return ['ok' => false, 'error' => 'Unknown action.'];
    }
    if ($sets !== []) {
        $sets['UpdatedBy'] = marketing_user_id();
        $sql = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($sets)));
        db()->prepare("UPDATE dbo.MktProspect SET $sql, UpdatedAt = SYSUTCDATETIME() WHERE ProspectID = :id")->execute($sets + ['id' => $id]);
    }
    mkt_prospect_event($id, $type, $note);

    return ['ok' => true];
}

/** Outreach tasks: send the first pitch, or follow up, once NextFollowUp falls due. */
function mkt_prospect_desired_tasks(callable $add): void
{
    $stmt = db()->prepare(<<<SQL
        SELECT ProspectID, Domain, Status, OwnerUserID, CONVERT(varchar(10), NextFollowUp, 23) AS NextFollowUp
        FROM dbo.MktProspect
        WHERE Status IN (N'to_contact', N'contacted', N'replied') AND NextFollowUp IS NOT NULL AND NextFollowUp <= :today
    SQL);
    $stmt->execute(['today' => mkt_local_date()]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $id = (int) $row['ProspectID'];
        [$title, $detail] = match ((string) $row['Status']) {
            'to_contact' => ['Send the outreach pitch: ', 'Draft or edit the pitch, send it from your own mailbox, then click Log as sent.'],
            'replied'    => ['Answer the reply: ', 'They replied — continue the conversation, then log the outcome (won, declined or a new follow-up date).'],
            default      => ['Follow up on outreach: ', 'No reply yet. Send a short follow-up and Log as sent, set a later date, or mark it declined.'],
        };
        $add("prospect:$id:followup", [
            'Title' => $title . mb_substr((string) $row['Domain'], 0, 180), 'TaskType' => 'outreach_followup', 'AssigneeRole' => 'coordinator',
            'AssigneeUserID' => (int) $row['OwnerUserID'] ?: null, 'RefType' => 'prospect', 'RefID' => $id,
            'Href' => '/marketing/backlinks/prospect.php?id=' . $id, 'DueDate' => (string) $row['NextFollowUp'], 'Detail' => $detail,
        ]);
    }
}
