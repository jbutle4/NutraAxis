<?php

require_once __DIR__ . '/approval-token.php';
require_once __DIR__ . '/alert-messages.php';

function qbo_insert_list_approvers_from_alert_subscribers(): array
{
    if (!alert_tables_available()) {
        return [];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT DISTINCT
            u.UserID,
            u.UserName,
            u.UserLogin,
            r.RoleName
        FROM dbo.AlertMessage am
        INNER JOIN dbo.AlertSubscription sub ON sub.alertID = am.alertID
        INNER JOIN dbo.[User] u ON u.UserID = sub.UserID
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE am.AlertName = :alert_name
          AND am.AlertStatus = 1
          AND u.UserLogin IS NOT NULL
          AND LTRIM(RTRIM(u.UserLogin)) <> ''
        ORDER BY u.UserName
    SQL);
    $stmt->execute(['alert_name' => ALERT_NAME_QBO_INSERT_APPROVAL_REQUEST]);

    return array_values(array_filter(
        $stmt->fetchAll(),
        static function (array $row): bool {
            $email = strtolower(trim((string) ($row['UserLogin'] ?? '')));

            return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        }
    ));
}

function qbo_insert_list_approvers(): array
{
    $approvers = approval_list_users_for_type('QBOInsert');
    if ($approvers !== []) {
        return $approvers;
    }

    return qbo_insert_list_approvers_from_alert_subscribers();
}

function qbo_insert_approval_token_create(int $invoiceId, int $userId): ?string
{
    return approval_token_create('QBOInsert', $invoiceId, $userId);
}

function qbo_insert_approval_token_invalidate(int $invoiceId): void
{
    approval_token_invalidate('QBOInsert', $invoiceId);
}

function qbo_insert_approval_token_validate(string $token, int $invoiceId): ?array
{
    return approval_token_validate('QBOInsert', $invoiceId, $token);
}

function qbo_insert_approval_token_resolve(string $token, int $invoiceId): ?array
{
    return approval_token_resolve('QBOInsert', $invoiceId, $token);
}

function qbo_insert_build_action_url(int $invoiceId, string $token, string $action): string
{
    return approval_site_url()
        . '/accounting/supplier-invoices/approve.php?id=' . $invoiceId
        . '&token=' . rawurlencode($token)
        . '&action=' . rawurlencode($action);
}
