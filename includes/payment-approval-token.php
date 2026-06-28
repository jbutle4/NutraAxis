<?php

require_once __DIR__ . '/approval-token.php';

function payment_approval_list_approvers(): array
{
    return approval_list_users_for_type('Payment');
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
