<?php

namespace App\Utilities;

class MiscUtility
{
    public function __construct()
    {
    }

    public static function randomHexColor(): string
    {
        $hexColorPart = function () {
            return str_pad(dechex(random_int(0, 255)), 2, '0', STR_PAD_LEFT);
        };

        return strtoupper($hexColorPart() . $hexColorPart() . $hexColorPart());
    }

    public function removeDirectory($dirname): bool
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }

        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        // Loop through the folder
        $dir = dir($dirname);
        while (false !== ($entry = $dir->read())) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Recurse
            $this->removeDirectory($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }

    //dump the contents of a variable to the error log in a readable format
    public static function errorLog($object = null): void
    {
        ob_start();
        var_dump($object);
        error_log(ob_get_clean());
    }
}
