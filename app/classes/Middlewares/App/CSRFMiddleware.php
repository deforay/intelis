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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        $modifyingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        // Check if method is one of the modifying methods
        if (!in_array($method, $modifyingMethods) || !isset($_SESSION['csrf_token'])) {
            return $handler->handle($request);
        } else {
            $currentURI = $request->getUri()->getPath();

            if (
                php_sapi_name() === 'cli' ||
                CommonService::isAjaxRequest($request) !== false ||
                fnmatch('/remote/remote/*', $currentURI) ||
                fnmatch('/system-admin/*', $currentURI) ||
                fnmatch('/api/*', $currentURI)
            ) {
                SecurityService::checkCSRF(request: $request);
            }
            return $handler->handle($request);
        }
    }
}
