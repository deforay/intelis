<?php

namespace App\Registries;

use Exception;
use ArrayObject;

class AppRegistry extends ArrayObject
{
    private static $appRegistry = null;

    public function __construct($array = array(), $flags = parent::ARRAY_AS_PROPS)
    {
        parent::__construct($array, $flags);
    }

    public static function setInstance(AppRegistry $registry)
    {
        if (self::$appRegistry !== null) {
            throw new Exception('Registry is already initialized');
        }
        self::$appRegistry = $registry;
    }

    public static function getInstance()
    {
        if (self::$appRegistry === null) {
            self::init();
        }
        return self::$appRegistry;
    }

    protected static function init()
    {
        self::setInstance(new self());
    }

    public static function set($index, $value)
    {
        $instance = self::getInstance();
        $instance->offsetSet($index, $value);
    }

    public static function get($index)
    {
        $instance = self::getInstance();

        if (!$instance->offsetExists($index)) {
            throw new Exception("No entry is registered for key '$index'");
        }

        return $instance->offsetGet($index);
    }
}
