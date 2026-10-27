<?php

namespace App\Services;

use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;

/**
 * Where a lab's request pulls have got to, per module: the last pull, and the page
 * a paged window has reached.
 *
 * A big window comes from the STS a page at a time (STS\RequestsService::getRequests),
 * and the lab saves each page before asking for the next. The marker is kept, so a
 * pull that is interrupted -- a dropped link, a closed terminal, a run out of time --
 * and run again with the same window carries on from the page it reached. A finished
 * window clears its marker. A dry run moves through the pages without keeping one.
 *
 * Everything is kept in request_pull_state. The markers used to be in a file under
 * var/cache, which a cache purge empties, and the last pull was read from
 * track_api_requests, a log of every call: 102 seconds per module on one lab. Until
 * the migration has added the table, the file is used as before. Once the table is
 * there, the file's markers move into it.
 */
final class RequestPageCursor
{
    public const PAGE_SIZE = 500;

    private const TABLE = 'request_pull_state';

    private const SCHEDULED = 'scheduled|';

    private const LAST_PULL = 'last-pull|';

    /** @var array<string, string> */
    private array $markers = [];

    private bool $inTable = false;

    /**
     * Without a database or a file (a dry run) nothing is kept. The defaults also
     * let the DI container, which registers every class here, build it: a required
     * value it cannot guess broke the container's compilation and every page with it.
     */
    public function __construct(
        private readonly ?DatabaseService $db = null,
        private readonly ?string $file = null
    ) {
        try {
            $this->inTable = $db !== null && $db->tableExists(self::TABLE);
            if ($this->inTable) {
                foreach ($db->rawQuery('SELECT state_key, state_value FROM `' . self::TABLE . '`') ?: [] as $row) {
                    $this->markers[(string) $row['state_key']] = (string) $row['state_value'];
                }
            }
        } catch (Throwable $e) {
            LoggerUtility::logError('Could not read request_pull_state: ' . $e->getMessage());
            $this->inTable = false;
        }

        $saved = $file !== null && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($saved)) {
            return;
        }
        $moved = true;
        foreach (array_filter($saved, 'is_string') as $key => $marker) {
            if ($this->inTable && !array_key_exists($key, $this->markers)) {
                $moved = $this->put((string) $key, $marker) && $moved;
            }
            $this->markers[(string) $key] ??= $marker;
        }
        // Only once every marker is in the table: a held window start lost here
        // skips the rows its window had not reached. The next run tries again.
        if ($this->inTable && $moved) {
            @unlink($file);
        }
    }

    /** One window of one module: the same pull run again finds its marker. */
    public static function key(string $module, ?string $since, ?string $manifestCode): string
    {
        return $module . '|' . ($since ?? '') . '|' . ($manifestCode ?? '');
    }

    /**
     * The scheduled pull's window of one module. While it is paged, its start is
     * held: each saved page moves the lab's last pull time, and a window that
     * started from that again would skip the rows between its last page and the
     * new start.
     */
    public static function scheduledKey(string $module, string $since): string
    {
        return self::SCHEDULED . $module . '|' . $since;
    }

    /** The start of a scheduled window of $module not paged to its end, or null. */
    public function heldStart(string $module): ?string
    {
        $prefix = self::SCHEDULED . $module . '|';
        $starts = [];
        foreach (array_keys($this->markers) as $key) {
            if (str_starts_with($key, $prefix)) {
                $starts[] = substr($key, strlen($prefix));
            }
        }
        return $starts === [] ? null : min($starts);
    }

    /** When the scheduled pull of $module last got an answer, or null if not known here. */
    public function lastPull(string $module): ?string
    {
        $pulled = $this->markers[self::LAST_PULL . $module] ?? '';
        return $pulled === '' ? null : $pulled;
    }

    /** Records that the pull of $module asked at $dateTime got its answer. */
    public function recordPull(string $module, string $dateTime): void
    {
        $this->set(self::LAST_PULL . $module, $dateTime);
    }

    /**
     * Holds a window from its first pull, before any page has come: the first
     * pull alone moves the lab's last pull time. A window already begun keeps
     * its marker. The marker of a window with no page yet is ''.
     */
    public function begin(string $key): void
    {
        if (!array_key_exists($key, $this->markers)) {
            $this->set($key, '');
        }
    }

    public function get(string $key): ?string
    {
        return $this->markers[$key] ?? null;
    }

    /**
     * Records the page just saved. Returns false when the STS answered with the
     * marker the lab already had: it would ask for the same page forever.
     */
    public function advance(string $key, string $after): bool
    {
        if (($this->markers[$key] ?? null) === $after) {
            return false;
        }
        $this->set($key, $after);
        return true;
    }

    public function finish(string $key): void
    {
        if (!array_key_exists($key, $this->markers)) {
            return;
        }
        unset($this->markers[$key]);
        if ($this->inTable) {
            $this->write('DELETE FROM `' . self::TABLE . '` WHERE state_key = ?', [$key]);
        } else {
            $this->saveFile();
        }
    }

    private function set(string $key, string $value): void
    {
        $this->markers[$key] = $value;
        if ($this->inTable) {
            $this->put($key, $value);
        } else {
            $this->saveFile();
        }
    }

    private function put(string $key, string $value): bool
    {
        return $this->write(
            'INSERT INTO `' . self::TABLE . '` (state_key, state_value, updated_datetime) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE state_value = VALUES(state_value), updated_datetime = VALUES(updated_datetime)',
            [$key, $value, DateUtility::getCurrentDateTime()]
        );
    }

    /**
     * A marker that cannot be kept is logged, not thrown: the pull it belongs to
     * has saved its requests already, and stopping the run keeps no marker either.
     */
    private function write(string $sql, array $params): bool
    {
        try {
            if ($this->db !== null && $this->db->rawQuery($sql, $params) !== false) {
                return true;
            }
            LoggerUtility::logError('Could not keep a request pull marker: ' . $this->db?->getLastError());
        } catch (Throwable $e) {
            LoggerUtility::logError('Could not keep a request pull marker: ' . $e->getMessage());
        }
        return false;
    }

    private function saveFile(): void
    {
        if ($this->file === null) {
            return;
        }
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $tmp = $this->file . '.tmp';
        if (@file_put_contents($tmp, json_encode($this->markers)) !== false) {
            @rename($tmp, $this->file);
        }
    }
}
