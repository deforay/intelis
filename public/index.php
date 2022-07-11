<?php

// use Laminas\Diactoros\ServerRequestFactory;
// use Vlsm\Utilities\Registry;

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_string($path) && __FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

require_once(__DIR__ . DIRECTORY_SEPARATOR . '/../startup.php');

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


// $serverRequestObject = ServerRequestFactory::fromGlobals();

// Registry::set('request', $serverRequestObject);

$requestURI = trim($_SERVER['REQUEST_URI'], "/");
$requestedPath = explode("?", $requestURI);


switch ($requestedPath[0]) {
    case '/':
        require APPLICATION_PATH . '/index.php';
        break;
    case '':
        require APPLICATION_PATH . '/index.php';
        break;
    default:
        if (file_exists(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath[0]) && is_file(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath[0])) {
            require(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath[0]);
        } else {
            require APPLICATION_PATH . '/error/404.php';
        }
        break;
}
