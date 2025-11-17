<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Middlewares\CorsMiddleware;
use App\Registries\ContainerRegistry;
use App\Middlewares\App\AclMiddleware;
use App\Middlewares\App\CSRFMiddleware;
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


// Create a server request object from the globals
$request = ServerRequestFactory::fromGlobals();

// Instantiate the middleware pipeline
$middlewarePipe = new MiddlewarePipe();

$uri = $request->getUri()->getPath();

$allowedDomains = [];
// Check if the IP addresses are already stored in the session
if (!isset($_SESSION['allowedDomains'])) {

    $host = rtrim($request->getUri()->getScheme() . "://" . $request->getUri()->getHost(), '/');
    $allowedDomains = ["$host:*"];

    $remoteURL = $general->getRemoteURL();

    if (!empty($remoteURL)) {
        $allowedDomains[] = "$remoteURL:*";
    }

    // Wildcard to allow all ports on 127.0.0.1 and localhost
    $allowedDomains[] = "http://127.0.0.1:*";
    $allowedDomains[] = "http://localhost:*";
    $allowedDomains[] = "https://127.0.0.1:*";
    $allowedDomains[] = "https://localhost:*";

    // Store the allowed domains in the session
    $_SESSION['allowedDomains'] = $allowedDomains;
} else {
    // Retrieve the allowed domains from the session
    $allowedDomains = $_SESSION['allowedDomains'];
}

$allowedDomains = implode(" ", $allowedDomains);

//$csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self' $allowedDomains;  img-src 'self' data: blob: $allowedDomains; font-src 'self'; object-src 'none'; frame-src 'self'; base-uri 'self'; form-action 'self';";
$csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self' $allowedDomains; img-src 'self' data: blob: $allowedDomains; font-src 'self'; object-src 'none'; frame-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self';";


$middlewarePipe->pipe(middleware(function ($request, $handler) use ($csp) {
    $response = $handler->handle($request);
    $response = $response->withAddedHeader('Content-Security-Policy', $csp);
    $response = $response->withAddedHeader('X-Frame-Options', 'SAMEORIGIN');
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

// Custom Middleware to set the current request in the AppRegistry
$middlewarePipe->pipe(middleware(function ($request, $handler) {

    $uri = $request->getUri();
    $path = $uri->getPath();
    $queryString = $uri->getQuery();
    // Clean up the URI Path for double slashes or dots
    $path = preg_replace('/([\\/\\.])\\1+/', '$1', (string) $path);
    $currentURI = $path . ($queryString ? "?$queryString" : '');

    AppRegistry::set('currentRequestBaseName', basename($path));
    AppRegistry::set('currentRequestURI', $currentURI);

    AppRegistry::set('request', $request);
    return $handler->handle($request);
}));

// Auth Middleware
// Check if the request is for the system admin or not
if (fnmatch('/system-admin*', $uri)) {
    // System Admin Authentication Middleware
    $middlewarePipe->pipe(ContainerRegistry::get(SystemAdminAuthMiddleware::class));
} else {
    // For the rest of the requests, apply AppAuthMiddleware
    $middlewarePipe->pipe(ContainerRegistry::get(AppAuthMiddleware::class));
}

// CSRF Middleware
$middlewarePipe->pipe(ContainerRegistry::get(CSRFMiddleware::class));

// ACL Middleware
$middlewarePipe->pipe(ContainerRegistry::get(AclMiddleware::class));

// Identify the requested page or resource
$middlewarePipe->pipe(new RequestHandlerMiddleware(ContainerRegistry::get(LegacyRequestHandler::class)));

// Handle the request and generate the response
$response = $middlewarePipe->handle($request);

//Emit the response
$emitter = new SapiEmitter();
$emitter->emit($response);
