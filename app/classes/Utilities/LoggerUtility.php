<?php

namespace App\Utilities;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use Throwable;
use Monolog\Level;
use Monolog\Logger;
use App\Utilities\MiscUtility;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;

final class LoggerUtility
{
    private static ?Logger $logger = null;
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
        @error_log("LoggerUtility: {$message} | PHP error_log: " . self::getPhpErrorLogPath());

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
