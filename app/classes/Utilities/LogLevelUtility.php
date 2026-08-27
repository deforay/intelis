<?php

declare(strict_types=1);

namespace App\Utilities;

/**
 * Extracted from monitoring/get-log-files.php's detectLogLevel(). That file also
 * executes request-handling code at the top level (reads $_POST, calls exit()), so it
 * cannot be require'd directly in a test without triggering that -- the function had to
 * move here to be tested in isolation.
 */
final class LogLevelUtility
{
    public static function detectLogLevel(string $line): string
    {
        $line = strtolower($line);
        if (str_contains($line, 'error') || str_contains($line, 'exception') || str_contains($line, 'fatal')) {
            return 'error';
        } elseif (str_contains($line, 'warn')) {
            return 'warning';
        } elseif (str_contains($line, 'info')) {
            return 'info';
        } elseif (str_contains($line, 'debug')) {
            return 'debug';
        }
        return 'info';
    }
}
