<?php

namespace App\Middlewares\App;

use App\Services\SecurityService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CSRFMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // Generate CSRF token
        SecurityService::generateCSRF($request);

        $currentURI = $request->getUri()->getPath();

        $method = strtoupper($request->getMethod());
        // Check if method is one of the modifying methods
        $modifyingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (
            php_sapi_name() === 'cli' ||
            fnmatch('/remote/remote/*', $currentURI) ||
            fnmatch('/system-admin/*', $currentURI) ||
            fnmatch('/api/*', $currentURI) ||
            !in_array($method, $modifyingMethods) ||
            !isset($_SESSION['csrf_token'])
        ) {
            return $handler->handle($request);
        } else {
            SecurityService::checkCSRF(request: $request);
            return $handler->handle($request);
        }
    }
}
