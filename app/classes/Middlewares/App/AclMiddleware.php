<?php

namespace App\Middlewares\App;

use App\Services\CommonService;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AclMiddleware implements MiddlewareInterface
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();

        // Skip ACL check for AJAX requests (X-Requested-With: XMLHttpRequest)
        // Skip ACL check for system-admin and API URLs
        if (
            CommonService::isAjaxRequest($request) !== false ||
            fnmatch('/system-admin*', $uri) ||
            fnmatch('/api*', $uri)
        ) {
            return $handler->handle($request);
        }

        // ACL Check
        if (false === _isAllowed($request)) {
            throw new SystemException(_translate("Sorry") . " {$_SESSION['userName']}. " . _translate('You do not have permission to access this page or resource.'), 401);
        }

        // Continue processing if the ACL check passed
        return $handler->handle($request);
    }
}
