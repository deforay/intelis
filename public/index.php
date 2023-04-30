<?php

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php');


use App\Middlewares\App\AppAuthMiddleware;
use Tuupola\Middleware\CorsMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Laminas\Diactoros\ServerRequestFactory;
use App\Middlewares\SystemAdminAuthMiddleware;
use App\HttpHandlers\LegacyRequestHandler;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;


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
// Check if the request is for the system admin or not
$uri = $request->getUri()->getPath();
if (strpos($uri, '/system-admin') === 0) {
    // System Admin Authentication Middleware
    $middlewarePipe->pipe(new SystemAdminAuthMiddleware());
} else {
    // For the rest of the requests, apply AppAuthMiddleware
    $middlewarePipe->pipe(new AppAuthMiddleware());
}


// 3. ACL Middleware
// TODO: Implement ACL Middleware

$middlewarePipe->pipe(new RequestHandlerMiddleware(new LegacyRequestHandler()));


// Handle the request and generate the response
$response = $middlewarePipe->handle($request);

//Emit the response
$emitter = new SapiEmitter();
$emitter->emit($response);
