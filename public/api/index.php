<?php

require_once dirname(__DIR__) . '/../bootstrap.php';

// public/api/index.php

use Slim\Factory\AppFactory;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use Laminas\Stratigility\MiddlewarePipe;
use App\Middlewares\Api\ApiAuthMiddleware;
use Slim\Middleware\BodyParsingMiddleware;

use function Laminas\Stratigility\middleware;

use Slim\Factory\ServerRequestCreatorFactory;
use App\Middlewares\Api\ApiErrorHandlingMiddleware;
use App\Middlewares\Api\ApiLegacyFallbackMiddleware;

AppFactory::setContainer(ContainerRegistry::getContainer());

$apiService = AppFactory::create();
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();


// Instantiate the middleware pipeline
$middlewarePipe = new MiddlewarePipe();

// CORS Middleware
$middlewarePipe->pipe(middleware(function ($request, $handler) {
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


$middlewarePipe->pipe(new BodyParsingMiddleware());

// Middleware to ensure we always return JSON only
$middlewarePipe->pipe(middleware(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response->withHeader('Content-Type', 'application/json');
}));

// API Auth Middleware that checks for Bearer token
$middlewarePipe->pipe(ContainerRegistry::get(ApiAuthMiddleware::class));

// ErrorHandling Middleware
$middlewarePipe->pipe(ContainerRegistry::get(ApiErrorHandlingMiddleware::class));

//API Routes
$apiService->any('/api/v1.1/init', function ($request, $response, $args) {
    // Start output buffering
    ob_start();
    require_once APPLICATION_PATH . '/api/v1.1/init.php';
    $output = ob_get_clean();

    // Set the output as the response body and set the Content-Type header
    $response->getBody()->write($output);
    return $response;
});

// TODO - Add more routes here
// TODO - Next version API to use Controllers/Actions


// Custom Middleware to set the request in the AppRegistry
$middlewarePipe->pipe(middleware(function ($request, $handler) {
    AppRegistry::set('request', $request);
    return $handler->handle($request);
}));

// Allow existing PHP includes using LegacyFallbackMiddleware
$middlewarePipe->pipe(ContainerRegistry::get(ApiLegacyFallbackMiddleware::class));

// Content Length Middleware
$middlewarePipe->pipe(middleware(function ($request, $handler) {
    $response = $handler->handle($request);
    // Calculate the length of the response body
    $length = strlen((string) $response->getBody());
    return $response->withHeader('Content-Length', (string) $length);
}));

$apiService->add($middlewarePipe);

$apiService->run();
