<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

require_once(__DIR__ . DIRECTORY_SEPARATOR . '/../startup.php');


// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: http://localhost:8100");
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

$_SERVER['REQUEST_URI'] = preg_replace('/([\/.])\1+/', '$1', $_SERVER['REQUEST_URI']);
$requestedPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), "/");

switch ($requestedPath) {
    case null:
    case '':
        require APPLICATION_PATH . '/index.php';
        break;
    default:
        if (is_dir(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath)) {
            require(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath . '/index.php');
        } else if (is_file(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath)) {
            require(APPLICATION_PATH . DIRECTORY_SEPARATOR . $requestedPath);
        } else {
            http_response_code(404);
            require APPLICATION_PATH . '/error/404.php';
        }
        break;
}
