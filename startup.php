<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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


define('SYSTEM_CONFIG', require_once(ROOT_PATH . "/configs/config." . APPLICATION_ENV . ".php"));

// Database Connection
$db = new MysqliDb(array(
    'host' => SYSTEM_CONFIG['dbHost'],
    'username' => SYSTEM_CONFIG['dbUser'],
    'password' => SYSTEM_CONFIG['dbPassword'],
    'db' =>  SYSTEM_CONFIG['dbName'],
    'port' => (!empty(SYSTEM_CONFIG['dbPort']) ? SYSTEM_CONFIG['dbPort'] : 3306),
    'charset' => (!empty(SYSTEM_CONFIG['dbCharset']) ? SYSTEM_CONFIG['dbCharset'] : 'utf8mb4')
));

// Locale
if (empty($_SESSION['APP_LOCALE'])) {
    $general = new \Vlsm\Models\General();
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