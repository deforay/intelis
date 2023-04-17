<?php

use App\Models\System;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

chdir(__DIR__);

include(__DIR__ . DIRECTORY_SEPARATOR . 'constants.php');
require_once(ROOT_PATH . '/vendor/autoload.php');


defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', \Laminas\Config\Factory::fromFile(CONFIG_FILE));

// Database Connection
$db = new MysqliDb(SYSTEM_CONFIG['database']);

$debug = false;

if (APPLICATION_ENV === 'development') {
    $debug = true;
}

(new System())
    ->bootstrap()
    ->debug($debug);
