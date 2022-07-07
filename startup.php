<?php


// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$domain = '';
if (php_sapi_name() !== 'cli') {
    // base directory
    $base_dir = __DIR__;

    $doc_root = preg_replace("!${_SERVER['SCRIPT_NAME']}$!", '', $_SERVER['SCRIPT_FILENAME']);

    // server protocol
    $protocol = (!empty($_SERVER['HTTPS'])  && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

    // domain name
    $domain = $_SERVER['SERVER_NAME'];

    // base url
    $base_url = preg_replace("!^${doc_root}!", '', $base_dir);

    // server port
    $port = $_SERVER['SERVER_PORT'];
    $disp_port = ($protocol == 'http' && $port == 80 || $protocol == 'https' && $port == 443) ? '' : ":$port";

    // put em all together to get the complete base URL
    $domain = "${protocol}://${domain}${disp_port}${base_url}";
}

defined('DOMAIN')
    || define('DOMAIN', $domain);

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', __DIR__);

defined('UPLOAD_PATH')
    || define('UPLOAD_PATH', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'uploads');

defined('TEMP_PATH')
    || define('TEMP_PATH', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'temporary');

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?
        getenv('APPLICATION_ENV') :
        'production'));

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'vendor'),
    get_include_path()
)));

require_once(APPLICATION_PATH . '/system/system.php');
require_once(APPLICATION_PATH . '/vendor/autoload.php');

$systemConfig = require_once(APPLICATION_PATH . "/configs/config." . APPLICATION_ENV . ".php");
define('SYSTEM_CONFIG', $systemConfig);

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
    $general = new \Vlsm\Models\General();
    $_SESSION['APP_TIMEZONE'] = $general->getGlobalConfig('default_time_zone');
    $_SESSION['APP_TIMEZONE'] = !empty($_SESSION['APP_TIMEZONE']) ? $_SESSION['APP_TIMEZONE'] : 'UTC';
}

date_default_timezone_set($_SESSION['APP_TIMEZONE']);


// $registry = \Vlsm\Utilities\Registry::getInstance();
// \Vlsm\Utilities\Registry::set('db', $db);
// var_dump(\Vlsm\Utilities\Registry::get('db'));die;

// $general = new \Vlsm\Models\General();
// if(isset($_POST) && !empty($_POST)){
//     \Vlsm\Utilities\Registry::set('unescaped', $_POST);
//     $_POST = $general->escape($_POST, MysqliDb::getInstance());
// }
