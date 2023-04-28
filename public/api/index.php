<?php

require_once(dirname(__DIR__) . '/../bootstrap.php');

// api/index.php
use DI\Container;
use Slim\Factory\AppFactory;
use App\Services\UserService;
use App\Middleware\Api\ApiAuthMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use App\Middleware\Api\LegacyFallbackMiddleware;

use function Laminas\Stratigility\middleware;

use Slim\Factory\ServerRequestCreatorFactory;


$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();


// Instantiate the middleware pipeline
$middlewarePipe = new MiddlewarePipe();

// 1. CORS Middleware

$middlewarePipe->pipe(middleware(function ($request, $handler) {
    session_destroy();
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        }

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        exit(0);
    }
    return $handler->handle($request);
}));


// 2. Middleware to ensure we always return JSON only
$middlewarePipe->pipe(middleware(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response->withHeader('Content-Type', 'application/json');
}));

// 3. API Auth Middleware that checks for Bearer token
$userModel = new UserService();
$middlewarePipe->pipe(new ApiAuthMiddleware($userModel));

//API Routes
$app->any('/api/v1.1/init', function ($request, $response, $args) {
    // Start output buffering
    ob_start();
    require APPLICATION_PATH . '/api/v1.1/init.php';
    $output = ob_get_clean();

    // Set the output as the response body and set the Content-Type header
    $response->getBody()->write($output);
    return $response;
});

// TODO - Add more routes here
// TODO - Next version API to use Controllers/Actions


// 4. Allow existing PHP includes using LegacyFallbackMiddleware
$middlewarePipe->pipe(new LegacyFallbackMiddleware());




$app->add($middlewarePipe);


$app->run();
