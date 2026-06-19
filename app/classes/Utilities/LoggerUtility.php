<?php

namespace App\Utilities;

use Throwable;
use Monolog\Level;
use Monolog\Logger;
use FilesystemIterator;
use App\Utilities\MiscUtility;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;

final class LoggerUtility
{
    private static ?Logger $logger = null;
    /** @var array<string, Logger> Cached per-channel loggers (separate files) */
    private static array $channelLoggers = [];
    private const string LOG_FILENAME = 'logfile.log';
    private const int LOG_ROTATIONS = 30;
    private const int MAX_FILE_SIZE_MB = 100; // Maximum size per log file in MB
    private const int MAX_TOTAL_LOG_SIZE_MB = 1000; // Maximum total size for all logs in MB
    private const int MAX_MESSAGE_LENGTH = 10000; // Maximum length for a single log message
    private static int $logCallCount = 0;
    private static int $maxLogsPerRequest = 10000; // Prevent infinite logging loops
    private static bool $hasLoggedFallback = false; // Prevent recursive fallback logging

    public static function getLogger(): Logger
    {
        if (isset(self::$logger)) {
            return self::$logger;
        }

        self::$logger = new Logger('app');
        $logDir = defined('LOG_PATH') ? LOG_PATH : VAR_PATH . '/logs';
        $logLevel = defined('LOG_LEVEL') ? self::parseLogLevel(LOG_LEVEL) : Level::Debug;

        try {
            // Check total log directory size before proceeding
            if (is_dir($logDir) && !self::checkLogDirectorySize($logDir)) {
                self::useFallbackHandler("Log directory exceeded maximum size limit");
                return self::$logger;
            }

            if (MiscUtility::makeDirectory($logDir, 0775)) {
                if (is_writable($logDir)) {
                    $logPath = $logDir . '/' . self::LOG_FILENAME;

                    // Check if current log file is too large
                    if (file_exists($logPath) && filesize($logPath) > self::MAX_FILE_SIZE_MB * 1024 * 1024) {
                        // Force rotation by renaming the file
                        $backupName = $logDir . '/' . date('Y-m-d') . '-' . self::LOG_FILENAME;
                        @rename($logPath, $backupName);
                    }

                    // Use RotatingFileHandler
                    $handler = new RotatingFileHandler(
                        $logPath,
                        self::LOG_ROTATIONS,
                        $logLevel,
                        true,
                        0664,
                        false
                    );
                    $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');

                    self::$logger->pushHandler($handler);
                } else {
                    self::useFallbackHandler("Log directory not writable: $logDir");
                }
            } else {
                self::useFallbackHandler("Failed to create log directory: $logDir");
            }
        } catch (Throwable $e) {
            self::useFallbackHandler("Exception during logger setup: " . $e->getMessage());
        }

        // CRITICAL: Ensure we always have at least one handler to prevent crashes
        if (self::$logger->getHandlers() === []) {
            self::useErrorLogHandler("No handlers were successfully configured - falling back to PHP error_log");
        }

        return self::$logger;
    }

    private static function checkLogDirectorySize(string $logDir): bool
    {
        try {
            $totalSize = 0;
            $maxSize = self::MAX_TOTAL_LOG_SIZE_MB * 1024 * 1024;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();

                    // Early exit if we've exceeded the limit
                    if ($totalSize > $maxSize) {
                        self::logToPhpErrorLog("WARNING: Log directory size exceeded {$maxSize} bytes. Cleaning up old logs...");
                        self::cleanupOldLogs($logDir);
                        return false;
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            self::logToPhpErrorLog("Failed to check log directory size: {$e->getMessage()}");
            return true; // Allow logging to continue on error
        }
    }

    private static function cleanupOldLogs(string $logDir): void
    {
        try {
            $files = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.log$/', (string) $file->getFilename())) {
                    $files[] = [
                        'path' => $file->getRealPath(),
                        'mtime' => $file->getMTime(),
                        'size' => $file->getSize()
                    ];
                }
            }

            // Sort by modification time (oldest first)
            usort($files, fn($a, $b): int => $a['mtime'] <=> $b['mtime']);

            // Delete oldest files until we're under 80% of the limit
            $targetSize = self::MAX_TOTAL_LOG_SIZE_MB * 1024 * 1024 * 0.8;
            $currentSize = array_sum(array_column($files, 'size'));

            foreach ($files as $file) {
                if ($currentSize <= $targetSize) {
                    break;
                }

                if (MiscUtility::deleteFile($file['path'])) {
                    $currentSize -= $file['size'];
                    self::logToPhpErrorLog("Deleted old log file: {$file['path']}");
                }
            }
        } catch (Throwable $e) {
            self::logToPhpErrorLog("Failed to cleanup old logs: {$e->getMessage()}");
        }
    }

    private static function useFallbackHandler(string $reason): void
    {
        try {
            // Try to use stderr as fallback
            $fallbackHandler = new StreamHandler('php://stderr', Level::Warning);
            self::$logger->pushHandler($fallbackHandler);
            self::logToPhpErrorLog("LoggerUtility fallback to stderr: {$reason}");
        } catch (Throwable $e) {
            // If even stderr fails, use PHP's error_log
            self::useErrorLogHandler("Stderr fallback failed: {$e->getMessage()} | Original reason: {$reason}");
        }
    }

    private static function useErrorLogHandler(string $reason): void
    {
        try {
            // ErrorLogHandler uses PHP's error_log() function
            $errorLogHandler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Level::Warning);
            self::$logger->pushHandler($errorLogHandler);
            self::logToPhpErrorLog("LoggerUtility using PHP error_log handler: {$reason}");
        } catch (Throwable $e) {
            // Last resort - nothing we can do, just exit gracefully
            self::logToPhpErrorLog("CRITICAL: All logging handlers failed: {$e->getMessage()} - logging disabled");
        }
    }

    /**
     * Safe logging to PHP's error_log to avoid recursion
     */
    private static function logToPhpErrorLog(string $message): void
    {
        if (self::$hasLoggedFallback) {
            return; // Prevent spam
        }

        self::$hasLoggedFallback = true;
        // PHP's error_log() writes raw lines, so strip control characters and line breaks here.
        $sanitizedMessage = MiscUtility::sanitizeCliString($message, preserveLineBreaks: false);
        @error_log("LoggerUtility: {$sanitizedMessage} | PHP error_log: " . self::getPhpErrorLogPath());

        // Reset flag after a moment to allow future errors
        register_shutdown_function(function (): void {
            self::$hasLoggedFallback = false;
        });
    }

    public static function getPhpErrorLogPath(): string
    {
        return ini_get('error_log') ?: 'stderr or server default';
    }

    private static function getCallerInfo(int $index = 1): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        return [
            'file' => $backtrace[$index]['file'] ?? '',
            'line' => $backtrace[$index]['line'] ?? 0,
        ];
    }

    public static function log(Level|string $level, string $message, array $context = []): void
    {
        try {
            // Prevent infinite logging loops
            self::$logCallCount++;
            if (self::$logCallCount > self::$maxLogsPerRequest) {
                self::logToPhpErrorLog("WARNING: Maximum logs per request exceeded. Possible logging loop detected.");
                return;
            }

            // Truncate message if too long
            if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
                $message = substr($message, 0, self::MAX_MESSAGE_LENGTH) . '... [message truncated - exceeded ' . self::MAX_MESSAGE_LENGTH . ' chars]';
            }

            $logger = self::getLogger();
            $callerInfo = self::getCallerInfo(1);
            $context['file'] ??= $callerInfo['file'];
            $context['line'] ??= $callerInfo['line'];

            // Sanitize context data
            $context = self::sanitizeContext($context);

            $logger->log($level, MiscUtility::toUtf8($message), $context);
        } catch (Throwable $e) {
            // CRITICAL: Never let logging crash the application
            self::logToPhpErrorLog("LoggerUtility::log() failed: {$e->getMessage()} | Original message: " . substr($message, 0, 200));
        }
    }

    /**
     * Log to a dedicated channel that writes to its own rotating file
     * (<channel>.log) in the log directory, keeping these entries out of the
     * main application log. Falls back to the PHP error log if the file
     * handler cannot be configured.
     */
    public static function logToChannel(string $channel, Level|string $level, string $message, array $context = []): void
    {
        try {
            self::$logCallCount++;
            if (self::$logCallCount > self::$maxLogsPerRequest) {
                self::logToPhpErrorLog("WARNING: Maximum logs per request exceeded. Possible logging loop detected.");
                return;
            }

            if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
                $message = substr($message, 0, self::MAX_MESSAGE_LENGTH) . '... [message truncated - exceeded ' . self::MAX_MESSAGE_LENGTH . ' chars]';
            }

            $logger = self::getChannelLogger($channel);
            $callerInfo = self::getCallerInfo(1);
            $context['file'] ??= $callerInfo['file'];
            $context['line'] ??= $callerInfo['line'];
            $context = self::sanitizeContext($context);

            $logger->log($level, MiscUtility::toUtf8($message), $context);
        } catch (Throwable $e) {
            self::logToPhpErrorLog("LoggerUtility::logToChannel() failed: {$e->getMessage()} | Original message: " . substr($message, 0, 200));
        }
    }

    private static function getChannelLogger(string $channel): Logger
    {
        $safeChannel = preg_replace('/[^a-zA-Z0-9._-]/', '-', $channel) ?: 'channel';

        if (isset(self::$channelLoggers[$safeChannel])) {
            return self::$channelLoggers[$safeChannel];
        }

        $logger = new Logger($safeChannel);
        $logDir = defined('LOG_PATH') ? LOG_PATH : VAR_PATH . '/logs';
        $logLevel = defined('LOG_LEVEL') ? self::parseLogLevel(LOG_LEVEL) : Level::Debug;

        try {
            if (MiscUtility::makeDirectory($logDir, 0775) && is_writable($logDir)) {
                $handler = new RotatingFileHandler(
                    $logDir . '/' . $safeChannel . '.log',
                    self::LOG_ROTATIONS,
                    $logLevel,
                    true,
                    0664,
                    false
                );
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                $logger->pushHandler($handler);
            } else {
                $logger->pushHandler(new ErrorLogHandler());
            }
        } catch (Throwable $e) {
            $logger->pushHandler(new ErrorLogHandler());
            self::logToPhpErrorLog("Failed to configure channel logger '{$safeChannel}': {$e->getMessage()}");
        }

        self::$channelLoggers[$safeChannel] = $logger;
        return $logger;
    }

    private static function sanitizeContext(array $context): array
    {
        $maxContextLength = 1000;
        $maxArrayDepth = 5;

        // Always sanitize, regardless of environment (safer default)
        $sanitized = [];

        foreach ($context as $key => $value) {
            // Skip if key is too long
            if (is_string($key) && strlen($key) > 100) {
                continue;
            }

            // Handle different types
            if (is_string($value)) {
                if (strlen($value) > $maxContextLength) {
                    $sanitized[$key] = substr($value, 0, $maxContextLength) . '... [truncated]';
                } else {
                    $sanitized[$key] = $value;
                }
            } elseif (is_array($value)) {
                if ($key === 'trace') {
                    // Limit trace depth
                    $sanitized[$key] = array_slice($value, 0, 10);
                } else {
                    // Recursively sanitize arrays but limit depth
                    $sanitized[$key] = self::sanitizeArrayRecursive($value, 0, $maxArrayDepth);
                }
            } elseif (is_object($value) || is_resource($value)) {
                $sanitized[$key] = '[omitted: ' . gettype($value) . ']';
            } elseif (is_scalar($value) || is_null($value)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '[omitted: ' . gettype($value) . ']';
            }
        }

        return $sanitized;
    }

    private static function sanitizeArrayRecursive(array $array, int $depth, int $maxDepth): array
    {
        if ($depth >= $maxDepth) {
            return ['[max depth reached]'];
        }

        $sanitized = [];
        $count = 0;
        $maxItems = 50; // Limit array items

        foreach ($array as $key => $value) {
            if ($count++ >= $maxItems) {
                $sanitized['...'] = '[truncated - max items reached]';
                break;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArrayRecursive($value, $depth + 1, $maxDepth);
            } elseif (is_string($value) && strlen($value) > 500) {
                $sanitized[$key] = substr($value, 0, 500) . '... [truncated]';
            } elseif (is_object($value) || is_resource($value)) {
                $sanitized[$key] = '[' . gettype($value) . ']';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    public static function logDebug(string $message, array $context = []): void
    {
        self::log(Level::Debug, $message, $context);
    }

    public static function logInfo(string $message, array $context = []): void
    {
        self::log(Level::Info, $message, $context);
    }

    public static function logWarning(string $message, array $context = []): void
    {
        self::log(Level::Warning, $message, $context);
    }

    public static function logError(string $message, array $context = []): void
    {
        self::log(Level::Error, $message, $context);
    }

    private static function parseLogLevel(string $level): Level
    {
        return match (strtoupper($level)) {
            'INFO' => Level::Info,
            'WARNING', 'WARN' => Level::Warning,
            'ERROR' => Level::Error,
            default => Level::Debug
        };
    }

    /**
     * Log the error and terminate with an appropriate CLI or HTML response.
     */
    public static function fatalError(string $title, Throwable|string $error): never
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        error_log("[FATAL] {$title}: {$message}");

        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "{$title}: {$message}\n");
            exit(1);
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        $debugMode = defined('SYSTEM_CONFIG')
            && !empty(SYSTEM_CONFIG['system']['debug_mode']);

        // Page copy (all translatable). Intentionally vendor/role agnostic — the
        // "support team" differs per deployment, so we never name who that is.
        $pageTitle     = _htmlTranslate("System Temporarily Unavailable");
        $heading       = _htmlTranslate("We can't reach the system right now");
        $intro         = _htmlTranslate("InteLIS could not connect to its database. This is usually temporary and often resolves on its own within a few minutes.");
        $stepsHeading  = _htmlTranslate("What you can try");
        $step1         = _htmlTranslate("Wait a minute, then reload this page — the database may simply be restarting.");
        $step2         = _htmlTranslate("If you can, restart the computer or server that runs InteLIS, then wait a minute and reload. This restarts the database and fixes the problem most of the time.");
        $step3         = _htmlTranslate("Make sure the server has enough free disk space and memory.");
        $step4         = _htmlTranslate("Confirm the database settings in the configuration are correct and the credentials have not changed.");
        $reloadLabel   = _htmlTranslate("Reload page");
        $supportLead   = _htmlTranslate("If the problem continues after a few minutes, please contact your support team and share the time this happened.");
        $detailsLabel  = _htmlTranslate("Technical details");

        $detail = $debugMode
            ? '<details class="tech"><summary>' . $detailsLabel . '</summary><pre>'
                . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre></details>'
            : '';

        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$pageTitle}</title>
        <style>
        :root {
            --bg: #eef2f7;
            --card: #ffffff;
            --ink: #1f2937;
            --muted: #6b7280;
            --accent: #2563eb;
            --line: #e5e7eb;
            --warn-bg: #fef3f2;
            --warn-ink: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            line-height: 1.55;
        }
        .card {
            background: var(--card);
            width: 100%;
            max-width: 760px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .card__top {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 28px 32px;
            background: var(--warn-bg);
            border-bottom: 1px solid var(--line);
        }
        .icon {
            flex: 0 0 auto;
            width: 44px; height: 44px;
            border-radius: 50%;
            background: var(--warn-ink);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; font-weight: 700;
        }
        h1 {
            font-size: 1.3rem;
            margin: 0;
            color: var(--warn-ink);
        }
        .card__body { padding: 28px 32px 32px; }
        .intro { margin: 0 0 22px; color: var(--ink); }
        h2 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin: 0 0 12px;
        }
        ol.steps {
            margin: 0 0 26px;
            padding: 0;
            list-style: none;
            counter-reset: step;
        }
        ol.steps li {
            counter-increment: step;
            position: relative;
            padding: 10px 0 10px 40px;
            border-bottom: 1px solid var(--line);
        }
        ol.steps li:last-child { border-bottom: 0; }
        ol.steps li::before {
            content: counter(step);
            position: absolute; left: 0; top: 9px;
            width: 26px; height: 26px;
            background: var(--accent);
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; font-weight: 600;
        }
        .actions { margin-bottom: 24px; }
        .btn {
            display: inline-block;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            padding: 11px 22px;
            border-radius: 9px;
            font-size: 0.95rem;
            font-weight: 600;
            border: 0;
            cursor: pointer;
        }
        .btn:hover { background: #1d4ed8; }
        .support {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px 16px;
            color: var(--muted);
            font-size: 0.92rem;
            margin: 0;
        }
        .tech { margin-top: 22px; font-size: 0.88rem; }
        .tech summary { cursor: pointer; color: var(--muted); }
        .tech pre {
            background: #0f172a; color: #e2e8f0;
            padding: 14px; border-radius: 8px;
            overflow-x: auto; margin-top: 10px;
            font-size: 0.82rem;
        }
        </style>
        </head>
        <body>
        <div class="card">
            <div class="card__top">
                <div class="icon">!</div>
                <h1>{$heading}</h1>
            </div>
            <div class="card__body">
                <p class="intro">{$intro}</p>
                <h2>{$stepsHeading}</h2>
                <ol class="steps">
                    <li>{$step1}</li>
                    <li>{$step2}</li>
                    <li>{$step3}</li>
                    <li>{$step4}</li>
                </ol>
                <div class="actions">
                    <a class="btn" href="javascript:location.reload()">{$reloadLabel}</a>
                </div>
                <p class="support">{$supportLead}</p>
                {$detail}
            </div>
        </div>
        </body>
        </html>
        HTML;

        exit;
    }

    /**
     * Reset the log call counter (useful for testing or long-running processes)
     */
    public static function resetLogCallCount(): void
    {
        self::$logCallCount = 0;
    }

    /**
     * Get current log statistics
     */
    public static function getLogStats(): array
    {
        $logDir = defined('LOG_PATH') ? LOG_PATH : VAR_PATH . '/logs';
        $stats = [
            'log_calls_this_request' => self::$logCallCount,
            'log_directory' => $logDir,
            'total_size_mb' => 0,
            'file_count' => 0
        ];

        try {
            if (is_dir($logDir)) {
                $totalSize = 0;
                $fileCount = 0;

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS)
                );

                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $totalSize += $file->getSize();
                        $fileCount++;
                    }
                }

                $stats['total_size_mb'] = round($totalSize / (1024 * 1024), 2);
                $stats['file_count'] = $fileCount;
            }
        } catch (Throwable $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }
}
