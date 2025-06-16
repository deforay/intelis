<?php

namespace App\Utilities;

use Throwable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

final class LoggerUtility
{
    private static ?Logger $logger = null;
    private const LOG_FILENAME = 'logfile.log';
    private const LOG_ROTATIONS = 30;

    public static function getLogger(): Logger
    {
        if (isset(self::$logger)) {
            return self::$logger;
        }

        self::$logger = new Logger('app');
        $logDir = defined('LOG_PATH') ? LOG_PATH : ROOT_PATH . '/logs';
        $logLevel = defined('LOG_LEVEL') ? self::parseLogLevel(LOG_LEVEL) : Level::Debug;

        try {
            if (MiscUtility::makeDirectory($logDir, 0775)) {
                if (is_writable($logDir)) {
                    $logPath = $logDir . '/' . self::LOG_FILENAME;
                    $handler = new RotatingFileHandler($logPath, self::LOG_ROTATIONS, $logLevel);
                    $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                    self::$logger->pushHandler($handler);
                } else {
                    self::useFallbackHandler("Log directory not writable: $logDir");
                }
            } else {
                self::useFallbackHandler("Failed to create log directory: $logDir");
            }
        } catch (Throwable $e) {
            self::useFallbackHandler($e->getMessage());
        }

        return self::$logger;
    }

    private static function useFallbackHandler(string $reason): void
    {
        $fallbackHandler = new StreamHandler('php://stderr', Level::Warning);
        self::$logger->pushHandler($fallbackHandler);
        error_log("LoggerUtility fallback: {$reason} | PHP error_log: " . self::getPhpErrorLogPath());
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
            $logger = self::getLogger();
            $callerInfo = self::getCallerInfo(1);
            $context['file'] ??= $callerInfo['file'];
            $context['line'] ??= $callerInfo['line'];
            $logger->log($level, MiscUtility::toUtf8($message), $context);
        } catch (Throwable $e) {
            error_log("LoggerUtility failed: {$e->getMessage()} | Original message: {$message}");
        }
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
            'DEBUG' => Level::Debug,
            'INFO' => Level::Info,
            'WARNING', 'WARN' => Level::Warning,
            'ERROR' => Level::Error,
            default => Level::Debug
        };
    }
}
