<?php

namespace App\Utilities;

use Throwable;
use InvalidArgumentException;
use App\Exceptions\SystemException;

final class CryptoUtility
{
    private const string KEY_FILE = VAR_PATH . '/key.storage'; // File to store the key

    private static function getKey(): string
    {
        if (!file_exists(self::KEY_FILE)) {
            throw new SystemException('Key retrieval failed: key file does not exist.');
        }

        $key = base64_decode(file_get_contents(self::KEY_FILE));
        if (!$key || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new SystemException('Key retrieval failed: invalid key.');
        }

        return $key;
    }

    public static function setKey(string $key): void
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException('Invalid key length.');
        }

        file_put_contents(self::KEY_FILE, base64_encode($key), LOCK_EX);
        chmod(self::KEY_FILE, 0600); // Ensure only the owner can access the key file
    }

    public static function encrypt(string $data, $key = null): string
    {
        $key ??= self::getKey();

        try {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            $cipher = sodium_bin2base64(
                $nonce .
                    sodium_crypto_secretbox(
                        $data,
                        $nonce,
                        (string) $key
                    ),
                SODIUM_BASE64_VARIANT_URLSAFE
            );

            sodium_memzero($data);
            sodium_memzero($key);

            return $cipher;
        } catch (Throwable $e) {
            throw new SystemException('Encryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function decrypt(string $encryptedData, $key = null): string
    {
        $key ??= self::getKey();

        // Validate the encryption key
        if (empty($key) || strlen((string) $key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException('Invalid decryption key provided.');
        }

        try {
            // Decode the base64-encoded encrypted data
            $decoded = sodium_base642bin($encryptedData, SODIUM_BASE64_VARIANT_URLSAFE);

            if ($decoded === '' || $decoded === '0' || $decoded === false) {
                throw new SystemException('Failed to decode the encrypted message.');
            }

            // Ensure the decoded message is not truncated
            if (strlen($decoded) < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
                throw new SystemException('The encrypted message was truncated.');
            }

            // Extract the nonce and ciphertext
            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            // Decrypt the ciphertext
            $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

            if ($plain === false) {
                throw new SystemException('The encrypted message is invalid or has been tampered with.');
            }

            sodium_memzero($ciphertext);
            sodium_memzero($key);

            return $plain;
        } catch (Throwable $e) {
            throw new SystemException('Decryption failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
