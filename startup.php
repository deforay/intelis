<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(__DIR__);

// Setup Application Constants
defined('ROOT_PATH')
    || define('ROOT_PATH', realpath(dirname(__FILE__)));

defined('WEB_ROOT')
    || define('WEB_ROOT', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');

defined('UPLOAD_PATH')
    || define('UPLOAD_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads');

defined('TEMP_PATH')
    || define('TEMP_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary');

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');


require_once(APPLICATION_PATH . '/system/system.php');
require_once(ROOT_PATH . '/vendor/autoload.php');


defined('CONFIG_FILE') ||
    define('CONFIG_FILE', ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php");

defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', \Laminas\Config\Factory::fromFile(CONFIG_FILE));

// Database Connection
$db = new MysqliDb(SYSTEM_CONFIG['database']);

$general = new \App\Models\General($db);
$system = new \App\Models\System($db);

// Setup Locale and Translation

$system->setupTranslation();

// Setup Timezone
$_SESSION['APP_TIMEZONE'] = $_SESSION['APP_TIMEZONE'] ??
    $general->getGlobalConfig('default_time_zone') ?? 'UTC';
date_default_timezone_set($_SESSION['APP_TIMEZONE']);


if (APPLICATION_ENV === 'development') {

    $whoops = new Whoops\Run;

    // We want the error page to be shown by default, if this is a
    // regular request, so that's the first thing to go into the stack:
    $whoops->pushHandler(new Whoops\Handler\PrettyPageHandler);

    // Now, we want a second handler that will run before the error page,
    // and immediately return an error message in JSON format, if something
    // goes awry.
    if (Whoops\Util\Misc::isAjaxRequest()) {
        $jsonHandler = new Whoops\Handler\JsonResponseHandler;

        // You can also tell JsonResponseHandler to give you a full stack trace:
        // $jsonHandler->addTraceToOutput(true);

        // Return a result compliant to the json:api spec
        // re: http://jsonapi.org/examples/#error-objects
        // tl;dr: error[] becomes errors[[]]
        $jsonHandler->setJsonApi(true);
        $whoops->pushHandler($jsonHandler);
    }
    $whoops->register();
}
