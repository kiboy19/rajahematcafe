<?php

namespace App\Payments\Notification;

use RuntimeException;

final class PayloadCipher
{
    private const KEY_ID_LENGTH = 4;
    private const TAG_LENGTH = 16;
    private const IV_LENGTH = 12;
    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private readonly string $payloadKey,
        private readonly string $keyId = '0001',
    ) {
        if (strlen($this->payloadKey) !== 64) {
            throw new RuntimeException('Payload key must be 64 hex characters (32 bytes).');
        }
        if (strlen($this->keyId) !== 4) {
            throw new RuntimeException('Key ID must be exactly 4 characters.');
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $key = hex2bin($this->payloadKey);
        if ($key === false) {
            throw new RuntimeException('Invalid payload key.');
        }
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($ciphertext === false || $tag === '') {
            throw new RuntimeException('Encryption failed.');
        }

        return $this->keyId . $iv . $ciphertext . $tag;
    }

    public function decrypt(string $encrypted): string
    {
        if (strlen($encrypted) < self::KEY_ID_LENGTH + self::IV_LENGTH + self::TAG_LENGTH) {
            throw new RuntimeException('Ciphertext too short.');
        }

        $keyId = substr($encrypted, 0, self::KEY_ID_LENGTH);
        $iv = substr($encrypted, self::KEY_ID_LENGTH, self::IV_LENGTH);
        $ciphertext = substr($encrypted, self::KEY_ID_LENGTH + self::IV_LENGTH, -self::TAG_LENGTH);
        $tag = substr($encrypted, -self::TAG_LENGTH);

        if ($keyId !== $this->keyId) {
            throw new RuntimeException('Unknown key ID.');
        }

        $key = hex2bin($this->payloadKey);
        if ($key === false) {
            throw new RuntimeException('Invalid payload key.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed (authentication tag mismatch).');
        }

        return $plaintext;
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }
}