<?php

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/env.php';

const PROVIDER_SIGNUP_SUPPORT_EMAIL = 'sales@nutraaxislabs.com';
const PROVIDER_SIGNUP_OPS_SILENT_EMAIL = 'NutraAxis@nfcllc.com';

function provider_signup_mail_base_url(): string
{
    $configured = rtrim(trim((string) env('SITE_URL', '')), '/');
    if ($configured !== '') {
        return $configured;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

    return $scheme . '://' . $host;
}

function provider_signup_apply_url(string $accessToken): string
{
    return provider_signup_mail_base_url() . '/provider-signup/apply.php?token=' . rawurlencode($accessToken);
}

function provider_signup_policy_url(string $accessToken): string
{
    return provider_signup_mail_base_url() . '/provider-signup/policy.php?token=' . rawurlencode($accessToken);
}

function provider_signup_accs_login_url(): string
{
    $configured = rtrim(trim((string) env('PROVIDER_ACCS_LOGIN_URL', '')), '/');
    if ($configured !== '') {
        return $configured;
    }

    return rtrim(trim((string) env('NUTRAAXIS_STORE_URL', 'https://www.nutraaxislabs.com')), '/');
}

function provider_signup_support_mailto_url(string $subject = 'Provider application help'): string
{
    return 'mailto:' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '?subject=' . rawurlencode($subject);
}

/**
 * @return array<string, string>
 */
function provider_signup_ops_silent_recipients(): array
{
    $configured = trim((string) env('PROVIDER_SIGNUP_OPS_EMAIL', ''));
    if ($configured !== '') {
        $emails = preg_split('/\s*,\s*/', $configured) ?: [];
        $recipients = [];
        foreach ($emails as $email) {
            $email = strtolower(trim($email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = $email;
            }
        }

        if ($recipients !== []) {
            return $recipients;
        }
    }

    return [strtolower(PROVIDER_SIGNUP_OPS_SILENT_EMAIL) => 'NutraAxis'];
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_provider(array $application, string $subject, string $plainBody, string $htmlBody): void
{
    $email = trim((string) ($application['ProviderEmail'] ?? ''));
    if ($email === '') {
        return;
    }

    mail_send_html_result($email, $subject, $htmlBody, $plainBody);
}

function provider_signup_mail_ops_silent(string $subject, string $plainBody, string $htmlBody): void
{
    $recipients = provider_signup_ops_silent_recipients();
    if ($recipients === []) {
        return;
    }

    mail_send_html_multi_result($recipients, [], $subject, $htmlBody, $plainBody);
}

function provider_signup_confirm_email_url(string $challengeToken): string
{
    return provider_signup_mail_base_url() . '/provider-signup/confirm-email.php?token=' . rawurlencode($challengeToken);
}

/**
 * Email ownership challenge — sent before an application row exists.
 */
function provider_signup_mail_email_challenge(string $providerEmail, string $challengeToken): void
{
    $providerEmail = strtolower(trim($providerEmail));
    if ($providerEmail === '' || !filter_var($providerEmail, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $confirmUrl = provider_signup_confirm_email_url($challengeToken);
    $subject = 'Confirm your email to continue your NutraAxis provider application';
    $plain = implode("\n", [
        'Confirm your email address to start (or resume) your NutraAxis provider application.',
        '',
        'This link expires in 60 minutes:',
        $confirmUrl,
        '',
        'If you did not request this, you can ignore this email.',
        '',
        'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.',
        '',
        '— NutraAxis',
    ]);
    $html = '<p>Confirm your email address to start (or resume) your NutraAxis provider application.</p>'
        . '<p><a href="' . htmlspecialchars($confirmUrl) . '">Confirm email and continue</a></p>'
        . '<p>This link expires in 60 minutes.</p>'
        . '<p>If you did not request this, you can ignore this email.</p>'
        . '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url()) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    mail_send_html_result($providerEmail, $subject, $html, $plain);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_application_started(array $application): void
{
    $company = trim((string) ($application['CompanyName'] ?? ''));
    $label = $company !== '' ? $company : 'your practice';
    $continueUrl = provider_signup_policy_url((string) $application['AccessToken']);
    $subject = 'Continue your NutraAxis provider application';

    $plain = implode("\n", [
        'Thank you for starting a NutraAxis provider application for ' . $label . '.',
        '',
        'Use the link below to review the Practitioner Reseller Policy, acknowledge it, and continue your application:',
        $continueUrl,
        '',
        'You can return to this link any time while your application is in draft or returned status.',
        '',
        'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.',
        '',
        '— NutraAxis',
    ]);

    $html = '<p>Thank you for starting a NutraAxis provider application for <strong>'
        . htmlspecialchars($label)
        . '</strong>.</p>'
        . '<p><a href="' . htmlspecialchars($continueUrl) . '">Continue your application</a></p>'
        . '<p>You will review and acknowledge the Practitioner Reseller Policy before completing the application form.</p>'
        . '<p>You can return to this link any time while your application is in draft or returned status.</p>'
        . '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url()) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
    provider_signup_mail_application_started_ops($application);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_application_started_ops(array $application): void
{
    $company = trim((string) ($application['CompanyName'] ?? ''));
    $id = (int) ($application['ApplicationID'] ?? 0);
    $mgmtUrl = provider_signup_mail_base_url() . '/operations-dashboard/signup-review/view.php?id=' . $id;
    $plainOps = implode("\n", [
        'A new provider application was started.',
        '',
        'Application ID: ' . $id,
        'Provider email: ' . (string) ($application['ProviderEmail'] ?? ''),
        'Practice: ' . ($company !== '' ? $company : '(not entered yet)'),
        '',
        'Review: ' . $mgmtUrl,
    ]);
    $htmlOps = '<p>A new provider application was started.</p>'
        . '<p><strong>Application ID:</strong> ' . htmlspecialchars((string) $id) . '<br>'
        . '<strong>Provider email:</strong> ' . htmlspecialchars((string) ($application['ProviderEmail'] ?? '')) . '<br>'
        . '<strong>Practice:</strong> ' . htmlspecialchars($company !== '' ? $company : '(not entered yet)') . '</p>'
        . '<p><a href="' . htmlspecialchars($mgmtUrl) . '">Review application</a></p>';

    provider_signup_mail_ops_silent('New provider application started — #' . $id, $plainOps, $htmlOps);
}

/**
 * Confirmation + return link after provider submits (includes complete-documents CTA when needed).
 *
 * @param array<string, mixed> $application
 * @param list<string> $documentWarnings
 */
function provider_signup_mail_application_submitted(array $application, array $documentWarnings = []): void
{
    $company = trim((string) ($application['CompanyName'] ?? ''));
    $label = $company !== '' ? $company : 'your practice';
    $applyUrl = provider_signup_apply_url((string) $application['AccessToken']);
    $id = (int) ($application['ApplicationID'] ?? 0);
    $needsDocuments = $documentWarnings !== [];
    $subject = $needsDocuments
        ? 'Complete your NutraAxis provider documents'
        : 'We received your NutraAxis provider application';

    $plainLines = [
        'Thank you for submitting your NutraAxis provider application for ' . $label . '.',
        '',
        'Application ID: ' . $id,
        'Status: Submitted for Review',
        '',
    ];

    if ($needsDocuments) {
        $plainLines[] = 'You can still upload your state reseller certificate and/or add ACH payout details using this secure link:';
        $plainLines[] = $applyUrl;
        $plainLines[] = '';
        $plainLines[] = 'Outstanding items:';
        foreach ($documentWarnings as $warning) {
            $plainLines[] = '- ' . $warning;
        }
        $plainLines[] = '';
        $plainLines[] = 'Your application is already under review. Completing these items helps with tax exemption and clinic payouts.';
    } else {
        $plainLines[] = 'Save this link if you need to return and review your application status or update documents:';
        $plainLines[] = $applyUrl;
    }

    $plainLines[] = '';
    $plainLines[] = 'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.';
    $plainLines[] = '';
    $plainLines[] = '— NutraAxis';

    $html = '<p>Thank you for submitting your NutraAxis provider application for <strong>'
        . htmlspecialchars($label)
        . '</strong>.</p>'
        . '<p><strong>Application ID:</strong> ' . htmlspecialchars((string) $id) . '<br>'
        . '<strong>Status:</strong> Submitted for Review</p>';

    if ($needsDocuments) {
        $html .= '<p>You can still upload your state reseller certificate and/or add ACH payout details:</p>'
            . '<p><a href="' . htmlspecialchars($applyUrl) . '">Complete documents &amp; ACH details</a></p>'
            . '<ul>';
        foreach ($documentWarnings as $warning) {
            $html .= '<li>' . htmlspecialchars($warning) . '</li>';
        }
        $html .= '</ul>'
            . '<p>Your application is already under review. Completing these items helps with tax exemption and clinic payouts.</p>';
    } else {
        $html .= '<p><a href="' . htmlspecialchars($applyUrl) . '">Return to your application</a></p>';
    }

    $html .= '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url()) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    provider_signup_mail_provider($application, $subject, implode("\n", $plainLines), $html);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_commented(array $application, string $comments): void
{
    $applyUrl = provider_signup_apply_url((string) $application['AccessToken']);
    $subject = 'Update on your NutraAxis provider application';
    $plain = implode("\n", [
        'An operations reviewer left a comment on your NutraAxis provider application:',
        '',
        $comments,
        '',
        'View your application: ' . $applyUrl,
        '',
        'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.',
        '',
        '— NutraAxis Operations',
    ]);
    $html = '<p>An operations reviewer left a comment on your NutraAxis provider application:</p>'
        . '<blockquote>' . nl2br(htmlspecialchars($comments)) . '</blockquote>'
        . '<p><a href="' . htmlspecialchars($applyUrl) . '">View your application</a></p>'
        . '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url()) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_returned(array $application, string $comments): void
{
    $applyUrl = provider_signup_apply_url((string) $application['AccessToken']);
    $subject = 'Action needed on your NutraAxis provider application';
    $plain = implode("\n", [
        'Your NutraAxis provider application was sent back for more information.',
        '',
        $comments !== '' ? "Reviewer notes:\n" . $comments . "\n" : '',
        'Please update your application and save your changes:',
        $applyUrl,
        '',
        'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.',
        '',
        '— NutraAxis Operations',
    ]);
    $html = '<p>Your NutraAxis provider application was sent back for more information.</p>';
    if ($comments !== '') {
        $html .= '<blockquote>' . nl2br(htmlspecialchars($comments)) . '</blockquote>';
    }
    $html .= '<p><a href="' . htmlspecialchars($applyUrl) . '">Update your application</a></p>'
        . '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url()) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_reopened(array $application, string $comments): void
{
    $applyUrl = provider_signup_apply_url((string) $application['AccessToken']);
    $subject = 'Your NutraAxis provider application was reopened';
    $plain = implode("\n", [
        'Your NutraAxis provider application has been reopened so you can make updates.',
        '',
        $comments !== '' ? "Reviewer notes:\n" . $comments . "\n" : '',
        'Continue your application here:',
        $applyUrl,
        '',
        'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.',
        '',
        '— NutraAxis Operations',
    ]);
    $html = '<p>Your NutraAxis provider application has been reopened so you can make updates.</p>';
    if ($comments !== '') {
        $html .= '<blockquote>' . nl2br(htmlspecialchars($comments)) . '</blockquote>';
    }
    $html .= '<p><a href="' . htmlspecialchars($applyUrl) . '">Continue your application</a></p>'
        . '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url()) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_provisioned(array $application, ?string $temporaryPassword = null): void
{
    $loginUrl = provider_signup_accs_login_url();
    $clinicId = trim((string) ($application['AccsClinicId'] ?? ''));
    $signInEmail = (string) ($application['AdminEmail'] ?? $application['ProviderEmail'] ?? '');
    $subject = 'Your NutraAxis Clinic Store is ready';
    $temporaryPassword = trim((string) $temporaryPassword);

    $plainLines = [
        'Your NutraAxis provider account has been created and your Clinic Store is ready.',
        '',
        'Sign in at: ' . $loginUrl,
        'Sign in email: ' . $signInEmail,
    ];

    if ($clinicId !== '') {
        $plainLines[] = 'Clinic ID: ' . $clinicId;
    }

    $plainLines[] = '';

    if ($temporaryPassword !== '') {
        $plainLines[] = 'Temporary password: ' . $temporaryPassword;
        $plainLines[] = 'Change this password after your first sign-in.';
    } else {
        $plainLines[] = 'Use your existing NutraAxis Labs password, or reset it from the sign-in page if needed.';
    }

    $plainLines[] = '';
    $plainLines[] = 'If you need help, email ' . PROVIDER_SIGNUP_SUPPORT_EMAIL . '.';
    $plainLines[] = '';
    $plainLines[] = '— NutraAxis';

    $plain = implode("\n", $plainLines);

    $html = '<p>Your NutraAxis provider account has been created and your Clinic Store is ready.</p>'
        . '<p><a href="' . htmlspecialchars($loginUrl) . '">Sign in to NutraAxis Labs</a><br>'
        . '<strong>Sign in email:</strong> ' . htmlspecialchars($signInEmail) . '<br>'
        . '<strong>Clinic ID:</strong> ' . htmlspecialchars($clinicId !== '' ? $clinicId : '(pending)') . '</p>';

    if ($temporaryPassword !== '') {
        $html .= '<p><strong>Temporary password:</strong> ' . htmlspecialchars($temporaryPassword)
            . '<br>Change this password after your first sign-in.</p>';
    } else {
        $html .= '<p>Use your existing NutraAxis Labs password, or reset it from the sign-in page if needed.</p>';
    }

    $html .= '<p>If you need help, email <a href="' . htmlspecialchars(provider_signup_support_mailto_url('Clinic Store login help')) . '">'
        . htmlspecialchars(PROVIDER_SIGNUP_SUPPORT_EMAIL) . '</a>.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
}
