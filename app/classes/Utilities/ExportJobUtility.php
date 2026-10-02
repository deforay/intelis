<?php

namespace App\Utilities;

use Throwable;
use App\Exceptions\ExportStoppedException;
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
 *
 * A background worker has no web server limiting how many run at once or how
 * long each may take, so this class does both: only MAX_WORKERS exports run at
 * a time (the rest wait their turn), a user asking again for an export already
 * under way gets that same job back, and a job running past MAX_RUNTIME_SECONDS
 * is stopped.
 */
final class ExportJobUtility
{
    /** Session values the export scripts and the services they call read, carried over to the worker. */
    private const array SESSION_KEYS = [
        'userId',
        'userName',
        'roleId',
        'privileges',
        'APP_LOCALE',
        'userLocale',
        'phpDateFormat',
        'facilityMap',
        'accessType',
        'labId',
        'instance',
    ];

    /**
     * Exports of up to this many rows run in the request itself: a worker,
     * its job file and the status checks would cost more than the export.
     * Deliberately, such an export behaves as exports always did: leaving the
     * page before it answers (usually about a second) loses the file, and the
     * user exports again. Making it survive that would need the very job
     * tracking this path exists to avoid.
     */
    private const int INLINE_MAX_ROWS = 5000;

    /**
     * Unfinished jobs allowed at once, running or waiting. Every waiting job is
     * a PHP process holding a database connection, so the queue is bounded too.
     */
    private const int MAX_ACTIVE_JOBS = 6;

    /** Exports allowed to run at the same time; each one is a full query and spreadsheet build. */
    private const int MAX_WORKERS = 2;

    /** A job the worker has not picked up (or, while waiting for a slot, heard from) within this long never will be. */
    private const int SPAWN_TIMEOUT_SECONDS = 60;

    /** A running job that has not reported in this long, and whose process is gone, has died. */
    private const int STALE_SECONDS = 600;

    /** No export may run longer than this, alive or not. */
    private const int MAX_RUNTIME_SECONDS = 7200;

    /** Job files older than this are swept away when a new job starts. */
    private const int RETENTION_SECONDS = 86400;

    /** Minimum gap between progress writes, so a big export is not I/O bound on its own status. */
    private const float PROGRESS_INTERVAL_SECONDS = 1.0;

    /** How often a worker waiting for a free slot looks again, and tells the browser it is still alive. */
    private const int SLOT_POLL_SECONDS = 2;

    /** A job still waiting for a slot after this long gives up, so the worker's total lifetime stays bounded. */
    private const int MAX_QUEUE_SECONDS = 1800;

    private const array FINISHED = ['done', 'failed', 'empty'];

    private static ?self $current = null;

    /** Rows written by an export running in the request (see runInline()), or null when none is. */
    private static ?int $inlineTicks = null;

    private float $lastProgressWrite = 0.0;

    private int $ticks = 0;

    private function __construct(private readonly string $id, private array $job)
    {
    }

    /**
     * True while an export runs under this class's control: in the background
     * worker, or in the request for a small one. Either way the limits are set
     * here, so the export script must not apply its own.
     */
    public static function inBackground(): bool
    {
        return self::$current !== null || self::$inlineTicks !== null;
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
        $noQuery = trim((string) ($_SESSION[$queryKey] ?? '')) === '';
        if ($noQuery || (isset($_SESSION[$countKey]) && (int) $_SESSION[$countKey] === 0)) {
            echo json_encode(['error' => self::noDataMessage(), 'empty' => true]);
            return true;
        }

        if (isset($_SESSION[$countKey]) && (int) $_SESSION[$countKey] <= self::INLINE_MAX_ROWS) {
            echo json_encode(self::runInline($script));
            return true;
        }

        $result = self::start($script, $queryKey, $_POST, $_GET);
        if (isset($result['jobId'])) {
            $result['statusUrl'] = self::statusUrl($result['jobId']);
        }
        echo json_encode($result);
        return true;
    }

    /**
     * Runs a small export right here, in the request, and answers with its
     * download grant, as the background job would once done. The script is
     * the one already running: required again without "async", it goes past
     * queueRequested() and exports as it always did.
     */
    private static function runInline(string $script): array
    {
        // Row count does not bound the cost of every export, so give the
        // request the worker's memory and a time limit before running it.
        $memory = MiscUtility::convertToBytes((string) ini_get('memory_limit'));
        if ($memory !== -1 && $memory < 1024 ** 3) {
            ini_set('memory_limit', '1G');
        }
        set_time_limit(300);
        // Export scripts only read the session, so let it go: the user's other
        // pages must not wait on this request's session lock while it runs.
        // The 300 seconds above is the old synchronous export's own limit.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $level = ob_get_level();
        self::$inlineTicks = 0;
        ob_start();
        try {
            (static function (string $script): void {
                require $script;
            })($script);
            $token = self::grantFromOutput((string) ob_get_clean(), 'inline');
            $rows = self::$inlineTicks;
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            LoggerUtility::logError('Export failed: ' . $e->getMessage(), ['script' => $script, 'exception' => $e]);
            $token = null;
            $rows = 0;
        } finally {
            self::$inlineTicks = null;
        }

        if (!DownloadTokenUtility::looksLikeToken((string) $token)) {
            return ['error' => _translate('Unable to generate the excel file')];
        }
        if ($rows === 0) {
            // The data changed after the listing was counted and nothing matches now.
            $file = DownloadTokenUtility::resolve($token);
            if ($file !== null) {
                self::remove($file);
            }
            return ['error' => self::noDataMessage(), 'empty' => true];
        }
        return ['token' => $token];
    }

    private static function noDataMessage(): string
    {
        return _translate('No data available to export. Please change the filters and search again.');
    }

    /** Called by an export script for every row it writes. */
    public static function tick(): void
    {
        if (self::$inlineTicks !== null) {
            self::$inlineTicks++;
            return;
        }
        if (self::$current === null) {
            return;
        }
        self::$current->ticks++;
        self::$current->progress(self::$current->ticks);
    }

    /**
     * Records a new job for the current user and launches its worker.
     * Answers with ['jobId' => ...]; with ['error' => ...] when the server
     * already has MAX_ACTIVE_JOBS unfinished; or with [] if there is nothing to
     * export or the worker could not be started. While the same user already
     * has this very export (same script, query and form values) queued or
     * running, answers with that job instead of starting a second one.
     */
    private static function start(string $script, string $queryKey, array $post, array $get): array
    {
        $script = realpath($script);
        if ($script === false || !self::isAppScript($script) || empty($_SESSION['userId'])) {
            return [];
        }

        $query = trim((string) ($_SESSION[$queryKey] ?? ''));
        if ($query === '') {
            return [];
        }

        $dir = self::directory();
        if (!is_dir($dir) && !MiscUtility::makeDirectory($dir, 0770)) {
            return [];
        }

        self::sweep();

        unset($post['async'], $post['csrf_token']);
        $userId = (string) $_SESSION['userId'];
        $fingerprint = hash('sha256', json_encode([$script, $query, $post, $get]));

        // Looking for the same export and recording a new one happen under one
        // lock, so two requests at once cannot both start it.
        $lock = @fopen($dir . '/start.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return [];
        }
        try {
            $unfinished = 0;
            $active = self::checkActiveJobs($userId, $fingerprint, $unfinished);
            if ($active !== null) {
                return ['jobId' => $active];
            }
            if ($unfinished >= self::MAX_ACTIVE_JOBS) {
                return ['error' => _translate('The server is busy with other exports. Please try again later.')];
            }

            $countKey = $queryKey . 'Count';
            $session = [$queryKey => $query];
            foreach ([...self::SESSION_KEYS, $countKey] as $key) {
                if (isset($_SESSION[$key])) {
                    $session[$key] = $_SESSION[$key];
                }
            }

            $id = bin2hex(random_bytes(16));
            $job = new self($id, [
                'id' => $id,
                'script' => $script,
                'userId' => $userId,
                'fingerprint' => $fingerprint,
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
                self::remove(self::path($id));
                self::remove(self::statusPath($id));
                return [];
            }

            return ['jobId' => $id];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Fails every unfinished job (anyone's) that has died or overrun, which
     * frees its slot, and returns the id of the one this user already has for
     * this export, if any. $unfinished receives how many jobs are still queued
     * or running. Starting an export is when stuck jobs matter, so
     * it is checked here rather than only when a browser asks for a status.
     */
    private static function checkActiveJobs(string $userId, string $fingerprint, int &$unfinished): ?string
    {
        $found = null;
        $unfinished = 0;
        foreach (glob(self::directory() . '/*.json') ?: [] as $path) {
            $job = self::load(basename($path, '.json'));
            if ($job === null || in_array($job->job['status'], self::FINISHED, true)) {
                continue;
            }
            $job->detectDeath();
            if (in_array($job->job['status'], self::FINISHED, true)) {
                continue;
            }
            $unfinished++;
            if ($job->job['userId'] === $userId && ($job->job['fingerprint'] ?? null) === $fingerprint) {
                $found = $job->id;
            }
        }
        return $found;
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
            } elseif ($again) {
                $response['token'] = _downloadToken($data['file']);
            } elseif ($claim && $job->update(['claimed' => true], fn(array $j): bool => empty($j['claimed']))) {
                // Only the one request that flips "claimed" gets the download.
                $response['token'] = _downloadToken($data['file']);
            } elseif ($claim || !empty($job->job['claimed'])) {
                $response['status'] = 'claimed';
            }
        }

        return $response;
    }

    /**
     * Worker entry point: waits for a free slot, restores the requester's
     * context and runs the export script with this job as its progress sink.
     * Returns a process exit code.
     */
    public static function run(string $id): int
    {
        $job = self::load($id);
        if ($job === null || $job->job['status'] !== 'queued') {
            return 1;
        }
        $script = realpath((string) ($job->job['script'] ?? ''));
        if ($script === false || !self::isAppScript($script)) {
            $job->fail('Unknown export script');
            return 1;
        }

        $slot = $job->acquireSlot();
        if ($slot === null) {
            // The job was given up on (or failed) while it waited.
            return 1;
        }

        $_SESSION = $job->job['session'];
        $_POST = $job->job['post'];
        $_GET = $job->job['get'] ?? [];
        if (!empty($_SESSION['APP_LOCALE'])) {
            ContainerRegistry::get(SystemService::class)->setLocale($_SESSION['APP_LOCALE']);
        }

        try {
            $started = $job->update(
                ['status' => 'running', 'pid' => getmypid(), 'startedAt' => time()],
                fn(array $j): bool => $j['status'] === 'queued'
            );
            if (!$started) {
                return 1;
            }
            // A worker blocked inside a query never reaches its own runtime
            // check, and nobody may be polling to stop it. SIGALRM has no
            // handler, so it ends the process outright, query or not; the
            // job is then failed as overrun and its slot is freed with it.
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(self::MAX_RUNTIME_SECONDS + 60);
            }

            self::$current = $job;
            ob_start();
            (static function (string $script): void {
                require $script;
            })($script);
            $file = self::fileFromOutput((string) ob_get_clean(), $id);

            if ($job->ticks === 0) {
                // The data changed after the listing was counted and nothing matches now.
                if ($file !== null) {
                    self::remove($file);
                }
                $job->update(['status' => 'empty', 'error' => self::noDataMessage()], self::isRunning(...));
                return 0;
            }
            if ($file !== null) {
                $job->update(
                    ['status' => 'done', 'file' => $file, 'processed' => $job->ticks, 'total' => $job->ticks],
                    self::isRunning(...)
                );
            }
        } catch (Throwable $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!$e instanceof ExportStoppedException) {
                LoggerUtility::logError('Background export failed: ' . $e->getMessage(), [
                    'job' => $id,
                    'script' => $job->job['script'],
                    'exception' => $e,
                ]);
            }
            $job->fail(_translate('Unable to generate the excel file'));
            return 1;
        } finally {
            self::$current = null;
            flock($slot, LOCK_UN);
            fclose($slot);
        }

        if ($job->job['status'] !== 'done') {
            $job->fail(_translate('Unable to generate the excel file'));
            return 1;
        }

        return 0;
    }

    /**
     * Export scripts end by echoing a download grant for the file they wrote;
     * minted in the worker for the restored user, it names that file. Anything
     * printed before it (a stray echo, a notice) is logged, not mistaken for
     * a failure.
     */
    private static function fileFromOutput(string $output, string $id): ?string
    {
        $token = self::grantFromOutput($output, $id);
        return DownloadTokenUtility::looksLikeToken($token) ? DownloadTokenUtility::resolve($token) : null;
    }

    /** The last line an export script printed, which is its download grant. */
    private static function grantFromOutput(string $output, string $id): string
    {
        $lines = preg_split('/\R/', trim($output)) ?: [];
        if (count($lines) > 1) {
            LoggerUtility::logWarning('Export printed more than its download grant', [
                'job' => $id,
                'output' => mb_substr(implode("\n", array_slice($lines, 0, -1)), 0, 2000),
            ]);
        }
        return trim((string) end($lines));
    }

    /**
     * Blocks until one of the MAX_WORKERS slots is free and returns its held
     * lock, or null if the job stopped being queued meanwhile. The lock goes
     * with the process, so a worker that dies frees its slot.
     *
     * @return resource|null
     */
    private function acquireSlot()
    {
        $this->update(['pid' => getmypid()]);
        $waitingSince = time();
        while (true) {
            $opened = 0;
            for ($i = 0; $i < self::MAX_WORKERS; $i++) {
                $handle = @fopen(self::directory() . "/slot-$i.lock", 'c');
                if ($handle === false) {
                    continue;
                }
                $opened++;
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    return $handle;
                }
                fclose($handle);
            }
            if ($opened === 0) {
                // Slot files this user cannot open (left by another user) would
                // otherwise keep the job waiting for ever: run it unlimited instead.
                LoggerUtility::logWarning('Export worker slots cannot be opened; running without a limit', [
                    'job' => $this->id,
                ]);
                return fopen('php://memory', 'r');
            }
            if (time() - $waitingSince > self::MAX_QUEUE_SECONDS) {
                $this->fail(_translate('The server is busy with other exports. Please try again later.'));
                return null;
            }
            sleep(self::SLOT_POLL_SECONDS);
            // A heartbeat, so the browser sees a waiting job rather than a dead one.
            if (!$this->update([], fn(array $j): bool => $j['status'] === 'queued')) {
                return null;
            }
        }
    }

    private static function isRunning(array $job): bool
    {
        return $job['status'] === 'running';
    }

    private function progress(int $processed): void
    {
        $now = microtime(true);
        if ($now - $this->lastProgressWrite < self::PROGRESS_INTERVAL_SECONDS) {
            return;
        }
        $this->lastProgressWrite = $now;
        $tooLong = time() - (int) ($this->job['startedAt'] ?? time()) > self::MAX_RUNTIME_SECONDS;
        // A job failed from outside (timed out, declared dead) stops here
        // instead of writing a file nobody will collect.
        if ($tooLong || !$this->update(['processed' => $processed], self::isRunning(...))) {
            if ($tooLong) {
                $this->fail(_translate('The export took too long and was stopped. Please narrow the filters.'));
            }
            throw new ExportStoppedException('Export job ' . $this->id . ' was stopped');
        }
    }

    /** Marks the job failed, unless it has already finished one way or another. */
    private function fail(string $error): void
    {
        $this->update(
            ['status' => 'failed', 'error' => $error],
            fn(array $j): bool => !in_array($j['status'], self::FINISHED, true)
        );
    }

    /** Marks a job failed when its worker never started, stopped reporting, or ran too long. */
    private function detectDeath(): void
    {
        $now = time();
        $age = $now - (int) $this->job['updatedAt'];
        $status = $this->job['status'];
        $runtime = $now - (int) ($this->job['startedAt'] ?? $now);

        if ($status === 'queued' && $age > self::SPAWN_TIMEOUT_SECONDS) {
            $this->failIf(
                _translate('The export could not be started. Please try again.'),
                fn(array $j): bool => $j['status'] === 'queued'
                    && $now - (int) $j['updatedAt'] > self::SPAWN_TIMEOUT_SECONDS
            );
        } elseif ($status === 'running' && $runtime > self::MAX_RUNTIME_SECONDS) {
            // Alive or not: a worker stuck in a query never reaches its own check.
            $stopped = $this->failIf(
                _translate('The export took too long and was stopped. Please narrow the filters.'),
                self::isRunning(...)
            );
            if ($stopped) {
                $this->stopWorker();
            }
        } elseif ($status === 'running' && $age > self::STALE_SECONDS && !$this->workerAlive()) {
            $this->failIf(
                _translate('The export stopped unexpectedly. Please try again.'),
                fn(array $j): bool => $j['status'] === 'running' && $now - (int) $j['updatedAt'] > self::STALE_SECONDS
            );
        }
    }

    private function failIf(string $error, callable $guard): bool
    {
        return $this->update(['status' => 'failed', 'error' => $error], $guard);
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
        $cmdline = @file_get_contents("/proc/$pid/cmdline");
        // A pid can be reused once the worker is gone, even by another export
        // worker: only the process running this very job counts.
        return $cmdline !== false
            && str_contains($cmdline, 'export-worker.php')
            && str_contains($cmdline, $this->id);
    }

    private function stopWorker(): void
    {
        if ($this->workerAlive() && function_exists('posix_kill')) {
            @posix_kill((int) $this->job['pid'], 15);
        }
    }

    /**
     * Applies $changes to the job file under an exclusive lock, so the worker
     * and the status requests never overwrite each other's changes. With a
     * $guard, the change is made only if the guard accepts the job as it is on
     * disk at that moment; returns whether it was made.
     */
    private function update(array $changes, ?callable $guard = null): bool
    {
        $lock = @fopen(self::path($this->id) . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            return false;
        }
        try {
            $fresh = self::load($this->id);
            if ($fresh !== null) {
                $this->job = $fresh->job;
            }
            if ($guard !== null && !$guard($this->job)) {
                return false;
            }
            $this->job = array_merge($this->job, $changes, ['updatedAt' => time()]);
            return $this->save();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
        if (!rename($tmp, $path)) {
            return false;
        }
        $this->publishStatus();
        return true;
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

    private static function isAppScript(string $script): bool
    {
        return str_starts_with($script, realpath(APPLICATION_PATH) . DIRECTORY_SEPARATOR);
    }

    private static function spawn(string $id): bool
    {
        $php = self::phpBinary();
        if ($php === null) {
            LoggerUtility::logError('Could not launch export worker: no PHP command-line binary found', [
                'job' => $id,
                'hint' => "Set ['system']['php_path'] in the config to the php binary for PHP " . PHP_VERSION,
            ]);
            return false;
        }
        $worker = BIN_PATH . '/export-worker.php';
        // The shell refuses to start the worker at all if its output cannot be
        // written, so a log the web user cannot append to must not block it.
        // Errors still reach the app log through LoggerUtility.
        $log = LOG_PATH . '/export-worker.log';
        if (is_file($log) ? !is_writable($log) : !is_writable(LOG_PATH)) {
            $log = '/dev/null';
        }

        // The worker sets its own alarm, but a PHP build without pcntl cannot;
        // coreutils' timeout then ends a worker stuck past MAX_RUNTIME_SECONDS.
        // It counts from launch, so it also allows for the longest slot wait.
        $timeout = '';
        foreach (['/usr/bin/timeout', '/bin/timeout'] as $binary) {
            if (is_executable($binary)) {
                $lifetime = self::MAX_RUNTIME_SECONDS + self::MAX_QUEUE_SECONDS + 120;
                $timeout = sprintf('%s -k 60 %d ', escapeshellarg($binary), $lifetime);
                break;
            }
        }

        // nohup + redirect + & detaches the worker, so this request returns at
        // once and the export outlives the page that started it.
        $cmd = sprintf(
            'APPLICATION_ENV=%s nohup %s%s %s %s >> %s 2>&1 &',
            escapeshellarg(APPLICATION_ENV),
            $timeout,
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

    /**
     * The command-line PHP to run the worker with, of the same version as the
     * PHP serving this request. PHP_BINDIR is fixed when PHP is built, so after
     * a package upgrade it can name a directory that no longer exists. On
     * Ubuntu this resolves to /usr/bin/php8.x, so nothing needs configuring.
     */
    private static function phpBinary(): ?string
    {
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '') {
            return PHP_BINARY;
        }
        $version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        // Each entry: a path, and whether its name alone vouches for the
        // version. An unversioned "php" follows update-alternatives or PATH and
        // can be a stray other install, so it is run once to ask its version.
        // The serving build's own directory comes first.
        $candidates = [
            [SYSTEM_CONFIG['system']['php_path'] ?? null, true],
            [PHP_BINDIR . "/php$version", true],
            [PHP_BINDIR . '/php', false],
            ["/usr/bin/php$version", true],
            ["/opt/homebrew/opt/php@$version/bin/php", true],
            ["/usr/local/opt/php@$version/bin/php", true],
            ['/usr/bin/php', false],
            ['/usr/local/bin/php', false],
            ['/opt/homebrew/bin/php', false],
        ];
        foreach ($candidates as [$candidate, $trusted]) {
            if (empty($candidate) || !is_file($candidate) || !is_executable($candidate)) {
                continue;
            }
            if ($trusted || self::binaryVersion($candidate) === $version) {
                return $candidate;
            }
        }
        return null;
    }

    /** The major.minor version a PHP binary reports, or null if it cannot be run. */
    private static function binaryVersion(string $binary): ?string
    {
        try {
            $code = escapeshellarg('echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;');
            $output = shell_exec(escapeshellarg($binary) . " -n -r $code 2>/dev/null");
        } catch (Throwable) {
            return null;
        }
        return is_string($output) ? trim($output) : null;
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
                self::remove($job['file']);
            }
            self::remove($path);
            self::remove($path . '.lock');
            self::remove(self::statusPath(basename($path, '.json')));
        }
    }

    private static function remove(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * The browser polls a static copy of the job's progress, which the web
     * server hands out without starting PHP: no session, no database. It holds
     * only what the status endpoint would say anyway, never the query, file or
     * grant, under the job's unguessable id. PHP is asked only to claim the
     * finished file, or when this copy stops changing.
     */
    private function publishStatus(): void
    {
        $dir = self::statusDirectory();
        if (!is_dir($dir) && !MiscUtility::makeDirectory($dir, 0775)) {
            return;
        }
        $path = self::statusPath($this->id);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $public = [
            'status' => $this->job['status'],
            'processed' => (int) $this->job['processed'],
            'total' => $this->job['total'] === null ? null : (int) $this->job['total'],
            'error' => $this->job['error'],
            'updatedAt' => (int) $this->job['updatedAt'],
        ];
        if (file_put_contents($tmp, json_encode($public)) !== false) {
            @chmod($tmp, 0644);
            @rename($tmp, $path);
        }
    }

    private static function statusDirectory(): string
    {
        return TEMP_PATH . '/export-status';
    }

    private static function statusPath(string $id): string
    {
        return self::statusDirectory() . '/' . $id . '.status';
    }

    private static function statusUrl(string $id): string
    {
        return '/temporary/export-status/' . $id . '.status';
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
