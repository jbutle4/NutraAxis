<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mail.php';

const ALERT_NAME_PROCESS_ABANDONED = 'process-abandoned';
const ALERT_NAME_PO_APPROVAL_REQUEST = 'po-approval-request';
const ALERT_NAME_PO_APPROVAL_NOTICE = 'po-approval-notice';
const ALERT_NAME_PO_STATUS_UPDATE = 'po-status-update';
const ALERT_NAME_PO_VIEWED_BY_APPROVER = 'po-viewed-by-approver';

const ALERT_ADDRESS_TYPES = ['TO', 'CC'];

function alert_tables_available(): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT OBJECT_ID(N'dbo.AlertMessage', N'U') AS object_id");
        $row = $stmt->fetch();

        $available = $row !== false && !empty($row['object_id']);
    } catch (Throwable) {
        $available = false;
    }

    return $available;
}

function alert_subscription_has_address_type(): bool
{
    if (!alert_tables_available()) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT COL_LENGTH('dbo.AlertSubscription', 'AddressType') AS col_len");
        $row = $stmt->fetch();

        return $row !== false && $row['col_len'] !== null;
    } catch (Throwable) {
        return false;
    }
}

function alert_normalize_address_type(string $value): string
{
    $value = strtoupper(trim($value));

    return in_array($value, ALERT_ADDRESS_TYPES, true) ? $value : 'TO';
}

function alert_list_messages(): array
{
    if (!alert_tables_available()) {
        return [];
    }

    $pdo = db();

    return $pdo->query(<<<SQL
        SELECT alertID, AlertName, AlertStatus, AlertDescription
        FROM dbo.AlertMessage
        ORDER BY AlertName
    SQL)->fetchAll();
}

function alert_get_message(int $alertId): ?array
{
    if (!alert_tables_available() || $alertId <= 0) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT alertID, AlertName, AlertStatus, AlertDescription FROM dbo.AlertMessage WHERE alertID = :id');
    $stmt->execute(['id' => $alertId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function alert_list_user_subscription_rows(int $userId): array
{
    if (!alert_tables_available()) {
        return [];
    }

    $pdo = db();
    $addressSelect = alert_subscription_has_address_type()
        ? 'sub.AddressType'
        : "N'TO' AS AddressType";

    $stmt = $pdo->prepare(<<<SQL
        SELECT
            sub.alertSubID,
            sub.alertID,
            {$addressSelect},
            am.AlertName,
            am.AlertDescription
        FROM dbo.AlertSubscription sub
        INNER JOIN dbo.AlertMessage am ON am.alertID = sub.alertID
        WHERE sub.UserID = :user_id
          AND am.AlertStatus = 1
        ORDER BY am.AlertName
    SQL);
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function alert_list_available_for_user(int $userId): array
{
    if (!alert_tables_available()) {
        return [];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT am.alertID, am.AlertName, am.AlertDescription
        FROM dbo.AlertMessage am
        WHERE am.AlertStatus = 1
          AND NOT EXISTS (
              SELECT 1
              FROM dbo.AlertSubscription sub
              WHERE sub.alertID = am.alertID
                AND sub.UserID = :user_id
          )
        ORDER BY am.AlertName
    SQL);
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function alert_save_user_subscription_changes(int $userId, array $rows, ?int $newAlertId, string $newAddressType): void
{
    if (!alert_tables_available()) {
        return;
    }

    $pdo = db();
    $hasAddressType = alert_subscription_has_address_type();
    $pdo->beginTransaction();

    try {
        foreach ($rows as $alertSubId => $row) {
            $alertSubId = (int) $alertSubId;
            if ($alertSubId <= 0 || !is_array($row)) {
                continue;
            }

            if (!empty($row['remove'])) {
                $delete = $pdo->prepare('DELETE FROM dbo.AlertSubscription WHERE alertSubID = :sub_id AND UserID = :user_id');
                $delete->execute(['sub_id' => $alertSubId, 'user_id' => $userId]);
                continue;
            }

            $addressType = alert_normalize_address_type((string) ($row['address_type'] ?? 'TO'));
            if ($hasAddressType) {
                $update = $pdo->prepare(<<<SQL
                    UPDATE dbo.AlertSubscription
                    SET AddressType = :address_type
                    WHERE alertSubID = :sub_id
                      AND UserID = :user_id
                SQL);
                $update->execute([
                    'address_type' => $addressType,
                    'sub_id'       => $alertSubId,
                    'user_id'      => $userId,
                ]);
            }
        }

        $newAlertId = (int) $newAlertId;
        if ($newAlertId > 0 && alert_get_message($newAlertId) !== null) {
            $exists = $pdo->prepare('SELECT alertSubID FROM dbo.AlertSubscription WHERE alertID = :alert_id AND UserID = :user_id');
            $exists->execute(['alert_id' => $newAlertId, 'user_id' => $userId]);
            if ($exists->fetch() === false) {
                $addressType = alert_normalize_address_type($newAddressType);
                if ($hasAddressType) {
                    $insert = $pdo->prepare(<<<SQL
                        INSERT INTO dbo.AlertSubscription (alertID, UserID, AddressType)
                        VALUES (:alert_id, :user_id, :address_type)
                    SQL);
                    $insert->execute([
                        'alert_id'      => $newAlertId,
                        'user_id'       => $userId,
                        'address_type'  => $addressType,
                    ]);
                } else {
                    $insert = $pdo->prepare('INSERT INTO dbo.AlertSubscription (alertID, UserID) VALUES (:alert_id, :user_id)');
                    $insert->execute(['alert_id' => $newAlertId, 'user_id' => $userId]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function alert_message_recipients(string $alertName): array
{
    $empty = ['to' => [], 'cc' => []];
    if (!alert_tables_available()) {
        return $empty;
    }

    $pdo = db();
    $addressSelect = alert_subscription_has_address_type()
        ? 'sub.AddressType'
        : "N'TO' AS AddressType";

    $stmt = $pdo->prepare(<<<SQL
        SELECT {$addressSelect}, u.UserLogin, u.UserName
        FROM dbo.AlertMessage am
        INNER JOIN dbo.AlertSubscription sub ON sub.alertID = am.alertID
        INNER JOIN dbo.[User] u ON u.UserID = sub.UserID
        WHERE am.AlertName = :alert_name
          AND am.AlertStatus = 1
    SQL);
    $stmt->execute(['alert_name' => $alertName]);

    $to = [];
    $cc = [];
    foreach ($stmt->fetchAll() as $row) {
        $email = strtolower(trim((string) $row['UserLogin']));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $name = trim((string) $row['UserName']);
        $bucket = strtoupper((string) $row['AddressType']) === 'CC' ? 'cc' : 'to';
        if ($bucket === 'cc') {
            $cc[$email] = $name !== '' ? $name : $email;
        } else {
            $to[$email] = $name !== '' ? $name : $email;
        }
    }

    return [
        'to' => mail_normalize_recipient_map($to),
        'cc' => mail_normalize_recipient_map($cc),
    ];
}

function alert_recipient_email_list(string $alertName): array
{
    $recipients = alert_message_recipients($alertName);

    return array_keys($recipients['to'] + $recipients['cc']);
}

function alert_send_message(string $alertName, string $subject, string $body): array
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

    $recipients = alert_message_recipients($alertName);
    $result['recipients'] = array_keys($recipients['to'] + $recipients['cc']);

    if ($result['recipients'] === []) {
        $result['skipped_reason'] = 'no_subscribers';

        return $result;
    }

    $greeting = count($result['recipients']) === 1
        ? 'Hello ' . reset($recipients['to'] ?: $recipients['cc']) . ','
        : 'Hello,';

    $send = mail_send_multi_result($recipients['to'], $recipients['cc'], $subject, $greeting . "\n\n" . $body);
    if ($send['ok']) {
        $result['ok'] = true;
        $result['sent'] = $result['recipients'];
    } else {
        $result['ok'] = false;
        foreach ($result['recipients'] as $email) {
            $result['failed'][$email] = (string) ($send['error'] ?? 'SMTP send failed.');
        }
    }

    return $result;
}

function alert_format_notify_message(array $notify, string $emptyMessage): string
{
    if (($notify['skipped_reason'] ?? null) === 'smtp_not_configured') {
        return 'SMTP is not configured, so no email was sent.';
    }

    if (($notify['skipped_reason'] ?? null) === 'no_subscribers') {
        return $emptyMessage;
    }

    $sent = $notify['sent'] ?? [];
    $failed = $notify['failed'] ?? [];

    if ($sent === [] && $failed !== []) {
        $parts = [];
        foreach ($failed as $email => $error) {
            $parts[] = is_int($email)
                ? (string) $error
                : $email . ($error !== '' ? ' (' . $error . ')' : '');
        }

        return 'Email failed for: ' . implode('; ', $parts) . '.';
    }

    if ($sent === []) {
        return 'No email was sent.';
    }

    $message = 'Email sent to: ' . implode(', ', $sent) . '.';
    if ($failed !== []) {
        $failedParts = [];
        foreach ($failed as $email => $error) {
            $failedParts[] = is_int($email)
                ? (string) $error
                : $email . ($error !== '' ? ' (' . $error . ')' : '');
        }
        $message .= ' Failed for: ' . implode('; ', $failedParts) . '.';
    }

    return $message;
}
