<?php

namespace App\Utilities;

use PDO;
use PDOException;
use Throwable;

/**
 * A small SQLite index of error-level log entries, kept next to the daily log
 * files in var/logs/errors.sqlite.
 *
 * The log files stay the record: every entry still goes there, with its full
 * context and trace. This index only answers the questions the files answer
 * slowly: which day an error ID was logged on, and how often the same error
 * has happened. Losing it loses nothing but those answers, so every write is
 * best-effort and any failure is dropped silently.
 *
 * SQLite rather than MySQL because errors are often MySQL's own: a stopped
 * server, a deadlock, a full disk. The index has to keep working then.
 */
final class ErrorIndexUtility
{
    public const string FILENAME = 'errors.sqlite';
    public const int RETENTION_DAYS = 90;

    /** How long a writer waits for another writer before giving up on the entry. */
    private const int BUSY_TIMEOUT_MS = 250;
    private const int MAX_MESSAGE_LENGTH = 1000;

    /** Error IDs from MiscUtility::generateErrorId(): PREFIX-XXXX-XXXX in Crockford base32. */
    public const string ERROR_ID_PATTERN = '/^[A-Z]{2,8}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/';

    private static ?PDO $pdo = null;
    private static ?string $path = null;

    /** Point the index at another file (tests), or back at the default with null. */
    public static function usePath(?string $path): void
    {
        self::$pdo = null;
        self::$path = $path;
    }

    public static function path(): string
    {
        return self::$path ?? (defined('LOG_PATH') ? LOG_PATH : VAR_PATH . '/logs') . '/' . self::FILENAME;
    }

    public static function isErrorId(string $value): bool
    {
        return preg_match(self::ERROR_ID_PATTERN, strtoupper(trim($value))) === 1;
    }

    /**
     * Same class, file and line is the same bug, whatever the message says, so
     * the message only counts when there is no location to go on.
     */
    public static function fingerprint(?string $exceptionClass, ?string $file, ?int $line, string $message): string
    {
        $file = self::relativePath($file);
        $key = ($file !== null && $file !== '')
            ? ($exceptionClass ?? '') . '|' . $file . '|' . ($line ?? 0)
            : ($exceptionClass ?? '') . '|' . $message;
        return substr(sha1($key), 0, 16);
    }

    /** Infrastructure a failure passes through but never starts in. */
    private const array PASS_THROUGH = [
        '/vendor/',
        '/app/classes/Services/DatabaseService.php',
        '/app/classes/Services/Database/',
    ];

    /**
     * The exception that actually went wrong, and the line of our code where it
     * did. Wrappers are unwound to the original, and when that was thrown inside
     * a library (a MySQL error surfaces in the database driver) the location is
     * the first line of application code that called into it.
     *
     * @return array{exception: Throwable, file: string, line: int}
     */
    public static function originOf(Throwable $e): array
    {
        while ($e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }

        $frames = [['file' => $e->getFile(), 'line' => $e->getLine()], ...$e->getTrace()];
        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file) || $file === '') {
                continue;
            }
            foreach (self::PASS_THROUGH as $marker) {
                if (str_contains($file, $marker)) {
                    continue 2;
                }
            }
            return ['exception' => $e, 'file' => $file, 'line' => (int) ($frame['line'] ?? 0)];
        }

        return ['exception' => $e, 'file' => $e->getFile(), 'line' => $e->getLine()];
    }

    /**
     * @param array{
     *     logged_at: string, level: string, message: string, error_id?: ?string,
     *     exception_class?: ?string, file?: ?string, line?: ?int,
     *     url?: ?string, user_id?: ?string, ip?: ?string
     * } $entry
     */
    public static function record(array $entry): void
    {
        try {
            $pdo = self::connection();
            if ($pdo === null) {
                return;
            }

            $message = mb_substr($entry['message'], 0, self::MAX_MESSAGE_LENGTH);
            $file = self::relativePath($entry['file'] ?? null);
            $line = isset($entry['line']) ? (int) $entry['line'] : null;
            $class = $entry['exception_class'] ?? null;

            $stmt = $pdo->prepare(
                'INSERT INTO errors (error_id, logged_at, log_date, level, fingerprint, exception_class, message, file, line, url, user_id, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $entry['error_id'] ?? null,
                $entry['logged_at'],
                substr($entry['logged_at'], 0, 10),
                $entry['level'],
                self::fingerprint($class, $file, $line, $message),
                $class,
                $message,
                $file,
                $line,
                $entry['url'] ?? null,
                $entry['user_id'] ?? null,
                $entry['ip'] ?? null,
            ]);
        } catch (Throwable) {
            // Best-effort by design: the log file already has this entry.
        }
    }

    /** @return array<string, mixed>|null */
    public static function find(string $errorId): ?array
    {
        try {
            $pdo = self::connection(create: false);
            if ($pdo === null) {
                return null;
            }
            $stmt = $pdo->prepare('SELECT * FROM errors WHERE error_id = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([strtoupper(trim($errorId))]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The same error grouped together, most frequent first.
     *
     * @return list<array{fingerprint: string, occurrences: int, first_seen: string, last_seen: string,
     *     message: string, exception_class: ?string, file: ?string, line: ?int, url: ?string, latest_error_id: ?string}>
     */
    public static function recurring(int $days = 7, int $limit = 20): array
    {
        try {
            $pdo = self::connection(create: false);
            if ($pdo === null) {
                return [];
            }
            $since = date('Y-m-d H:i:s', time() - $days * 86400);
            $stmt = $pdo->prepare(
                'SELECT e.fingerprint, g.occurrences, g.first_seen, g.last_seen,
                        e.message, e.exception_class, e.file, e.line, e.url,
                        (SELECT error_id FROM errors x
                          WHERE x.fingerprint = e.fingerprint AND x.error_id IS NOT NULL AND x.logged_at >= :since
                          ORDER BY x.id DESC LIMIT 1) AS latest_error_id
                   FROM (SELECT fingerprint, COUNT(*) AS occurrences, MIN(logged_at) AS first_seen,
                                MAX(logged_at) AS last_seen, MAX(id) AS last_id
                           FROM errors WHERE logged_at >= :since GROUP BY fingerprint) g
                   JOIN errors e ON e.id = g.last_id
                  ORDER BY g.occurrences DESC, g.last_seen DESC
                  LIMIT :limit'
            );
            $stmt->bindValue(':since', $since);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['occurrences'] = (int) $row['occurrences'];
                $row['line'] = $row['line'] === null ? null : (int) $row['line'];
            }
            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Housekeeping: check the file, then drop old rows. A damaged file is
     * deleted and the next error starts a new one; the log files still hold
     * every entry, so nothing is lost but the history of the index itself.
     *
     * @return array{deleted: int, compacted: bool, rebuilt: bool}
     */
    public static function prune(int $retentionDays = self::RETENTION_DAYS, bool $compact = false): array
    {
        $result = ['deleted' => 0, 'compacted' => false, 'rebuilt' => false];
        if (!is_file(self::path())) {
            return $result;
        }

        if (self::isDamaged()) {
            self::$pdo = null;
            foreach (['', '-wal', '-shm'] as $suffix) {
                MiscUtility::deleteFile(self::path() . $suffix);
            }
            $result['rebuilt'] = true;
            return $result;
        }

        $pdo = self::connection(create: false);
        if ($pdo === null) {
            return $result;
        }

        $cutoff = date('Y-m-d H:i:s', time() - $retentionDays * 86400);
        $stmt = $pdo->prepare('DELETE FROM errors WHERE logged_at < ?');
        $stmt->execute([$cutoff]);
        $result['deleted'] = $stmt->rowCount();

        if ($compact) {
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $pdo->exec('VACUUM');
            $result['compacted'] = true;
        }

        return $result;
    }

    /**
     * Only SQLite's own verdict counts: a failed check or a "not a database" /
     * "malformed" error. Anything else, a busy writer or a missing driver,
     * says nothing about the file, and deleting on it would throw away a
     * healthy index.
     */
    private static function isDamaged(): bool
    {
        if (!extension_loaded('pdo_sqlite')) {
            return false;
        }
        try {
            $pdo = new PDO('sqlite:' . self::path(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            return $pdo->query('PRAGMA quick_check')->fetchColumn() !== 'ok';
        } catch (PDOException $e) {
            return str_contains($e->getMessage(), 'not a database') || str_contains($e->getMessage(), 'malformed');
        } catch (Throwable) {
            return false;
        }
    }

    private static function connection(bool $create = true): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $path = self::path();
        if (!$create && !is_file($path)) {
            return null;
        }
        if (!extension_loaded('pdo_sqlite') || !is_dir(dirname($path)) || !is_writable(dirname($path))) {
            return null;
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 1,
            ]);
            $pdo->exec('PRAGMA busy_timeout = ' . self::BUSY_TIMEOUT_MS);
            // WAL lets the viewer read while requests write; it is stored in the
            // file, so this is a no-op on every open after the first.
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS errors (
                    id INTEGER PRIMARY KEY,
                    error_id TEXT,
                    logged_at TEXT NOT NULL,
                    log_date TEXT NOT NULL,
                    level TEXT NOT NULL,
                    fingerprint TEXT NOT NULL,
                    exception_class TEXT,
                    message TEXT NOT NULL,
                    file TEXT,
                    line INTEGER,
                    url TEXT,
                    user_id TEXT,
                    ip TEXT
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_errors_error_id ON errors (error_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_errors_logged_at ON errors (logged_at)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_errors_fingerprint ON errors (fingerprint, logged_at)');
            self::$pdo = $pdo;
        } catch (Throwable) {
            return null;
        }

        return self::$pdo;
    }

    private static function relativePath(?string $file): ?string
    {
        if ($file === null || $file === '') {
            return $file;
        }
        if (defined('ROOT_PATH') && str_starts_with($file, ROOT_PATH . '/')) {
            return substr($file, strlen(ROOT_PATH) + 1);
        }
        return $file;
    }
}
