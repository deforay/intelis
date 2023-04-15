<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


use Laminas\Config\Config;


/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(__DIR__);

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
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?
        getenv('APPLICATION_ENV') :
        'production'));

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(__DIR__ . '/vendor'),
    get_include_path()
)));

require_once(APPLICATION_PATH . '/system/system.php');
require_once(ROOT_PATH . '/vendor/autoload.php');

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

defined('CONFIG_FILE') ||
    define('CONFIG_FILE', ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php");

defined('SYSTEM_CONFIG') ||
    define('SYSTEM_CONFIG', (new Config(include(CONFIG_FILE), true))->toArray());

// Database Connection
$db = new MysqliDb(array(
    'host' => SYSTEM_CONFIG['database']['host'],
    'username' => SYSTEM_CONFIG['database']['username'],
    'password' => SYSTEM_CONFIG['database']['password'],
    'db' =>  SYSTEM_CONFIG['database']['name'],
    'port' => (!empty(SYSTEM_CONFIG['database']['port']) ? SYSTEM_CONFIG['database']['port'] : 3306),
    'charset' => (!empty(SYSTEM_CONFIG['database']['charset']) ? SYSTEM_CONFIG['database']['charset'] : 'utf8mb4')
));

// Locale
if (empty($_SESSION['APP_LOCALE'])) {
    $general = new \App\Models\General();
    $_SESSION['APP_LOCALE'] = $general->getGlobalConfig('app_locale');
    $_SESSION['APP_LOCALE'] = !empty($_SESSION['APP_LOCALE']) ? $_SESSION['APP_LOCALE'] : 'en_US';
}

putenv('LC_ALL=' . $_SESSION['APP_LOCALE']);
putenv('LANGUAGE=' . $_SESSION['APP_LOCALE']);
setlocale(LC_ALL,  $_SESSION['APP_LOCALE']);
$domain = "messages";
bindtextdomain($domain, APPLICATION_PATH . DIRECTORY_SEPARATOR . 'locale');
bind_textdomain_codeset($domain, 'UTF-8');
textdomain($domain);

// Timezone
if (empty($_SESSION['APP_TIMEZONE'])) {
    $_SESSION['APP_TIMEZONE'] = $general->getGlobalConfig('default_time_zone');
    $_SESSION['APP_TIMEZONE'] = !empty($_SESSION['APP_TIMEZONE']) ? $_SESSION['APP_TIMEZONE'] : 'UTC';
}

date_default_timezone_set($_SESSION['APP_TIMEZONE']);
