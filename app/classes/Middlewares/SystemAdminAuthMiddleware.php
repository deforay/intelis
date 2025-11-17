<?php

namespace App\Middlewares;

use Override;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;

class SystemAdminAuthMiddleware implements MiddlewareInterface
{
    protected array $excludedUris = [
        '/system-admin/login/login.php',
        '/system-admin/login/adminLoginProcess.php',
        '/system-admin/setup/index.php',
        '/system-admin/setup/registerProcess.php',
        // Add other routes to exclude from the authentication check here
    ];
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Get the requested URI
        $uri = $request->getUri()->getPath();

        // Clean up the URI
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);

        if (
            !isset($_SESSION['userId']) && !isset($_SESSION['requestedURI']) &&
            strtolower($request->getHeaderLine('X-Requested-With')) !== 'xmlhttprequest'
        ) {
            $_SESSION['systemAdminrequestedURI'] = AppRegistry::get('currentRequestURI');
        }

        $redirect = null;
        if ($this->shouldExcludeFromAuthCheck($request)) {

            // Skip the authentication check if the request is an AJAX request,
            // a CLI request, or if the requested URI is excluded from the
            // authentication check
            return $handler->handle($request);
        } elseif (empty($_SESSION['adminUserId'])) {
            if (CommonService::isAjaxRequest($request)) {
                return new JsonResponse(['error' => 'session_expired'], 401);
            }
            // Redirect to the login page if the system user is not logged in
            $redirect = new RedirectResponse('/system-admin/login/login.php');
        }

        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        } else {
            return $handler->handle($request);
        }
    }

    private function shouldExcludeFromAuthCheck(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri()->getPath();
        if (CommonService::isCliRequest()) {
            return true;
        }

        if (CommonService::isAjaxRequest($request) && CommonService::isSameOriginRequest($request) === false) {
            throw new SystemException(_translate('Invalid request origin.'), 403);
        }
        return CommonService::isExcludedUri($uri, $this->excludedUris ?? []);
    }
}
