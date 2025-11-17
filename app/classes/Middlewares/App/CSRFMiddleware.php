<?php

namespace App\Middlewares\App;

use Override;
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
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // Generate CSRF token if not already set
        SecurityService::generateCSRF();

        $currentURI = $request->getUri()->getPath();

        $method = strtoupper($request->getMethod());
        // Check if method is one of the modifying methods
        $modifyingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (
            CommonService::isCliRequest() ||
            $this->isExcludedUri($currentURI) ||
            !in_array($method, $modifyingMethods) ||
            !isset($_SESSION['csrf_token'])
        ) {
            return $handler->handle($request);
        } else {
            SecurityService::checkCSRF(request: $request);
            return $handler->handle($request);
        }
    }
    // Helper function to check if the current URI is in the excluded list
    protected function isExcludedUri(string $uri): bool
    {
        foreach ($this->excludedUris as $excludedUri) {
            if (fnmatch($excludedUri, $uri)) {
                return true;
            }
        }
        return false;
    }
}
