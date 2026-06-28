<?php

require_once __DIR__ . '/approval-token.php';

function qbo_insert_list_approvers(): array
{
    return approval_list_users_for_type('QBOInsert');
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
