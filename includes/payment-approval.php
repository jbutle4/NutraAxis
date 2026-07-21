<?php

require_once __DIR__ . '/approval.php';
require_once __DIR__ . '/po-payment.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/alert-messages.php';
require_once __DIR__ . '/payment-approval-token.php';
require_once __DIR__ . '/qbo-insert-approval.php';

const PAYMENT_APPROVAL_STATUS_PENDING = 'Pending';
const PAYMENT_APPROVAL_STATUS_SUBMITTED = 'Submitted for Approval';
const PAYMENT_APPROVAL_STATUS_SENT_BACK = 'Sent Back for Comment';
const PAYMENT_APPROVAL_STATUS_PAID = 'Paid';
const PAYMENT_APPROVAL_STATUS_TRANSMITTED = 'Transmitted to QBO';
const PAYMENT_APPROVAL_STATUS_FAILED = 'Failed';
const PAYMENT_APPROVAL_STATUS_CANCELLED = 'Cancelled';

const PAYMENT_APPROVAL_INVOICE_PAYABLE_STATUSES = [
    'Draft',
    'Sent Back for Comment',
    'Rejected',
    'Failed',
    'Posted',
];

const PAYMENT_APPROVAL_EDITABLE_STATUSES = [
    PAYMENT_APPROVAL_STATUS_PENDING,
    PAYMENT_APPROVAL_STATUS_SENT_BACK,
];

const PAYMENT_APPROVAL_ACTIONS = [
    'approve'   => [
        'label'            => 'Approve Payment & Post to QBO',
        'result'           => 'Approved',
        'status'           => PAYMENT_APPROVAL_STATUS_TRANSMITTED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'reject'    => [
        'label'            => 'Reject',
        'result'           => 'Rejected',
        'status'           => PAYMENT_APPROVAL_STATUS_CANCELLED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'send_back' => [
        'label'            => 'Send Back with Comments',
        'result'           => 'Sent Back with Comments',
        'status'           => PAYMENT_APPROVAL_STATUS_SENT_BACK,
        'require_comments' => true,
        'viewed_message'   => false,
    ],
    'cancel'    => [
        'label'            => 'Cancel',
        'result'           => 'Viewed by Approver',
        'status'           => null,
        'require_comments' => false,
        'viewed_message'   => true,
    ],
];

const PAYMENT_APPROVAL_LIST_SORT_COLUMNS = [
    'payment_date' => 'Payment date',
    'reference'    => 'Invoice',
    'supplier'     => 'Supplier',
    'amount'       => 'Amount',
    'submitted'    => 'Requested by',
];

const PAYMENT_APPROVAL_LIST_SORT_SQL = [
    'payment_date' => 'p.PaymentDate',
    'reference'    => 'si.DocNumber',
    'supplier'     => 's.SupplierName',
    'amount'       => 'p.PaymentAmount',
    'submitted'    => 'cu.UserName',
];

const PAYMENT_APPROVAL_LIST_SORT_NUMERIC = ['amount'];

const PAYMENT_APPROVAL_INVOICE_LIST_SORT_COLUMNS = [
    'doc_number' => 'Invoice #',
    'supplier'   => 'Supplier',
    'txn_date'   => 'Invoice date',
    'total'      => 'Total',
    'submitted'  => 'Submitted By',
];

const PAYMENT_APPROVAL_INVOICE_LIST_SORT_SQL = [
    'doc_number' => 'si.DocNumber',
    'supplier'   => 's.SupplierName',
    'txn_date'   => 'si.TxnDate',
    'total'      => 'si.TotalAmt',
    'submitted'  => 'cu.UserName',
];

const PAYMENT_APPROVAL_INVOICE_LIST_SORT_NUMERIC = ['total'];

const PAYMENT_APPROVAL_INVOICE_ACTIONS = [
    'approve'   => [
        'label'            => 'Approve Payment',
        'result'           => 'Approved',
        'status'           => QBO_INSERT_STATUS_POSTED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'reject'    => [
        'label'            => 'Reject',
        'result'           => 'Rejected',
        'status'           => QBO_INSERT_STATUS_REJECTED,
        'require_comments' => false,
        'viewed_message'   => false,
    ],
    'send_back' => [
        'label'            => 'Send Back with Comments',
        'result'           => 'Sent Back with Comments',
        'status'           => QBO_INSERT_STATUS_SENT_BACK,
        'require_comments' => true,
        'viewed_message'   => false,
    ],
    'cancel'    => [
        'label'            => 'Cancel',
        'result'           => 'Viewed by Approver',
        'status'           => null,
        'require_comments' => false,
        'viewed_message'   => true,
    ],
];

function payment_approval_is_stub_mode(): bool
{
    return filter_var(env('QBO_INSERT_STUB', '1'), FILTER_VALIDATE_BOOLEAN);
}

function payment_approval_actions(): array
{
    $actions = PAYMENT_APPROVAL_ACTIONS;
    if (payment_approval_is_stub_mode()) {
        $actions['approve']['label'] = 'Approve Payment (Test Mode)';
    }

    return $actions;
}

function payment_approval_invoice_submit_error(?array $invoice): ?string
{
    if ($invoice === null) {
        return 'Linked supplier invoice not found.';
    }

    $status = (string) ($invoice['SyncStatus'] ?? '');
    if ($status === 'Submitted for Approval') {
        return 'This invoice is already submitted for approval.';
    }

    if ($status === 'Voided') {
        return 'This supplier invoice is voided and cannot be paid.';
    }

    if (!in_array($status, PAYMENT_APPROVAL_INVOICE_PAYABLE_STATUSES, true)) {
        return 'This supplier invoice cannot be submitted for payment approval in its current status.';
    }

    require_once __DIR__ . '/qbo-insert-approval.php';
    if (!qbo_insert_has_invoice_attachment((int) $invoice['SupplierInvoiceID'])) {
        return 'Upload a copy of the supplier invoice (Invoice PDF) before submitting payment for approval.';
    }

    return null;
}

function payment_approval_can_read_queue(): bool
{
    return approval_can_read_type('Payment');
}

function payment_approval_can_take_action(): bool
{
    return approval_can_act_on_type('Payment');
}

function payment_approval_require_read(): void
{
    auth_require_login();
    if (payment_approval_can_read_queue()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the payment approval queue.');
}

function payment_approval_require_action(): void
{
    payment_approval_require_read();
    if (payment_approval_can_take_action()) {
        return;
    }
    auth_render_access_denied('You do not have permission to act on payment approvals.');
}

function payment_approval_is_invoice_payment(?array $payment): bool
{
    return $payment !== null && !empty($payment['SupplierInvoiceID']);
}

function payment_approval_is_editable(?array $payment): bool
{
    if (!payment_approval_is_invoice_payment($payment)) {
        return false;
    }

    return in_array((string) ($payment['PaymentStatus'] ?? ''), PAYMENT_APPROVAL_EDITABLE_STATUSES, true);
}

function payment_approval_invoice_has_approved(int $invoiceId): bool
{
    foreach (payment_approval_invoice_list_log($invoiceId) as $entry) {
        if (($entry['ApproverResult'] ?? '') === 'Approved') {
            return true;
        }
    }

    return false;
}

function payment_approval_invoice_pending_sql_exclude_qbo_recovery(): string
{
    return <<<SQL
          AND NOT EXISTS (
              SELECT 1
              FROM dbo.ApprovalLog al
              WHERE al.ApprovalType = N'Payment'
                AND al.EntityType = N'SupplierInvoice'
                AND al.EntityID = si.SupplierInvoiceID
                AND al.ApproverResult = N'Approved'
          )
    SQL;
}

function payment_approval_count_pending(): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM dbo.SupplierInvoice si WHERE si.SyncStatus = :status'
        . payment_approval_invoice_pending_sql_exclude_qbo_recovery()
    );
    $stmt->execute(['status' => QBO_INSERT_STATUS_SUBMITTED]);

    return (int) $stmt->fetchColumn();
}

function payment_approval_list_pending(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            si.SupplierInvoiceID,
            si.DocNumber,
            si.TxnDate,
            si.DueDate,
            si.TotalAmt,
            si.SyncStatus,
            si.ModifiedDate,
            s.SupplierName,
            cu.UserName AS CreatedByName
        FROM dbo.SupplierInvoice si
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        LEFT JOIN dbo.[User] cu ON cu.UserID = si.CreatedByUser
        WHERE si.SyncStatus = :status
    SQL;
    $sql .= payment_approval_invoice_pending_sql_exclude_qbo_recovery();

    $sortState = table_sort_state(PAYMENT_APPROVAL_INVOICE_LIST_SORT_COLUMNS, 'txn_date', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PAYMENT_APPROVAL_INVOICE_LIST_SORT_SQL, $sortState, 'txn_date', 'doc_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => QBO_INSERT_STATUS_SUBMITTED]);

    return $stmt->fetchAll();
}

function payment_approval_invoice_is_standalone(?array $invoice): bool
{
    return $invoice !== null && empty($invoice['POID']);
}

function payment_approval_invoice_can_submit(array $invoice): bool
{
    $status = (string) ($invoice['SyncStatus'] ?? '');
    $invoiceId = (int) ($invoice['SupplierInvoiceID'] ?? 0);

    // After payment approval, Failed invoices recover via manual QBO Insert — not payment re-submit.
    if ($status === QBO_INSERT_STATUS_FAILED && $invoiceId > 0 && payment_approval_invoice_has_approved($invoiceId)) {
        return false;
    }

    if (in_array($status, QBO_INSERT_EDITABLE_STATUSES, true)) {
        return true;
    }

    return supplier_invoice_posted_is_reopenable($invoice);
}

function payment_approval_invoice_actions(): array
{
    $actions = PAYMENT_APPROVAL_INVOICE_ACTIONS;
    if (payment_approval_is_stub_mode()) {
        $actions['approve']['label'] = 'Approve Payment (Test Mode)';
    }

    return $actions;
}

function payment_approval_invoice_list_log(int $invoiceId): array
{
    return approval_list_log('Payment', $invoiceId, 'SupplierInvoice');
}

function payment_approval_invoice_submit(int $invoiceId): array
{
    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }

    if (!payment_approval_invoice_can_submit($invoice)) {
        return ['ok' => false, 'error' => 'This invoice cannot be submitted for approval in its current status.'];
    }

    $validationError = payment_approval_invoice_submit_error($invoice);
    if ($validationError !== null) {
        return ['ok' => false, 'error' => $validationError];
    }

    $current = (string) $invoice['SyncStatus'];

    try {
        payment_approval_invoice_token_invalidate($invoiceId);

        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.SupplierInvoice
            SET SyncStatus = :status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :modified_by
            WHERE SupplierInvoiceID = :id
        SQL);
        $stmt->execute([
            'status'      => QBO_INSERT_STATUS_SUBMITTED,
            'modified_by' => auth_user()['UserID'] ?? null,
            'id'          => $invoiceId,
        ]);

        $invoice['SyncStatus'] = QBO_INSERT_STATUS_SUBMITTED;
        $isResubmit = !in_array($current, [QBO_INSERT_STATUS_DRAFT, QBO_INSERT_STATUS_SENT_BACK], true);
        $notify = payment_approval_invoice_notify_approvers($invoice, $isResubmit);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => payment_approval_format_exception($e)];
    }
}

function payment_approval_invoice_resubmit(int $invoiceId): array
{
    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }

    if ($invoice['SyncStatus'] !== QBO_INSERT_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only invoices already submitted for approval can be resubmitted.'];
    }

    if (payment_approval_invoice_has_approved($invoiceId)) {
        return ['ok' => false, 'error' => 'This invoice is awaiting QBO insert recovery. Use Resubmit for QBO Insert instead.'];
    }

    try {
        payment_approval_invoice_token_invalidate($invoiceId);
        $notify = payment_approval_invoice_notify_approvers($invoice, true);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => payment_approval_format_exception($e)];
    }
}

function payment_approval_invoice_process_action(int $invoiceId, string $action, string $comments = '', ?array $actingUser = null): array
{
    if (!isset(PAYMENT_APPROVAL_INVOICE_ACTIONS[$action])) {
        return ['ok' => false, 'error' => 'Invalid approval action.'];
    }

    $config = PAYMENT_APPROVAL_INVOICE_ACTIONS[$action];
    $comments = trim($comments);

    if ($config['require_comments'] && $comments === '') {
        return ['ok' => false, 'error' => 'Comments are required for this action.'];
    }

    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }

    if ($invoice['SyncStatus'] !== QBO_INSERT_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only invoices submitted for approval can be actioned.'];
    }

    // QBO recovery submissions are actioned by QBO Insert approvers, not Payment.
    if (payment_approval_invoice_has_approved($invoiceId)) {
        return ['ok' => false, 'error' => 'This invoice is awaiting QBO insert recovery, not payment approval.'];
    }

    $user = $actingUser ?? auth_user();
    if ($user === null || empty($user['UserID'])) {
        return ['ok' => false, 'error' => 'Unable to identify the approver for this action.'];
    }

    $approverName = (string) ($user['UserName'] ?? 'Unknown Approver');
    $approverId = (int) $user['UserID'];
    $newStatus = $config['status'];
    $stubNote = null;
    $qboError = null;

    if ($action === 'approve') {
        $hasBill = trim((string) ($invoice['QBO_BillId'] ?? '')) !== '';
        if ($hasBill) {
            $newStatus = QBO_INSERT_STATUS_POSTED;
        } elseif (payment_approval_is_stub_mode()) {
            $stubNote = 'Payment approval stub mode: approval recorded; QuickBooks bill was not created. Use Submit for QBO Insert when ready to post, or set QBO_INSERT_STUB=0.';
        } else {
            require_once __DIR__ . '/quickbooks.php';
            $post = qbo_create_bill_from_supplier_invoice($invoiceId);
            if (!$post['ok']) {
                $newStatus = QBO_INSERT_STATUS_FAILED;
                $qboError = (string) ($post['error'] ?? 'QuickBooks bill creation failed.');
            }
        }
    }

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if ($newStatus !== null) {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.SupplierInvoice
                SET SyncStatus = :status,
                    LastSyncError = :sync_error,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedByUser = :modified_by
                WHERE SupplierInvoiceID = :id
            SQL);
            $stmt->execute([
                'status'      => $newStatus,
                'sync_error'  => $qboError,
                'modified_by' => $approverId,
                'id'          => $invoiceId,
            ]);
        }

        $logComments = $comments !== '' ? $comments : null;
        if ($stubNote !== null) {
            $logComments = trim(($logComments ?? '') . ($logComments !== null && $logComments !== '' ? "\n" : '') . $stubNote);
        }

        approval_append_log(
            'Payment',
            $invoiceId,
            $approverName,
            $config['result'],
            $logComments !== '' ? $logComments : null,
            $approverId,
            'SupplierInvoice'
        );

        $pdo->commit();

        payment_approval_invoice_token_invalidate($invoiceId);

        $invoice = supplier_invoice_get($invoiceId) ?? $invoice;
        payment_approval_invoice_notify_requestor($invoice, $config, $approverName, $comments, $stubNote ?? $qboError);

        if ($action === 'approve' && $newStatus === QBO_INSERT_STATUS_FAILED) {
            return ['ok' => false, 'error' => $qboError ?? 'QuickBooks bill creation failed.', 'status' => $newStatus];
        }

        return ['ok' => true, 'error' => null, 'status' => $newStatus ?? $invoice['SyncStatus']];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => payment_approval_format_exception($e)];
    }
}

function payment_approval_invoice_notify_requestor(array $invoice, array $config, string $approverName, string $comments, ?string $qboError = null): void
{
    $reference = supplier_invoice_reference($invoice);
    $siteUrl = approval_site_url();
    $invoiceId = (int) $invoice['SupplierInvoiceID'];
    $alertName = !empty($config['viewed_message'])
        ? ALERT_NAME_PAYMENT_VIEWED_BY_APPROVER
        : ALERT_NAME_PAYMENT_STATUS_UPDATE;

    if (!empty($config['viewed_message'])) {
        $subject = "Supplier invoice {$reference} viewed by approver";
        $body = implode("\n", [
            "Supplier invoice {$reference} was accessed and viewed by the approver.",
            '',
            "Approver: {$approverName}",
            '',
            "View invoice: {$siteUrl}/accounting/supplier-invoices/view.php?id={$invoiceId}",
        ]);
    } else {
        $status = (string) ($config['status'] ?? $config['result'] ?? 'Updated');
        $subject = "Supplier invoice {$reference} — {$status}";
        $body = implode("\n", [
            'The supplier invoice approval status has been updated.',
            '',
            "Invoice #: {$reference}",
            "New status: {$status}",
            "Actioned by: {$approverName}",
        ]);
        if ($comments !== '') {
            $body .= "\nComments: {$comments}";
        }
        if ($qboError !== null && $qboError !== '') {
            $body .= "\nQuickBooks error: {$qboError}";
            if (($invoice['SyncStatus'] ?? '') === QBO_INSERT_STATUS_FAILED
                || ($config['status'] ?? null) === QBO_INSERT_STATUS_FAILED) {
                $body .= "\nPayment approval was recorded. Use Submit for QBO Insert on the invoice to retry posting with accounting.";
            }
        }
        $body .= "\n\nView invoice: {$siteUrl}/accounting/supplier-invoices/view.php?id={$invoiceId}";
    }

    alert_send_message($alertName, $subject, $body);
}

function payment_approval_invoice_notify_approvers(array $invoice, bool $isResubmit = false): array
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

        return payment_approval_merge_notify_results($result, payment_approval_notify_approval_watchers_for_invoice($invoice, $isResubmit, payment_approval_submitter_name()));
    }

    $approvers = payment_approval_list_approvers();
    $submitter = payment_approval_submitter_name();
    $invoiceId = (int) $invoice['SupplierInvoiceID'];
    $reference = supplier_invoice_reference($invoice);
    $subject = $isResubmit
        ? "Supplier invoice {$reference} resubmitted for approval"
        : "Supplier invoice {$reference} submitted for approval";

    if ($approvers === []) {
        $result['skipped_reason'] = 'no_subscribers';
    } else {
        $poId = !empty($invoice['POID']) ? (int) $invoice['POID'] : 0;
        $attachments = approval_collect_submission_attachments($poId, $invoiceId);
        $attachmentNote = approval_plain_attachment_note($attachments);

        foreach ($approvers as $approver) {
            $email = strtolower(trim((string) $approver['UserLogin']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $token = payment_approval_invoice_token_create($invoiceId, (int) $approver['UserID']);
            if ($token === null) {
                $result['failed'][$email] = 'Unable to create approval token.';
                continue;
            }

            $actionUrls = [
                'approve'   => payment_approval_invoice_build_action_url($invoiceId, $token, 'approve'),
                'reject'    => payment_approval_invoice_build_action_url($invoiceId, $token, 'reject'),
                'send_back' => payment_approval_invoice_build_action_url($invoiceId, $token, 'send_back'),
                'review'    => approval_site_url()
                    . '/accounting/supplier-invoices/approve.php?id=' . $invoiceId
                    . '&token=' . rawurlencode($token),
            ];

            $intro = approval_submission_intro_text($isResubmit);
            $htmlBody = approval_build_action_email_html(
                $intro,
                [
                    'Invoice #'    => $reference,
                    'Supplier'     => (string) ($invoice['SupplierName'] ?? ''),
                    'Total'        => accounting_format_money($invoice['TotalAmt'] ?? 0),
                    'Submitted by' => $submitter,
                ],
                $actionUrls,
                'review the full supplier invoice'
            );
            $plainBody = implode("\n", [
                $intro,
                '',
                "Invoice #: {$reference}",
                'Supplier: ' . ($invoice['SupplierName'] ?? ''),
                'Total: ' . accounting_format_money($invoice['TotalAmt'] ?? 0),
                "Submitted by: {$submitter}",
                '',
                'Approve: ' . $actionUrls['approve'],
                'Reject: ' . $actionUrls['reject'],
                'Return for comment: ' . $actionUrls['send_back'],
                'Review invoice: ' . $actionUrls['review'],
            ]) . $attachmentNote;

            $result['recipients'][] = $email;
            $greeting = 'Hello ' . ((string) ($approver['UserName'] ?? $email)) . ',';
            $send = $attachments === []
                ? mail_send_multi_result([$email => (string) ($approver['UserName'] ?? $email)], [], $subject, $greeting . "\n\n" . $plainBody, $htmlBody)
                : mail_send_multi_attachments_result([$email => (string) ($approver['UserName'] ?? $email)], [], $subject, $greeting . "\n\n" . $plainBody, $htmlBody, $attachments);
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

    return payment_approval_merge_notify_results($result, payment_approval_notify_approval_watchers_for_invoice($invoice, $isResubmit, $submitter));
}

function payment_approval_notify_approval_watchers_for_invoice(array $invoice, bool $isResubmit, string $submitter): array
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

    $alertRecipients = alert_message_recipients(ALERT_NAME_PAYMENT_APPROVAL_REQUEST);
    $approverEmails = array_keys(payment_approval_recipient_emails_for_approvers());
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
        return $result;
    }

    $reference = supplier_invoice_reference($invoice);
    $invoiceId = (int) $invoice['SupplierInvoiceID'];
    $subject = $isResubmit
        ? "Supplier invoice {$reference} resubmitted for approval"
        : "Supplier invoice {$reference} submitted for approval";
    $viewUrl = approval_site_url() . '/accounting/supplier-invoices/view.php?id=' . $invoiceId;
    $body = implode("\n", [
        $isResubmit
            ? 'A supplier invoice has been resubmitted for approval.'
            : 'A supplier invoice has been submitted for approval.',
        '',
        "Invoice #: {$reference}",
        'Supplier: ' . ($invoice['SupplierName'] ?? ''),
        'Total: ' . accounting_format_money($invoice['TotalAmt'] ?? 0),
        ($isResubmit ? "Resubmitted by: {$submitter}" : "Submitted by: {$submitter}"),
        'Status: Submitted for Approval',
        '',
        'This is a notification only. Only designated payment approvers can approve or reject.',
        '',
        "View invoice: {$viewUrl}",
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

function payment_approval_list_pending_payments(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            p.PaymentID,
            p.PaymentDate,
            p.PaymentAmount,
            p.PaymentType,
            p.PaymentStatus,
            p.SupplierInvoiceID,
            si.DocNumber,
            s.SupplierName,
            cu.UserName AS CreatedByName
        FROM dbo.POPayment p
        INNER JOIN dbo.SupplierInvoice si ON si.SupplierInvoiceID = p.SupplierInvoiceID
        INNER JOIN dbo.Supplier s ON s.SupplierID = si.SupplierID
        LEFT JOIN dbo.[User] cu ON cu.UserID = p.CreatedByUser
        WHERE p.SupplierInvoiceID IS NOT NULL
          AND p.PaymentStatus = :status
    SQL;

    $sortState = table_sort_state(PAYMENT_APPROVAL_LIST_SORT_COLUMNS, 'payment_date', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PAYMENT_APPROVAL_LIST_SORT_SQL, $sortState, 'payment_date', 'reference');

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => PAYMENT_APPROVAL_STATUS_SUBMITTED]);

    return $stmt->fetchAll();
}

function payment_approval_list_log(int $paymentId, int $supplierInvoiceId): array
{
    return approval_list_log('Payment', $paymentId, null, $supplierInvoiceId);
}

function payment_approval_can_submit(array $payment): bool
{
    return payment_approval_is_editable($payment);
}

function payment_approval_submit(int $paymentId): array
{
    $payment = po_payment_get($paymentId);
    if (!payment_approval_is_invoice_payment($payment)) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }

    if (!payment_approval_can_submit($payment)) {
        return ['ok' => false, 'error' => 'This payment cannot be submitted for approval in its current status.'];
    }

    $invoice = po_payment_get_invoice((int) $payment['SupplierInvoiceID']);
    $invoiceError = payment_approval_invoice_submit_error($invoice);
    if ($invoiceError !== null) {
        return ['ok' => false, 'error' => $invoiceError];
    }

    try {
        payment_approval_token_invalidate($paymentId);

        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            UPDATE dbo.POPayment
            SET PaymentStatus = :status,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedbyUser = :modified_by
            WHERE PaymentID = :id
        SQL);
        $stmt->execute([
            'status'      => PAYMENT_APPROVAL_STATUS_SUBMITTED,
            'modified_by' => auth_user()['UserID'] ?? null,
            'id'          => $paymentId,
        ]);

        $payment['PaymentStatus'] = PAYMENT_APPROVAL_STATUS_SUBMITTED;
        $notify = payment_approval_notify_approvers_of_submission($payment, $invoice, false);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => payment_approval_format_exception($e)];
    }
}

function payment_approval_resubmit(int $paymentId): array
{
    $payment = po_payment_get($paymentId);
    if (!payment_approval_is_invoice_payment($payment)) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }

    if ($payment['PaymentStatus'] !== PAYMENT_APPROVAL_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only payments already submitted for approval can be resubmitted.'];
    }

    $invoice = po_payment_get_invoice((int) $payment['SupplierInvoiceID']);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Linked supplier invoice not found.'];
    }

    try {
        payment_approval_token_invalidate($paymentId);
        $notify = payment_approval_notify_approvers_of_submission($payment, $invoice, true);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => payment_approval_format_exception($e)];
    }
}

function payment_approval_process_action(int $paymentId, string $action, string $comments = '', ?array $actingUser = null): array
{
    $actions = payment_approval_actions();
    if (!isset($actions[$action])) {
        return ['ok' => false, 'error' => 'Invalid approval action.'];
    }

    $config = $actions[$action];
    $comments = trim($comments);

    if ($config['require_comments'] && $comments === '') {
        return ['ok' => false, 'error' => 'Comments are required for this action.'];
    }

    $payment = po_payment_get($paymentId);
    if (!payment_approval_is_invoice_payment($payment)) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }

    if ($payment['PaymentStatus'] !== PAYMENT_APPROVAL_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only payments submitted for approval can be actioned.'];
    }

    $user = $actingUser ?? auth_user();
    if ($user === null || empty($user['UserID'])) {
        return ['ok' => false, 'error' => 'Unable to identify the approver for this action.'];
    }

    $approverName = (string) ($user['UserName'] ?? 'Unknown Approver');
    $approverId = (int) $user['UserID'];
    $supplierInvoiceId = (int) $payment['SupplierInvoiceID'];
    $newStatus = $config['status'];
    $stubNote = null;
    $qboError = null;
    $billPostedViaPayment = false;

    if ($action === 'approve') {
        $invoice = po_payment_get_invoice($supplierInvoiceId);
        if ($invoice === null) {
            return ['ok' => false, 'error' => 'Linked supplier invoice not found.'];
        }

        if (payment_approval_is_stub_mode()) {
            $stubNote = 'Payment approval stub mode: approval recorded; QuickBooks bill and payment were not created.';
            $newStatus = PAYMENT_APPROVAL_STATUS_PAID;
        } else {
            require_once __DIR__ . '/quickbooks.php';

            if (($invoice['SyncStatus'] ?? '') !== 'Posted' || trim((string) ($invoice['QBO_BillId'] ?? '')) === '') {
                $billResult = qbo_create_bill_from_supplier_invoice($supplierInvoiceId);
                if (!$billResult['ok']) {
                    $qboError = (string) ($billResult['error'] ?? 'QuickBooks bill creation failed.');
                    try {
                        $pdo = db();
                        $stmt = $pdo->prepare(<<<SQL
                            UPDATE dbo.SupplierInvoice
                            SET SyncStatus = N'Failed',
                                LastSyncError = :sync_error,
                                ModifiedDate = SYSUTCDATETIME(),
                                ModifiedByUser = :modified_by
                            WHERE SupplierInvoiceID = :id
                        SQL);
                        $stmt->execute([
                            'sync_error'  => $qboError,
                            'modified_by' => $approverId,
                            'id'          => $supplierInvoiceId,
                        ]);
                    } catch (Throwable $e) {
                        error_log('payment approval bill failure update: ' . $e->getMessage());
                    }

                    return ['ok' => false, 'error' => $qboError, 'status' => 'Failed'];
                }

                $billPostedViaPayment = true;
                $invoice = po_payment_get_invoice($supplierInvoiceId) ?? $invoice;
            }

            $payResult = qbo_create_bill_payment_from_invoice_payment($paymentId);
            if (!$payResult['ok']) {
                $qboError = (string) ($payResult['error'] ?? 'QuickBooks bill payment failed.');
                $newStatus = PAYMENT_APPROVAL_STATUS_FAILED;
            } else {
                $newStatus = PAYMENT_APPROVAL_STATUS_TRANSMITTED;
            }
        }
    }

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if ($action === 'approve' && payment_approval_is_stub_mode()) {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.SupplierInvoice
                SET SyncStatus = N'Posted',
                    LastSyncError = NULL,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedByUser = :modified_by
                WHERE SupplierInvoiceID = :id
                  AND SyncStatus <> N'Posted'
            SQL);
            $stmt->execute([
                'modified_by' => $approverId,
                'id'          => $supplierInvoiceId,
            ]);

            approval_append_log(
                'QBOInsert',
                $supplierInvoiceId,
                $approverName,
                'Approved',
                $stubNote,
                $approverId
            );
        } elseif ($billPostedViaPayment) {
            approval_append_log(
                'QBOInsert',
                $supplierInvoiceId,
                $approverName,
                'Approved',
                'Bill created as part of payment approval.',
                $approverId
            );
        }

        if ($newStatus !== null) {
            $extras = '';
            $extraParams = [];
            if (in_array($newStatus, [PAYMENT_APPROVAL_STATUS_PAID, PAYMENT_APPROVAL_STATUS_TRANSMITTED], true)) {
                $extras = ', PaymentMadeDate = SYSUTCDATETIME(), PaymentMadeAmount = PaymentAmount, PaymentMadeBy = :made_by';
                $extraParams['made_by'] = $approverName;
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.POPayment
                SET PaymentStatus = :status,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :modified_by
                    {$extras}
                WHERE PaymentID = :id
            SQL);
            $stmt->execute(array_merge([
                'status'      => $newStatus,
                'modified_by' => $approverId,
                'id'          => $paymentId,
            ], $extraParams));
        }

        $logComments = $comments !== '' ? $comments : null;
        if ($stubNote !== null) {
            $logComments = trim(($logComments ?? '') . ($logComments !== null && $logComments !== '' ? "\n" : '') . $stubNote);
        }
        if ($qboError !== null && $action === 'approve') {
            $logComments = trim(($logComments ?? '') . ($logComments !== null && $logComments !== '' ? "\n" : '') . 'QuickBooks error: ' . $qboError);
        }

        approval_append_log(
            'Payment',
            $paymentId,
            $approverName,
            $newStatus === PAYMENT_APPROVAL_STATUS_FAILED ? 'Failed' : $config['result'],
            $logComments !== '' ? $logComments : null,
            $approverId,
            null,
            'SupplierInvoice',
            $supplierInvoiceId
        );

        $pdo->commit();

        payment_approval_token_invalidate($paymentId);

        $payment = po_payment_get($paymentId) ?? $payment;
        $notifyConfig = $config;
        $notifyConfig['status'] = $newStatus ?? $payment['PaymentStatus'];
        payment_approval_notify_requestor($payment, $notifyConfig, $approverName, $comments);

        if ($action === 'approve' && $newStatus === PAYMENT_APPROVAL_STATUS_FAILED) {
            return ['ok' => false, 'error' => $qboError ?? 'QuickBooks bill payment failed.', 'status' => $newStatus];
        }

        return ['ok' => true, 'error' => null, 'status' => $newStatus ?? $payment['PaymentStatus']];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => payment_approval_format_exception($e)];
    }
}

function payment_approval_format_exception(Throwable $e): string
{
    error_log('payment approval error: ' . $e->getMessage());

    return 'An unexpected error occurred. Please try again or contact support.';
}

function payment_approval_submitter_name(): string
{
    return (string) (auth_user()['UserName'] ?? 'An accounting user');
}

function payment_approval_format_notify_message(array $notify): string
{
    return alert_format_notify_message(
        $notify,
        'No users with Payment Approval access are configured. No approval email was sent.'
    );
}

function payment_approval_recipient_emails_for_approvers(): array
{
    $emails = [];
    foreach (payment_approval_list_approvers() as $approver) {
        $email = strtolower(trim((string) $approver['UserLogin']));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = (string) ($approver['UserName'] ?? $email);
        }
    }

    return $emails;
}

function payment_approval_merge_notify_results(array ...$results): array
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

    if ($merged['sent'] !== [] || $merged['failed'] !== []) {
        $merged['skipped_reason'] = null;
    }

    return $merged;
}

function payment_approval_notify_approval_watchers(array $payment, array $invoice, bool $isResubmit, string $submitter): array
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

    $alertRecipients = alert_message_recipients(ALERT_NAME_PAYMENT_APPROVAL_REQUEST);
    $approverEmails = array_keys(payment_approval_recipient_emails_for_approvers());
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
        return $result;
    }

    $reference = supplier_invoice_reference($invoice);
    $subject = $isResubmit
        ? "Payment for {$reference} resubmitted for approval"
        : "Payment for {$reference} submitted for approval";
    $viewUrl = approval_site_url() . '/accounting/invoice-payments/edit.php?id=' . (int) $payment['PaymentID'];
    $body = implode("\n", [
        $isResubmit
            ? 'An invoice payment request has been resubmitted for approval.'
            : 'An invoice payment request has been submitted for approval.',
        '',
        "Invoice #: {$reference}",
        'Supplier: ' . ($invoice['SupplierName'] ?? ''),
        'Amount: ' . accounting_format_money($payment['PaymentAmount'] ?? 0),
        ($isResubmit ? "Resubmitted by: {$submitter}" : "Submitted by: {$submitter}"),
        'Status: Submitted for Approval',
        '',
        'This is a notification only. Only designated payment approvers can approve or reject.',
        '',
        "View payment: {$viewUrl}",
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

function payment_approval_notify_approvers_of_submission(array $payment, array $invoice, bool $isResubmit = false): array
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

        return payment_approval_merge_notify_results($result, payment_approval_notify_approval_watchers($payment, $invoice, $isResubmit, payment_approval_submitter_name()));
    }

    $approvers = payment_approval_list_approvers();
    $submitter = payment_approval_submitter_name();
    $paymentId = (int) $payment['PaymentID'];
    $supplierInvoiceId = (int) $payment['SupplierInvoiceID'];
    $reference = supplier_invoice_reference($invoice);
    $subject = $isResubmit
        ? "Payment for {$reference} resubmitted for approval"
        : "Payment for {$reference} submitted for approval";

    if ($approvers === []) {
        $result['skipped_reason'] = 'no_subscribers';
    } else {
        $poId = !empty($payment['POID']) ? (int) $payment['POID'] : (!empty($invoice['POID']) ? (int) $invoice['POID'] : 0);
        $attachments = approval_collect_submission_attachments($poId, $supplierInvoiceId);
        $intro = approval_submission_intro_text($isResubmit);
        $attachmentNote = approval_plain_attachment_note($attachments);

        foreach ($approvers as $approver) {
            $email = strtolower(trim((string) $approver['UserLogin']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $token = payment_approval_token_create($paymentId, $supplierInvoiceId, (int) $approver['UserID']);
            if ($token === null) {
                $result['failed'][$email] = 'Unable to create approval token.';
                continue;
            }

            $actionUrls = [
                'approve'   => payment_approval_build_action_url($paymentId, $token, 'approve'),
                'reject'    => payment_approval_build_action_url($paymentId, $token, 'reject'),
                'send_back' => payment_approval_build_action_url($paymentId, $token, 'send_back'),
                'review'    => approval_site_url()
                    . '/accounting/invoice-payments/approve.php?id=' . $paymentId
                    . '&token=' . rawurlencode($token),
            ];

            $htmlBody = approval_build_action_email_html(
                $intro,
                [
                    'Invoice #'    => $reference,
                    'Supplier'     => (string) ($invoice['SupplierName'] ?? ''),
                    'Amount'       => accounting_format_money($payment['PaymentAmount'] ?? 0),
                    'Submitted by' => $submitter,
                ],
                $actionUrls,
                'review the payment request'
            );
            $plainBody = implode("\n", [
                $intro,
                '',
                "Invoice #: {$reference}",
                'Amount: ' . accounting_format_money($payment['PaymentAmount'] ?? 0),
                "Submitted by: {$submitter}",
                '',
                'Approve: ' . $actionUrls['approve'],
                'Reject: ' . $actionUrls['reject'],
                'Return for comment: ' . $actionUrls['send_back'],
                'Review payment: ' . $actionUrls['review'],
            ]) . $attachmentNote;

            $result['recipients'][] = $email;
            $greeting = 'Hello ' . ((string) ($approver['UserName'] ?? $email)) . ',';
            $send = $attachments === []
                ? mail_send_multi_result([$email => (string) ($approver['UserName'] ?? $email)], [], $subject, $greeting . "\n\n" . $plainBody, $htmlBody)
                : mail_send_multi_attachments_result([$email => (string) ($approver['UserName'] ?? $email)], [], $subject, $greeting . "\n\n" . $plainBody, $htmlBody, $attachments);
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

    return payment_approval_merge_notify_results($result, payment_approval_notify_approval_watchers($payment, $invoice, $isResubmit, $submitter));
}

function payment_approval_notify_requestor(array $payment, array $config, string $approverName, string $comments): void
{
    $siteUrl = approval_site_url();
    $paymentId = (int) $payment['PaymentID'];
    $alertName = !empty($config['viewed_message'])
        ? ALERT_NAME_PAYMENT_VIEWED_BY_APPROVER
        : ALERT_NAME_PAYMENT_STATUS_UPDATE;

    if (!empty($config['viewed_message'])) {
        $subject = 'Invoice payment viewed by approver';
        $body = implode("\n", [
            'An invoice payment request was accessed and viewed by the approver.',
            '',
            "Approver: {$approverName}",
            '',
            "View payment: {$siteUrl}/accounting/invoice-payments/edit.php?id={$paymentId}",
        ]);
    } else {
        $status = (string) ($config['status'] ?? $config['result'] ?? 'Updated');
        $subject = "Invoice payment — {$status}";
        $body = implode("\n", [
            'The invoice payment approval status has been updated.',
            '',
            'Amount: ' . accounting_format_money($payment['PaymentAmount'] ?? 0),
            "New status: {$status}",
            "Actioned by: {$approverName}",
        ]);
        if ($comments !== '') {
            $body .= "\nComments: {$comments}";
        }
        $body .= "\n\nView payment: {$siteUrl}/accounting/invoice-payments/edit.php?id={$paymentId}";
    }

    $result = alert_send_message($alertName, $subject, $body);
    if (($result['skipped_reason'] ?? null) === 'no_subscribers') {
        error_log('payment_approval_notify_requestor skipped (no alert subscribers) for payment ' . $paymentId);
    }
}
