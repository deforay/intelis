<?php

namespace App\Services;

use App\Utilities\CryptoUtility;

/**
 * Backup encryption key management.
 *
 * Each instance owns a single random backup key that db-tools uses as the backup
 * encryption password and that is saved to the STS for recovery. The same base64
 * value plays three roles: the db-tools encryption password, the blob saved to
 * the STS, and the one-time offline recovery code shown at creation.
 *
 * The key lives at var/backup-key.storage, sodium-encrypted with the instance key
 * (the same key.storage CryptoUtility uses), mode 0600. Identity/verification is
 * by fingerprint -- hash('sha256', key) -- so the raw key is never transmitted or
 * logged for comparison.
 *
 * This class owns all backup-key logic so bin/backup-key.php (recovery) and
 * db-tools.php (read-for-encryption) share one path. Autowired like other
 * App\Services.
 */
final class BackupEncryptionService
{
    private const string KEYSTORE_FILE = VAR_PATH . '/backup-key.storage';
    private const int KEY_VERSION = 1;

    /** True if a local backup key already exists (used to print the recovery code only on first creation). */
    public function localKeyExists(): bool
    {
        return is_file(self::KEYSTORE_FILE);
    }

    /**
     * Read the local backup key (base64), decrypting the keystore. Returns null if
     * no key has been created yet. Used by db-tools.php -- never creates a key.
     */
    public function getLocalKey(): ?string
    {
        if (!is_file(self::KEYSTORE_FILE)) {
            return null;
        }

        $cipher = trim((string) file_get_contents(self::KEYSTORE_FILE));
        if ($cipher === '') {
            return null;
        }

        return CryptoUtility::decrypt($cipher);
    }

    /**
     * Return the local backup key, generating and persisting one on first call.
     * The key is base64(random_bytes(32)); the keystore stores its sodium
     * ciphertext at 0600.
     */
    public function getOrCreateLocalKey(): string
    {
        $existing = $this->getLocalKey();
        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        $key = base64_encode(random_bytes(32));

        file_put_contents(self::KEYSTORE_FILE, CryptoUtility::encrypt($key), LOCK_EX);
        @chmod(self::KEYSTORE_FILE, 0600);

        return $key;
    }

    /** Fingerprint used for recovery verification and identity. Never transmit the key itself. */
    public function fingerprint(string $key): string
    {
        return hash('sha256', $key);
    }

    public function getKeyVersion(): int
    {
        return self::KEY_VERSION;
    }
}
