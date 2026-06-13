<?php

require_once __DIR__ . '/env.php';

function mail_send(string $to, string $subject, string $body): bool
{
    return mail_send_result($to, $subject, $body)['ok'];
}

function mail_send_result(string $to, string $subject, string $body, ?string $htmlBody = null): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient email address.'];
    }

    return mail_send_multi_result([$to => $to], [], $subject, $body, $htmlBody);
}

function mail_send_html_result(string $to, string $subject, string $htmlBody, string $plainBody): array
{
    return mail_send_result($to, $subject, $plainBody, $htmlBody);
}

function mail_send_html_multi_result(array $toRecipients, array $ccRecipients, string $subject, string $htmlBody, string $plainBody): array
{
    return mail_send_multi_result($toRecipients, $ccRecipients, $subject, $plainBody, $htmlBody);
}

function mail_send_multi_result(array $toRecipients, array $ccRecipients, string $subject, string $body, ?string $htmlBody = null): array
{
    $toRecipients = mail_normalize_recipient_map($toRecipients);
    $ccRecipients = mail_normalize_recipient_map($ccRecipients);

    if ($toRecipients === [] && $ccRecipients === []) {
        return ['ok' => false, 'error' => 'No recipients specified.'];
    }

    if ($toRecipients === [] && $ccRecipients !== []) {
        $firstEmail = array_key_first($ccRecipients);
        $toRecipients[$firstEmail] = $ccRecipients[$firstEmail];
        unset($ccRecipients[$firstEmail]);
    }

    $from = mail_from_address();
    $fromName = mail_from_name();

    if (mail_smtp_is_configured()) {
        return mail_send_smtp_multi($toRecipients, $ccRecipients, $subject, $body, $from, $fromName, $htmlBody);
    }

    $primaryTo = array_key_first($toRecipients);
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . mail_format_address($from, $fromName),
        'Reply-To: ' . mail_reply_to_address(),
        'X-Mailer: NutraAxis-Operations',
    ];
    if ($ccRecipients !== []) {
        $headers[] = 'Cc: ' . mail_format_recipient_list($ccRecipients);
    }

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $boundary = mail_mime_boundary();
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $messageBody = mail_build_multipart_alternative($body, $htmlBody, $boundary);
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $messageBody = mail_normalize_message_body($body);
    }

    $ok = @mail(
        $primaryTo,
        mail_encode_subject($subject),
        str_replace("\n", "\r\n", $messageBody),
        implode("\r\n", $headers)
    );

    return [
        'ok'    => $ok,
        'error' => $ok ? null : 'PHP mail() failed to send message.',
    ];
}

function mail_normalize_recipient_map(array $recipients): array
{
    $normalized = [];
    foreach ($recipients as $email => $name) {
        if (is_int($email)) {
            $email = (string) $name;
            $name = '';
        }

        $email = strtolower(trim((string) $email));
        $name = trim((string) $name);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $normalized[$email] = $name !== '' ? $name : $email;
    }

    return $normalized;
}

function mail_format_recipient_list(array $recipients): string
{
    $parts = [];
    foreach ($recipients as $email => $name) {
        $parts[] = mail_format_address((string) $email, (string) $name);
    }

    return implode(', ', $parts);
}

function mail_from_address(): string
{
    $smtpUser = trim((string) env('SMTP_USER', ''));
    if (mail_smtp_is_configured() && $smtpUser !== '' && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
        // Office 365 requires the visible From address to match the authenticated mailbox.
        return $smtpUser;
    }

    $from = trim((string) env('MAIL_FROM', ''));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }

    if ($smtpUser !== '' && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
        return $smtpUser;
    }

    return 'noreply@nutraaxis.com';
}

function mail_from_name(): string
{
    $name = trim((string) env('MAIL_FROM_NAME', ''));

    return $name !== '' ? $name : 'NutraAxis Operations';
}

function mail_reply_to_address(): string
{
    $replyTo = trim((string) env('MAIL_REPLY_TO', ''));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        return $replyTo;
    }

    return 'nutrateam@nfcllc.com';
}

function mail_smtp_is_configured(): bool
{
    $host = trim((string) env('SMTP_HOST', ''));
    $user = trim((string) env('SMTP_USER', ''));
    $pass = (string) env('SMTP_PASS', '');

    return $host !== '' && $user !== '' && $pass !== '';
}

function mail_config_status(): array
{
    return [
        'smtp_configured' => mail_smtp_is_configured(),
        'transport'       => mail_smtp_is_configured() ? 'smtp' : 'php_mail',
        'smtp_host'       => trim((string) env('SMTP_HOST', '')),
        'smtp_port'       => (int) env('SMTP_PORT', '587'),
        'smtp_user'       => trim((string) env('SMTP_USER', '')),
        'smtp_encryption' => strtolower(trim((string) env('SMTP_ENCRYPTION', 'tls'))),
        'from_address'    => mail_from_address(),
        'from_name'       => mail_from_name(),
        'reply_to'        => mail_reply_to_address(),
    ];
}

function mail_send_php_mail(string $to, string $subject, string $body, string $from, string $fromName): bool
{
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . mail_format_address($from, $fromName),
        'Reply-To: ' . mail_reply_to_address(),
        'X-Mailer: NutraAxis-Operations',
    ]);

    $ok = @mail($to, mail_encode_subject($subject), $body, $headers);
    if (!$ok) {
        error_log("mail_send failed (PHP mail) for {$to}: {$subject}");
    }

    return $ok;
}

function mail_send_smtp_multi(array $toRecipients, array $ccRecipients, string $subject, string $body, string $from, string $fromName, ?string $htmlBody = null): array
{
    $allRecipients = array_keys($toRecipients + $ccRecipients);
    if ($allRecipients === []) {
        return ['ok' => false, 'error' => 'No recipients specified.'];
    }

    $host = trim((string) env('SMTP_HOST', ''));
    $port = (int) env('SMTP_PORT', '587');
    $user = trim((string) env('SMTP_USER', ''));
    $pass = (string) env('SMTP_PASS', '');
    $encryption = strtolower(trim((string) env('SMTP_ENCRYPTION', 'tls')));

    if ($host === '' || $user === '' || $pass === '') {
        return ['ok' => false, 'error' => 'SMTP credentials are incomplete.'];
    }

    if (strcasecmp($from, $user) !== 0) {
        $from = $user;
    }

    $remote = $encryption === 'ssl'
        ? "ssl://{$host}:{$port}"
        : "tcp://{$host}:{$port}";

    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );

    if ($socket === false) {
        $error = "SMTP connect failed to {$remote}: {$errstr} ({$errno})";
        error_log('mail_send ' . $error);

        return ['ok' => false, 'error' => $error];
    }

    stream_set_timeout($socket, 20);

    try {
        mail_smtp_expect($socket, [220]);

        $clientHost = mail_smtp_client_host();
        mail_smtp_command($socket, "EHLO {$clientHost}");
        mail_smtp_expect($socket, [250]);

        if ($encryption === 'tls') {
            mail_smtp_command($socket, 'STARTTLS');
            mail_smtp_expect($socket, [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }

            mail_smtp_command($socket, "EHLO {$clientHost}");
            mail_smtp_expect($socket, [250]);
        }

        if ($user !== '') {
            mail_smtp_command($socket, 'AUTH LOGIN');
            mail_smtp_expect($socket, [334]);
            mail_smtp_command($socket, base64_encode($user));
            mail_smtp_expect($socket, [334]);
            mail_smtp_command($socket, base64_encode($pass));
            mail_smtp_expect($socket, [235]);
        }

        mail_smtp_command($socket, 'MAIL FROM:<' . mail_smtp_envelope_address($from) . '>');
        mail_smtp_expect($socket, [250]);

        foreach ($allRecipients as $recipient) {
            mail_smtp_command($socket, 'RCPT TO:<' . mail_smtp_envelope_address($recipient) . '>');
            mail_smtp_expect($socket, [250, 251]);
        }

        mail_smtp_command($socket, 'DATA');
        mail_smtp_expect($socket, [354]);

        $message = mail_build_message_multi($toRecipients, $ccRecipients, $subject, $body, $from, $fromName, $htmlBody);
        fwrite($socket, $message);
        mail_smtp_command($socket, '.');
        $dataResponse = mail_smtp_read($socket);
        $dataCode = (int) substr(trim($dataResponse), 0, 3);
        if ($dataCode !== 250) {
            throw new RuntimeException(trim($dataResponse));
        }

        mail_smtp_command($socket, 'QUIT');
        mail_smtp_read($socket);

        $smtpResponse = trim($dataResponse);
        error_log('mail_send SMTP accepted message to ' . implode(', ', $allRecipients) . ': ' . $smtpResponse);

        return [
            'ok'            => true,
            'error'         => null,
            'smtp_response' => $smtpResponse,
        ];
    } catch (Throwable $e) {
        $error = trim($e->getMessage());
        error_log('mail_send SMTP failed for ' . implode(', ', $allRecipients) . ': ' . $error);

        return ['ok' => false, 'error' => $error !== '' ? $error : 'SMTP send failed.'];
    } finally {
        fclose($socket);
    }
}

function mail_send_smtp(string $to, string $subject, string $body, string $from, string $fromName): array
{
    return mail_send_smtp_multi([$to => $to], [], $subject, $body, $from, $fromName);
}

function mail_smtp_diagnose(string $to): array
{
    $to = trim($to);
    $result = [
        'to'              => $to,
        'ok'              => false,
        'steps'           => [],
        'error'           => null,
        'smtp_configured' => mail_smtp_is_configured(),
    ];

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'Invalid recipient email address.';

        return $result;
    }

    if (!mail_smtp_is_configured()) {
        $result['error'] = 'SMTP is not fully configured.';

        return $result;
    }

    $host = trim((string) env('SMTP_HOST', ''));
    $port = (int) env('SMTP_PORT', '587');
    $user = trim((string) env('SMTP_USER', ''));
    $pass = (string) env('SMTP_PASS', '');
    $encryption = strtolower(trim((string) env('SMTP_ENCRYPTION', 'tls')));
    $from = mail_from_address();

    $remote = $encryption === 'ssl'
        ? "ssl://{$host}:{$port}"
        : "tcp://{$host}:{$port}";

    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]])
    );

    if ($socket === false) {
        $result['error'] = "SMTP connect failed to {$remote}: {$errstr} ({$errno})";

        return $result;
    }

    stream_set_timeout($socket, 20);

    try {
        $result['steps'][] = mail_smtp_step($socket, 'greeting', [220]);

        $clientHost = mail_smtp_client_host();
        mail_smtp_command($socket, "EHLO {$clientHost}");
        $result['steps'][] = mail_smtp_step($socket, 'ehlo', [250]);

        if ($encryption === 'tls') {
            mail_smtp_command($socket, 'STARTTLS');
            $result['steps'][] = mail_smtp_step($socket, 'starttls', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed.');
            }

            mail_smtp_command($socket, "EHLO {$clientHost}");
            $result['steps'][] = mail_smtp_step($socket, 'ehlo_tls', [250]);
        }

        mail_smtp_command($socket, 'AUTH LOGIN');
        $result['steps'][] = mail_smtp_step($socket, 'auth_login', [334]);
        mail_smtp_command($socket, base64_encode($user));
        $result['steps'][] = mail_smtp_step($socket, 'auth_user', [334]);
        mail_smtp_command($socket, base64_encode($pass));
        $result['steps'][] = mail_smtp_step($socket, 'auth_pass', [235]);

        mail_smtp_command($socket, 'MAIL FROM:<' . mail_smtp_envelope_address($from) . '>');
        $result['steps'][] = mail_smtp_step($socket, 'mail_from', [250]);

        mail_smtp_command($socket, 'RCPT TO:<' . mail_smtp_envelope_address($to) . '>');
        $rcptStep = mail_smtp_step($socket, 'rcpt_to', [250, 251]);
        $result['steps'][] = $rcptStep;

        mail_smtp_command($socket, 'QUIT');
        mail_smtp_read($socket);

        $result['ok'] = $rcptStep['ok'];
        if (!$rcptStep['ok']) {
            $result['error'] = $rcptStep['response'];
        }
    } catch (Throwable $e) {
        $result['error'] = trim($e->getMessage());
    } finally {
        fclose($socket);
    }

    return $result;
}

function mail_smtp_step($socket, string $label, array $codes): array
{
    $response = mail_smtp_read($socket);
    $code = (int) substr(trim($response), 0, 3);
    $ok = in_array($code, $codes, true);

    return [
        'step'     => $label,
        'ok'       => $ok,
        'code'     => $code,
        'response' => trim($response),
    ];
}

function mail_build_message(string $to, string $subject, string $body, string $from, string $fromName): string
{
    return mail_build_message_multi([$to => $to], [], $subject, $body, $from, $fromName);
}

function mail_build_message_multi(array $toRecipients, array $ccRecipients, string $subject, string $body, string $from, string $fromName, ?string $htmlBody = null): string
{
    $headers = [
        'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
        'To: ' . mail_format_recipient_list($toRecipients),
        'From: ' . mail_format_address($from, $fromName),
        'Reply-To: ' . mail_reply_to_address(),
        'Subject: ' . mail_encode_subject($subject),
        'MIME-Version: 1.0',
        'X-Mailer: NutraAxis-Operations',
    ];

    if ($ccRecipients !== []) {
        $headers[] = 'Cc: ' . mail_format_recipient_list($ccRecipients);
    }

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $boundary = mail_mime_boundary();
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $messageBody = mail_build_multipart_alternative($body, $htmlBody, $boundary);
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $messageBody = mail_normalize_message_body($body);
    }

    return implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $messageBody) . "\r\n";
}

function mail_mime_boundary(): string
{
    return 'na-' . bin2hex(random_bytes(12));
}

function mail_normalize_message_body(string $body): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $body);

    return preg_replace('/^\./m', '..', $normalized) ?? $normalized;
}

function mail_build_multipart_alternative(string $plainBody, string $htmlBody, string $boundary): string
{
    $plainBody = mail_normalize_message_body($plainBody);
    $htmlBody = mail_normalize_message_body($htmlBody);

    $parts = [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $plainBody,
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $htmlBody,
        '--' . $boundary . '--',
        '',
    ];

    return implode("\n", $parts);
}

function mail_smtp_command($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function mail_smtp_read($socket): string
{
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function mail_smtp_expect($socket, array $codes): void
{
    $response = mail_smtp_read($socket);
    $code = (int) substr(trim($response), 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException(trim($response));
    }
}

function mail_smtp_client_host(): string
{
    $host = (string) ($_SERVER['SERVER_NAME'] ?? '');
    if ($host === '') {
        $host = parse_url((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), PHP_URL_HOST) ?: 'localhost';
    }

    return preg_replace('/[^a-zA-Z0-9.\-]/', '', $host) ?: 'localhost';
}

function mail_smtp_envelope_address(string $email): string
{
    return trim($email);
}

function mail_format_address(string $email, string $name): string
{
    $name = str_replace(['"', "\r", "\n"], '', $name);

    return $name !== '' ? "\"{$name}\" <{$email}>" : $email;
}

function mail_encode_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}
