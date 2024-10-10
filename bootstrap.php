<?php

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {

    session_name('appSession');

    // Set cookie parameters before starting the session
    session_set_cookie_params([
        'path' => '/',    // Available in entire domain
        'domain' => $_SERVER['HTTP_HOST'], // Default to current domain
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Only set secure flag if HTTPS is enabled
        'httponly' => true, // Only accessible via HTTP protocol, not JavaScript
        'samesite' => 'Lax' // Strict or Lax
    ]);

    session_start();
}

use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

// Application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

// Application paths
chdir(__DIR__);

defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__)));

const WEB_ROOT          = ROOT_PATH . DIRECTORY_SEPARATOR . 'public';
const CACHE_PATH        = ROOT_PATH . DIRECTORY_SEPARATOR . 'cache';
const APPLICATION_PATH  = ROOT_PATH . DIRECTORY_SEPARATOR . 'app';
const UPLOAD_PATH       = WEB_ROOT  . DIRECTORY_SEPARATOR . 'uploads';
const TEMP_PATH         = WEB_ROOT  . DIRECTORY_SEPARATOR . 'temporary';

// Set up autoloading
require_once ROOT_PATH . '/vendor/autoload.php';

// Load constants
require_once __DIR__ . '/app/system/constants.php';

// Load system version
require_once __DIR__ . '/app/system/version.php';

// Dependency Injection
require_once __DIR__ . '/app/system/di.php';

// Global functions
require_once __DIR__ . '/app/system/functions.php';

// Just putting $db and SYSTEM_CONFIG here in case there are
// some old scripts that are still depending on these.
$db = ContainerRegistry::get(DatabaseService::class);

// Generate CSRF token
SecurityService::generateCSRF();

defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', ContainerRegistry::get('applicationConfig'));


set_error_handler(function ($severity, $message, $file, $line) {
    $exception = new ErrorException($message, 0, $severity, $file, $line);
    $trace = debug_backtrace();

    // Check if debug mode is enabled
    if (SYSTEM_CONFIG['system']['debug_mode'] || APPLICATION_ENV === 'development') {
        // In debug mode, log all error levels but only throw exceptions for severe errors
        LoggerUtility::log('error', $exception->getMessage(), [
            'exception' => $exception,
            'trace' => $trace
        ]);
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            throw $exception;
        }
    } else {
        // In production mode, log and throw exceptions only for severe errors
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            LoggerUtility::log('error', $exception->getMessage(), [
                'exception' => $exception,
                'trace' => $trace
            ]);
            throw $exception;
        }
        // Optionally, log other errors without throwing exceptions
        // LoggerUtility::log('warning', $exception->getMessage(), ['exception' => $exception]);
    }
});

set_exception_handler(function ($exception) {
    LoggerUtility::logError($exception->getMessage(), [
        'exception' => $exception,
        'trace' => $exception->getTraceAsString()
    ]);
    // Handle the final response for uncaught exceptions here or exit gracefully.
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        LoggerUtility::log('critical', $error['message'], [
            'error' => $error,
            'trace' => debug_backtrace()
        ]);
    }
});


/** @var SystemService $system */
$system = ContainerRegistry::get(SystemService::class);

$system
    ->bootstrap()
    ->debug(SYSTEM_CONFIG['system']['debug_mode'] ?: false);
