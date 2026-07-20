<?php

declare(strict_types=1);

// Upgrade-in-progress bounce. Must run before any require so a half-replaced
// codebase cannot be loaded. Marker files live at public/.maintenance (one dir
// up from public/api/) and are managed by scripts/upgrade.sh --maintenance.
if (is_file(dirname(__DIR__) . '/.maintenance')) {
    http_response_code(503);
    header('Retry-After: 120');
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'maintenance',
        'message' => 'Upgrade in progress. Please retry in a moment.',
        'retryAfter' => 120,
    ]);
    exit;
}

require_once dirname(__DIR__) . '/../bootstrap.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Routing\RouteCollectorProxy;

use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Middlewares\Api\ApiAuthMiddleware;
use App\Middlewares\Api\ApiErrorHandlingMiddleware;
use App\Middlewares\Api\ApiLegacyFallbackMiddleware;
use App\HttpHandlers\InterfaceApi\ActivateInstallationHandler;
use App\HttpHandlers\InterfaceApi\GetConnectionHandler;
use App\HttpHandlers\InterfaceApi\SubmitResultsHandler;
use App\Middlewares\Api\InterfaceRequestGuardMiddleware;
use App\Middlewares\Api\InterfaceApiEnabledMiddleware;
use App\Middlewares\Api\InterfaceInstallationAuthMiddleware;
use App\Services\InterfaceApi\InterfaceInstallationService;

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

$app->any(
    '/api/v1.1/init',
    function (ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        // Start output buffering
        ob_start();
        require_once APPLICATION_PATH . '/api/v1.1/init.php';
        $output = ob_get_clean();

        // Set the output as the response body
        $response->getBody()->write($output);
        return $response;
    }
);

$interfaceApi = $app->group('/api/v1/interface', function (RouteCollectorProxy $group): void {
    $group->post('/activate', ActivateInstallationHandler::class);
    $group->get('/connection', GetConnectionHandler::class)
        ->add(new InterfaceInstallationAuthMiddleware(
            ContainerRegistry::get(InterfaceInstallationService::class),
            'connection:read'
        ));
    $group->post('/results', SubmitResultsHandler::class)
        ->add(new InterfaceInstallationAuthMiddleware(
            ContainerRegistry::get(InterfaceInstallationService::class),
            'results:write'
        ));
});
$interfaceApi->add(ContainerRegistry::get(InterfaceApiEnabledMiddleware::class));

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
    function (
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ) use ($responseFactory): ResponseInterface {
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
    ContainerRegistry::get(InterfaceRequestGuardMiddleware::class),

    // 3) Body parsing (JSON, form, etc.)
    new BodyParsingMiddleware(),

    // 4) Always return JSON content-type
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $response = $handler->handle($request);
        return $response->withHeader('Content-Type', 'application/json');
    },

    // 5) API Auth Middleware (Bearer token, etc.)
    ContainerRegistry::get(ApiAuthMiddleware::class),

    // 6) Custom middleware to set the request in the AppRegistry
    function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        AppRegistry::set('request', $request);
        return $handler->handle($request);
    },

    // 7) Legacy fallback for existing PHP includes
    ContainerRegistry::get(ApiLegacyFallbackMiddleware::class),

    // 8) Content-Length Middleware
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
