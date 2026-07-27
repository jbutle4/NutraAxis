<?php

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/env.php';

const PROVIDER_SIGNUP_SUPPORT_EMAIL = 'sales@nutraaxislabs.com';
const PROVIDER_SIGNUP_PROVISIONED_SUPPORT_EMAIL = 'support@nutraaxislabs.com';
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

function provider_signup_provisioned_support_mailto_url(string $subject = 'Clinic Store help'): string
{
    return 'mailto:' . PROVIDER_SIGNUP_PROVISIONED_SUPPORT_EMAIL . '?subject=' . rawurlencode($subject);
}

function provider_signup_mail_logo_url(): string
{
    return provider_signup_mail_base_url() . '/assets/logos/nutraaxis-logo-email.png';
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
    $company = trim((string) ($application['CompanyName'] ?? ''));
    $label = $company !== '' ? $company : 'your practice';
    $subject = 'Welcome to NutraAxis — your Clinic Store account is ready';
    $temporaryPassword = trim((string) $temporaryPassword);
    $logoUrl = provider_signup_mail_logo_url();
    $supportEmail = PROVIDER_SIGNUP_PROVISIONED_SUPPORT_EMAIL;
    $supportMailto = provider_signup_provisioned_support_mailto_url();

    $plainLines = [
        'Welcome and congratulations!',
        '',
        'Your NutraAxis provider account has been created for ' . $label . '. We are excited to have you with us.',
        '',
        'WHAT YOU CAN DO NOW',
        '- You can buy products today at wholesale pricing, with applicable state sales tax applied.',
        '- Once your reseller certificate is validated, tax exemption will be applied and your account will be updated accordingly.',
        '',
        'YOUR CLINIC STORE',
        '- Your co-branded clinic storefront will be provisioned after August 3, 2026. As clinic admin, you will be able to set your own retail pricing for patients.',
        '- You can invite patients with your clinic QR code or shareable store link (available in your Clinic Store admin after the storefront is live).',
        '- You can add your own clinic logo to the storefront.',
        '',
        'COMMISSIONS',
        '- Commission transfers are sent monthly after reporting summaries are completed.',
        '',
        'SIGN IN TO GET STARTED',
        'Sign in: ' . $loginUrl,
        'Sign in email: ' . $signInEmail,
    ];

    if ($clinicId !== '') {
        $plainLines[] = 'Clinic ID: ' . $clinicId;
    }

    $plainLines[] = '';

    if ($temporaryPassword !== '') {
        $plainLines[] = 'Temporary password: ' . $temporaryPassword;
        $plainLines[] = 'Please change this password after your first sign-in.';
    } else {
        $plainLines[] = 'Use your existing NutraAxis Labs password, or reset it from the sign-in page if needed.';
    }

    $plainLines[] = '';
    $plainLines[] = 'Questions or issues? Contact us anytime at ' . $supportEmail . '.';
    $plainLines[] = '';
    $plainLines[] = '— The NutraAxis Team';

    $plain = implode("\n", $plainLines);

    $passwordHtml = $temporaryPassword !== ''
        ? '<p style="margin:0 0 8px;font-size:15px;line-height:1.5;color:#1a2e2d;"><strong>Temporary password:</strong> '
            . htmlspecialchars($temporaryPassword)
            . '</p><p style="margin:0;font-size:14px;line-height:1.5;color:#5a7170;">Please change this password after your first sign-in.</p>'
        : '<p style="margin:0;font-size:14px;line-height:1.5;color:#5a7170;">Use your existing NutraAxis Labs password, or reset it from the sign-in page if needed.</p>';

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . htmlspecialchars($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background-color:#f5fafa;font-family:Inter,Arial,Helvetica,sans-serif;color:#1a2e2d;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f5fafa;">'
        . '<tr><td align="center" style="padding:24px 16px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;">'
        . '<tr><td style="background-color:#e8f5f4;border-radius:12px 12px 0 0;padding:28px 32px 24px;text-align:center;">'
        . '<img src="' . htmlspecialchars($logoUrl) . '" alt="NutraAxis" width="220" height="40" style="display:block;margin:0 auto;max-width:220px;height:auto;border:0;" />'
        . '</td></tr>'
        . '<tr><td style="background-color:#ffffff;padding:32px;border-left:1px solid #d6ecea;border-right:1px solid #d6ecea;">'
        . '<h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;font-weight:800;color:#1a2e2d;">Welcome and congratulations!</h1>'
        . '<p style="margin:0 0 24px;font-size:16px;line-height:1.6;color:#5a7170;">Your NutraAxis provider account has been created for <strong style="color:#1a2e2d;">'
        . htmlspecialchars($label)
        . '</strong>. We\'re excited to have you with us.</p>'
        . '<h2 style="margin:0 0 10px;font-size:13px;line-height:1.4;letter-spacing:0.08em;text-transform:uppercase;color:#2a6b65;">What you can do now</h2>'
        . '<ul style="margin:0 0 24px;padding:0 0 0 20px;font-size:15px;line-height:1.6;color:#1a2e2d;">'
        . '<li style="margin-bottom:8px;">You can buy products today at wholesale pricing, with applicable state sales tax applied.</li>'
        . '<li>Once your reseller certificate is validated, tax exemption will be applied and your account will be updated accordingly.</li>'
        . '</ul>'
        . '<h2 style="margin:0 0 10px;font-size:13px;line-height:1.4;letter-spacing:0.08em;text-transform:uppercase;color:#2a6b65;">Your Clinic Store</h2>'
        . '<ul style="margin:0 0 24px;padding:0 0 0 20px;font-size:15px;line-height:1.6;color:#1a2e2d;">'
        . '<li style="margin-bottom:8px;">Your co-branded clinic storefront will be provisioned after <strong>August 3, 2026</strong>. As clinic admin, you\'ll be able to set your own retail pricing for patients.</li>'
        . '<li style="margin-bottom:8px;">You can invite patients with your clinic\'s <strong>QR code</strong> or shareable store link (available in your Clinic Store admin after the storefront is live).</li>'
        . '<li>You can add your own clinic logo to the storefront.</li>'
        . '</ul>'
        . '<h2 style="margin:0 0 10px;font-size:13px;line-height:1.4;letter-spacing:0.08em;text-transform:uppercase;color:#2a6b65;">Commissions</h2>'
        . '<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#1a2e2d;">Commission transfers are sent monthly after reporting summaries are completed.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">'
        . '<tr><td align="center">'
        . '<a href="' . htmlspecialchars($loginUrl) . '" style="display:inline-block;background-color:#3d8b85;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;line-height:1;padding:14px 28px;border-radius:8px;">Sign in to NutraAxis Labs</a>'
        . '</td></tr></table>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f5fafa;border:1px solid #d6ecea;border-radius:8px;">'
        . '<tr><td style="padding:20px 22px;">'
        . '<p style="margin:0 0 12px;font-size:13px;line-height:1.4;letter-spacing:0.08em;text-transform:uppercase;color:#2a6b65;font-weight:700;">Sign in to get started</p>'
        . '<p style="margin:0 0 8px;font-size:15px;line-height:1.5;color:#1a2e2d;"><strong>Sign in email:</strong> '
        . htmlspecialchars($signInEmail) . '</p>'
        . '<p style="margin:0 0 8px;font-size:15px;line-height:1.5;color:#1a2e2d;"><strong>Clinic ID:</strong> '
        . htmlspecialchars($clinicId !== '' ? $clinicId : '(pending)') . '</p>'
        . $passwordHtml
        . '</td></tr></table>'
        . '</td></tr>'
        . '<tr><td style="background-color:#ffffff;border:1px solid #d6ecea;border-top:0;border-radius:0 0 12px 12px;padding:0 32px 32px;">'
        . '<p style="margin:0;font-size:15px;line-height:1.6;color:#5a7170;">Questions or issues? Contact us anytime at '
        . '<a href="' . htmlspecialchars($supportMailto) . '" style="color:#3d8b85;text-decoration:none;font-weight:600;">'
        . htmlspecialchars($supportEmail) . '</a>.</p>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 8px 0;text-align:center;">'
        . '<p style="margin:0;font-size:13px;line-height:1.5;color:#5a7170;">- The NutraAxis Team</p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
}
