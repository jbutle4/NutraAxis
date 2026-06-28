<?php

require_once __DIR__ . '/te.php';
require_once __DIR__ . '/approval-token.php';

const TE_APPROVAL_TOKEN_BYTES = APPROVAL_TOKEN_BYTES;
const TE_APPROVAL_TOKEN_EXPIRY_DAYS = APPROVAL_TOKEN_EXPIRY_DAYS;

function te_approval_site_url(): string
{
    return approval_site_url();
}

function te_approval_token_hash(string $token): string
{
    return approval_token_hash($token);
}

function te_approval_token_purge_expired(): void
{
    approval_token_purge_expired();
}

function te_list_te_approvers(): array
{
    return approval_list_users_for_type('TE');
}

function te_list_po_processors(): array
{
    require_once __DIR__ . '/admin.php';

    return admin_list_users_with_permission('TEProcessing', 'R');
}

function te_approval_token_create(int $reportId, int $userId): ?string
{
    return approval_token_create('TE', $reportId, $userId);
}

function te_approval_token_invalidate_for_report(int $reportId): void
{
    approval_token_invalidate('TE', $reportId);
}

function te_approval_token_validate(string $token, int $reportId): ?array
{
    return approval_token_validate('TE', $reportId, $token);
}

function te_approval_token_resolve(string $token, int $reportId): ?array
{
    return approval_token_resolve('TE', $reportId, $token);
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
    $intro = $isResubmit
        ? 'A travel and expense report has been resubmitted for your approval.'
        : 'A travel and expense report has been submitted for your approval.';

    return approval_build_action_email_html(
        $intro,
        [
            'Report #'     => (string) ($report['ReportNumber'] ?? ''),
            'Employee'     => (string) ($report['EmployeeName'] ?? ''),
            'Period'       => te_period_label($report),
            'Total due'    => te_format_money((float) ($report['TotalReimbursementDue'] ?? 0)),
            'Submitted by' => $submitter,
        ],
        $actionUrls,
        'review the full expense report'
    );
}
