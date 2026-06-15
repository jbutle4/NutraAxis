<?php

require_once __DIR__ . '/te.php';
require_once __DIR__ . '/te-attachments.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/alert-messages.php';
require_once __DIR__ . '/te-approval-token.php';

const TE_STATUS_CREATED = 'Created';
const TE_STATUS_SUBMITTED = 'Submitted for Approval';
const TE_STATUS_REJECTED = 'Rejected';
const TE_STATUS_APPROVED = 'Approved';
const TE_STATUS_SENT_BACK = 'Sent Back for Comment';
const TE_STATUS_VIEWED = 'Viewed by Approver';

const TE_APPROVAL_ACTIONS = [
    'approve'   => [
        'label'            => 'Approve Report',
        'result'           => 'Approved',
        'status'           => TE_STATUS_APPROVED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'reject'    => [
        'label'            => 'Reject Report',
        'result'           => 'Rejected',
        'status'           => TE_STATUS_REJECTED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'send_back' => [
        'label'            => 'Send Back with Comments',
        'result'           => 'Sent Back with Comments',
        'status'           => TE_STATUS_SENT_BACK,
        'require_comments' => true,
        'viewed_message'   => false,
    ],
    'cancel'    => [
        'label'            => 'Cancel',
        'result'           => 'Viewed by Approver',
        'status'           => TE_STATUS_VIEWED,
        'require_comments' => false,
        'viewed_message'   => true,
    ],
];

const TE_APPROVAL_LIST_SORT_COLUMNS = [
    'report_number' => 'Report #',
    'employee'      => 'Employee',
    'period'        => 'Period',
    'total'         => 'Total',
    'submitted_by'  => 'Submitted By',
];

const TE_APPROVAL_LIST_SORT_SQL = [
    'report_number' => 'r.ReportNumber',
    'employee'      => 'eu.UserName',
    'period'        => 'r.PeriodStart',
    'total'         => 'r.TotalReimbursementDue',
    'submitted_by'  => 'eu.UserName',
];

const TE_APPROVAL_LIST_SORT_NUMERIC = ['total'];

function te_require_approval_read(): void
{
    auth_require_login();
    if (te_can_read_approval_queue()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the T&E approval queue.');
}

function te_require_approval_action(): void
{
    te_require_approval_read();
    if (te_can_take_approval_action()) {
        return;
    }
    auth_render_access_denied('You do not have permission to act on T&E report approvals.');
}

function te_count_pending_approvals(): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dbo.TEReport WHERE ReportStatus = :status');
    $stmt->execute(['status' => TE_STATUS_SUBMITTED]);

    return (int) $stmt->fetchColumn();
}

function te_list_pending_approvals(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            r.ReportID,
            r.ReportNumber,
            r.ReportStatus,
            r.PeriodStart,
            r.PeriodEnd,
            r.TotalReimbursementDue,
            r.SubmittedAt,
            eu.UserName AS EmployeeName
        FROM dbo.TEReport r
        INNER JOIN dbo.[User] eu ON eu.UserID = r.EmployeeUserID
        WHERE r.ReportStatus = :status
    SQL;

    $sortState = table_sort_state(TE_APPROVAL_LIST_SORT_COLUMNS, 'period', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(TE_APPROVAL_LIST_SORT_SQL, $sortState, 'period', 'report_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => TE_STATUS_SUBMITTED]);

    return $stmt->fetchAll();
}

function te_list_approval_log(int $reportId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT ApprovalID, ReportID, ApproverName, ApproverResult, ApproverComments, LogDate
        FROM dbo.TEApprovalLog
        WHERE ReportID = :id
        ORDER BY LogDate DESC
    SQL);
    $stmt->execute(['id' => $reportId]);

    return $stmt->fetchAll();
}

function te_can_submit_for_approval(array $report): bool
{
    return in_array((string) ($report['ReportStatus'] ?? ''), [TE_STATUS_CREATED, TE_STATUS_SENT_BACK, TE_STATUS_REJECTED], true);
}

function te_submit_for_approval(int $reportId): array
{
    $report = te_get_report($reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'Expense report not found.'];
    }

    if (!te_can_submit_for_approval($report)) {
        return ['ok' => false, 'error' => 'This expense report cannot be submitted for approval in its current status.'];
    }

    if (empty($report['CertificationAccepted'])) {
        return ['ok' => false, 'error' => 'Certification must be accepted before submitting for approval.'];
    }

    te_recalculate_report_total($reportId);
    $report = te_get_report($reportId);

    $current = (string) $report['ReportStatus'];

    try {
        $pdo = db();
        te_approval_token_invalidate_for_report($reportId);

        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.TEReport
            SET ReportStatus = :status,
                SubmittedAt = SYSUTCDATETIME(),
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :modified_by
            WHERE ReportID = :id
        SQL);
        $stmt->execute([
            'status'      => TE_STATUS_SUBMITTED,
            'modified_by' => auth_user()['UserID'] ?? null,
            'id'          => $reportId,
        ]);

        $report['ReportStatus'] = TE_STATUS_SUBMITTED;
        $isResubmit = !in_array($current, [TE_STATUS_CREATED, TE_STATUS_SENT_BACK], true);
        $notify = te_notify_approvers_of_submission($report, $isResubmit);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => te_format_exception_message($e, 'submit this expense report for approval')];
    }
}

function te_resubmit_for_approval(int $reportId): array
{
    $report = te_get_report($reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'Expense report not found.'];
    }

    if ($report['ReportStatus'] !== TE_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => "Cannot resubmit for approval from status \"{$report['ReportStatus']}\"."];
    }

    try {
        te_approval_token_invalidate_for_report($reportId);
        $notify = te_notify_approvers_of_submission($report, true);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => te_format_exception_message($e, 'resubmit this expense report for approval')];
    }
}

function te_process_approval_action(int $reportId, string $action, string $comments = '', ?array $actingUser = null): array
{
    if (!isset(TE_APPROVAL_ACTIONS[$action])) {
        return ['ok' => false, 'error' => 'Invalid approval action.'];
    }

    $config = TE_APPROVAL_ACTIONS[$action];
    $comments = trim($comments);

    if ($config['require_comments'] && $comments === '') {
        return ['ok' => false, 'error' => 'Comments are required for this action.'];
    }

    $report = te_get_report($reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'Expense report not found.'];
    }

    if ($report['ReportStatus'] !== TE_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only expense reports submitted for approval can be actioned.'];
    }

    $user = $actingUser ?? auth_user();
    if ($user === null || empty($user['UserID'])) {
        return ['ok' => false, 'error' => 'Unable to identify the approver for this action.'];
    }

    $approverName = (string) ($user['UserName'] ?? 'Unknown Approver');
    $approverId = (int) $user['UserID'];

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $approveExtras = '';
        $approveParams = [];
        if ($config['status'] === TE_STATUS_APPROVED) {
            $approveExtras = ', ApprovedTotalDue = :approved_total, ApprovedAt = SYSUTCDATETIME()';
            $approveParams['approved_total'] = (float) ($report['TotalReimbursementDue'] ?? 0);
        }

        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.TEReport
            SET ReportStatus = :status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :modified_by
                {$approveExtras}
            WHERE ReportID = :id
        SQL);
        $stmt->execute(array_merge([
            'status'      => $config['status'],
            'modified_by' => $approverId,
            'id'          => $reportId,
        ], $approveParams));

        $log = $pdo->prepare(<<<SQL
            INSERT INTO dbo.TEApprovalLog (ReportID, ApproverName, ApproverResult, ApproverComments)
            OUTPUT INSERTED.ApprovalID AS inserted_id
            VALUES (:report, :name, :result, :comments)
        SQL);
        $log->execute([
            'report'   => $reportId,
            'name'     => $approverName,
            'result'   => $config['result'],
            'comments' => $comments !== '' ? $comments : null,
        ]);

        $pdo->commit();

        te_approval_token_invalidate_for_report($reportId);

        $report = te_get_report($reportId) ?? $report;
        te_notify_users_of_status_change($report, $config, $approverName, $comments);

        if ($config['status'] === TE_STATUS_APPROVED) {
            te_notify_processors_of_approval($report, $approverName, $comments);
        }

        return ['ok' => true, 'error' => null, 'status' => $config['status']];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => te_format_exception_message($e, 'process this approval action')];
    }
}

function te_approval_submitter_name(): string
{
    return (string) (auth_user()['UserName'] ?? 'An employee');
}

function te_format_approval_notify_message(array $notify): string
{
    return alert_format_notify_message(
        $notify,
        'No designated T&E approvers are configured. No approval email was sent.'
    );
}

function te_merge_approval_notify_results(array ...$results): array
{
    $merged = [
        'smtp_configured' => mail_smtp_is_configured(),
        'recipients'      => [],
        'sent'            => [],
        'failed'          => [],
        'skipped_reason'  => null,
    ];

    foreach ($results as $result) {
        if (($result['skipped_reason'] ?? null) !== null && $merged['skipped_reason'] === null) {
            $merged['skipped_reason'] = $result['skipped_reason'];
        }
        foreach ($result['recipients'] ?? [] as $email) {
            $merged['recipients'][] = $email;
        }
        foreach ($result['sent'] ?? [] as $email) {
            $merged['sent'][] = $email;
        }
        foreach ($result['failed'] ?? [] as $email => $message) {
            $merged['failed'][$email] = $message;
        }
    }

    $merged['recipients'] = array_values(array_unique($merged['recipients']));
    $merged['sent'] = array_values(array_unique($merged['sent']));
    if ($merged['sent'] !== []) {
        $merged['skipped_reason'] = null;
    }

    return $merged;
}

function te_notify_approvers_of_submission(array $report, bool $isResubmit = false): array
{
    $result = [
        'smtp_configured' => mail_smtp_is_configured(),
        'recipients'      => [],
        'sent'            => [],
        'failed'          => [],
        'skipped_reason'  => null,
    ];

    if (!mail_smtp_is_configured()) {
        $result['skipped_reason'] = 'smtp_not_configured';

        return $result;
    }

    $approvers = te_list_te_approvers();
    $submitter = te_approval_submitter_name();
    $reportId = (int) $report['ReportID'];
    $reportNumber = (string) $report['ReportNumber'];
    $subject = $isResubmit
        ? "T&E report {$reportNumber} resubmitted for approval"
        : "T&E report {$reportNumber} submitted for approval";

    if ($approvers === []) {
        $result['skipped_reason'] = 'no_subscribers';
    } else {
        foreach ($approvers as $approver) {
            $email = strtolower(trim((string) $approver['UserLogin']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $token = te_approval_token_create($reportId, (int) $approver['UserID']);
            if ($token === null) {
                $result['failed'][$email] = 'Unable to create approval token.';
                continue;
            }

            $actionUrls = [
                'approve'   => te_approval_build_action_url($reportId, $token, 'approve'),
                'reject'    => te_approval_build_action_url($reportId, $token, 'reject'),
                'send_back' => te_approval_build_action_url($reportId, $token, 'send_back'),
                'review'    => te_approval_site_url()
                    . '/travel-expense/approve.php?id=' . $reportId
                    . '&token=' . rawurlencode($token),
            ];

            $htmlBody = te_approval_build_action_email_html($report, $submitter, $isResubmit, $actionUrls);
            $plainBody = implode("\n", [
                $isResubmit
                    ? 'A travel and expense report has been resubmitted for your approval.'
                    : 'A travel and expense report has been submitted for your approval.',
                '',
                "Report #: {$reportNumber}",
                'Employee: ' . ($report['EmployeeName'] ?? ''),
                'Total due: ' . te_format_money((float) ($report['TotalReimbursementDue'] ?? 0)),
                "Submitted by: {$submitter}",
                '',
                'Approve: ' . $actionUrls['approve'],
                'Reject: ' . $actionUrls['reject'],
                'Return for comment: ' . $actionUrls['send_back'],
                'Review report: ' . $actionUrls['review'],
            ]);

            $result['recipients'][] = $email;
            $greeting = 'Hello ' . ((string) ($approver['UserName'] ?? $email)) . ',';
            $send = mail_send_multi_result([$email => (string) ($approver['UserName'] ?? $email)], [], $subject, $greeting . "\n\n" . $plainBody, $htmlBody);
            if ($send['ok']) {
                $result['sent'][] = $email;
            } else {
                $result['failed'][$email] = (string) ($send['error'] ?? 'SMTP send failed.');
            }
        }

        if ($result['sent'] === [] && $result['failed'] === []) {
            $result['skipped_reason'] = 'no_subscribers';
        }
    }

    return $result;
}

function te_notify_users_of_status_change(array $report, array $config, string $approverName, string $comments): void
{
    $reportNumber = (string) $report['ReportNumber'];
    $siteUrl = te_approval_site_url();
    $alertName = !empty($config['viewed_message']) ? ALERT_NAME_TE_VIEWED_BY_APPROVER : ALERT_NAME_TE_STATUS_UPDATE;

    if (!empty($config['viewed_message'])) {
        $subject = "T&E report {$reportNumber} viewed by approver";
        $body = implode("\n", [
            "Travel & expense report {$reportNumber} was accessed and viewed by the approver.",
            '',
            "Approver: {$approverName}",
            '',
            "View report: {$siteUrl}/travel-expense/view.php?id=" . (int) $report['ReportID'],
        ]);
    } else {
        $status = (string) ($config['status'] ?? $config['result'] ?? 'Updated');
        $subject = "T&E report {$reportNumber} status changed to {$status}";
        $body = implode("\n", [
            'The travel and expense report status has been updated.',
            '',
            "Report #: {$reportNumber}",
            "New status: {$status}",
            "Actioned by: {$approverName}",
        ]);
        if ($comments !== '') {
            $body .= "\nComments: {$comments}";
        }
        $body .= "\n\nView report: {$siteUrl}/travel-expense/view.php?id=" . (int) $report['ReportID'];
    }

    alert_send_message($alertName, $subject, $body);
}

function te_build_processor_attachments(array $report, array $totals, string $approverName, string $comments): array
{
    $attachments = [];
    $summaryName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($report['ReportNumber'] ?? 'TE-report')) . '-summary.txt';
    $summaryText = te_build_summary_text($report, $totals);
    $summaryText .= "\n\nApproved by: {$approverName}\n";
    if ($comments !== '') {
        $summaryText .= "Approver comments: {$comments}\n";
    }
    $attachments[] = [
        'filename'     => $summaryName,
        'content'      => $summaryText,
        'content_type' => 'text/plain; charset=UTF-8',
    ];

    foreach (te_list_attachments((int) $report['ReportID']) as $meta) {
        $full = te_get_attachment((int) $meta['AttachmentID']);
        if ($full === null) {
            continue;
        }
        $attachments[] = [
            'filename'     => (string) $full['FileName'],
            'content'      => (string) $full['FileData'],
            'content_type' => (string) ($full['ContentType'] ?: 'application/pdf'),
        ];
    }

    return $attachments;
}

function te_notify_processors_of_approval(array $report, string $approverName, string $comments): array
{
    $result = [
        'smtp_configured' => mail_smtp_is_configured(),
        'recipients'      => [],
        'sent'            => [],
        'failed'          => [],
        'skipped_reason'  => null,
    ];

    if (!mail_smtp_is_configured()) {
        $result['skipped_reason'] = 'smtp_not_configured';

        return $result;
    }

    $processors = te_list_po_processors();
    if ($processors === []) {
        $result['skipped_reason'] = 'no_subscribers';
        error_log('te_notify_processors_of_approval skipped (no IsPOProcessor users) for ' . ($report['ReportNumber'] ?? ''));

        return $result;
    }

    $totals = te_calculate_totals((int) $report['ReportID'], (float) ($report['MileageRate'] ?? 0.70));
    $reportNumber = (string) $report['ReportNumber'];
    $subject = "Approved T&E report {$reportNumber} ready for processing";
    $viewUrl = te_approval_site_url() . '/travel-expense/view.php?id=' . (int) $report['ReportID'];
    $printUrl = te_approval_site_url() . '/travel-expense/print.php?id=' . (int) $report['ReportID'];
    $plainBody = implode("\n", [
        'An approved travel and expense report is ready for PO/payroll processing.',
        '',
        "Report #: {$reportNumber}",
        'Employee: ' . ($report['EmployeeName'] ?? ''),
        'Period: ' . te_period_label($report),
        'Total due: ' . te_format_money((float) ($report['TotalReimbursementDue'] ?? 0)),
        "Approved by: {$approverName}",
    ]);
    if ($comments !== '') {
        $plainBody .= "\nApprover comments: {$comments}";
    }
    $plainBody .= "\n\nView report: {$viewUrl}\nPrintable copy: {$printUrl}\n\nReceipt PDFs and a summary text file are attached.";

    $htmlBody = nl2br(htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8'));
    $attachments = te_build_processor_attachments($report, $totals, $approverName, $comments);

    foreach ($processors as $processor) {
        $email = strtolower(trim((string) $processor['UserLogin']));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $result['recipients'][] = $email;
        $greeting = 'Hello ' . ((string) ($processor['UserName'] ?? $email)) . ',';
        $send = mail_send_multi_attachments_result(
            [$email => (string) ($processor['UserName'] ?? $email)],
            [],
            $subject,
            $greeting . "\n\n" . $plainBody,
            $htmlBody,
            $attachments
        );
        if ($send['ok']) {
            $result['sent'][] = $email;
        } else {
            $result['failed'][$email] = (string) ($send['error'] ?? 'SMTP send failed.');
        }
    }

    return $result;
}
