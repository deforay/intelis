<?php

if (session_status() == PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_name('appSession');
    session_start();
}

chdir(__DIR__);

require_once(__DIR__ . '/app/system/constants.php');
require_once(ROOT_PATH . '/vendor/autoload.php');

use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Dependency Injection
require_once(APPLICATION_PATH . '/system/di.php');

// Global functions
require_once(APPLICATION_PATH . '/system/functions.php');

// Just putting $db and SYSTEM_CONFIG here in case there are
// some old scripts that are still depending on these.
$db = ContainerRegistry::get(DatabaseService::class);

defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', ContainerRegistry::get('applicationConfig'));


set_error_handler(function ($severity, $message, $file, $line) {
    $exception = new ErrorException($message, 0, $severity, $file, $line);

    // Check if debug mode is enabled
    if (SYSTEM_CONFIG['system']['debug_mode'] || APPLICATION_ENV === 'development') {
        // In debug mode, log all error levels but only throw exceptions for severe errors
        LoggerUtility::log('error', $exception->getMessage(), ['exception' => $exception]);
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            throw $exception;
        }
    } else {
        // In production mode, log and throw exceptions only for severe errors
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            LoggerUtility::log('error', $exception->getMessage(), ['exception' => $exception]);
            throw $exception;
        }
        // Optionally, log other errors without throwing exceptions
        // LoggerUtility::log('warning', $exception->getMessage(), ['exception' => $exception]);
    }
});

set_exception_handler(function ($exception) {
    LoggerUtility::log('error', $exception->getMessage(), ['exception' => $exception]);
    // Handle the final response for uncaught exceptions here or exit gracefully.
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        LoggerUtility::log('critical', $error['message'], $error);
    }
});


/** @var SystemService $system */
$system = ContainerRegistry::get(SystemService::class);

$system
    ->bootstrap()
    ->debug(SYSTEM_CONFIG['system']['debug_mode'] ?: false);
