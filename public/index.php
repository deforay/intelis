<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\ResponseEmitter;

use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\App\AclMiddleware;
use App\Middlewares\App\CSRFMiddleware;
use App\Middlewares\App\AppAuthMiddleware;
use App\Middlewares\SystemAdminAuthMiddleware;
use App\Middlewares\ErrorHandlerMiddleware;
use App\HttpHandlers\LegacyRequestHandler;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * Helper: add middleware in FIFO order while respecting Slim's LIFO execution.
 *
 * @param \Slim\App $app
 * @param array<int, callable|object|string> $stack
 */
function addMiddlewareStack(\Slim\App $app, array $stack): void
{
    // Slim executes last added first → reverse for registration
    foreach (array_reverse($stack) as $middleware) {
        $app->add($middleware);
    }
}

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// ---------------------------------------------------------------------
// Create ServerRequest from globals
// ---------------------------------------------------------------------

$serverRequestCreator = ServerRequestCreatorFactory::create();
/** @var ServerRequestInterface $request */
$request = $serverRequestCreator->createServerRequestFromGlobals();

// ---------------------------------------------------------------------
// Build CSP header
// ---------------------------------------------------------------------

$uriPath = $request->getUri()->getPath();

$allowedDomains = [];
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

    $_SESSION['allowedDomains'] = $allowedDomains;
} else {
    $allowedDomains = $_SESSION['allowedDomains'];
}

$allowedDomainsString = implode(" ", $allowedDomains);

$csp = "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "connect-src 'self' $allowedDomainsString; "
    . "img-src 'self' data: blob: $allowedDomainsString; "
    . "font-src 'self'; "
    . "object-src 'none'; "
    . "frame-src 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'self';";

// ---------------------------------------------------------------------
// Create Slim App
// ---------------------------------------------------------------------

$app = AppFactory::create();

// ---------------------------------------------------------------------
// Routes: catch-all that delegates to LegacyRequestHandler
// ---------------------------------------------------------------------

$legacyHandler = ContainerRegistry::get(LegacyRequestHandler::class);

$app->any('/{path:.*}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($legacyHandler): ResponseInterface {
    // Let the handler decide based on URI
    return $legacyHandler->handle($request);
});

// ---------------------------------------------------------------------
// Middleware stack in FIFO (conceptual) order
// ---------------------------------------------------------------------

$systemAdminAuth = ContainerRegistry::get(SystemAdminAuthMiddleware::class);
$appAuth = ContainerRegistry::get(AppAuthMiddleware::class);

$middlewareStack = [

    // 0) Error Handler Middleware (should wrap everything)
    ContainerRegistry::get(ErrorHandlerMiddleware::class),

    // 1) CSP + security headers
    function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($csp): ResponseInterface {
        $response = $handler->handle($request);
        $response = $response->withAddedHeader('Content-Security-Policy', $csp);
        $response = $response->withAddedHeader('X-Frame-Options', 'SAMEORIGIN');
        return $response->withAddedHeader('X-Content-Type-Options', 'nosniff');
    },

    // 2) CORS Middleware
    new CorsMiddleware([
        "origin" => ["*"],
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "headers.allow" => ["Content-Type", "Authorization", "Accept"],
        "headers.expose" => ["*"],
        "credentials" => false,
        "cache" => 86400,
    ]),

    // 3) Custom middleware to set current request in AppRegistry
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
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
    },

    // 4) Auth Middleware (System Admin vs App)
    function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($systemAdminAuth, $appAuth): ResponseInterface {
        $uri = $request->getUri()->getPath();

        if (fnmatch('/system-admin*', $uri)) {
            return $systemAdminAuth->process($request, $handler);
        }

        return $appAuth->process($request, $handler);
    },

    // 5) CSRF Middleware
    ContainerRegistry::get(CSRFMiddleware::class),

    // 6) ACL Middleware
    ContainerRegistry::get(AclMiddleware::class),
];

// Register with Slim in reverse so execution matches FIFO above
addMiddlewareStack($app, $middlewareStack);

// ---------------------------------------------------------------------
// Handle the request and emit the response
// ---------------------------------------------------------------------

$response = $app->handle($request);

$emitter = new ResponseEmitter();
$emitter->emit($response);
