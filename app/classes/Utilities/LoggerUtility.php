<?php

namespace App\Utilities;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

class LoggerUtility
{
    // Log the error with Monolog, including the file, line, and stack trace
    public static function log($level, $message, $context = [])
    {
        $logger = new Logger('error_logger');

        $handler = new RotatingFileHandler(ROOT_PATH . '/logs/logfile.log', 30, Logger::ERROR, true, 0777);
        $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
        $logger->pushHandler($handler);
        $logger->log($level, $message, $context ?? []);
    }
}
