<?php

namespace App\Middlewares\App;

use App\Services\CommonService;
use App\Services\SecurityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CSRFMiddleware implements MiddlewareInterface
{
    // Exclude specific routes from CSRF check
    private array $excludedUris = [
        '/remote/*',
        '/system-admin/*',
        '/api/*',
        // Add other routes to exclude from the CSRF check here
    ];
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // Generate CSRF token if not already set
        SecurityService::generateCSRF();

        $currentURI = $request->getUri()->getPath();

        $method = strtoupper($request->getMethod());
        // Check if method is one of the modifying methods
        $modifyingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (
            CommonService::isAjaxRequest($request) ||
            CommonService::isCliRequest() ||
            CommonService::isExcludedUri($currentURI, $this->excludedUris) ||
            !in_array($method, $modifyingMethods) ||
            empty($_SESSION['csrf_token'])
        ) {
            return $handler->handle($request);
        }

        SecurityService::checkCSRF(request: $request);
        return $handler->handle($request);
    }
}
