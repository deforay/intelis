<?php

namespace App\Utilities;

class MemoUtility
{
    private static array $cache = [];

    /**
     * Generate a safe, consistent hash key for memoization.
     *
     * @param string $caller Caller function/class or fallback identifier
     * @param array $args Arguments to hash
     * @return string 40-character SHA-1 hash
     */
    public static function hashKey(string $caller, array $args): string
    {
        return sha1($caller . '|' . serialize($args));
    }

    /**
     * Memoize a callable with optional TTL.
     *
     * @param string $key Unique key (e.g., hashed by hashKey)
     * @param callable $callback Logic to cache
     * @param float|null $ttl Optional TTL in seconds
     * @return mixed
     */
    public static function memo(string $key, callable $callback, ?float $ttl = null): mixed
    {
        $now = microtime(true);

        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = [
                'value' => $callback(),
                'timestamp' => $now
            ];
        } elseif ($ttl !== null && ($now - self::$cache[$key]['timestamp']) > $ttl) {
            self::$cache[$key] = [
                'value' => $callback(),
                'timestamp' => $now
            ];
        }

        return self::$cache[$key]['value'];
    }

    /**
     * Automatically memoize based on caller + args, with optional TTL.
     *
     * @param callable $callback The logic to memoize
     * @param float|null $ttl Optional TTL in seconds
     * @return mixed
     */
    public static function remember(callable $callback, ?float $ttl = null): mixed
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $caller = ($trace['class'] ?? '') . ($trace['type'] ?? '') . ($trace['function'] ?? '');

        if (empty($caller) || $caller === 'Closure') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
            $caller = ($trace['file'] ?? '') . ':' . ($trace['line'] ?? 0);
        }

        $args = func_get_args();
        array_shift($args); // remove callback

        $key = self::hashKey($caller, $args);

        return self::memo($key, $callback, $ttl);
    }

    /**
     * Clear memo cache manually (useful for long-running scripts).
     */
    public static function clear(): void
    {
        self::$cache = [];
    }
}
