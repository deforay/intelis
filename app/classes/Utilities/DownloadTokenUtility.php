<?php

namespace App\Utilities;

use Throwable;
use App\Exceptions\SystemException;

/**
 * Short-lived signed grants for download.php.
 *
 * Historically download.php had no privilege of its own, so the only thing that
 * let a non-superadmin fetch a generated export was AclMiddleware's Referer
 * fallback: come from a page you are allowed to use, and you are let through.
 * That made authorization depend on a header the browser is free to omit -- a
 * reopened tab, a restored session, a privacy extension or a PDF viewer that
 * refetches the document all arrive with no Referer and earn a 403, and only
 * for non-superadmins, which is why it reads as "works for the admin, not for
 * me".
 *
 * A grant is minted server-side at the moment the file is produced, by the code
 * that already decided the user was entitled to it, and travels in place of the
 * old `f` value. It carries the file, the user it was minted for and an expiry,
 * signed with a key that never leaves the server. Nothing about it depends on
 * the browser volunteering a header.
 *
 * Token layout (all URL-safe, so it survives being pasted straight into a query
 * string without escaping):
 *
 *     dl1.<base64url(payload json)>.<base64url(hmac-sha256)>
 *
 * The payload names the file relative to its root rather than by absolute path,
 * so the server's directory layout is no longer disclosed in the URL the way the
 * old base64-of-/var/www/... value disclosed it.
 */
final class DownloadTokenUtility
{
    public const string TOKEN_PREFIX = 'dl1.';

    /** Grants are meant to be spent immediately; this is slack, not a lifetime. */
    public const int DEFAULT_TTL_SECONDS = 900;

    private const string KEY_FILE = VAR_PATH . '/download-signing.key';

    /** Purpose separation, so this key can never be confused with another use of the same bytes. */
    private const string KEY_CONTEXT = 'intelis:download-token:v1';

    /**
     * Roots a grant may name, keyed by the short code stored in the token.
     * Keep these to named directories: never the whole var/ tree, which also
     * holds logs, cache and backups that must not be downloadable.
     */
    private static function roots(): array
    {
        return [
            'p' => TEMP_PATH,      // public/temporary (legacy public files)
            'v' => VAR_TEMP_PATH,  // var/temporary (manifests + future non-public files)
        ];
    }

    /**
     * Mints a grant for a file the current user is entitled to.
     *
     * Accepts either an absolute path or a name relative to one of the roots,
     * because producers across the app echo both shapes. Returns an empty string
     * if the file cannot be placed under a trusted root -- callers echo the
     * result straight into a response, and an empty body is what the existing
     * JavaScript already treats as "generation failed".
     */
    public static function sign(string $filePath, ?int $ttlSeconds = null): string
    {
        $located = self::locate($filePath);
        if ($located === null) {
            LoggerUtility::logWarning('Refused to sign a download outside the trusted roots', [
                'requested_file' => $filePath,
            ]);
            return '';
        }

        [$rootKey, $relativePath] = $located;

        $payload = [
            'r' => $rootKey,
            'f' => $relativePath,
            'u' => (string) ($_SESSION['userId'] ?? ''),
            'x' => time() + ($ttlSeconds ?? self::DEFAULT_TTL_SECONDS),
        ];

        $encodedPayload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        return self::TOKEN_PREFIX . $encodedPayload . '.' . self::base64UrlEncode(self::signature($encodedPayload));
    }

    /**
     * Cheap shape test, so download.php can tell a grant from a legacy `f` value
     * before trying to decode either.
     */
    public static function looksLikeToken(string $value): bool
    {
        return str_starts_with($value, self::TOKEN_PREFIX);
    }

    /**
     * Verifies a grant and returns the absolute path it names, or null if the
     * signature does not check out, the grant has expired, it was minted for a
     * different user, or the file is no longer there.
     *
     * $reason is filled with a short machine-readable cause for logging. It is
     * deliberately not shown to the user: the distinction between "expired" and
     * "forged" is useful in a log and useless in a browser.
     */
    public static function resolve(string $token, ?string &$reason = null): ?string
    {
        $reason = null;

        if (!self::looksLikeToken($token)) {
            $reason = 'not_a_token';
            return null;
        }

        $parts = explode('.', substr($token, strlen(self::TOKEN_PREFIX)));
        if (count($parts) !== 2) {
            $reason = 'malformed';
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;

        $expected = self::signature($encodedPayload);
        $provided = self::base64UrlDecode($encodedSignature);
        if ($provided === '' || !hash_equals($expected, $provided)) {
            $reason = 'bad_signature';
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            $reason = 'bad_payload';
            return null;
        }

        if ((int) ($payload['x'] ?? 0) < time()) {
            $reason = 'expired';
            return null;
        }

        // A grant is spendable only by the session it was minted for, so a URL
        // that leaks (shoulder-surfed, pasted into a ticket, sitting in history)
        // is inert in anyone else's hands.
        if ((string) ($payload['u'] ?? '') !== (string) ($_SESSION['userId'] ?? '')) {
            $reason = 'wrong_user';
            return null;
        }

        $root = self::roots()[$payload['r'] ?? ''] ?? null;
        if ($root === null) {
            $reason = 'unknown_root';
            return null;
        }

        $absolute = realpath($root . DIRECTORY_SEPARATOR . ($payload['f'] ?? ''));
        $realRoot = realpath($root);
        if (
            $absolute === false
            || $realRoot === false
            || !str_starts_with($absolute, $realRoot . DIRECTORY_SEPARATOR)
        ) {
            $reason = 'missing_file';
            return null;
        }

        return $absolute;
    }

    /**
     * Places a file under one of the trusted roots and returns [rootKey, relativePath].
     *
     * The path is resolved with realpath() before it is compared, so a traversal
     * dressed up as a relative name cannot escape the root it claims to be in.
     */
    private static function locate(string $filePath): ?array
    {
        if ($filePath === '') {
            return null;
        }

        $candidates = [$filePath];
        foreach (self::roots() as $root) {
            $candidates[] = $root . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR . '/');
        }

        foreach ($candidates as $candidate) {
            $absolute = realpath($candidate);
            if ($absolute === false) {
                continue;
            }

            foreach (self::roots() as $rootKey => $root) {
                $realRoot = realpath($root);
                if ($realRoot === false || !str_starts_with($absolute, $realRoot . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $relative = substr($absolute, strlen($realRoot) + 1);
                return [$rootKey, str_replace(DIRECTORY_SEPARATOR, '/', $relative)];
            }
        }

        return null;
    }

    private static function signature(string $encodedPayload): string
    {
        return hash_hmac('sha256', $encodedPayload, self::key(), true);
    }

    /**
     * The signing key, created on first use.
     *
     * This is deliberately its own key rather than a share of CryptoUtility's
     * secretbox key: signing grants and encrypting stored data have different
     * blast radii, and nothing here should ever be a reason to load the other.
     */
    private static function key(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }

        if (is_readable(self::KEY_FILE)) {
            $stored = base64_decode((string) file_get_contents(self::KEY_FILE), true);
            if ($stored !== false && strlen($stored) === 32) {
                return $key = hash_hmac('sha256', self::KEY_CONTEXT, $stored, true);
            }
        }

        return $key = hash_hmac('sha256', self::KEY_CONTEXT, self::createKey(), true);
    }

    /**
     * Writes a fresh key, tolerating the race where two workers reach this at
     * once: the file is written exclusively and then read back, so whichever
     * write landed is the one every worker ends up using. Without that re-read
     * the two workers would sign with different keys and each would reject the
     * other's grants.
     *
     * The umask is tightened around the open rather than the mode being fixed
     * after the fact: fopen() creates at 0666 & ~umask, so a chmod that only
     * runs once the bytes are down leaves a window where the signing key is
     * world-readable, and anyone who read it in that window could forge a grant
     * for any file and any user. A key that cannot be locked down is refused
     * outright for the same reason.
     */
    private static function createKey(): string
    {
        $previousUmask = umask(0077);

        try {
            $key = random_bytes(32);

            $handle = @fopen(self::KEY_FILE, 'c+');
            if ($handle === false) {
                throw new SystemException('Unable to open the download signing key file');
            }

            try {
                if (!flock($handle, LOCK_EX)) {
                    throw new SystemException('Unable to lock the download signing key file');
                }

                $existing = base64_decode((string) stream_get_contents($handle), true);
                if ($existing !== false && strlen($existing) === 32) {
                    return $existing;
                }

                // Tighten an already-existing file too: an earlier build created
                // this before the umask was in place.
                if (!chmod(self::KEY_FILE, 0600)) {
                    throw new SystemException('Unable to restrict the download signing key file');
                }

                clearstatcache(true, self::KEY_FILE);
                if ((fileperms(self::KEY_FILE) & 0077) !== 0) {
                    throw new SystemException('Download signing key file is readable by other users');
                }

                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, base64_encode($key));
                fflush($handle);

                return $key;
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (Throwable $e) {
            throw new SystemException('Download signing key is unavailable: ' . $e->getMessage(), 500, $e);
        } finally {
            umask($previousUmask);
        }
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $encoded): string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
