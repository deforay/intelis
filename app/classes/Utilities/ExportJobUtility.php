<?php

namespace App\Utilities;

use Throwable;
use App\Services\SystemService;
use App\Registries\ContainerRegistry;

/**
 * Runs a long Excel export as a detached background process.
 *
 * A synchronous export dies the moment the user navigates away, and holds the
 * session lock for its whole run so every other page the user opens meanwhile
 * just hangs. Instead, the export endpoint records what to export in a job file
 * and hands it to bin/export-worker.php, which runs the very same export script
 * outside the web request and reports its progress back into that file. The
 * browser polls /common/export-job-status.php from whichever page the user is
 * on and downloads the file once the job is done.
 *
 * Job files live under var/ (never web-accessible) because they carry the SQL
 * of the export and a slice of the requester's session.
 */
final class ExportJobUtility
{
    /** Session values the export scripts read, carried over to the worker. */
    private const array SESSION_KEYS = [
        'userId',
        'userName',
        'APP_LOCALE',
        'userLocale',
        'phpDateFormat',
        'facilityMap',
        'accessType',
    ];

    /** A job the worker has not picked up within this long never will be. */
    private const int SPAWN_TIMEOUT_SECONDS = 60;

    /** A running job that has not reported in this long has died. */
    private const int STALE_SECONDS = 600;

    /** Job files older than this are swept away when a new job starts. */
    private const int RETENTION_SECONDS = 86400;

    /** Minimum gap between progress writes, so a big export is not I/O bound on its own status. */
    private const float PROGRESS_INTERVAL_SECONDS = 1.0;

    private static ?self $current = null;

    private float $lastProgressWrite = 0.0;

    private int $ticks = 0;

    private function __construct(private readonly string $id, private array $job) {}

    /** True while running inside the background worker rather than a web request. */
    public static function inBackground(): bool
    {
        return self::$current !== null;
    }

    /**
     * Called by an export script, first thing: when the page asked for a
     * background export (async=yes), queues this same script as a job, answers
     * with its id and returns true, and the script must stop there. Otherwise
     * returns false and the script carries on exactly as it always did.
     *
     * $queryKey names the session entry holding the listing's SQL; the listing
     * also stores its row count under the same name plus "Count".
     */
    public static function queueRequested(string $script, string $queryKey): bool
    {
        if (($_POST['async'] ?? '') !== 'yes' || self::inBackground()) {
            return false;
        }
        // Nothing listed means nothing to export: say so instead of handing over
        // an empty spreadsheet.
        $countKey = $queryKey . 'Count';
        if (trim((string) ($_SESSION[$queryKey] ?? '')) === '' || (isset($_SESSION[$countKey]) && (int) $_SESSION[$countKey] === 0)) {
            echo json_encode(['error' => self::noDataMessage(), 'empty' => true]);
            return true;
        }

        echo json_encode(['jobId' => self::start($script, $queryKey, $_POST, $_GET)]);
        return true;
    }

    private static function noDataMessage(): string
    {
        return _translate('No data available to export. Please change the filters and search again.');
    }

    /** Called by an export script for every row it writes. */
    public static function tick(): void
    {
        if (self::$current === null) {
            return;
        }
        self::$current->ticks++;
        self::$current->progress(self::$current->ticks);
    }

    /**
     * Records a new job for the current user and launches its worker.
     * Returns the job id, or null if there is nothing to export or the worker
     * could not be started.
     */
    private static function start(string $script, string $queryKey, array $post, array $get): ?string
    {
        $script = realpath($script);
        if ($script === false || !str_starts_with($script, realpath(APPLICATION_PATH) . DIRECTORY_SEPARATOR) || empty($_SESSION['userId'])) {
            return null;
        }

        $query = trim((string) ($_SESSION[$queryKey] ?? ''));
        if ($query === '') {
            return null;
        }

        self::sweep();

        $countKey = $queryKey . 'Count';
        $session = [$queryKey => $query];
        foreach ([...self::SESSION_KEYS, $countKey] as $key) {
            if (isset($_SESSION[$key])) {
                $session[$key] = $_SESSION[$key];
            }
        }
        unset($post['async'], $post['csrf_token']);

        $id = bin2hex(random_bytes(16));
        $job = new self($id, [
            'id' => $id,
            'script' => $script,
            'userId' => (string) $_SESSION['userId'],
            'status' => 'queued',
            'processed' => 0,
            // The listing counted this same query when it last drew; the export
            // query itself cannot be wrapped in a COUNT (it repeats column names).
            'total' => isset($_SESSION[$countKey]) ? (int) $_SESSION[$countKey] : null,
            'file' => null,
            'error' => null,
            'claimed' => false,
            'createdAt' => time(),
            'updatedAt' => time(),
            'post' => $post,
            'get' => $get,
            'session' => $session,
        ]);

        if (!$job->save() || !self::spawn($id)) {
            @unlink(self::path($id));
            return null;
        }

        return $id;
    }

    /**
     * What the browser may know about a job: never the query, session or path.
     * With $claim set, a finished job hands out its download exactly once, so
     * several open tabs do not each download the same file. $again asks for a
     * fresh grant for a file already handed out, when the user wants it again.
     */
    public static function status(string $id, string $userId, bool $claim = false, bool $again = false): ?array
    {
        $job = self::load($id);
        if ($job === null || $job->job['userId'] !== $userId) {
            return null;
        }

        $job->detectDeath();

        $data = $job->job;
        $response = [
            'id' => $id,
            'status' => $data['status'],
            'processed' => (int) $data['processed'],
            'total' => $data['total'] === null ? null : (int) $data['total'],
            'error' => $data['error'],
        ];

        if ($data['status'] === 'done') {
            if (!is_file((string) $data['file'])) {
                $response['status'] = 'failed';
                $response['error'] = _translate('The exported file is no longer available. Please export again.');
            } elseif ($claim && !$data['claimed']) {
                $job->update(['claimed' => true]);
                $response['token'] = _downloadToken($data['file']);
            } elseif ($again) {
                $response['token'] = _downloadToken($data['file']);
            } elseif ($data['claimed']) {
                $response['status'] = 'claimed';
            }
        }

        return $response;
    }

    /**
     * Worker entry point: restores the requester's context and runs the export
     * script with this job as its progress sink. Returns a process exit code.
     */
    public static function run(string $id): int
    {
        $job = self::load($id);
        if ($job === null || $job->job['status'] !== 'queued') {
            return 1;
        }
        $script = realpath((string) ($job->job['script'] ?? ''));
        if ($script === false || !str_starts_with($script, realpath(APPLICATION_PATH) . DIRECTORY_SEPARATOR)) {
            $job->fail('Unknown export script');
            return 1;
        }

        $_SESSION = $job->job['session'];
        $_POST = $job->job['post'];
        $_GET = $job->job['get'] ?? [];
        if (!empty($_SESSION['APP_LOCALE'])) {
            ContainerRegistry::get(SystemService::class)->setLocale($_SESSION['APP_LOCALE']);
        }

        try {
            $job->update(['status' => 'running', 'pid' => getmypid()]);

            self::$current = $job;
            ob_start();
            (static function (string $script): void {
                require $script;
            })($script);
            $output = trim((string) ob_get_clean());

            // Export scripts end by echoing a download grant for the file they
            // wrote; minted here for the restored user, it names that file.
            $file = DownloadTokenUtility::looksLikeToken($output) ? DownloadTokenUtility::resolve($output) : null;
            if ($job->ticks === 0) {
                // The data changed after the listing was counted and nothing matches now.
                if ($file !== null) {
                    @unlink($file);
                }
                $job->update(['status' => 'empty', 'error' => self::noDataMessage()]);
                return 0;
            } elseif ($file !== null) {
                $job->complete($file, $job->ticks);
            }
        } catch (Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            LoggerUtility::logError('Background export failed: ' . $e->getMessage(), [
                'job' => $id,
                'script' => $job->job['script'],
                'exception' => $e,
            ]);
            $job->fail(_translate('Unable to generate the excel file'));
            return 1;
        } finally {
            self::$current = null;
        }

        if ($job->job['status'] !== 'done') {
            $job->fail(_translate('Unable to generate the excel file'));
            return 1;
        }

        return 0;
    }

    private function progress(int $processed): void
    {
        $now = microtime(true);
        if ($now - $this->lastProgressWrite < self::PROGRESS_INTERVAL_SECONDS) {
            return;
        }
        $this->lastProgressWrite = $now;
        $this->update(['processed' => $processed]);
    }

    private function complete(string $file, int $processed): void
    {
        $this->update(['status' => 'done', 'file' => $file, 'processed' => $processed, 'total' => $processed]);
    }

    private function fail(string $error): void
    {
        $this->update(['status' => 'failed', 'error' => $error]);
    }

    /** Marks a job failed when its worker never started or stopped reporting. */
    private function detectDeath(): void
    {
        $age = time() - (int) $this->job['updatedAt'];
        if ($this->job['status'] === 'queued' && $age > self::SPAWN_TIMEOUT_SECONDS) {
            $this->fail(_translate('The export could not be started. Please try again.'));
        } elseif ($this->job['status'] === 'running' && $age > self::STALE_SECONDS && !$this->workerAlive()) {
            $this->fail(_translate('The export stopped unexpectedly. Please try again.'));
        }
    }

    /**
     * A big query can run for many minutes before its first row arrives, so
     * silence alone does not mean the worker died. Where the process table can
     * be read, ask it; elsewhere fall back to the silence.
     */
    private function workerAlive(): bool
    {
        $pid = (int) ($this->job['pid'] ?? 0);
        if ($pid <= 0 || !is_dir('/proc')) {
            return false;
        }
        return is_dir('/proc/' . $pid);
    }

    private function update(array $changes): void
    {
        // Re-read first: the browser may have claimed the job while the worker ran.
        $fresh = self::load($this->id);
        if ($fresh !== null) {
            $this->job = $fresh->job;
        }
        $this->job = array_merge($this->job, $changes, ['updatedAt' => time()]);
        $this->save();
    }

    private function save(): bool
    {
        $dir = self::directory();
        if (!is_dir($dir) && !MiscUtility::makeDirectory($dir, 0770)) {
            return false;
        }
        $path = self::path($this->id);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, json_encode($this->job, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0660);
        return rename($tmp, $path);
    }

    private static function load(string $id): ?self
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }
        $content = @file_get_contents(self::path($id));
        $job = $content === false ? null : json_decode($content, true);
        return is_array($job) ? new self($id, $job) : null;
    }

    private static function spawn(string $id): bool
    {
        $php = SYSTEM_CONFIG['system']['php_path'] ?? null;
        if (empty($php)) {
            $php = (PHP_SAPI === 'cli' && PHP_BINARY !== '') ? PHP_BINARY : PHP_BINDIR . '/php';
        }
        $worker = BIN_PATH . '/export-worker.php';
        // The shell refuses to start the worker at all if its output cannot be
        // written, so a log the web user cannot append to must not block it.
        // Errors still reach the app log through LoggerUtility.
        $log = LOG_PATH . '/export-worker.log';
        if (is_file($log) ? !is_writable($log) : !is_writable(LOG_PATH)) {
            $log = '/dev/null';
        }

        // nohup + redirect + & detaches the worker, so this request returns at
        // once and the export outlives the page that started it.
        $cmd = sprintf(
            'APPLICATION_ENV=%s nohup %s %s %s >> %s 2>&1 &',
            escapeshellarg(APPLICATION_ENV),
            escapeshellarg($php),
            escapeshellarg($worker),
            escapeshellarg($id),
            escapeshellarg($log)
        );

        try {
            shell_exec($cmd);
        } catch (Throwable $e) {
            LoggerUtility::logError('Could not launch export worker: ' . $e->getMessage(), ['job' => $id]);
            return false;
        }
        return true;
    }

    /** Removes old job files and the exports they produced. */
    private static function sweep(): void
    {
        $cutoff = time() - self::RETENTION_SECONDS;
        foreach (glob(self::directory() . '/*.json') ?: [] as $path) {
            if (@filemtime($path) >= $cutoff) {
                continue;
            }
            $job = json_decode((string) @file_get_contents($path), true);
            if (is_array($job) && !empty($job['file']) && is_file($job['file'])) {
                @unlink($job['file']);
            }
            @unlink($path);
        }
    }

    private static function directory(): string
    {
        return VAR_PATH . '/export-jobs';
    }

    private static function path(string $id): string
    {
        return self::directory() . '/' . $id . '.json';
    }
}
