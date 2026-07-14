<?php

/**
 * Shared AES-256-GCM helpers for sensitive file payloads stored in Azure Blob.
 *
 * Wire format (binary): magic(5) "NAXE1" + iv(12) + tag(16) + ciphertext
 */

require_once __DIR__ . '/env.php';

const FILE_CRYPTO_MAGIC = 'NAXE1';
const FILE_CRYPTO_IV_BYTES = 12;
const FILE_CRYPTO_TAG_BYTES = 16;

function file_crypto_key(): string
{
    static $key = null;

    if ($key !== null) {
        return $key;
    }

    $configured = trim((string) env_first([
        'FILE_CRYPTO_ENCRYPTION_KEY',
        'PROVIDER_SIGNUP_ENCRYPTION_KEY',
    ], ''));

    if ($configured !== '') {
        return $key = hash('sha256', $configured, true);
    }

    // Keep legacy provider-signup fallback string so existing tax/ACH ciphertext still decrypts.
    $fallback = trim((string) env_first(['DB_PASS', 'DB_PASSWORD'], 'nutraaxis-provider-signup'));

    return $key = hash('sha256', $fallback, true);
}

function file_crypto_is_encrypted_payload(string $payload): bool
{
    return strncmp($payload, FILE_CRYPTO_MAGIC, strlen(FILE_CRYPTO_MAGIC)) === 0;
}

/**
 * @return string Binary ciphertext (includes magic header)
 */
function file_crypto_encrypt_bytes(string $plaintext): string
{
    $iv = random_bytes(FILE_CRYPTO_IV_BYTES);
    $tag = '';
    $cipher = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        file_crypto_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',
        FILE_CRYPTO_TAG_BYTES
    );

    if ($cipher === false || strlen($tag) !== FILE_CRYPTO_TAG_BYTES) {
        throw new RuntimeException('Unable to encrypt file payload.');
    }

    return FILE_CRYPTO_MAGIC . $iv . $tag . $cipher;
}

/**
 * @return string|null Plaintext bytes, or null if decryption fails
 */
function file_crypto_decrypt_bytes(string $payload): ?string
{
    $magicLen = strlen(FILE_CRYPTO_MAGIC);
    $minLen = $magicLen + FILE_CRYPTO_IV_BYTES + FILE_CRYPTO_TAG_BYTES + 1;
    if (!file_crypto_is_encrypted_payload($payload) || strlen($payload) < $minLen) {
        return null;
    }

    $offset = $magicLen;
    $iv = substr($payload, $offset, FILE_CRYPTO_IV_BYTES);
    $offset += FILE_CRYPTO_IV_BYTES;
    $tag = substr($payload, $offset, FILE_CRYPTO_TAG_BYTES);
    $offset += FILE_CRYPTO_TAG_BYTES;
    $cipher = substr($payload, $offset);

    $plain = openssl_decrypt(
        $cipher,
        'aes-256-gcm',
        file_crypto_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    return is_string($plain) ? $plain : null;
}
