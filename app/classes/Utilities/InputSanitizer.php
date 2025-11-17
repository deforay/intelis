<?php

namespace App\Utilities;

use HTMLPurifier;
use HTMLPurifier_Config;

final class InputSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Allowed', '');
            $config->set('Cache.DefinitionImpl', null);
            self::$purifier = new HTMLPurifier($config);
        }

        return self::$purifier;
    }

    public static function clean(?string $value): string
    {
        if($value === null) {
            return '';
        }
        return self::purifier()->purify($value);
    }
}
