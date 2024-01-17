<?php

namespace App\Utilities;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class LoggerUtility
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger('logger');
            $handler = new RotatingFileHandler(ROOT_PATH . '/logs/logfile.log', 30, Logger::DEBUG);
            $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
            self::$logger->pushHandler($handler);
        }
        return self::$logger;
    }

    public static function log($level, $message, $context = [])
    {
        $logger = self::getLogger();
        $logger->log($level, $message, $context);
    }
}
