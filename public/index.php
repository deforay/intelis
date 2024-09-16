<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Middlewares\CorsMiddleware;
use App\Registries\ContainerRegistry;
use Laminas\Stratigility\MiddlewarePipe;
use App\HttpHandlers\LegacyRequestHandler;
use App\Middlewares\App\AppAuthMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use Laminas\Diactoros\ServerRequestFactory;
use function Laminas\Stratigility\middleware;
use App\Middlewares\SystemAdminAuthMiddleware;

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$remoteURL = $general->getRemoteURL();

// Create a server request object from the globals
$request = ServerRequestFactory::fromGlobals();

// Instantiate the middleware pipeline
$middlewarePipe = new MiddlewarePipe();

$uri = $request->getUri()->getPath();

$host = $request->getUri()->getHost();

$allowedDomains = [];

if (!empty($remoteURL)) {
    $allowedDomains[] = $remoteURL;
}

$allowedDomains[] = $host;

$csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self'";

// Adding 'blob:' to img-src directive
$imgSrcPolicy = "img-src 'self' data: blob:";

foreach ($allowedDomains as $domain) {
    $csp .= " $domain";
    $imgSrcPolicy .= " $domain";
}

$csp .= "; $imgSrcPolicy; font-src 'self'; object-src 'none'; frame-src 'self'; base-uri 'self'; form-action 'self';";

$middlewarePipe->pipe(middleware(function ($request, $handler) use ($csp) {
    $response = $handler->handle($request);
    $response = $response->withAddedHeader('Content-Security-Policy', $csp);
    return  $response->withAddedHeader('X-Content-Type-Options', 'nosniff');
}));


// Error Handler Middleware
$middlewarePipe->pipe(ContainerRegistry::get(ErrorHandlerMiddleware::class));


// CORS Middleware
// Add CORS Middleware
$middlewarePipe->pipe(new CorsMiddleware([
    "origin" => ["*"], // Allow any origin, or specify a list of allowed origins
    "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"], // Allowed HTTP methods
    "headers.allow" => ["Content-Type", "Authorization", "Accept"], // Allowed request headers
    "headers.expose" => ["*"], // Headers that clients are allowed to access
    "credentials" => false, // Set to true if you want to allow cookies to be sent with CORS requests
    "cache" => 86400, // Cache preflight request for 1 day (in seconds)
]));


// Auth Middleware
// Check if the request is for the system admin or not
if (fnmatch('/system-admin*', $uri)) {
    // System Admin Authentication Middleware
    $middlewarePipe->pipe(ContainerRegistry::get(SystemAdminAuthMiddleware::class));
} else {
    // For the rest of the requests, apply AppAuthMiddleware
    $middlewarePipe->pipe(ContainerRegistry::get(AppAuthMiddleware::class));
}

// ACL Middleware
// TODO: Implement ACL Middleware

// Custom Middleware to set the request in the AppRegistry
$middlewarePipe->pipe(middleware(function ($request, $handler) {
    AppRegistry::set('request', $request);
    return $handler->handle($request);
}));

$middlewarePipe->pipe(new RequestHandlerMiddleware(ContainerRegistry::get(LegacyRequestHandler::class)));

// Handle the request and generate the response
$response = $middlewarePipe->handle($request);

//Emit the response
$emitter = new SapiEmitter();
$emitter->emit($response);
