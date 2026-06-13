<?php

require_once __DIR__ . '/po.php';

const PO_APPROVAL_TOKEN_BYTES = 32;
const PO_APPROVAL_TOKEN_EXPIRY_DAYS = 14;

function po_approval_site_url(): string
{
    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function po_approval_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function po_approval_token_purge_expired(): void
{
    $pdo = db();
    $pdo->exec('DELETE FROM dbo.POApprovalToken WHERE ExpiresAt < SYSUTCDATETIME() OR UsedAt IS NOT NULL');
}

function po_list_po_approvers(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT UserID, UserName, UserLogin
        FROM dbo.[User]
        WHERE IsPOApprover = 1
          AND UserLogin IS NOT NULL
          AND LTRIM(RTRIM(UserLogin)) <> ''
        ORDER BY UserName
    SQL);

    return $stmt->fetchAll();
}

function po_approval_token_create(int $poId, int $userId): ?string
{
    $token = bin2hex(random_bytes(PO_APPROVAL_TOKEN_BYTES));
    $tokenHash = po_approval_token_hash($token);
    $expiresAt = (new DateTimeImmutable('+' . PO_APPROVAL_TOKEN_EXPIRY_DAYS . ' days'))->format('Y-m-d H:i:s');

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.POApprovalToken (POID, UserID, TokenHash, ExpiresAt)
        VALUES (:po, :user, :hash, :expires)
    SQL);
    $stmt->execute([
        'po'      => $poId,
        'user'    => $userId,
        'hash'    => $tokenHash,
        'expires' => $expiresAt,
    ]);

    return $token;
}

function po_approval_token_invalidate_for_po(int $poId): void
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.POApprovalToken
        SET UsedAt = SYSUTCDATETIME()
        WHERE POID = :po
          AND UsedAt IS NULL
    SQL);
    $stmt->execute(['po' => $poId]);
}

function po_approval_token_validate(string $token, int $poId): ?array
{
    $token = trim($token);
    if ($token === '' || !ctype_xdigit($token) || strlen($token) !== PO_APPROVAL_TOKEN_BYTES * 2) {
        return null;
    }

    try {
        po_approval_token_purge_expired();

        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT
                t.TokenID,
                t.POID,
                t.UserID,
                t.ExpiresAt,
                u.UserLogin,
                u.UserName,
                u.IsPOApprover
            FROM dbo.POApprovalToken t
            INNER JOIN dbo.[User] u ON u.UserID = t.UserID
            WHERE t.TokenHash = :hash
              AND t.POID = :po
              AND t.UsedAt IS NULL
              AND t.ExpiresAt >= SYSUTCDATETIME()
        SQL);
        $stmt->execute([
            'hash' => po_approval_token_hash($token),
            'po'   => $poId,
        ]);
        $row = $stmt->fetch();

        if ($row === false || empty($row['IsPOApprover'])) {
            return null;
        }

        return $row;
    } catch (Throwable $e) {
        error_log('po_approval_token_validate failed: ' . $e->getMessage());

        return null;
    }
}

function po_approval_token_resolve(string $token, int $poId): ?array
{
    $row = po_approval_token_validate($token, $poId);
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

function po_approval_build_action_url(int $poId, string $token, string $action): string
{
    return po_approval_site_url()
        . '/po-management/approve.php?id=' . $poId
        . '&token=' . rawurlencode($token)
        . '&action=' . rawurlencode($action);
}

function po_approval_build_action_email_html(array $order, string $submitter, bool $isResubmit, array $actionUrls): string
{
    $poNumber = htmlspecialchars((string) $order['PONumber'], ENT_QUOTES, 'UTF-8');
    $supplier = htmlspecialchars((string) ($order['SupplierName'] ?? ''), ENT_QUOTES, 'UTF-8');
    $totalDue = htmlspecialchars(po_format_money((float) ($order['TotalDue'] ?? 0)), ENT_QUOTES, 'UTF-8');
    $intro = $isResubmit
        ? 'A purchase order has been resubmitted for your approval.'
        : 'A purchase order has been submitted for your approval.';
    $submitterEsc = htmlspecialchars($submitter, ENT_QUOTES, 'UTF-8');
    $reviewUrl = htmlspecialchars((string) ($actionUrls['review'] ?? ''), ENT_QUOTES, 'UTF-8');
    $approveUrl = htmlspecialchars((string) ($actionUrls['approve'] ?? ''), ENT_QUOTES, 'UTF-8');
    $rejectUrl = htmlspecialchars((string) ($actionUrls['reject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sendBackUrl = htmlspecialchars((string) ($actionUrls['send_back'] ?? ''), ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
  <p>{$intro}</p>
  <table cellpadding="4" cellspacing="0" style="margin: 16px 0;">
    <tr><td><strong>PO Number</strong></td><td>{$poNumber}</td></tr>
    <tr><td><strong>Supplier</strong></td><td>{$supplier}</td></tr>
    <tr><td><strong>Total due</strong></td><td>{$totalDue}</td></tr>
    <tr><td><strong>Submitted by</strong></td><td>{$submitterEsc}</td></tr>
  </table>
  <p style="margin: 24px 0 12px;">Choose an action (links expire in 14 days):</p>
  <p>
    <a href="{$approveUrl}" style="display:inline-block;padding:10px 16px;background:#1a7f37;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;">Approve</a>
    <a href="{$rejectUrl}" style="display:inline-block;padding:10px 16px;background:#b42318;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;">Reject</a>
    <a href="{$sendBackUrl}" style="display:inline-block;padding:10px 16px;background:#555;color:#fff;text-decoration:none;border-radius:4px;">Return for Comment</a>
  </p>
  <p style="margin-top: 24px;">Or <a href="{$reviewUrl}">review the full purchase order</a> before deciding.</p>
  <p style="margin-top: 32px; color: #666; font-size: 12px;">NutraAxis Operations</p>
</body>
</html>
HTML;
}
