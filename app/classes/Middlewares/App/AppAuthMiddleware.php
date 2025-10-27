<?php

namespace App\Middlewares\App;

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class AppAuthMiddleware implements MiddlewareInterface
{

    // Exclude specific routes from authentication check
    private array $excludedUris = [
        '/includes/captcha.php',
        '/users/edit-profile-helper.php',
        '/remote/*',
        '/setup/*',
        '/login/*',
        '/tasks/*'
        // Add other routes to exclude from the authentication check here
    ];
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        // Get the requested URI
        $uri = $request->getUri()->getPath();

        // Clean up the URI
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);

        $redirect = null;
        if ($this->shouldExcludeFromAuthCheck($request)) {

            // Skip the authentication check if the request is an AJAX request,
            // a CLI request, or if the requested URI is excluded from the
            // authentication check
            return $handler->handle($request);
        } elseif (empty($_SESSION['userId'])) {
            // Redirect to the login page if the user is not logged in
            $redirect = new RedirectResponse('/login/login.php');
        } elseif (isset($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {

            // Redirect to the edit profile page if the user is logged in but needs to change their password
            $_SESSION['alertMsg'] = _translate("Please change your password to proceed.", true);
            if (basename((string) $uri) !== "edit-profile.php") {
                $redirect = new RedirectResponse('/users/edit-profile.php');
            }
        }

        // Only store the requested URI if the user is not logged in and it's not already set
        if (empty($_SESSION['userId']) && CommonService::isExcludedUri($uri, $this->excludedUris ?? []) !== true) {
            $_SESSION['requestedURI'] ??= AppRegistry::get('currentRequestURI');
        }

        if ($redirect !== null && $redirect instanceof RedirectResponse) {
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

        if (CommonService::isAjaxRequest($request)) {
            if (CommonService::isSameOriginRequest($request) === false) {
                throw new SystemException(_translate('Invalid request origin.'), 403);
            }
            return true;
        }

        if (CommonService::isExcludedUri($uri, $this->excludedUris) === true) {
            return true;
        }
        return false;
    }
}
