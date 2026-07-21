<?php

require_once __DIR__ . '/approval-token.php';

const PO_APPROVAL_TOKEN_BYTES = APPROVAL_TOKEN_BYTES;
const PO_APPROVAL_TOKEN_EXPIRY_DAYS = APPROVAL_TOKEN_EXPIRY_DAYS;

function po_approval_site_url(): string
{
    return approval_site_url();
}

function po_approval_token_hash(string $token): string
{
    return approval_token_hash($token);
}

function po_approval_token_purge_expired(): void
{
    approval_token_purge_expired();
}

function po_list_po_approvers(): array
{
    return approval_list_users_for_type('PO');
}

function po_approval_token_create(int $poId, int $userId): ?string
{
    return approval_token_create('PO', $poId, $userId);
}

function po_approval_token_invalidate_for_po(int $poId): void
{
    approval_token_invalidate('PO', $poId);
}

function po_approval_token_validate(string $token, int $poId): ?array
{
    return approval_token_validate('PO', $poId, $token);
}

function po_approval_token_resolve(string $token, int $poId): ?array
{
    return approval_token_resolve('PO', $poId, $token);
}

function po_approval_build_action_url(int $poId, string $token, string $action): string
{
    return approval_site_url()
        . '/po-management/approval-action.php?id=' . $poId
        . '&token=' . rawurlencode($token)
        . '&action=' . rawurlencode($action);
}

function po_approval_build_action_email_html(array $order, string $submitter, bool $isResubmit, array $actionUrls): string
{
    $intro = $isResubmit
        ? 'A purchase order has been resubmitted for your approval.'
        : 'A purchase order has been submitted for your approval.';

    return approval_build_action_email_html(
        $intro,
        [
            'PO #'         => (string) ($order['PONumber'] ?? ''),
            'Supplier'     => (string) ($order['SupplierName'] ?? ''),
            'Total due'    => po_format_money($order['TotalDue'] ?? 0),
            'Submitted by' => $submitter,
        ],
        $actionUrls,
        'review the full purchase order'
    );
}
