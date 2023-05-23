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
        // Get the requested URI
        $uri = $request->getUri()->getPath();

        // Clean up the URI
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);

        $_SESSION['requestedURI'] = $uri;

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
            $_SESSION['alertMsg'] = _("Please change your password to proceed.");
            if (basename($_SESSION['requestedURI']) !== "editProfile.php") {
                $redirect = new RedirectResponse('/users/editProfile.php');
            }
        }

        if (!is_null($redirect)) {
            return $redirect;
        } else {
            return $handler->handle($request);
        }
    }

    private function shouldExcludeFromAuthCheck(ServerRequestInterface $request): bool
    {
        $return = false;
        if (
            php_sapi_name() === 'cli' ||
            strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest'
        ) {
            $return = true;
        } else {

            // Check if the URI matches the /remote/* pattern
            if (fnmatch('/remote/*', $_SESSION['requestedURI'])) {
                $return = true;
            } else {
                $excludedRoutes = [
                    '/login/login.php',
                    '/login/loginProcess.php',
                    '/login/logout.php',
                    '/setup/index.php',
                    '/setup/registerProcess.php',
                    '/setup/registerProcess.php',
                    '/includes/captcha.php',
                    '/users/editProfileHelper.php',
                    // Add other routes to exclude from the authentication check here
                ];
                $return = in_array($_SESSION['requestedURI'], $excludedRoutes, true);
            }
        }

        return $return;
    }
}
