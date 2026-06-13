<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/alert-messages.php';

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

function po_submit_for_approval(int $poId): array
{
    $order = po_get_order($poId);
    if ($order === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    $current = $order['POStatus'];
    if (!in_array($current, [PO_STATUS_CREATED, PO_STATUS_SENT_BACK], true)) {
        return ['ok' => false, 'error' => "Cannot submit for approval from status \"{$current}\"."];
    }

    try {
        $pdo = db();
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

        $notify = po_notify_approvers_of_submission($order);

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

function po_process_approval_action(int $poId, string $action, string $comments = ''): array
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

    $user = auth_user();
    $approverName = (string) ($user['UserName'] ?? 'Unknown Approver');

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.PurchaseOrder
            SET POStatus = :status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedbyUser = :modified_by
            WHERE POID = :id
        SQL);
        $stmt->execute([
            'status'      => $config['status'],
            'modified_by' => $user['UserID'] ?? null,
            'id'          => $poId,
        ]);

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
    return alert_recipient_email_list(ALERT_NAME_PO_APPROVAL_REQUEST);
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

    $poNumber = (string) $order['PONumber'];
    $submitter = (string) (auth_user()['UserName'] ?? 'A PO user');
    $subject = $isResubmit
        ? "PO {$poNumber} resubmitted for approval"
        : "PO {$poNumber} submitted for approval";
    $reviewUrl = env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net') . '/po-management/approve.php?id=' . (int) $order['POID'];
    $intro = $isResubmit
        ? 'A purchase order approval notification has been resent for your review.'
        : 'A purchase order has been submitted for your review.';
    $body = implode("\n", [
        $intro,
        '',
        "PO Number: {$poNumber}",
        'Supplier: ' . ($order['SupplierName'] ?? ''),
        ($isResubmit ? "Resent by: {$submitter}" : "Submitted by: {$submitter}"),
        'Status: Submitted for Approval',
        '',
        "Review PO: {$reviewUrl}",
    ]);

    $result = alert_send_message(ALERT_NAME_PO_APPROVAL_REQUEST, $subject, $body);
    if (($result['skipped_reason'] ?? null) === 'no_subscribers') {
        error_log('po_notify_approvers_of_submission skipped (no alert subscribers) for PO ' . $poNumber);
    }

    return $result;
}

function po_format_approval_notify_message(array $notify): string
{
    return alert_format_notify_message(
        $notify,
        'No users are subscribed to PO Approval Request alerts. No approval email was sent.'
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
