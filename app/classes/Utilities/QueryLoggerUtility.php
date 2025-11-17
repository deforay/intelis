<?php

namespace App\Utilities;

use Throwable;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

final class QueryLoggerUtility
{
    private static ?Logger $queryLogger = null;

    private static function getQueryLogger(): Logger
    {
        if (!self::$queryLogger instanceof Logger) {
            self::$queryLogger = new Logger('query_logger');

            try {
                // Try to use the RotatingFileHandler for query logging
                $handler = new RotatingFileHandler(LOG_PATH . '/db/query.log', 30, Level::Debug);
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                self::$queryLogger->pushHandler($handler);
            } catch (Throwable $e) {
                // If the logs directory is not writable, fallback to stderr
                $fallbackHandler = new StreamHandler('php://stderr', Level::Warning);
                self::$queryLogger->pushHandler($fallbackHandler);
                self::$queryLogger->warning('Query log file could not be written to. Fallback to stderr: ' . $e->getMessage());
            }
        }
        return self::$queryLogger;
    }

    public static function log(string $query, array $bindings = [], ?float $executionTime = null): void
    {
        $logger = self::getQueryLogger();

        // Truncate query if it exceeds 10,000 characters (or any other limit)
        $maxLength = 10000;
        if (strlen($query) > $maxLength) {
            $query = substr($query, 0, $maxLength) . '... [truncated]';
        }
        $context = [];
        if ($bindings !== []) {
            $context['bindings']  = $bindings;
        }
        if ($executionTime !== null) {
            $context['execution_time'] = "$executionTime ms";
        }

        $logger->info('SQL Query Executed', array_merge(['query' => $query], $context));
    }
}
