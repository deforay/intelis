<?php

namespace App\Utilities;


use Throwable;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

final class LoggerUtility
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('logger');

            try {
                // Try to use the RotatingFileHandler for logging
                $handler = new RotatingFileHandler(ROOT_PATH . '/logs/logfile.log', 30, Logger::DEBUG);
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                self::$logger->pushHandler($handler);
            } catch (Throwable $e) {
                // If the logs directory is not writable, fallback to stderr
                $fallbackHandler = new StreamHandler('php://stderr', Logger::WARNING);
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
        $index = 1;
        if (isset($backtrace[$index])) {
            $callerInfo['file'] = $backtrace[$index]['file'];
            $callerInfo['line'] = $backtrace[$index]['line'];
        }

        return $callerInfo;
    }

    public static function log($level, $message, array $context = []): void
    {
        $logger = self::getLogger();

        $callerInfo = self::getCallerInfo(1);

        $context['file'] ??= $callerInfo['file'] ?? '';
        $context['line'] ??= $callerInfo['line'] ?? '';
        $logger->log($level, $message, $context);
    }

    public static function logError($message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function logInfo($message, array $context = []): void
    {
        self::log('info', $message, $context);
    }
}
