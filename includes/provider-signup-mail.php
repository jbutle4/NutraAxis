<?php

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/env.php';

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

function provider_signup_ops_recipients(): array
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

    $fallback = trim((string) env('PO_TEAM_EMAIL', ''));
    if ($fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
        return [$fallback => $fallback];
    }

    return [];
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

function provider_signup_mail_ops(string $subject, string $plainBody, string $htmlBody): void
{
    $recipients = provider_signup_ops_recipients();
    if ($recipients === []) {
        return;
    }

    mail_send_html_multi_result($recipients, [], $subject, $htmlBody, $plainBody);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_application_started(array $application): void
{
    $company = trim((string) ($application['CompanyName'] ?? ''));
    $label = $company !== '' ? $company : 'your practice';
    $applyUrl = provider_signup_apply_url((string) $application['AccessToken']);
    $subject = 'Continue your NutraAxis provider application';

    $plain = implode("\n", [
        'Thank you for starting a NutraAxis provider application for ' . $label . '.',
        '',
        'Use the link below to save your progress and submit when ready:',
        $applyUrl,
        '',
        'You can return to this link any time while your application is in draft or returned status.',
        '',
        '— NutraAxis',
    ]);

    $html = '<p>Thank you for starting a NutraAxis provider application for <strong>'
        . htmlspecialchars($label)
        . '</strong>.</p>'
        . '<p><a href="' . htmlspecialchars($applyUrl) . '">Continue your application</a></p>'
        . '<p>You can return to this link any time while your application is in draft or returned status.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_submitted(array $application): void
{
    $id = (int) ($application['ApplicationID'] ?? 0);
    $company = trim((string) ($application['CompanyName'] ?? 'Provider application'));
    $subject = 'NutraAxis provider application submitted';

    $plainProvider = implode("\n", [
        'Your NutraAxis provider application has been submitted for review.',
        '',
        'Application ID: ' . $id,
        'Practice: ' . $company,
        '',
        'Our operations team will review your information and contact you if anything else is needed.',
        '',
        '— NutraAxis',
    ]);

    $htmlProvider = '<p>Your NutraAxis provider application has been submitted for review.</p>'
        . '<p><strong>Application ID:</strong> ' . htmlspecialchars((string) $id) . '<br>'
        . '<strong>Practice:</strong> ' . htmlspecialchars($company) . '</p>'
        . '<p>Our operations team will review your information and contact you if anything else is needed.</p>';

    provider_signup_mail_provider($application, $subject, $plainProvider, $htmlProvider);

    $mgmtUrl = provider_signup_mail_base_url() . '/operations-dashboard/signup-review/view.php?id=' . $id;
    $plainOps = implode("\n", [
        'A provider application was submitted.',
        '',
        'Application ID: ' . $id,
        'Practice: ' . $company,
        'Provider email: ' . (string) ($application['ProviderEmail'] ?? ''),
        '',
        'Review: ' . $mgmtUrl,
    ]);
    $htmlOps = '<p>A provider application was submitted.</p>'
        . '<p><strong>Application ID:</strong> ' . htmlspecialchars((string) $id) . '<br>'
        . '<strong>Practice:</strong> ' . htmlspecialchars($company) . '<br>'
        . '<strong>Provider email:</strong> ' . htmlspecialchars((string) ($application['ProviderEmail'] ?? '')) . '</p>'
        . '<p><a href="' . htmlspecialchars($mgmtUrl) . '">Review application</a></p>';

    provider_signup_mail_ops('Provider application submitted — ' . $company, $plainOps, $htmlOps);
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
        '— NutraAxis Operations',
    ]);
    $html = '<p>An operations reviewer left a comment on your NutraAxis provider application:</p>'
        . '<blockquote>' . nl2br(htmlspecialchars($comments)) . '</blockquote>'
        . '<p><a href="' . htmlspecialchars($applyUrl) . '">View your application</a></p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
    provider_signup_mail_ops(
        'Provider application comment — #' . (int) ($application['ApplicationID'] ?? 0),
        $plain,
        $html
    );
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
        '— NutraAxis Operations',
    ]);
    $html = '<p>Your NutraAxis provider application was sent back for more information.</p>';
    if ($comments !== '') {
        $html .= '<blockquote>' . nl2br(htmlspecialchars($comments)) . '</blockquote>';
    }
    $html .= '<p><a href="' . htmlspecialchars($applyUrl) . '">Update your application</a></p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
    provider_signup_mail_ops(
        'Provider application returned — #' . (int) ($application['ApplicationID'] ?? 0),
        $plain,
        $html
    );
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
        '— NutraAxis Operations',
    ]);
    $html = '<p>Your NutraAxis provider application has been reopened so you can make updates.</p>';
    if ($comments !== '') {
        $html .= '<blockquote>' . nl2br(htmlspecialchars($comments)) . '</blockquote>';
    }
    $html .= '<p><a href="' . htmlspecialchars($applyUrl) . '">Continue your application</a></p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
    provider_signup_mail_ops(
        'Provider application reopened — #' . (int) ($application['ApplicationID'] ?? 0),
        $plain,
        $html
    );
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_approved(array $application): void
{
    $company = trim((string) ($application['CompanyName'] ?? 'your practice'));
    $subject = 'Your NutraAxis provider application is approved';

    $plain = implode("\n", [
        'Your NutraAxis provider application for ' . $company . ' has been approved by our operations team.',
        '',
        'We are creating your Clinic Store in ACCS now. You will receive another email when your provider account is ready to use.',
        '',
        '— NutraAxis',
    ]);

    $html = '<p>Your NutraAxis provider application for <strong>'
        . htmlspecialchars($company)
        . '</strong> has been approved by our operations team.</p>'
        . '<p>We are creating your Clinic Store in ACCS now. You will receive another email when your provider account is ready to use.</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
    provider_signup_mail_ops(
        'Provider application approved — #' . (int) ($application['ApplicationID'] ?? 0),
        $plain,
        $html
    );
}

/**
 * @param array<string, mixed> $application
 */
function provider_signup_mail_provisioned(array $application): void
{
    $storeUrl = rtrim(trim((string) env('NUTRAAXIS_STORE_URL', 'https://www.nutraaxislabs.com/')), '/') . '/';
    $clinicId = trim((string) ($application['AccsClinicId'] ?? ''));
    $subject = 'Your NutraAxis provider account is ready';

    $plain = implode("\n", [
        'Your NutraAxis provider account has been approved and created.',
        '',
        'Store: ' . $storeUrl,
        'Sign in email: ' . (string) ($application['AdminEmail'] ?? $application['ProviderEmail'] ?? ''),
        $clinicId !== '' ? 'Clinic ID: ' . $clinicId : 'Clinic ID: (pending)',
        '',
        'Use the credentials sent separately or your assigned password to sign in.',
        '',
        '— NutraAxis',
    ]);

    $html = '<p>Your NutraAxis provider account has been approved and created.</p>'
        . '<p><strong>Store:</strong> <a href="' . htmlspecialchars($storeUrl) . '">' . htmlspecialchars($storeUrl) . '</a><br>'
        . '<strong>Sign in email:</strong> ' . htmlspecialchars((string) ($application['AdminEmail'] ?? $application['ProviderEmail'] ?? '')) . '<br>'
        . '<strong>Clinic ID:</strong> ' . htmlspecialchars($clinicId !== '' ? $clinicId : '(pending)') . '</p>';

    provider_signup_mail_provider($application, $subject, $plain, $html);
    provider_signup_mail_ops(
        'Provider account provisioned — #' . (int) ($application['ApplicationID'] ?? 0),
        $plain,
        $html
    );
}
