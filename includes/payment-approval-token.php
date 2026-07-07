<?php

require_once __DIR__ . '/approval-token.php';
require_once __DIR__ . '/alert-messages.php';

function payment_approval_list_approvers_from_alert_subscribers(): array
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
    $stmt->execute(['alert_name' => ALERT_NAME_PAYMENT_APPROVAL_REQUEST]);

    return array_values(array_filter(
        $stmt->fetchAll(),
        static function (array $row): bool {
            $email = strtolower(trim((string) ($row['UserLogin'] ?? '')));

            return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
        }
    ));
}

function payment_approval_list_approvers(): array
{
    $approvers = approval_list_users_for_type('Payment');
    if ($approvers !== []) {
        return $approvers;
    }

    return payment_approval_list_approvers_from_alert_subscribers();
}

function payment_approval_token_create(int $paymentId, int $supplierInvoiceId, int $userId): ?string
{
    return approval_token_create(
        'Payment',
        $paymentId,
        $userId,
        null,
        'SupplierInvoice',
        $supplierInvoiceId
    );
}

function payment_approval_token_invalidate(int $paymentId): void
{
    approval_token_invalidate('Payment', $paymentId);
}

function payment_approval_token_validate(string $token, int $paymentId): ?array
{
    return approval_token_validate('Payment', $paymentId, $token);
}

function payment_approval_token_resolve(string $token, int $paymentId): ?array
{
    return approval_token_resolve('Payment', $paymentId, $token);
}

function payment_approval_build_action_url(int $paymentId, string $token, string $action): string
{
    return approval_site_url()
        . '/accounting/invoice-payments/approve.php?id=' . $paymentId
        . '&token=' . rawurlencode($token)
        . '&action=' . rawurlencode($action);
}

function payment_approval_invoice_token_create(int $invoiceId, int $userId): ?string
{
    return approval_token_create('Payment', $invoiceId, $userId, 'SupplierInvoice');
}

function payment_approval_invoice_token_invalidate(int $invoiceId): void
{
    approval_token_invalidate('Payment', $invoiceId, 'SupplierInvoice');
}

function payment_approval_invoice_token_resolve(string $token, int $invoiceId): ?array
{
    return approval_token_resolve('Payment', $invoiceId, $token, 'SupplierInvoice');
}

function payment_approval_invoice_build_action_url(int $invoiceId, string $token, string $action): string
{
    return approval_site_url()
        . '/accounting/supplier-invoices/approve.php?id=' . $invoiceId
        . '&token=' . rawurlencode($token)
        . '&action=' . rawurlencode($action);
}
