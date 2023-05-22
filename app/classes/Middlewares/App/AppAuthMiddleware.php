<?php

namespace App\Middlewares\App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class AppAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start the session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }


        $redirect = null;
        if ($this->isAjaxRequest($request) || $this->isCliRequest() || $this->shouldExcludeFromAuthCheck($request)) {

            // Skip the authentication check if the request is an AJAX request,
            // a CLI request, or if the requested URI is excluded from the
            // authentication check
            return $handler->handle($request);
        } elseif (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {

            // Redirect to the login page if the user is not logged in
            $redirect = new RedirectResponse('/login/login.php');
        } elseif (isset($_SESSION['forcePasswordReset']) && $_SESSION['forcePasswordReset'] == 1) {

            // Redirect to the edit profile page if the user is logged in but needs to change their password
            $_SESSION['alertMsg'] = _("Please change your password to proceed.");
            if (stripos($_SERVER['REQUEST_URI'], "editProfile.php") === false) {
                $redirect = new RedirectResponse('/users/editProfile.php');
            }
        }

        if (!is_null($redirect)) {
            return $redirect;
        } else {
            return $handler->handle($request);
        }
    }

    private function isAjaxRequest(ServerRequestInterface $request): bool
    {
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    private function isCliRequest(): bool
    {
        return (php_sapi_name() === 'cli');
    }

    private function shouldExcludeFromAuthCheck(ServerRequestInterface $request): bool
    {
        // Get the requested URI
        $uri = $request->getUri()->getPath();

        // Clean up the URI
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);

        $_SESSION['requestedURI'] = $uri;

        // Check if the URI matches the /remote/* pattern
        if (fnmatch('/remote/*', $uri)) {
            return true;
        }

        //error_log($uri);

        $excludedRoutes = [
            '/login/login.php',
            '/login/loginProcess.php',
            '/setup/index.php',
            '/setup/registerProcess.php',
            '/setup/registerProcess.php',
            '/includes/captcha.php',
            // Add other routes to exclude from the authentication check here
        ];

        return in_array($uri, $excludedRoutes, true);
    }
}
