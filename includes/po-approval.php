<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/alert-messages.php';
require_once __DIR__ . '/po-approval-token.php';

const PO_STATUS_CREATED = 'Created';
const PO_STATUS_SUBMITTED = 'Submitted for Approval';
const PO_STATUS_REJECTED = 'Rejected';
const PO_STATUS_APPROVED = 'Approved';
const PO_STATUS_SENT_BACK = 'Sent Back for Comment';
const PO_STATUS_VIEWED = 'Viewed by Approver';
const PO_STATUS_ACCOUNTING = 'Submitted to Accounting for Payment';
const PO_STATUS_PAID = 'Paid';

const PO_APPROVAL_ACTIONS = [
    'approve'   => [
        'label'            => 'Approve PO',
        'result'           => 'Approved',
        'status'           => PO_STATUS_APPROVED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'reject'    => [
        'label'            => 'Reject PO',
        'result'           => 'Rejected',
        'status'           => PO_STATUS_REJECTED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'send_back' => [
        'label'            => 'Send Back with Comments',
        'result'           => 'Sent Back with Comments',
        'status'           => PO_STATUS_SENT_BACK,
        'require_comments' => true,
        'viewed_message'   => false,
    ],
    'cancel'    => [
        'label'            => 'Cancel',
        'result'           => 'Viewed by Approver',
        'status'           => PO_STATUS_VIEWED,
        'require_comments' => false,
        'viewed_message'   => true,
    ],
];

function po_require_approval_read(): void
{
    auth_require_login();
    if (po_can_read_approval_queue()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the PO approval queue.');
}

function po_require_approval_action(): void
{
    po_require_approval_read();
    if (po_can_take_approval_action()) {
        return;
    }
    auth_render_access_denied('You do not have permission to act on purchase order approvals.');
}

function po_count_pending_approvals(): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dbo.PurchaseOrder WHERE POStatus = :status');
    $stmt->execute(['status' => PO_STATUS_SUBMITTED]);

    return (int) $stmt->fetchColumn();
}

function po_list_pending_approvals(): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            po.POID,
            po.PONumber,
            po.POStatus,
            po.OrderDate,
            po.Subtotal,
            po.TotalDue,
            po.CreateDate,
            s.SupplierName,
            cu.UserName AS CreatedByName
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        INNER JOIN dbo.[User] cu ON cu.UserID = po.CreatedByUser
        WHERE po.POStatus = :status
        ORDER BY po.CreateDate ASC
    SQL);
    $stmt->execute(['status' => PO_STATUS_SUBMITTED]);

    return $stmt->fetchAll();
}

function po_list_approval_log(int $poId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT ApprovalID, POID, ApproverName, ApproverResult, ApproverComments, LogDate
        FROM dbo.POApprovalLog
        WHERE POID = :id
        ORDER BY LogDate DESC
    SQL);
    $stmt->execute(['id' => $poId]);

    return $stmt->fetchAll();
}

function po_can_submit_for_approval(array $order): bool
{
    $status = (string) ($order['POStatus'] ?? '');

    if (in_array($status, [PO_STATUS_CREATED, PO_STATUS_SENT_BACK, PO_STATUS_REJECTED], true)) {
        return true;
    }

    return $status === PO_STATUS_APPROVED && po_requires_reapproval($order);
}

function po_submit_for_approval(int $poId): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if (!po_can_submit_for_approval($order)) {
        return ['ok' => false, 'error' => 'This purchase order cannot be submitted for approval in its current status.'];
    }

    $current = $order['POStatus'];

    try {
        $pdo = db();
        po_approval_token_invalidate_for_po($poId);

        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.PurchaseOrder
            SET POStatus = :status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedbyUser = :modified_by
            WHERE POID = :id
        SQL);
        $stmt->execute([
            'status'      => PO_STATUS_SUBMITTED,
            'modified_by' => auth_user()['UserID'] ?? null,
            'id'          => $poId,
        ]);

        require_once __DIR__ . '/audit.php';
        audit_log_po_status_change($poId, $current, PO_STATUS_SUBMITTED);

        $order['POStatus'] = PO_STATUS_SUBMITTED;
        $isResubmit = !in_array($current, [PO_STATUS_CREATED, PO_STATUS_SENT_BACK], true);
        $notify = po_notify_approvers_of_submission($order, $isResubmit);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => po_format_exception_message($e, 'submit this purchase order for approval')];
    }
}

function po_resubmit_for_approval(int $poId): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if ($order['POStatus'] !== PO_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => "Cannot resubmit for approval from status \"{$order['POStatus']}\"."];
    }

    try {
        $pdo = db();
        po_approval_token_invalidate_for_po($poId);

        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.PurchaseOrder
            SET ModifiedDate = SYSUTCDATETIME(),
                ModifiedbyUser = :modified_by
            WHERE POID = :id
        SQL);
        $stmt->execute([
            'modified_by' => auth_user()['UserID'] ?? null,
            'id'          => $poId,
        ]);

        $notify = po_notify_approvers_of_submission($order, true);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => po_format_exception_message($e, 'resubmit this purchase order for approval')];
    }
}

function po_process_approval_action(int $poId, string $action, string $comments = '', ?array $actingUser = null): array
{
    if (!isset(PO_APPROVAL_ACTIONS[$action])) {
        return ['ok' => false, 'error' => 'Invalid approval action.'];
    }

    $config = PO_APPROVAL_ACTIONS[$action];
    $comments = trim($comments);

    if ($config['require_comments'] && $comments === '') {
        return ['ok' => false, 'error' => 'Comments are required for this action.'];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    if ($order['POStatus'] !== PO_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only purchase orders submitted for approval can be actioned.'];
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
        if ($config['status'] === PO_STATUS_APPROVED) {
            $approveExtras = ', ApprovedTotalDue = :approved_total, ApprovedAt = SYSUTCDATETIME(), RequiresReapproval = 0';
            $approveParams['approved_total'] = (float) $order['TotalDue'];
        }

        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.PurchaseOrder
            SET POStatus = :status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedbyUser = :modified_by
                {$approveExtras}
            WHERE POID = :id
        SQL);
        $stmt->execute(array_merge([
            'status'      => $config['status'],
            'modified_by' => $approverId,
            'id'          => $poId,
        ], $approveParams));

        $log = $pdo->prepare(<<<SQL
            INSERT INTO dbo.POApprovalLog (POID, ApproverName, ApproverResult, ApproverComments)
            OUTPUT INSERTED.ApprovalID AS inserted_id
            VALUES (:po, :name, :result, :comments)
        SQL);
        $log->execute([
            'po'       => $poId,
            'name'     => $approverName,
            'result'   => $config['result'],
            'comments' => $comments !== '' ? $comments : null,
        ]);
        $approvalId = db_fetch_inserted_int($log, 'inserted_id');

        $pdo->commit();

        po_approval_token_invalidate_for_po($poId);

        require_once __DIR__ . '/audit.php';
        audit_log_po_approval_action($poId, PO_STATUS_SUBMITTED, $config['status'], [
            'ApprovalID'       => $approvalId,
            'POID'             => $poId,
            'ApproverName'     => $approverName,
            'ApproverResult'   => $config['result'],
            'ApproverComments' => $comments !== '' ? $comments : null,
        ]);

        po_notify_po_users_of_status_change($order, $config, $approverName, $comments);

        return ['ok' => true, 'error' => null, 'status' => $config['status']];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => po_format_exception_message($e, 'process this approval action')];
    }
}

function po_advance_accounting_status(int $poId, string $newStatus): array
{
    if (!in_array($newStatus, [PO_STATUS_ACCOUNTING, PO_STATUS_PAID], true)) {
        return ['ok' => false, 'error' => 'Invalid accounting status.'];
    }

    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    $allowed = match ($newStatus) {
        PO_STATUS_ACCOUNTING => $order['POStatus'] === PO_STATUS_APPROVED,
        PO_STATUS_PAID       => $order['POStatus'] === PO_STATUS_ACCOUNTING,
        default              => false,
    };

    if (!$allowed) {
        return ['ok' => false, 'error' => "Cannot change status from {$order['POStatus']} to {$newStatus}."];
    }

    if ($newStatus === PO_STATUS_ACCOUNTING && po_requires_reapproval($order)) {
        return [
            'ok'    => false,
            'error' => 'Total due changed after approval. Resubmit this purchase order for approval before sending to accounting.',
        ];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.PurchaseOrder
        SET POStatus = :status,
            ModifiedDate = SYSUTCDATETIME(),
            ModifiedbyUser = :modified_by
        WHERE POID = :id
    SQL);
    $oldStatus = $order['POStatus'];
    $stmt->execute([
        'status'      => $newStatus,
        'modified_by' => auth_user()['UserID'] ?? null,
        'id'          => $poId,
    ]);

    require_once __DIR__ . '/audit.php';
    audit_log_po_status_change($poId, $oldStatus, $newStatus);

    po_notify_po_users_of_status_change($order, [
        'result'         => $newStatus,
        'status'         => $newStatus,
        'viewed_message' => false,
    ], (string) (auth_user()['UserName'] ?? 'System'), '');

    return ['ok' => true, 'error' => null];
}

function po_recipient_emails_for_approvers(): array
{
    $emails = [];
    foreach (po_list_po_approvers() as $approver) {
        $email = strtolower(trim((string) $approver['UserLogin']));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = (string) ($approver['UserName'] ?? $email);
        }
    }

    return $emails;
}

function po_merge_approval_notify_results(array ...$results): array
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

function po_notify_approval_watchers(array $order, bool $isResubmit, string $submitter): array
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

    $alertRecipients = alert_message_recipients(ALERT_NAME_PO_APPROVAL_REQUEST);
    $approverEmails = array_keys(po_recipient_emails_for_approvers());
    $watchers = $alertRecipients['cc'];

    foreach ($alertRecipients['to'] as $email => $name) {
        if (!isset($watchers[$email]) && !in_array($email, $approverEmails, true)) {
            $watchers[$email] = $name;
        }
    }

    foreach ($approverEmails as $email) {
        unset($watchers[$email]);
    }

    if ($watchers === []) {
        $result['skipped_reason'] = 'no_subscribers';

        return $result;
    }

    $poNumber = (string) $order['PONumber'];
    $subject = $isResubmit
        ? "PO {$poNumber} resubmitted for approval"
        : "PO {$poNumber} submitted for approval";
    $viewUrl = po_approval_site_url() . '/po-management/view.php?id=' . (int) $order['POID'];
    $body = implode("\n", [
        $isResubmit
            ? 'A purchase order has been resubmitted for approval.'
            : 'A purchase order has been submitted for approval.',
        '',
        "PO Number: {$poNumber}",
        'Supplier: ' . ($order['SupplierName'] ?? ''),
        'Total due: ' . po_format_money((float) ($order['TotalDue'] ?? 0)),
        ($isResubmit ? "Resubmitted by: {$submitter}" : "Submitted by: {$submitter}"),
        'Status: Submitted for Approval',
        '',
        'This is a notification only. Only designated PO approvers can approve or reject.',
        '',
        "View PO: {$viewUrl}",
    ]);

    $result['recipients'] = array_keys($watchers);
    $send = mail_send_multi_result($watchers, [], $subject, 'Hello,' . "\n\n" . $body);
    if ($send['ok']) {
        $result['sent'] = $result['recipients'];
    } else {
        foreach ($result['recipients'] as $email) {
            $result['failed'][$email] = (string) ($send['error'] ?? 'SMTP send failed.');
        }
    }

    return $result;
}

function po_notify_approvers_of_submission(array $order, bool $isResubmit = false): array
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

        return po_merge_approval_notify_results($result, po_notify_approval_watchers($order, $isResubmit, po_approval_submitter_name()));
    }

    $approvers = po_list_po_approvers();
    $submitter = po_approval_submitter_name();
    $poId = (int) $order['POID'];
    $poNumber = (string) $order['PONumber'];
    $subject = $isResubmit
        ? "PO {$poNumber} resubmitted for approval"
        : "PO {$poNumber} submitted for approval";

    if ($approvers === []) {
        error_log('po_notify_approvers_of_submission skipped (no IsPOApprover users) for PO ' . $poNumber);
        $result['skipped_reason'] = 'no_subscribers';
    } else {
        foreach ($approvers as $approver) {
            $email = strtolower(trim((string) $approver['UserLogin']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $token = po_approval_token_create($poId, (int) $approver['UserID']);
            if ($token === null) {
                $result['failed'][$email] = 'Unable to create approval token.';
                continue;
            }

            $actionUrls = [
                'approve'   => po_approval_build_action_url($poId, $token, 'approve'),
                'reject'    => po_approval_build_action_url($poId, $token, 'reject'),
                'send_back' => po_approval_build_action_url($poId, $token, 'send_back'),
                'review'    => po_approval_site_url()
                    . '/po-management/approve.php?id=' . $poId
                    . '&token=' . rawurlencode($token),
            ];

            $htmlBody = po_approval_build_action_email_html($order, $submitter, $isResubmit, $actionUrls);
            $plainBody = implode("\n", [
                $isResubmit
                    ? 'A purchase order has been resubmitted for your approval.'
                    : 'A purchase order has been submitted for your approval.',
                '',
                "PO Number: {$poNumber}",
                'Supplier: ' . ($order['SupplierName'] ?? ''),
                'Total due: ' . po_format_money((float) ($order['TotalDue'] ?? 0)),
                "Submitted by: {$submitter}",
                '',
                'Approve: ' . $actionUrls['approve'],
                'Reject: ' . $actionUrls['reject'],
                'Return for comment: ' . $actionUrls['send_back'],
                'Review PO: ' . $actionUrls['review'],
                '',
                'These links expire in ' . PO_APPROVAL_TOKEN_EXPIRY_DAYS . ' days.',
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

    return po_merge_approval_notify_results($result, po_notify_approval_watchers($order, $isResubmit, $submitter));
}

function po_approval_submitter_name(): string
{
    return (string) (auth_user()['UserName'] ?? 'A PO user');
}

function po_format_approval_notify_message(array $notify): string
{
    return alert_format_notify_message(
        $notify,
        'No designated PO approvers are configured. No approval email was sent.'
    );
}

function po_notify_po_users_of_status_change(array $order, array $config, string $approverName, string $comments): void
{
    $poNumber = (string) $order['PONumber'];
    $siteUrl = env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net');
    $alertName = !empty($config['viewed_message'])
        ? ALERT_NAME_PO_VIEWED_BY_APPROVER
        : ALERT_NAME_PO_STATUS_UPDATE;

    if (!empty($config['viewed_message'])) {
        $subject = "PO {$poNumber} viewed by approver";
        $body = implode("\n", [
            "Purchase order {$poNumber} was accessed and viewed by the approver.",
            '',
            "Approver: {$approverName}",
            'Status: Viewed by Approver',
            '',
            "View PO: {$siteUrl}/po-management/view.php?id=" . (int) $order['POID'],
        ]);
    } else {
        $status = (string) ($config['status'] ?? $config['result'] ?? 'Updated');
        $subject = "PO {$poNumber} status changed to {$status}";
        $body = implode("\n", [
            "The purchase order status has been updated.",
            '',
            "PO Number: {$poNumber}",
            "New status: {$status}",
            "Actioned by: {$approverName}",
        ]);
        if ($comments !== '') {
            $body .= "\nComments: {$comments}";
        }
        $body .= "\n\nView PO: {$siteUrl}/po-management/view.php?id=" . (int) $order['POID'];
    }

    $result = alert_send_message($alertName, $subject, $body);
    if (($result['skipped_reason'] ?? null) === 'no_subscribers') {
        error_log('po_notify_po_users_of_status_change skipped (no alert subscribers) for PO ' . $poNumber);
    }
}
