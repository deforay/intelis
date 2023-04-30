<?php

if (session_status() == PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_name('appSession');
    session_start();
}

chdir(__DIR__);

require_once(__DIR__ . '/app/system/constants.php');
require_once(ROOT_PATH . '/vendor/autoload.php');


use App\Services\SystemService;
use Laminas\Config\Factory;


$configFile = ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php";
if (!file_exists($configFile)) {
    $configFile = ROOT_PATH . "/configs/config.production.php";
}
defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', Factory::fromFile($configFile));
// Database Connection
$db = new MysqliDb(SYSTEM_CONFIG['database']);

$debugMode = SYSTEM_CONFIG['system']['debug_mode'] ?? false;

(new SystemService())
    ->bootstrap()
    ->debug($debugMode);
