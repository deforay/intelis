<?php

require_once(dirname(__DIR__) . '/../bootstrap.php');

// api/index.php
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Tuupola\Middleware\CorsMiddleware;
use Slim\Factory\ServerRequestCreatorFactory;

use App\Middleware\ApiAuthMiddleware;
use App\Middleware\LegacyFallbackMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use App\Models\Users;

$container = new Container();
AppFactory::setContainer($container);

$app = AppFactory::create();
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// 1. CORS Middleware
$app->add(new CorsMiddleware([
    'origin' => ['*'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers.allow' => ['Content-Type', 'Authorization'],
    'headers.expose' => [],
    'credentials' => false,
    'cache' => 0,
]));

// 2. API Auth Middleware that checks for Bearer token
$userModel = new Users();
$app->add(new ApiAuthMiddleware($userModel));


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


// 3. Allow existing PHP includes using LegacyFallbackMiddleware
$app->add(new LegacyFallbackMiddleware());


// 4. Always return JSON only
$app->add(function (Request $request, RequestHandlerInterface $handler) {
    $response = $handler->handle($request);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
