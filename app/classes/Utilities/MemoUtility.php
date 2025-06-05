<?php

namespace App\Utilities;

use App\Utilities\ApcuCacheUtility;
use App\Registries\ContainerRegistry;

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

    public static function memo(string $key, callable $callback, ?float $ttl = null, bool $crossRequest = true): mixed
    {
        $now = microtime(true);
        $effectiveTtl = $ttl !== null ? (int) ceil($ttl) : 300; // default is 5 mins
        $apcuCache = $crossRequest ? ContainerRegistry::get(ApcuCacheUtility::class) : null;

        if (!isset(self::$cache[$key])) {
            $value = $crossRequest
                ? $apcuCache->get($key, function () use ($callback) {
                    return $callback();
                }, $effectiveTtl)
                : $callback();

            if (count(self::$cache) >= self::MAX_ENTRIES) {
                array_shift(self::$cache); // Remove oldest
            }

            self::$cache[$key] = [
                'value' => $value,
                'timestamp' => $now
            ];
        } elseif ($ttl !== null && ($now - self::$cache[$key]['timestamp']) > $ttl) {
            $value = $callback();
            self::$cache[$key] = [
                'value' => $value,
                'timestamp' => $now
            ];

            if ($crossRequest) {
                $apcuCache->set($key, $value, $effectiveTtl);
            }
        }

        return self::$cache[$key]['value'];
    }

    public static function remember(callable $callback, ?float $ttl = null, bool $crossRequest = true): mixed
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

        return self::memo($key, $callback, $ttl, $crossRequest);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }
}
