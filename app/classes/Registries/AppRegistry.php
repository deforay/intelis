<?php

namespace App\Registries;

use Exception;
use Throwable;
use App\Utilities\LoggerUtility;

class AppRegistry
{
    private static ?AppRegistry $instance = null;
    private static array $items = [];
    private static bool $reportedMissingRequest = false;

    public static function getInstance(): AppRegistry
    {
        if (!static::$instance instanceof \App\Registries\AppRegistry) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public static function set(string $key, $value): void
    {
        self::$items[$key] = $value;
    }

    public static function get(string $key)
    {
        $value = self::$items[$key] ?? null;

        if ($key === 'request' && $value === null && self::$reportedMissingRequest === false) {
            self::$reportedMissingRequest = true; // log once per process/request
            self::logMissingRequest();
        }

        return $value;
    }

    public static function remove(string $key): void
    {
        unset(self::$items[$key]);
    }

    public function __construct()
    {
        if (self::$instance instanceof \App\Registries\AppRegistry) {
            throw new Exception("Cannot instantiate a singleton.");
        }
        self::$instance = $this;
    }

    public function __clone()
    {
        throw new Exception("Cannot clone a singleton.");
    }
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }

    private static function logMissingRequest(): void
    {
        // Lightweight tracer to find callers that ask for a missing request object
        try {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
            $frames = [];
            foreach ($trace as $frame) {
                if (!empty($frame['file'])) {
                    $frames[] = $frame['file'] . ':' . ($frame['line'] ?? 0);
                }
            }

            $message = 'AppRegistry::get("request") returned null';
            if (class_exists(LoggerUtility::class)) {
                LoggerUtility::logWarning($message, ['trace' => $frames]);
            } else {
                @error_log($message . ' trace: ' . implode(' <- ', $frames));
            }
        } catch (Throwable) {
            // Never let tracing break execution
        }
    }
}
