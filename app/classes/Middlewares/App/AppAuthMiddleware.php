<?php

namespace App\Middlewares\App;

use App\Registries\AppRegistry;
use App\Services\CommonService;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AppAuthMiddleware implements MiddlewareInterface
{
    // URIs that are public / must not require login
    private array $excludedUris = [
        '/includes/captcha.php',
        '/users/edit-profile-helper.php',
        '/remote/*',
        '/setup/*',
        '/login/*',
    ];

    // Inactivity window (seconds). Adjust as needed.
    private int $maxIdle = 1800; // 30 minutes

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);

        // 1) Public routes (no auth)
        if ($this->shouldExcludeFromAuthCheck($request)) {
            return $handler->handle($request);
        }

        $isAjax = $this->isAjax($request);

        // 2) Not logged in
        if (empty($_SESSION['userId'])) {
            // Remember requested URI for post-login redirect (only for non-AJAX)
            if (!$isAjax) {
                $_SESSION['requestedURI'] ??= AppRegistry::get('currentRequestURI');
                return new RedirectResponse('/login/login.php');
            }
            return $this->jsonExpired();
        }

        // 3) Force password change path (only for full page)
        if (!empty($_SESSION['forcePasswordReset']) && (int)$_SESSION['forcePasswordReset'] === 1) {
            $_SESSION['alertMsg'] = _translate("Please change your password to proceed.", true);
            if (basename((string) $uri) !== "edit-profile.php") {
                return new RedirectResponse('/users/edit-profile.php');
            }
        }

        // 4) Idle timeout check (no polling; enforced only when a request happens)
        $now  = time();
        $last = $_SESSION['last_activity'] ?? $now;

        if (($now - $last) > $this->maxIdle) {
            // Hard-expire session
            $this->expireSession();
            return $isAjax ? $this->jsonExpired() : new RedirectResponse('/login/login.php?e=timeout');
        }

        // 5) Refresh "activity" ONLY on non-AJAX page requests
        //    (prevents background XHR/data refreshers from keeping sessions alive)
        if (!$isAjax) {
            $_SESSION['last_activity'] = $now;
        }

        return $handler->handle($request);
    }

    private function shouldExcludeFromAuthCheck(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri()->getPath();

        // IMPORTANT: do NOT exclude AJAX globally.
        // Only exclude CLI and explicitly excluded URIs.
        if (CommonService::isCliRequest()) {
            return true;
        }

        if (CommonService::isExcludedUri($uri, $this->excludedUris) === true) {
            return true;
        }

        return false;
    }

    private function isAjax(ServerRequestInterface $request): bool
    {
        // Honor your existing helper if it’s robust:
        $ajax = CommonService::isAjaxRequest($request);
        if ($ajax !== false) {
            return true;
        }
        // Also treat JSON/fetch as AJAX
        $accept = $request->getHeaderLine('Accept');
        $xrq    = strtolower($request->getHeaderLine('X-Requested-With'));
        return ($xrq === 'xmlhttprequest') || (stripos($accept, 'application/json') !== false);
    }

    private function jsonExpired(): JsonResponse
    {
        // 401 : session expired
        return new JsonResponse(
            ['error' => 'session_expired'],
            401,
            ['Cache-Control' => 'no-store, no-cache, must-revalidate']
        );
    }

    private function expireSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_unset();
            session_destroy();
        }
    }
}
