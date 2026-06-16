<?php

require_once __DIR__ . '/te.php';

const TE_APPROVAL_TOKEN_BYTES = 32;
const TE_APPROVAL_TOKEN_EXPIRY_DAYS = 14;

function te_approval_site_url(): string
{
    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function te_approval_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function te_approval_token_purge_expired(): void
{
    $pdo = db();
    $pdo->exec('DELETE FROM dbo.TEApprovalToken WHERE ExpiresAt < SYSUTCDATETIME() OR UsedAt IS NOT NULL');
}

function te_list_te_approvers(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT UserID, UserName, UserLogin
        FROM dbo.[User]
        WHERE IsTEApprover = 1
          AND UserLogin IS NOT NULL
          AND LTRIM(RTRIM(UserLogin)) <> ''
        ORDER BY UserName
    SQL);

    return $stmt->fetchAll();
}

function te_list_po_processors(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT UserID, UserName, UserLogin
        FROM dbo.[User]
        WHERE IsPOProcessor = 1
          AND UserLogin IS NOT NULL
          AND LTRIM(RTRIM(UserLogin)) <> ''
        ORDER BY UserName
    SQL);

    return $stmt->fetchAll();
}

function te_approval_token_create(int $reportId, int $userId): ?string
{
    $token = bin2hex(random_bytes(TE_APPROVAL_TOKEN_BYTES));
    $tokenHash = te_approval_token_hash($token);
    $expiresAt = (new DateTimeImmutable('+' . TE_APPROVAL_TOKEN_EXPIRY_DAYS . ' days'))->format('Y-m-d H:i:s');

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.TEApprovalToken (ReportID, UserID, TokenHash, ExpiresAt)
        VALUES (:report, :user, :hash, :expires)
    SQL);
    $stmt->execute([
        'report'  => $reportId,
        'user'    => $userId,
        'hash'    => $tokenHash,
        'expires' => $expiresAt,
    ]);

    return $token;
}

function te_approval_token_invalidate_for_report(int $reportId): void
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.TEApprovalToken
        SET UsedAt = SYSUTCDATETIME()
        WHERE ReportID = :report
          AND UsedAt IS NULL
    SQL);
    $stmt->execute(['report' => $reportId]);
}

function te_approval_token_validate(string $token, int $reportId): ?array
{
    $token = trim($token);
    if ($token === '' || !ctype_xdigit($token) || strlen($token) !== TE_APPROVAL_TOKEN_BYTES * 2) {
        return null;
    }

    try {
        te_approval_token_purge_expired();

        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT
                t.TokenID,
                t.ReportID,
                t.UserID,
                t.ExpiresAt,
                u.UserLogin,
                u.UserName,
                u.IsTEApprover
            FROM dbo.TEApprovalToken t
            INNER JOIN dbo.[User] u ON u.UserID = t.UserID
            WHERE t.TokenHash = :hash
              AND t.ReportID = :report
              AND t.UsedAt IS NULL
              AND t.ExpiresAt >= SYSUTCDATETIME()
        SQL);
        $stmt->execute([
            'hash'   => te_approval_token_hash($token),
            'report' => $reportId,
        ]);
        $row = $stmt->fetch();

        if ($row === false || empty($row['IsTEApprover'])) {
            return null;
        }

        return $row;
    } catch (Throwable $e) {
        error_log('te_approval_token_validate failed: ' . $e->getMessage());

        return null;
    }
}

function te_approval_token_resolve(string $token, int $reportId): ?array
{
    $row = te_approval_token_validate($token, $reportId);
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

function te_approval_build_action_url(int $reportId, string $token, string $action): string
{
    return te_approval_site_url()
        . '/travel-expense/approve.php?id=' . $reportId
        . '&token=' . rawurlencode($token)
        . '&action=' . rawurlencode($action);
}

function te_approval_build_action_email_html(array $report, string $submitter, bool $isResubmit, array $actionUrls): string
{
    $reportNumber = htmlspecialchars((string) $report['ReportNumber'], ENT_QUOTES, 'UTF-8');
    $employee = htmlspecialchars((string) ($report['EmployeeName'] ?? ''), ENT_QUOTES, 'UTF-8');
    $totalDue = htmlspecialchars(te_format_money((float) ($report['TotalReimbursementDue'] ?? 0)), ENT_QUOTES, 'UTF-8');
    $period = htmlspecialchars(te_period_label($report), ENT_QUOTES, 'UTF-8');
    $intro = $isResubmit
        ? 'A travel and expense report has been resubmitted for your approval.'
        : 'A travel and expense report has been submitted for your approval.';
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
    <tr><td><strong>Report #</strong></td><td>{$reportNumber}</td></tr>
    <tr><td><strong>Employee</strong></td><td>{$employee}</td></tr>
    <tr><td><strong>Period</strong></td><td>{$period}</td></tr>
    <tr><td><strong>Total due</strong></td><td>{$totalDue}</td></tr>
    <tr><td><strong>Submitted by</strong></td><td>{$submitterEsc}</td></tr>
  </table>
  <p style="margin: 24px 0 12px;">Choose an action (links expire in 14 days):</p>
  <p>
    <a href="{$approveUrl}" style="display:inline-block;padding:10px 16px;background:#1a7f37;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;">Approve</a>
    <a href="{$rejectUrl}" style="display:inline-block;padding:10px 16px;background:#b42318;color:#fff;text-decoration:none;border-radius:4px;margin-right:8px;">Reject</a>
    <a href="{$sendBackUrl}" style="display:inline-block;padding:10px 16px;background:#555;color:#fff;text-decoration:none;border-radius:4px;">Return for Comment</a>
  </p>
  <p style="margin-top: 24px;">Or <a href="{$reviewUrl}">review the full expense report</a> before deciding.</p>
  <p style="margin-top: 32px; color: #666; font-size: 12px;">NutraAxis Operations</p>
</body>
</html>
HTML;
}
