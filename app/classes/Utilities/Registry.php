<?php

namespace App\Utilities;

use Exception;
use ArrayObject;

class Registry extends ArrayObject
{
    private static $_registry = null;

    public function __construct($array = array(), $flags = parent::ARRAY_AS_PROPS)
    {
        parent::__construct($array, $flags);
    }

    public static function setInstance(Registry $registry)
    {
        if (self::$_registry !== null) {
            throw new Exception('Registry is already initialized');
        }
        self::$_registry = $registry;
    }

    public static function getInstance()
    {
        if (self::$_registry === null) {
            self::init();
        }
        return self::$_registry;
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
