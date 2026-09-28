<?php

require_once __DIR__ . '/marketing.php';

const MKT_PROVIDERS = ['anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI'];

function mkt_prompt_keys(): array
{
    return db()->query(<<<SQL
        SELECT p.PromptKey,
               MAX(p.Version) AS LatestVersion,
               MAX(CASE WHEN p.IsActive = 1 THEN p.Version END) AS ActiveVersion,
               MAX(CASE WHEN p.IsActive = 1 THEN p.Provider END) AS ActiveProvider,
               MAX(CASE WHEN p.IsActive = 1 THEN p.Model END) AS ActiveModel,
               MAX(p.CreatedAt) AS LastChanged,
               (SELECT COUNT(*) FROM dbo.MktApiUsage u WHERE u.PromptKey = p.PromptKey
                  AND u.CreatedAt >= DATEADD(DAY, -30, SYSUTCDATETIME())) AS Calls30d
        FROM dbo.MktPrompt p
        GROUP BY p.PromptKey
        ORDER BY p.PromptKey
    SQL)->fetchAll();
}

function mkt_prompt_versions(string $key): array
{
    $stmt = db()->prepare('SELECT p.*, u.UserName AS CreatedByName FROM dbo.MktPrompt p LEFT JOIN dbo.[User] u ON u.UserID = p.CreatedBy WHERE p.PromptKey = :k ORDER BY p.Version DESC');
    $stmt->execute(['k' => $key]);

    return $stmt->fetchAll();
}

function mkt_prompt_active(string $key): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktPrompt WHERE PromptKey = :k AND IsActive = 1');
    $stmt->execute(['k' => $key]);

    return $stmt->fetch() ?: null;
}

function mkt_prompt_create_version(array $input): array
{
    $key = trim((string) ($input['prompt_key'] ?? ''));
    if (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $key)) {
        return ['ok' => false, 'error' => 'Prompt key must look like area.name (lowercase, dots and underscores).'];
    }
    $template = trim((string) ($input['user_template'] ?? ''));
    if ($template === '') {
        return ['ok' => false, 'error' => 'The user template is required.'];
    }
    $provider = array_key_exists((string) ($input['provider'] ?? ''), MKT_PROVIDERS) ? (string) $input['provider'] : 'anthropic';
    $temperature = trim((string) ($input['temperature'] ?? ''));

    $pdo = db();
    $next = $pdo->prepare('SELECT COALESCE(MAX(Version), 0) + 1 FROM dbo.MktPrompt WHERE PromptKey = :k');
    $next->execute(['k' => $key]);
    $version = (int) $next->fetchColumn();
    $activate = !empty($input['activate']) || $version === 1;

    $data = [
        'PromptKey'    => $key,
        'Version'      => $version,
        'Provider'     => $provider,
        'Model'        => trim((string) ($input['model'] ?? '')) ?: null,
        'SystemPrompt' => trim((string) ($input['system_prompt'] ?? '')) ?: null,
        'UserTemplate' => $template,
        'Temperature'  => $temperature === '' ? null : max(0, min(1, (float) $temperature)),
        'MaxTokens'    => max(100, min(32000, (int) ($input['max_tokens'] ?? 2000) ?: 2000)),
        'IsActive'     => 0,
        'Notes'        => trim((string) ($input['notes'] ?? '')) ?: null,
        'CreatedBy'    => marketing_user_id(),
    ];
    $cols = array_keys($data);
    $stmt = $pdo->prepare('INSERT INTO dbo.MktPrompt (' . implode(', ', $cols) . ') OUTPUT INSERTED.PromptID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
    $stmt->execute($data);
    $id = db_fetch_inserted_int($stmt, 'inserted_id');
    $built = audit_build_insert('MktPrompt', $data, 'PromptID', $id);
    audit_log_change($built['change'], $built['reverse']);

    if ($activate) {
        mkt_prompt_activate($key, $version);
    }

    return ['ok' => true, 'version' => $version];
}

function mkt_prompt_activate(string $key, int $version): bool
{
    $pdo = db();
    $current = mkt_prompt_active($key);
    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE dbo.MktPrompt SET IsActive = 0 WHERE PromptKey = :k AND IsActive = 1')->execute(['k' => $key]);
        $stmt = $pdo->prepare('UPDATE dbo.MktPrompt SET IsActive = 1 WHERE PromptKey = :k AND Version = :v');
        $stmt->execute(['k' => $key, 'v' => $version]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();

            return false;
        }
        $pdo->commit();
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
    audit_log_change(
        "UPDATE dbo.MktPrompt SET IsActive = 1 WHERE PromptKey = " . audit_sql_literal($key) . " AND Version = $version",
        $current !== null
            ? "UPDATE dbo.MktPrompt SET IsActive = CASE WHEN Version = " . (int) $current['Version'] . " THEN 1 ELSE 0 END WHERE PromptKey = " . audit_sql_literal($key)
            : "UPDATE dbo.MktPrompt SET IsActive = 0 WHERE PromptKey = " . audit_sql_literal($key)
    );

    return true;
}
