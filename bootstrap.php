<?php

use App\Models\System;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

chdir(__DIR__);

include(__DIR__ . DIRECTORY_SEPARATOR . 'constants.php');
require_once(ROOT_PATH . '/vendor/autoload.php');


$configFile = ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php";
if (!file_exists($configFile)) {
    $configFile = ROOT_PATH . "/configs/config.production.php";
}
defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', \Laminas\Config\Factory::fromFile($configFile));

// Database Connection
$db = new MysqliDb(SYSTEM_CONFIG['database']);

$debugMode = SYSTEM_CONFIG['system']['debug_mode'] ?: false;

(new System())
    ->bootstrap()
    ->debug($debugMode);