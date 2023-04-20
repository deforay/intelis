<?php

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php');

use App\Middleware\ApiMiddleware;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Stratigility\MiddlewarePipe;
use App\RequestHandler as LegacyRequestHandler;
use App\Middleware\AuthMiddleware;
use App\Middleware\SystemAdminMiddleware;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Tuupola\Middleware\CorsMiddleware;



// Create a server request object from the globals
$request = ServerRequestFactory::fromGlobals();


// Instantiate the middleware pipeline
$middlewarePipe = new MiddlewarePipe();


// 1. CORS Middleware
$middlewarePipe->pipe(new CorsMiddleware([
    "origin" => ["*"], // Allow any origin, or specify a list of allowed origins
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], // Allowed HTTP methods
    "headers.allow" => ["Content-Type", "Authorization", "Accept"], // Allowed request headers
    "headers.expose" => ["*"], // Headers that clients are allowed to access
    "credentials" => false, // Set to true if you want to allow cookies to be sent with CORS requests
    "cache" => 86400, // Cache preflight request for 1 day (in seconds)
]));


// 2. Auth Middleware
$uri = $request->getUri()->getPath();

// 2.1 Only apply AuthMiddleware if the request is not for /api or /system-admin
if (strpos($uri, '/api') !== 0 && strpos($uri, '/system-admin') !== 0) {
    $middlewarePipe->pipe(new AuthMiddleware());
}

// 2.2 API and System Admin middleware
if (strpos($uri, '/api') === 0) {
    $middlewarePipe->pipe(new ApiMiddleware());
} elseif (strpos($uri, '/system-admin') === 0) {
    $middlewarePipe->pipe(new SystemAdminMiddleware());
}

// 3. ACL Middleware

$middlewarePipe->pipe(new RequestHandlerMiddleware(new LegacyRequestHandler()));


// Handle the request and emit the response
$response = $middlewarePipe->handle($request);
$emitter = new SapiEmitter();
$emitter->emit($response);
