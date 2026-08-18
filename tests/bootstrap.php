<?php

declare(strict_types=1);

// A bootstrap both defines constants and requires files by definition, which is the one
// thing PSR1's side-effects rule asks a file not to do. The rule is right about ordinary
// files and cannot be satisfied by this one.
// phpcs:disable PSR1.Files.SideEffects

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The application's constants -- sample statuses, country identifiers -- are plain
// namespaced consts rather than class constants, so the autoloader never sees them and
// any code path referencing one fatals in a unit test. Loading them here means a test
// can drive real service code instead of steering around it.
//
// constants.php resolves CORE\SYSADMIN_SECRET_KEY_FILE against VAR_PATH at load time, so
// that has to exist first. It points at a throwaway directory: nothing under test writes
// there, and a test that did would be reaching further than a unit test should.
if (!defined('VAR_PATH')) {
    define('VAR_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'intelis-tests-var');
}
if (!is_dir(VAR_PATH)) {
    mkdir(VAR_PATH, 0777, true);
}

require_once dirname(__DIR__) . '/app/system/constants.php';

// The app's translation helpers live in app/system/functions.php, which pulls in the
// whole framework on load. A unit test only needs _translate() to hand back the string
// it was given, so it is stubbed here rather than dragging the framework into the suite.
// Guarded, so if the real one is ever loaded first it wins.
if (!function_exists('_translate')) {
    function _translate(?string $text, bool|string $escapeTextOrContext = false): string
    {
        return (string) $text;
    }
}
