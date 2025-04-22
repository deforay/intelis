<?php

namespace App\Utilities;

class MemoUtility
{
    private static array $cache = [];
    private const MAX_ENTRIES = 1000;

    private static function deepSort(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::deepSort($value);
            }
        }

        if (array_keys($array) !== range(0, count($array) - 1)) {
            ksort($array);
        }

        return $array;
    }

    public static function hashKey(string $caller, array $args): string
    {
        $normalizedArgs = self::deepSort($args);
        return sha1($caller . '|' . serialize($normalizedArgs));
    }

    public static function memo(string $key, callable $callback, ?float $ttl = null): mixed
    {
        $now = microtime(true);

        if (!isset(self::$cache[$key])) {
            if (count(self::$cache) >= self::MAX_ENTRIES) {
                array_shift(self::$cache); // Remove oldest
            }

            self::$cache[$key] = [
                'value' => $callback(),
                'timestamp' => $now
            ];
        } elseif ($ttl !== null && ($now - self::$cache[$key]['timestamp']) > $ttl) {
            self::$cache[$key] = [
                'value' => is_callable($callback) ? $callback() : null,
                'timestamp' => $now
            ];
        }

        return self::$cache[$key]['value'];
    }

    public static function remember(callable $callback, ?float $ttl = null): mixed
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = ($trace[1]['class'] ?? '') . ($trace[1]['type'] ?? '') . ($trace[1]['function'] ?? '');

        if (empty($caller) || $caller === 'Closure') {
            $caller = ($trace[0]['file'] ?? '') . ':' . ($trace[0]['line'] ?? 0);
        }


        $args = [];
        if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
            $args = $reflection->getStaticVariables();
        }

        $key = self::hashKey($caller, $args);

        return self::memo($key, $callback, $ttl);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }
}
