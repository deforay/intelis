<?php

namespace App\Utilities;


use Throwable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use App\Utilities\ApcuCacheUtility;
use App\Registries\ContainerRegistry;
use Monolog\Handler\RotatingFileHandler;

final class LoggerUtility
{
    private static ?Logger $logger = null;

    public static function isLogFolderWritable(int $refreshIntervalSeconds = 300): bool
    {
        /** @var ApcuCacheUtility $apcuCache */
        $apcuCache = ContainerRegistry::get(ApcuCacheUtility::class);

        $logDir = ROOT_PATH . '/logs';
        $cacheKey = 'log_folder_writable_status';

        $isWritable = $apcuCache->get($cacheKey, function () use ($logDir) {
            return is_dir($logDir) && is_writable($logDir);
        }, $refreshIntervalSeconds);

        if ($isWritable !== null) {
            return $isWritable;
        }

        // Fallback to session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $now = time();

            if (!isset($_SESSION['log_folder_writable_check_time']) || ($now - $_SESSION['log_folder_writable_check_time']) > $refreshIntervalSeconds) {
                $_SESSION['log_folder_writable'] = is_dir($logDir) && is_writable($logDir);
                $_SESSION['log_folder_writable_check_time'] = $now;
            }

            return $_SESSION['log_folder_writable'];
        }

        // Fallback if no cache and no session
        return is_dir($logDir) && is_writable($logDir);
    }

    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('logger');

            try {
                // Try to use the RotatingFileHandler for logging
                $handler = new RotatingFileHandler(ROOT_PATH . '/logs/logfile.log', 30, Level::Debug);
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                self::$logger->pushHandler($handler);
            } catch (Throwable $e) {
                // If the logs directory is not writable, fallback to stderr
                $fallbackHandler = new StreamHandler('php://stderr', Level::Warning);
                self::$logger->pushHandler($fallbackHandler);
                self::$logger->warning('Log file could not be written to. Fallback to stderr: ' . $e->getMessage());
            }
        }
        return self::$logger;
    }

    public static function getCallerInfo($index = 1)
    {
        $backtrace = debug_backtrace();

        $callerInfo = [
            'file' => '',
            'line' => 0
        ];

        if (isset($backtrace[$index])) {
            $callerInfo['file'] = $backtrace[$index]['file'];
            $callerInfo['line'] = $backtrace[$index]['line'];
        }

        return $callerInfo;
    }

    public static function log($level, $message, array $context = []): void
    {
        try {
            $logger = self::getLogger();

            $callerInfo = self::getCallerInfo(1);

            $context['file'] ??= $callerInfo['file'] ?? '';
            $context['line'] ??= $callerInfo['line'] ?? '';
            $logger->log($level, MiscUtility::toUtf8($message), $context);
        } catch (Throwable $e) {
            // If logging fails, fall back to PHP error log so that the app does not crash
            error_log('LoggerUtility failed to log message: ' . $e->getMessage());
            error_log('Original log message: ' . $message);
        }
    }


    public static function logDebug($message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }
    public static function logError($message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function logInfo($message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function logWarning($message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }
}
