<?php

namespace App\Registries;

class AppRegistry
{
    private static ?AppRegistry $instance = null;
    private static array $items = [];

    public static function getInstance(): AppRegistry
    {
        if (null === static::$instance) {
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
        return self::$items[$key] ?? null;
    }

    public function __construct()
    {
        if (self::$instance) {
            throw new \Exception("Cannot instantiate a singleton.");
        }
        self::$instance = $this;
    }

    public function __clone()
    {
        throw new \Exception("Cannot clone a singleton.");
    }
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}
