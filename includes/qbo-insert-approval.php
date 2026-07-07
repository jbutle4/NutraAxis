<?php

require_once __DIR__ . '/approval.php';
require_once __DIR__ . '/supplier-invoice.php';
require_once __DIR__ . '/supplier-invoice-attachments.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/alert-messages.php';
require_once __DIR__ . '/qbo-insert-approval-token.php';

const QBO_INSERT_STATUS_DRAFT = 'Draft';
const QBO_INSERT_STATUS_SUBMITTED = 'Submitted for Approval';
const QBO_INSERT_STATUS_REJECTED = 'Rejected';
const QBO_INSERT_STATUS_SENT_BACK = 'Sent Back for Comment';
const QBO_INSERT_STATUS_POSTED = 'Posted';
const QBO_INSERT_STATUS_FAILED = 'Failed';
const QBO_INSERT_STATUS_VOIDED = 'Voided';

const QBO_INSERT_EDITABLE_STATUSES = [
    QBO_INSERT_STATUS_DRAFT,
    QBO_INSERT_STATUS_REJECTED,
    QBO_INSERT_STATUS_SENT_BACK,
    QBO_INSERT_STATUS_FAILED,
];

const QBO_INSERT_APPROVAL_ACTIONS = [
    'approve'   => [
        'label'            => 'Approve & Post to QBO',
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

const QBO_INSERT_APPROVAL_LIST_SORT_COLUMNS = [
    'doc_number' => 'Invoice #',
    'supplier'   => 'Supplier',
    'txn_date'   => 'Invoice date',
    'total'      => 'Total',
    'submitted'  => 'Submitted By',
];

const QBO_INSERT_APPROVAL_LIST_SORT_SQL = [
    'doc_number' => 'si.DocNumber',
    'supplier'   => 's.SupplierName',
    'txn_date'   => 'si.TxnDate',
    'total'      => 'si.TotalAmt',
    'submitted'  => 'cu.UserName',
];

const QBO_INSERT_APPROVAL_LIST_SORT_NUMERIC = ['total'];

function qbo_insert_is_stub_mode(): bool
{
    return filter_var(env('QBO_INSERT_STUB', '1'), FILTER_VALIDATE_BOOLEAN);
}

function qbo_insert_approval_actions(): array
{
    $actions = QBO_INSERT_APPROVAL_ACTIONS;
    if (qbo_insert_is_stub_mode()) {
        $actions['approve']['label'] = 'Approve (Test Mode)';
    }

    return $actions;
}

function qbo_insert_can_read_queue(): bool
{
    return approval_can_read_type('QBOInsert');
}

function qbo_insert_can_take_action(): bool
{
    return approval_can_act_on_type('QBOInsert');
}

function qbo_insert_require_read(): void
{
    auth_require_login();
    if (qbo_insert_can_read_queue()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the QBO insert approval queue.');
}

function qbo_insert_require_action(): void
{
    qbo_insert_require_read();
    if (qbo_insert_can_take_action()) {
        return;
    }
    auth_render_access_denied('You do not have permission to act on QBO insert approvals.');
}

function qbo_insert_count_pending(): int
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM dbo.SupplierInvoice WHERE SyncStatus = :status AND POID IS NOT NULL');
    $stmt->execute(['status' => QBO_INSERT_STATUS_SUBMITTED]);

    return (int) $stmt->fetchColumn();
}

function qbo_insert_list_pending(array $filters = []): array
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
          AND si.POID IS NOT NULL
    SQL;

    $sortState = table_sort_state(QBO_INSERT_APPROVAL_LIST_SORT_COLUMNS, 'txn_date', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(QBO_INSERT_APPROVAL_LIST_SORT_SQL, $sortState, 'txn_date', 'doc_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => QBO_INSERT_STATUS_SUBMITTED]);

    return $stmt->fetchAll();
}

function qbo_insert_list_approval_log(int $invoiceId): array
{
    require_once __DIR__ . '/approval.php';

    return approval_list_log('QBOInsert', $invoiceId);
}

function qbo_insert_can_submit(array $invoice): bool
{
    return in_array((string) ($invoice['SyncStatus'] ?? ''), QBO_INSERT_EDITABLE_STATUSES, true);
}

function qbo_insert_has_invoice_attachment(int $invoiceId): bool
{
    foreach (supplier_invoice_list_attachments($invoiceId) as $attachment) {
        if (($attachment['AttachmentKind'] ?? '') === 'InvoicePDF') {
            return true;
        }
    }

    return false;
}

function qbo_insert_submit_for_approval(int $invoiceId): array
{
    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }

    if (!qbo_insert_can_submit($invoice)) {
        return ['ok' => false, 'error' => 'This invoice cannot be submitted for QBO insert approval in its current status.'];
    }

    if (!qbo_insert_has_invoice_attachment($invoiceId)) {
        return ['ok' => false, 'error' => 'Upload a copy of the supplier invoice (Invoice PDF) before submitting for approval.'];
    }

    $current = (string) $invoice['SyncStatus'];

    try {
        qbo_insert_approval_token_invalidate($invoiceId);

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
        $notify = qbo_insert_notify_approvers_of_submission($invoice, $isResubmit);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => supplier_invoice_format_exception($e)];
    }
}

function qbo_insert_resubmit_for_approval(int $invoiceId): array
{
    $invoice = supplier_invoice_get($invoiceId);
    if ($invoice === null) {
        return ['ok' => false, 'error' => 'Supplier invoice not found.'];
    }

    if ($invoice['SyncStatus'] !== QBO_INSERT_STATUS_SUBMITTED) {
        return ['ok' => false, 'error' => 'Only invoices already submitted for approval can be resubmitted.'];
    }

    try {
        qbo_insert_approval_token_invalidate($invoiceId);
        $notify = qbo_insert_notify_approvers_of_submission($invoice, true);

        return ['ok' => true, 'error' => null, 'notify' => $notify];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => supplier_invoice_format_exception($e)];
    }
}

function qbo_insert_process_approval_action(int $invoiceId, string $action, string $comments = '', ?array $actingUser = null): array
{
    if (!isset(QBO_INSERT_APPROVAL_ACTIONS[$action])) {
        return ['ok' => false, 'error' => 'Invalid approval action.'];
    }

    $config = QBO_INSERT_APPROVAL_ACTIONS[$action];
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

    $user = $actingUser ?? auth_user();
    if ($user === null || empty($user['UserID'])) {
        return ['ok' => false, 'error' => 'Unable to identify the approver for this action.'];
    }

    $approverName = (string) ($user['UserName'] ?? 'Unknown Approver');
    $approverId = (int) $user['UserID'];
    $newStatus = $config['status'];
    $qboError = null;
    $stubNote = null;

    if ($action === 'approve') {
        if (qbo_insert_is_stub_mode()) {
            $stubNote = 'QBO insert stub mode: approval recorded; QuickBooks bill was not created.';
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

        require_once __DIR__ . '/approval.php';
        approval_append_log(
            'QBOInsert',
            $invoiceId,
            $approverName,
            $config['result'],
            $logComments !== '' ? $logComments : null,
            $approverId
        );

        $pdo->commit();

        qbo_insert_approval_token_invalidate($invoiceId);

        $invoice = supplier_invoice_get($invoiceId) ?? $invoice;
        qbo_insert_notify_requestor_of_status_change($invoice, $config, $approverName, $comments, $stubNote ?? $qboError);

        if ($action === 'approve' && qbo_insert_is_stub_mode()) {
            qbo_insert_notify_approvers_stub_approved($invoice, $approverName);
        }

        if ($action === 'approve' && $newStatus === QBO_INSERT_STATUS_FAILED) {
            return ['ok' => false, 'error' => $qboError ?? 'QuickBooks bill creation failed.', 'status' => $newStatus];
        }

        return ['ok' => true, 'error' => null, 'status' => $newStatus ?? $invoice['SyncStatus']];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => supplier_invoice_format_exception($e)];
    }
}

function supplier_invoice_format_exception(Throwable $e): string
{
    error_log('supplier invoice approval error: ' . $e->getMessage());

    return 'An unexpected error occurred. Please try again or contact support.';
}

function qbo_insert_submitter_name(): string
{
    return (string) (auth_user()['UserName'] ?? 'An accounting user');
}

function qbo_insert_format_notify_message(array $notify): string
{
    return alert_format_notify_message(
        $notify,
        qbo_insert_approver_config_message($notify['approver_config_issue'] ?? 'no_roles')
    );
}

function qbo_insert_approver_config_message(string $issue = 'no_roles'): string
{
    if ($issue === 'invalid_email') {
        return 'QBO insert approvers are configured, but none have a valid email login. Update user email addresses in Site Admin → Users. No approval email was sent.';
    }

    return 'No users with QBO Insert Approval access are configured. In Site Admin → Roles, grant QBO Insert Approval (Update) to at least one role, assign users to that role, and ensure each user has a valid email login. No approval email was sent.';
}

function qbo_insert_approver_config_issue(array $approvers): string
{
    if ($approvers === []) {
        return 'no_roles';
    }

    foreach ($approvers as $approver) {
        $email = strtolower(trim((string) ($approver['UserLogin'])));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'ok';
        }
    }

    return 'invalid_email';
}

function qbo_insert_recipient_emails_for_approvers(): array
{
    $emails = [];
    foreach (qbo_insert_list_approvers() as $approver) {
        $email = strtolower(trim((string) $approver['UserLogin']));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[$email] = (string) ($approver['UserName'] ?? $email);
        }
    }

    return $emails;
}

function qbo_insert_merge_approval_notify_results(array ...$results): array
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

function qbo_insert_notify_approval_watchers(array $invoice, bool $isResubmit, string $submitter): array
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

    $alertRecipients = alert_message_recipients(ALERT_NAME_QBO_INSERT_APPROVAL_REQUEST);
    $approverEmails = array_keys(qbo_insert_recipient_emails_for_approvers());
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

    $reference = supplier_invoice_reference($invoice);
    $subject = $isResubmit
        ? "Supplier invoice {$reference} resubmitted for QBO approval"
        : "Supplier invoice {$reference} submitted for QBO approval";
    $viewUrl = approval_site_url() . '/accounting/supplier-invoices/view.php?id=' . (int) $invoice['SupplierInvoiceID'];
    $body = implode("\n", [
        $isResubmit
            ? 'A supplier invoice has been resubmitted for QBO insert approval.'
            : 'A supplier invoice has been submitted for QBO insert approval.',
        '',
        "Invoice #: {$reference}",
        'Supplier: ' . ($invoice['SupplierName'] ?? ''),
        'Total: ' . accounting_format_money($invoice['TotalAmt'] ?? 0),
        ($isResubmit ? "Resubmitted by: {$submitter}" : "Submitted by: {$submitter}"),
        'Status: Submitted for Approval',
        '',
        'This is a notification only. Only designated QBO insert approvers can approve or reject.',
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

function qbo_insert_notify_approvers_of_submission(array $invoice, bool $isResubmit = false): array
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

        return qbo_insert_merge_approval_notify_results($result, qbo_insert_notify_approval_watchers($invoice, $isResubmit, qbo_insert_submitter_name()));
    }

    $approvers = qbo_insert_list_approvers();
    $submitter = qbo_insert_submitter_name();
    $invoiceId = (int) $invoice['SupplierInvoiceID'];
    $reference = supplier_invoice_reference($invoice);
    $subject = $isResubmit
        ? "Supplier invoice {$reference} resubmitted for QBO approval"
        : "Supplier invoice {$reference} submitted for QBO approval";

    if ($approvers === []) {
        $result['skipped_reason'] = 'no_subscribers';
        $result['approver_config_issue'] = 'no_roles';
    } else {
        foreach ($approvers as $approver) {
            $email = strtolower(trim((string) $approver['UserLogin']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $token = qbo_insert_approval_token_create($invoiceId, (int) $approver['UserID']);
            if ($token === null) {
                $result['failed'][$email] = 'Unable to create approval token.';
                continue;
            }

            $actionUrls = [
                'approve'   => qbo_insert_build_action_url($invoiceId, $token, 'approve'),
                'reject'    => qbo_insert_build_action_url($invoiceId, $token, 'reject'),
                'send_back' => qbo_insert_build_action_url($invoiceId, $token, 'send_back'),
                'review'    => approval_site_url()
                    . '/accounting/supplier-invoices/approve.php?id=' . $invoiceId
                    . '&token=' . rawurlencode($token),
            ];

            $intro = $isResubmit
                ? 'A supplier invoice has been resubmitted for your QBO insert approval.'
                : 'A supplier invoice has been submitted for your QBO insert approval.';
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
            $result['approver_config_issue'] = qbo_insert_approver_config_issue($approvers);
        }
    }

    return qbo_insert_merge_approval_notify_results($result, qbo_insert_notify_approval_watchers($invoice, $isResubmit, $submitter));
}

function qbo_insert_notify_requestor_of_status_change(array $invoice, array $config, string $approverName, string $comments, ?string $qboError = null): void
{
    $reference = supplier_invoice_reference($invoice);
    $siteUrl = approval_site_url();
    $alertName = !empty($config['viewed_message'])
        ? ALERT_NAME_QBO_INSERT_VIEWED_BY_APPROVER
        : ALERT_NAME_QBO_INSERT_STATUS_UPDATE;

    if (!empty($config['viewed_message'])) {
        $subject = "Supplier invoice {$reference} viewed by approver";
        $body = implode("\n", [
            "Supplier invoice {$reference} was accessed and viewed by the approver.",
            '',
            "Approver: {$approverName}",
            '',
            "View invoice: {$siteUrl}/accounting/supplier-invoices/view.php?id=" . (int) $invoice['SupplierInvoiceID'],
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
        }
        $body .= "\n\nView invoice: {$siteUrl}/accounting/supplier-invoices/view.php?id=" . (int) $invoice['SupplierInvoiceID'];
    }

    $result = alert_send_message($alertName, $subject, $body);
    if (($result['skipped_reason'] ?? null) === 'no_subscribers') {
        error_log('qbo_insert_notify_requestor_of_status_change skipped (no alert subscribers) for invoice ' . $reference);
    }
}

function qbo_insert_notify_approvers_stub_approved(array $invoice, string $approverName): void
{
    if (!mail_smtp_is_configured()) {
        return;
    }

    $reference = supplier_invoice_reference($invoice);
    $subject = "[Test mode] QBO insert approved for {$reference}";
    $body = implode("\n", [
        'This is a test-mode approval. No QuickBooks Bill was created.',
        '',
        "Invoice #: {$reference}",
        "Supplier: " . ($invoice['SupplierName'] ?? ''),
        "Approved by: {$approverName}",
        '',
        'Set QBO_INSERT_STUB=0 in application settings when ready to post bills to QuickBooks.',
        '',
        'View invoice: ' . approval_site_url() . '/accounting/supplier-invoices/view.php?id=' . (int) $invoice['SupplierInvoiceID'],
    ]);

    foreach (qbo_insert_list_approvers() as $approver) {
        $email = strtolower(trim((string) $approver['UserLogin']));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        mail_send_multi_result([$email => (string) ($approver['UserName'] ?? $email)], [], $subject, $body);
    }
}
