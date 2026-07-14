<?php

require_once __DIR__ . '/file-crypto.php';

/**
 * Symmetric key for sensitive provider-signup form fields (tax ID, ACH).
 * Shares resolution with file_crypto_key() so one ops secret covers fields and documents.
 */
function provider_signup_encryption_key(): string
{
    return file_crypto_key();
}

function provider_signup_encrypt(?string $plaintext): ?string
{
    $plaintext = trim((string) $plaintext);
    if ($plaintext === '') {
        return null;
    }

    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        provider_signup_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt sensitive provider signup value.');
    }

    return base64_encode($iv . $tag . $cipher);
}

function provider_signup_decrypt(?string $ciphertext): string
{
    $ciphertext = trim((string) $ciphertext);
    if ($ciphertext === '') {
        return '';
    }

    $raw = base64_decode($ciphertext, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }

    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt(
        $cipher,
        'aes-256-gcm',
        provider_signup_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return is_string($plain) ? $plain : '';
}

function provider_signup_mask_sensitive(string $value, int $visible = 4): string
{
    $value = trim($value);
    if ($value === '') {
        return '—';
    }

    if (strlen($value) <= $visible) {
        return str_repeat('•', strlen($value));
    }

    return str_repeat('•', max(0, strlen($value) - $visible)) . substr($value, -$visible);
}
