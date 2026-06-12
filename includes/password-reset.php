<?php

require_once __DIR__ . '/mail.php';

const PASSWORD_RESET_TOKEN_BYTES = 32;
const PASSWORD_RESET_EXPIRY_HOURS = 1;
const PASSWORD_RESET_MIN_LENGTH = 8;

function password_reset_site_url(): string
{
    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function password_reset_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function password_reset_find_user_by_login(string $login): ?array
{
    $login = trim($login);
    if ($login === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT UserID, UserName, UserLogin FROM dbo.[User] WHERE UserLogin = :login');
    $stmt->execute(['login' => $login]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function password_reset_purge_expired(): void
{
    $pdo = db();
    $pdo->exec('DELETE FROM dbo.PasswordResetToken WHERE ExpiresAt < SYSUTCDATETIME() OR UsedAt IS NOT NULL');
}

function password_reset_request(string $login): array
{
    $login = trim($login);
    if ($login === '') {
        return ['ok' => false, 'error' => 'Enter your email address.'];
    }

    if (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid email address.'];
    }

    try {
        password_reset_purge_expired();

        $user = password_reset_find_user_by_login($login);
        if ($user !== null) {
            $token = bin2hex(random_bytes(PASSWORD_RESET_TOKEN_BYTES));
            $tokenHash = password_reset_hash_token($token);
            $expiresAt = (new DateTimeImmutable('+' . PASSWORD_RESET_EXPIRY_HOURS . ' hours'))->format('Y-m-d H:i:s');

            $pdo = db();
            $invalidate = $pdo->prepare('UPDATE dbo.PasswordResetToken SET UsedAt = SYSUTCDATETIME() WHERE UserID = :user AND UsedAt IS NULL');
            $invalidate->execute(['user' => (int) $user['UserID']]);

            $insert = $pdo->prepare(<<<SQL
                INSERT INTO dbo.PasswordResetToken (UserID, TokenHash, ExpiresAt)
                VALUES (:user, :hash, :expires)
            SQL);
            $insert->execute([
                'user'    => (int) $user['UserID'],
                'hash'    => $tokenHash,
                'expires' => $expiresAt,
            ]);

            $resetUrl = password_reset_site_url() . '/login/reset-password/?token=' . rawurlencode($token);
            $subject = 'Reset your NutraAxis Operations password';
            $body = implode("\n", [
                'Hello ' . ($user['UserName'] ?? 'there') . ',',
                '',
                'We received a request to reset your NutraAxis Operations password.',
                'Use the link below to choose a new password. This link expires in ' . PASSWORD_RESET_EXPIRY_HOURS . ' hour(s).',
                '',
                $resetUrl,
                '',
                'If you did not request a password reset, you can ignore this email.',
                '',
                'NutraAxis Operations',
            ]);

            if (!mail_send((string) $user['UserLogin'], $subject, $body)) {
                error_log('password_reset_request: failed to send reset email for ' . $user['UserLogin']);
            }
        }

        return [
            'ok'      => true,
            'error'   => null,
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ];
    } catch (Throwable $e) {
        error_log('password_reset_request failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to process your request right now. Please try again later.'];
    }
}

function password_reset_validate_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !ctype_xdigit($token) || strlen($token) !== PASSWORD_RESET_TOKEN_BYTES * 2) {
        return null;
    }

    try {
        password_reset_purge_expired();

        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            SELECT
                t.TokenID,
                t.UserID,
                t.ExpiresAt,
                u.UserLogin,
                u.UserName
            FROM dbo.PasswordResetToken t
            INNER JOIN dbo.[User] u ON u.UserID = t.UserID
            WHERE t.TokenHash = :hash
              AND t.UsedAt IS NULL
              AND t.ExpiresAt >= SYSUTCDATETIME()
        SQL);
        $stmt->execute(['hash' => password_reset_hash_token($token)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    } catch (Throwable $e) {
        error_log('password_reset_validate_token failed: ' . $e->getMessage());

        return null;
    }
}

function password_reset_validate_password(string $password, string $confirm): ?string
{
    if ($password === '' || $confirm === '') {
        return 'Enter and confirm your new password.';
    }

    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }

    if (strlen($password) < PASSWORD_RESET_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_RESET_MIN_LENGTH . ' characters.';
    }

    return null;
}

function password_reset_complete(string $token, string $password, string $confirm): array
{
    $passwordError = password_reset_validate_password($password, $confirm);
    if ($passwordError !== null) {
        return ['ok' => false, 'error' => $passwordError];
    }

    $reset = password_reset_validate_token($token);
    if ($reset === null) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired. Request a new one.'];
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $update = $pdo->prepare(<<<SQL
            UPDATE dbo.[User]
            SET UserPassword = :password,
                ModifiedDate = SYSUTCDATETIME(),
                LastPasswordReset = SYSUTCDATETIME()
            WHERE UserID = :id
        SQL);
        $update->execute([
            'password' => $password,
            'id'       => (int) $reset['UserID'],
        ]);

        $mark = $pdo->prepare('UPDATE dbo.PasswordResetToken SET UsedAt = SYSUTCDATETIME() WHERE TokenID = :id');
        $mark->execute(['id' => (int) $reset['TokenID']]);

        $invalidate = $pdo->prepare('UPDATE dbo.PasswordResetToken SET UsedAt = SYSUTCDATETIME() WHERE UserID = :user AND UsedAt IS NULL');
        $invalidate->execute(['user' => (int) $reset['UserID']]);

        $pdo->commit();

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('password_reset_complete failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to reset your password right now. Please try again later.'];
    }
}
