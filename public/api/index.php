<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/../bootstrap.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\BodyParsingMiddleware;

use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Middlewares\Api\ApiAuthMiddleware;
use App\Middlewares\Api\ApiErrorHandlingMiddleware;
use App\Middlewares\Api\ApiLegacyFallbackMiddleware;

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

// ---------------------------------------------------------------------
// Create Slim App and attach container
// ---------------------------------------------------------------------

AppFactory::setContainer(ContainerRegistry::getContainer());
$app = AppFactory::create();

// ---------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------

$app->any('/api/v1.1/init', function (ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
    // Start output buffering
    ob_start();
    require_once APPLICATION_PATH . '/api/v1.1/init.php';
    $output = ob_get_clean();

    // Set the output as the response body
    $response->getBody()->write($output);
    return $response;
});

// TODO - Add more routes here
// TODO - Next version API to use Controllers/Actions

// ---------------------------------------------------------------------
// Middleware stack in FIFO (conceptual) order
// ---------------------------------------------------------------------

$responseFactory = $app->getResponseFactory();

$middlewareStack = [

    // 0) Error handling wrapper for the entire stack
    ContainerRegistry::get(ApiErrorHandlingMiddleware::class),

    // 1) CORS (handles OPTIONS and sets CORS headers)
    function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($responseFactory): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        // Preflight request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = $responseFactory->createResponse(204);

            if ($origin !== '') {
                $response = $response
                    ->withHeader('Access-Control-Allow-Origin', $origin)
                    ->withHeader('Access-Control-Allow-Credentials', 'true')
                    ->withHeader('Access-Control-Max-Age', '86400');
            }

            $reqMethod = $request->getHeaderLine('Access-Control-Request-Method');
            $reqHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

            if ($reqMethod !== '') {
                $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            }

            if ($reqHeaders !== '') {
                $response = $response->withHeader('Access-Control-Allow-Headers', $reqHeaders);
            }

            return $response;
        }

        // Normal request: let it pass through, then add headers
        $response = $handler->handle($request);

        if ($origin !== '') {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Max-Age', '86400');
        }

        return $response;
    },

    // 2) Body parsing (JSON, form, etc.)
    new BodyParsingMiddleware(),

    // 3) Always return JSON content-type
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('Content-Type', 'application/json');
    },

    // 4) API Auth Middleware (Bearer token, etc.)
    ContainerRegistry::get(ApiAuthMiddleware::class),

    // 5) Custom middleware to set the request in the AppRegistry
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        AppRegistry::set('request', $request);
        return $handler->handle($request);
    },

    // 6) Legacy fallback for existing PHP includes
    ContainerRegistry::get(ApiLegacyFallbackMiddleware::class),

    // 7) Content-Length Middleware
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $response = $handler->handle($request);

        // Calculate the length of the response body
        $length = strlen((string) $response->getBody());
        return $response->withHeader('Content-Length', (string) $length);
    },
];

// Register with Slim in reverse so execution matches FIFO above
addMiddlewareStack($app, $middlewareStack);

// ---------------------------------------------------------------------
// Run the app (Slim creates the ServerRequest from globals internally)
// ---------------------------------------------------------------------

$app->run();
