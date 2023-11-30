<?php

namespace App\Registries;

class AppRegistry
{
    /**
     * The instance of the AppRegistry.
     *
     * @var AppRegistry
     */
    private static AppRegistry $instance;

    /**
     * The array of stored values.
     *
     * @var array
     */
    private $values = [];

    /**
     * Gets the instance of the AppRegistry.
     *
     * @return AppRegistry
     */
    public static function getInstance(): AppRegistry
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Stores a value in the registry.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * Gets a value from the registry.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * AppRegistry constructor.
     */
    protected function __construct()
    {
    }

    /**
     * Prevent the instance from being cloned.
     */
    private function __clone()
    {
    }

    /**
     * Prevent the instance from being unserialized.
     */
    private function __wakeup()
    {
    }
}
