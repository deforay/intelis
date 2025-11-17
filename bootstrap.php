<?php

ini_set('expose_php', 0);
ini_set('session.use_strict_mode', 1);


// Application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');


defined('INTELIS_SESSION_NAME')
    || define('INTELIS_SESSION_NAME', 'appSessionv2');


if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name(INTELIS_SESSION_NAME);

    // Smart secure detection: also works behind proxies
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '0')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // Set cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,          // Session cookie, expires on browser close
        'path' => '/',            // Available throughout the domain
        'secure' => $isSecure,    // Only send cookie over HTTPS
        'httponly' => true,       // JS cannot access session cookie
        'samesite' => 'Lax'       // Lax is perfect for login forms and redirects
    ]);

    if (!session_start()) {
        throw new Exception('Failed to start session');
    }
}


use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Application paths
chdir(__DIR__);

defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__)));

define('WEB_ROOT', ROOT_PATH . '/public');
define('CONFIG_PATH', ROOT_PATH . '/configs');
define('VAR_PATH', ROOT_PATH . '/var');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('CACHE_PATH', VAR_PATH . '/cache');
define('LOG_PATH', VAR_PATH . '/logs');
define('APPLICATION_PATH', ROOT_PATH . '/app');
define('BIN_PATH', ROOT_PATH . '/bin');
define('UPLOAD_PATH', WEB_ROOT . '/uploads');
define('TEMP_PATH', WEB_ROOT . '/temporary');

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


defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', ContainerRegistry::get('applicationConfig'));


$debugMode = !empty(SYSTEM_CONFIG['system']['debug_mode'] ?? false);

define(
    'LOG_LEVEL',
    (APPLICATION_ENV === 'development' || $debugMode === true) ? 'DEBUG' : 'INFO'
);



if (APPLICATION_ENV === 'production' && $debugMode !== true) {
    ini_set('display_errors', 0); // Never display errors in production
    ini_set('log_errors', 1);     // Always log them instead
}

// Just putting $db here in case there are
// some old scripts that are still depending on this variable being available.
$db = ContainerRegistry::get(DatabaseService::class);

set_error_handler(function ($severity, $message, $file, $line) use ($debugMode) {
    $exception = new ErrorException($message, 0, $severity, $file, $line);
    $trace = debug_backtrace();

    // Check if debug mode is enabled
    if ($debugMode === true || APPLICATION_ENV === 'development') {
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
    ->debug($debugMode);
