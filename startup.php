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
    // if (empty($_SESSION['csrf'])) {
    //     $_SESSION['csrf'] = bin2hex(random_bytes(32));
    // }    
}

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

    defined('DOMAIN')
        || define('DOMAIN', $domain);
}

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
defined('COVID_19_REPORT_PATH')
    || define('COVID_19_REPORT_PATH', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'covid-19/results/pdf');

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'vendor'),
    realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'models'),
    get_include_path()
)));


require_once('autoload.php');
require_once(APPLICATION_PATH . "/configs/config." . APPLICATION_ENV . ".php");

// Let us create database object
$db = new MysqliDb(array(
    'host' => $systemConfig['dbHost'],
    'username' => $systemConfig['dbUser'],
    'password' => $systemConfig['dbPassword'],
    'db' =>  $systemConfig['dbName'],
    'port' => (!empty($systemConfig['dbPort']) ? $systemConfig['dbPort'] : 3306),
    'charset' => (!empty($systemConfig['dbCharset']) ? $systemConfig['dbCharset'] : 'utf8mb4')
));

if (empty($_SESSION['APP_TIMEZONE'])) {
    $general = new \Vlsm\Models\General();
    $_SESSION['APP_TIMEZONE'] = $general->getGlobalConfig('default_time_zone');
    $_SESSION['APP_TIMEZONE'] = !empty($_SESSION['APP_TIMEZONE']) ? $_SESSION['APP_TIMEZONE'] : 'UTC';
}

date_default_timezone_set($_SESSION['APP_TIMEZONE']);
