<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/status-update-portal.php';
require_once __DIR__ . '/status-update/BlobStore.php';
require_once __DIR__ . '/status-update/DeckReader.php';
require_once __DIR__ . '/status-update/DeckWriter.php';
require_once __DIR__ . '/status-update/ChangeValidator.php';
require_once __DIR__ . '/status-update/ClaudeClient.php';

final class StatusUpdateRepository
{
    public function latestTemplate(): ?array
    {
        $pdo = db();
        $stmt = $pdo->query(<<<SQL
            SELECT TOP (1) *
            FROM dbo.status_template
            ORDER BY uploaded_at DESC, id DESC
        SQL);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function insertTemplate(string $blobPath, int $fieldCount, string $author, bool $useNextWeek, ?string $archivedPath): int
    {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.status_template (
                blob_path, archived_blob_path, use_for_next_week, field_count, uploaded_by, uploaded_at
            )
            OUTPUT INSERTED.id AS inserted_id
            VALUES (:blob, :archived, :use_next, :fields, :author, SYSUTCDATETIME())
        SQL);
        $stmt->execute([
            'blob'     => $blobPath,
            'archived' => $archivedPath,
            'use_next' => $useNextWeek ? 1 : 0,
            'fields'   => $fieldCount,
            'author'   => $author,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function weekByDate(string $date): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM dbo.status_week WHERE status_date = :d');
        $stmt->execute(['d' => $date]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function insertWeek(string $date, string $baseBlob, string $author): int
    {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.status_week (status_date, state, base_blob, opened_by, opened_at)
            OUTPUT INSERTED.id AS inserted_id
            VALUES (:d, N'draft', :blob, :author, SYSUTCDATETIME())
        SQL);
        $stmt->execute(['d' => $date, 'blob' => $baseBlob, 'author' => $author]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function changesForWeek(int $weekId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT * FROM dbo.status_change
            WHERE week_id = :wid
            ORDER BY id ASC
        SQL);
        $stmt->execute(['wid' => $weekId]);

        return $stmt->fetchAll();
    }

    public function latestChangeIdForField(int $weekId, string $fieldKey): int
    {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT TOP (1) id FROM dbo.status_change
            WHERE week_id = :wid AND field_key = :key
            ORDER BY id DESC
        SQL);
        $stmt->execute(['wid' => $weekId, 'key' => $fieldKey]);
        $id = $stmt->fetchColumn();

        return $id === false ? 0 : (int) $id;
    }

    /**
     * @param list<string> $newValue
     */
    public function insertChange(
        int $weekId,
        string $fieldKey,
        array $oldValue,
        array $newValue,
        string $source,
        ?string $reason,
        string $author
    ): int {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.status_change (
                week_id, field_key, old_value, new_value, source, reason, author, created_at
            )
            OUTPUT INSERTED.id AS inserted_id
            VALUES (
                :wid, :key, :old, :new, :source, :reason, :author, SYSUTCDATETIME()
            )
        SQL);
        $stmt->execute([
            'wid'    => $weekId,
            'key'    => $fieldKey,
            'old'    => json_encode($oldValue, JSON_UNESCAPED_UNICODE),
            'new'    => json_encode($newValue, JSON_UNESCAPED_UNICODE),
            'source' => $source,
            'reason' => $reason,
            'author' => $author,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function addChatMessage(int $weekId, string $author, string $role, string $content): void
    {
        $pdo = db();
        $pdo->prepare(<<<SQL
            INSERT INTO dbo.status_chat_message (week_id, author, role, content, created_at)
            VALUES (:wid, :author, :role, :content, SYSUTCDATETIME())
        SQL)->execute([
            'wid'     => $weekId,
            'author'  => $author,
            'role'    => $role,
            'content' => $content,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function chatThread(int $weekId, string $author, int $limit = 20): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT TOP (:lim) *
            FROM dbo.status_chat_message
            WHERE week_id = :wid AND author = :author
            ORDER BY id DESC
        SQL);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':wid', $weekId, PDO::PARAM_INT);
        $stmt->bindValue(':author', $author);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_reverse($rows);
    }
}

final class StatusUpdateDraftService
{
    public function __construct(
        private StatusUpdateRepository $repo = new StatusUpdateRepository(),
        private StatusUpdateBlobStore $blob = new StatusUpdateBlobStore(),
    ) {
    }

    /**
     * @return array{ok: bool, error: ?string, map: array<string, list<string>>, base_map: array<string, array<string, mixed>>}
     */
    public function buildDraftValues(array $week): array
    {
        $base = $this->blob->get((string) $week['base_blob']);
        if (!$base['ok']) {
            return ['ok' => false, 'error' => $base['error'] ?? 'Missing base deck.', 'map' => [], 'base_map' => []];
        }

        $reader = new DeckReader();
        $parsed = $reader->readFromBytes((string) $base['content']);
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => $parsed['error'] ?? 'Invalid base deck.', 'map' => [], 'base_map' => []];
        }

        $values = [];
        foreach ($parsed['map'] as $key => $field) {
            if (empty($field['editable'])) {
                continue;
            }
            $values[$key] = $field['value'];
        }

        foreach ($this->repo->changesForWeek((int) $week['id']) as $change) {
            $decoded = json_decode((string) $change['new_value'], true);
            if (is_array($decoded)) {
                $values[(string) $change['field_key']] = array_values(array_map('strval', $decoded));
            }
        }

        return ['ok' => true, 'error' => null, 'map' => $values, 'base_map' => $parsed['map']];
    }

    public function openWeek(string $date, string $author): array
    {
        if ($this->repo->weekByDate($date) !== null) {
            return ['ok' => true, 'error' => null, 'week' => $this->repo->weekByDate($date)];
        }

        $template = $this->repo->latestTemplate();
        if ($template === null) {
            return ['ok' => false, 'error' => 'No template has been uploaded yet.', 'week' => null];
        }

        $sourceBlob = null;
        if (!empty($template['use_for_next_week'])) {
            $sourceBlob = (string) $template['blob_path'];
        } else {
            $pdo = db();
            $stmt = $pdo->query(<<<SQL
                SELECT TOP (1) final_blob
                FROM dbo.status_week
                WHERE state = N'published' AND final_blob IS NOT NULL
                ORDER BY status_date DESC
            SQL);
            $row = $stmt->fetch();
            $sourceBlob = $row !== false && !empty($row['final_blob'])
                ? (string) $row['final_blob']
                : (string) $template['blob_path'];
        }

        $basePath = 'weekly/' . $date . '/base.pptx';
        $copy = $this->blob->copy($sourceBlob, $basePath);
        if (!$copy['ok']) {
            return ['ok' => false, 'error' => $copy['error'] ?? 'Unable to copy base deck for the week.', 'week' => null];
        }

        if (!empty($template['use_for_next_week'])) {
            $pdo = db();
            $pdo->exec('UPDATE dbo.status_template SET use_for_next_week = 0 WHERE use_for_next_week = 1');
        }

        $weekId = $this->repo->insertWeek($date, $basePath, $author);

        $week = $this->repo->weekByDate($date);

        return ['ok' => $week !== null, 'error' => $week === null ? 'Week was created but could not be loaded.' : null, 'week' => $week];
    }

    public function acceptChange(
        array $week,
        string $fieldKey,
        array $newValue,
        int $expectedChangeId,
        string $source,
        ?string $reason,
        string $author
    ): array {
        if (($week['state'] ?? '') === 'published') {
            return ['ok' => false, 'error' => 'This week is published and read-only.'];
        }

        $draft = $this->buildDraftValues($week);
        if (!$draft['ok']) {
            return ['ok' => false, 'error' => $draft['error']];
        }

        $validator = new StatusUpdateChangeValidator();
        $check = $validator->validate($fieldKey, $draft['base_map'], $newValue);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => $check['error']];
        }

        $currentId = $this->repo->latestChangeIdForField((int) $week['id'], $fieldKey);
        if ($expectedChangeId !== $currentId) {
            return [
                'ok'       => false,
                'error'    => 'conflict',
                'conflict' => [
                    'field'         => $fieldKey,
                    'latest_id'     => $currentId,
                    'their_value'   => $draft['map'][$fieldKey] ?? [],
                ],
            ];
        }

        $oldValue = $draft['map'][$fieldKey] ?? [''];

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(<<<SQL
                SELECT TOP (1) id
                FROM dbo.status_change WITH (UPDLOCK, HOLDLOCK)
                WHERE week_id = :wid AND field_key = :key
                ORDER BY id DESC
            SQL);
            $stmt->execute(['wid' => (int) $week['id'], 'key' => $fieldKey]);
            $lockedId = (int) ($stmt->fetchColumn() ?: 0);
            if ($lockedId !== $expectedChangeId) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'conflict', 'conflict' => ['field' => $fieldKey, 'latest_id' => $lockedId]];
            }

            $this->repo->insertChange(
                (int) $week['id'],
                $fieldKey,
                $oldValue,
                $check['value'],
                $source,
                $reason,
                $author
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'error' => 'Unable to save change.'];
        }

        return ['ok' => true, 'error' => null];
    }
}

final class StatusUpdateTemplateService
{
    public function __construct(
        private StatusUpdateRepository $repo = new StatusUpdateRepository(),
        private StatusUpdateBlobStore $blob = new StatusUpdateBlobStore(),
    ) {
    }

    public function validateUpload(string $tmpPath, ?string $originalName = null): array
    {
        $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
        if ($ext !== 'pptx') {
            $ext = strtolower(pathinfo($tmpPath, PATHINFO_EXTENSION));
        }
        if ($ext !== 'pptx') {
            $handle = @fopen($tmpPath, 'rb');
            $magic = is_resource($handle) ? fread($handle, 2) : '';
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($magic !== 'PK') {
                return ['ok' => false, 'error' => 'Upload a .pptx file.', 'summary' => null];
            }
        }

        if (filesize($tmpPath) > 50 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'File exceeds the 50 MB limit.', 'summary' => null];
        }

        $bytes = file_get_contents($tmpPath);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'Unable to read upload.', 'summary' => null];
        }

        $reader = new DeckReader();
        $parsed = $reader->readFromBytes($bytes);

        return [
            'ok'      => $parsed['ok'],
            'error'   => $parsed['ok'] ? null : ($parsed['error'] ?? 'Template validation failed.'),
            'summary' => [
                'field_count' => count($parsed['map']),
                'problems'    => $parsed['problems'],
                'slides'      => count($parsed['slide_paths']),
            ],
        ];
    }

    public function confirmStore(string $tmpPath, string $author, bool $useNextWeek): array
    {
        $bytes = file_get_contents($tmpPath);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'Upload expired or missing. Upload again.'];
        }

        $reader = new DeckReader();
        $parsed = $reader->readFromBytes($bytes);
        if (!$parsed['ok']) {
            return ['ok' => false, 'error' => $parsed['error'] ?? 'Template validation failed.'];
        }

        $path = 'template/current.pptx';
        $archived = null;
        $existing = $this->repo->latestTemplate();
        if ($existing !== null) {
            $archived = 'template/archive/' . gmdate('Ymd-His') . '_' . preg_replace('/[^a-z0-9_-]+/i', '_', $author) . '.pptx';
            $this->blob->copy((string) $existing['blob_path'], $archived);
        }

        $put = $this->blob->put($path, $bytes);
        if (!$put['ok']) {
            return ['ok' => false, 'error' => $put['error'] ?? 'Blob upload failed.'];
        }

        $this->repo->insertTemplate($path, count($parsed['map']), $author, $useNextWeek, $archived);

        return ['ok' => true, 'error' => null];
    }
}

final class StatusUpdatePublishService
{
    public function __construct(
        private StatusUpdateRepository $repo = new StatusUpdateRepository(),
        private StatusUpdateBlobStore $blob = new StatusUpdateBlobStore(),
        private StatusUpdateDraftService $draft = new StatusUpdateDraftService(),
    ) {
    }

    public function publish(array $week, string $author): array
    {
        if (($week['state'] ?? '') !== 'draft') {
            return ['ok' => false, 'error' => 'Only draft weeks can be published.'];
        }

        $draftValues = $this->draft->buildDraftValues($week);
        if (!$draftValues['ok']) {
            return ['ok' => false, 'error' => $draftValues['error']];
        }

        $base = $this->blob->get((string) $week['base_blob']);
        if (!$base['ok']) {
            return ['ok' => false, 'error' => 'Missing base deck.'];
        }

        $writer = new DeckWriter();
        $rendered = $writer->render((string) $base['content'], $draftValues['map'], (string) $week['status_date']);
        if (!$rendered['ok'] || !is_string($rendered['bytes'])) {
            return ['ok' => false, 'error' => $rendered['error'] ?? 'Render failed.'];
        }

        $date = (string) $week['status_date'];
        try {
            $labelDate = (new DateTimeImmutable($date))->format('M_d');
        } catch (Throwable) {
            $labelDate = str_replace('-', '_', $date);
        }
        $finalPath = 'final-update/' . $date . '/NutraAxis_Status_Presentation_' . $labelDate . '.pptx';

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.status_week WITH (ROWLOCK)
                SET state = N'published', published_by = :author, published_at = SYSUTCDATETIME()
                WHERE id = :id AND state = N'draft'
            SQL);
            $stmt->execute(['author' => $author, 'id' => (int) $week['id']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Week is no longer in draft state.'];
            }

            $put = $this->blob->putIfAbsent($finalPath, $rendered['bytes']);
            if (!$put['ok']) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => $put['error'] ?? 'Unable to write final deck.'];
            }

            $pdo->prepare('UPDATE dbo.status_week SET final_blob = :path WHERE id = :id')
                ->execute(['path' => $finalPath, 'id' => (int) $week['id']]);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'error' => 'Publish failed.'];
        }

        return ['ok' => true, 'error' => null, 'final_path' => $finalPath];
    }
}