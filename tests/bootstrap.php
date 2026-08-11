<?php

declare(strict_types=1);

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
