<?php

if (session_status() == PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_name('appSession');
    session_start();
}

chdir(__DIR__);

require_once(__DIR__ . '/app/system/constants.php');
require_once(ROOT_PATH . '/vendor/autoload.php');

use App\Services\SystemService;
use App\Registries\ContainerRegistry;

// Dependency Injection
require_once(APPLICATION_PATH . '/system/di.php');

// Just putting $db and SYSTEM_CONFIG here in case there are
// some old scripts that are still depending on these.
$db = ContainerRegistry::get('db');

defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', ContainerRegistry::get('applicationConfig'));

/** @var SystemService $system */
$system = ContainerRegistry::get(SystemService::class);

$system
    ->bootstrap()
    ->debug(SYSTEM_CONFIG['system']['debug_mode'] ?: false);
