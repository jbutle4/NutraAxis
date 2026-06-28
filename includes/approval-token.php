<?php

require_once __DIR__ . '/approval.php';

function approval_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function approval_token_purge_expired(): void
{
    $pdo = db();
    $pdo->exec('DELETE FROM dbo.ApprovalToken WHERE ExpiresAt < SYSUTCDATETIME() OR UsedAt IS NOT NULL');
}

function approval_token_create(
    string $approvalType,
    int $entityId,
    int $userId,
    ?string $entityType = null,
    ?string $secondaryEntityType = null,
    ?int $secondaryEntityId = null
): ?string {
    $config = approval_type_config($approvalType);
    if ($config === null) {
        return null;
    }

    $token = bin2hex(random_bytes(APPROVAL_TOKEN_BYTES));
    $tokenHash = approval_token_hash($token);
    $expiresAt = (new DateTimeImmutable('+' . APPROVAL_TOKEN_EXPIRY_DAYS . ' days'))->format('Y-m-d H:i:s');

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.ApprovalToken (
            ApprovalType, EntityType, EntityID,
            SecondaryEntityType, SecondaryEntityID,
            UserID, TokenHash, ExpiresAt
        )
        VALUES (
            :approval_type, :entity_type, :entity_id,
            :secondary_entity_type, :secondary_entity_id,
            :user_id, :hash, :expires
        )
    SQL);
    $stmt->execute([
        'approval_type'         => $approvalType,
        'entity_type'           => $entityType ?? $config['entity_type'],
        'entity_id'             => $entityId,
        'secondary_entity_type' => $secondaryEntityType ?? ($config['secondary_type'] ?? null),
        'secondary_entity_id'   => $secondaryEntityId,
        'user_id'               => $userId,
        'hash'                  => $tokenHash,
        'expires'               => $expiresAt,
    ]);

    return $token;
}

function approval_token_invalidate(
    string $approvalType,
    int $entityId,
    ?string $entityType = null
): void {
    $config = approval_type_config($approvalType);
    if ($config === null) {
        return;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.ApprovalToken
        SET UsedAt = SYSUTCDATETIME()
        WHERE ApprovalType = :approval_type
          AND EntityType = :entity_type
          AND EntityID = :entity_id
          AND UsedAt IS NULL
    SQL);
    $stmt->execute([
        'approval_type' => $approvalType,
        'entity_type'   => $entityType ?? $config['entity_type'],
        'entity_id'     => $entityId,
    ]);
}

function approval_token_validate(string $approvalType, int $entityId, string $token, ?string $entityType = null): ?array
{
    $config = approval_type_config($approvalType);
    if ($config === null) {
        return null;
    }

    $entityType = $entityType ?? $config['entity_type'];

    $token = trim($token);
    if ($token === '' || !ctype_xdigit($token) || strlen($token) !== APPROVAL_TOKEN_BYTES * 2) {
        return null;
    }

    try {
        approval_token_purge_expired();

        $permissionColumn = $config['permission'];
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT
                t.TokenID,
                t.ApprovalType,
                t.EntityType,
                t.EntityID,
                t.SecondaryEntityType,
                t.SecondaryEntityID,
                t.UserID,
                t.ExpiresAt,
                u.UserLogin,
                u.UserName,
                r.{$permissionColumn} AS PermissionValue
            FROM dbo.ApprovalToken t
            INNER JOIN dbo.[User] u ON u.UserID = t.UserID
            INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
            WHERE t.TokenHash = :hash
              AND t.ApprovalType = :approval_type
              AND t.EntityType = :entity_type
              AND t.EntityID = :entity_id
              AND t.UsedAt IS NULL
              AND t.ExpiresAt >= SYSUTCDATETIME()
        SQL);
        $stmt->execute([
            'hash'          => approval_token_hash($token),
            'approval_type' => $approvalType,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
        ]);
        $row = $stmt->fetch();

        if ($row === false || !permission_has((string) ($row['PermissionValue'] ?? ''), 'U')) {
            return null;
        }

        return $row;
    } catch (Throwable $e) {
        error_log('approval_token_validate failed: ' . $e->getMessage());

        return null;
    }
}

function approval_token_resolve(string $approvalType, int $entityId, string $token, ?string $entityType = null): ?array
{
    $row = approval_token_validate($approvalType, $entityId, $token, $entityType);
    if ($row === null) {
        return null;
    }

    return [
        'token_id' => (int) $row['TokenID'],
        'can_act'  => true,
        'user'     => [
            'UserID'    => (int) $row['UserID'],
            'UserName'  => (string) $row['UserName'],
            'UserLogin' => (string) $row['UserLogin'],
        ],
    ];
}

function approval_build_action_email_html(
    string $intro,
    array $summaryRows,
    array $actionUrls,
    string $reviewLabel
): string {
    $rowsHtml = '';
    foreach ($summaryRows as $label => $value) {
        $labelEsc = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
        $valueEsc = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $rowsHtml .= "<tr><td><strong>{$labelEsc}</strong></td><td>{$valueEsc}</td></tr>";
    }

    $introEsc = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $reviewUrl = htmlspecialchars((string) ($actionUrls['review'] ?? ''), ENT_QUOTES, 'UTF-8');
    $approveUrl = htmlspecialchars((string) ($actionUrls['approve'] ?? ''), ENT_QUOTES, 'UTF-8');
    $rejectUrl = htmlspecialchars((string) ($actionUrls['reject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sendBackUrl = htmlspecialchars((string) ($actionUrls['send_back'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reviewLabelEsc = htmlspecialchars($reviewLabel, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
  <p>{$introEsc}</p>
  <table cellpadding="4" cellspacing="0" style="margin: 16px 0;">{$rowsHtml}</table>
  <p style="margin: 24px 0 12px;">Choose an action (links expire in 14 days):</p>
  <p>
    <a href="{$approveUrl}" style="display:inline-block;padding:10px 16px;background:#1a7f37;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;">Approve</a>
    <a href="{$rejectUrl}" style="display:inline-block;padding:10px 16px;background:#b42318;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;">Reject</a>
    <a href="{$sendBackUrl}" style="display:inline-block;padding:10px 16px;background:#555;color:#fff;text-decoration:none;border-radius:4px;">Return for Comment</a>
  </p>
  <p style="margin-top: 24px;">Or <a href="{$reviewUrl}">{$reviewLabelEsc}</a> before deciding.</p>
  <p style="margin-top: 32px; color: #666; font-size: 12px;">NutraAxis Operations</p>
</body>
</html>
HTML;
}
