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



// Create a server request object from the globals
$request = ServerRequestFactory::fromGlobals();


// Instantiate the middleware pipeline
$middlewarePipe = new MiddlewarePipe();

// Add middleware

$uri = $request->getUri()->getPath();

// Only apply AuthMiddleware if the request is not for /api or /system-admin
if (strpos($uri, '/api') !== 0 && strpos($uri, '/system-admin') !== 0) {
    $middlewarePipe->pipe(new AuthMiddleware());
}

// API and System Admin middleware
if (strpos($uri, '/api') === 0) {
    $middlewarePipe->pipe(new ApiMiddleware());
} elseif (strpos($uri, '/system-admin') === 0) {
    $middlewarePipe->pipe(new SystemAdminMiddleware());
}

$middlewarePipe->pipe(new RequestHandlerMiddleware(new LegacyRequestHandler()));


// Handle the request and emit the response
$response = $middlewarePipe->handle($request);
$emitter = new SapiEmitter();
$emitter->emit($response);
