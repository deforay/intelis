<?php

namespace App\Middlewares\App;

use Override;
use App\Services\CommonService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class AppAuthMiddleware implements MiddlewareInterface
{
    /**
     * URIs that are always public (no auth, no token)
     * We need to keep this list tight.
     */
    private array $publicUris = [
        '/includes/captcha.php',
        '/login/*',
        '/setup/*',
        '/users/edit-profile-helper.php',
    ];

    /**
     * URIs intended for machine-to-machine (m2m) access.
     * Treat as public for now; when token support is ready this branch can enforce it.
     */
    private array $m2mUris = [
        '/remote/*',
        '/tasks/remote/*',
    ];

    // Inactivity window (seconds).
    private int $maxIdle = 1800; // 30 minutes

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = $this->normalizePath($request->getUri()->getPath());
        $isAjax = $this->isAjax($request);

        if ($path === '/__forbidden__') {
            return $this->forbiddenJsonOrRedirect($isAjax);
        }

        if (CommonService::isCliRequest()) {
            return $handler->handle($request);
        }

        if ($this->isPublic($path)) {
            return $handler->handle($request);
        }

        if ($this->isM2M($path)) {
            // TODO: enforce token validation once the endpoints switch to auth tokens.
            return $handler->handle($request);
        }

        if (empty($_SESSION['userId'])) {
            if ($method === 'GET' && !$isAjax) {
                $_SESSION['requestedURI'] ??= $this->safeRequestedUri($request, $path);
                return new RedirectResponse('/login/login.php');
            }
            return $this->jsonExpired();
        }

        if (!empty($_SESSION['forcePasswordReset']) && (int) $_SESSION['forcePasswordReset'] === 1) {
            $_SESSION['alertMsg'] = _translate("Please change your password to proceed.", true);
            if (basename((string) $path) !== 'edit-profile.php') {
                return new RedirectResponse('/users/edit-profile.php');
            }
        }

        $now = time();
        $last = $_SESSION['last_activity'] ?? $now;
        if (($now - $last) > $this->maxIdle) {
            $this->expireSession();
            return $isAjax
                ? $this->jsonExpired()
                : new RedirectResponse('/login/login.php?e=timeout');
        }

        if (!$isAjax && ($method === 'GET' || $method === 'HEAD')) {
            $_SESSION['last_activity'] = $now;
        }

        $response = $handler->handle($request);
        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '') {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Vary', 'Origin');
        }

        return $response;
    }

    private function safeRequestedUri(ServerRequestInterface $req, string $path): string
    {
        $host = strtolower($req->getHeaderLine('Host'));
        $ref = $req->getHeaderLine('Referer') ?: $req->getHeaderLine('Origin');

        if ($ref !== '' && $ref !== '0') {
            $parts = parse_url($ref);
            if (isset($parts['host']) && ($parts['host'] !== '' && $parts['host'] !== '0') && strtolower($parts['host']) !== $host) {
                return $path;
            }
        }

        if (!str_starts_with($path, '/')) {
            return '/';
        }

        foreach (['/remote/', '/tasks/'] as $deny) {
            if (str_starts_with($path, $deny)) {
                return '/';
            }
        }

        return $path;
    }

    private function normalizePath(string $p): string
    {
        $p = urldecode($p);
        if (str_contains($p, '..')) {
            return '/__forbidden__';
        }
        $p = preg_replace('#/{2,}#', '/', $p);
        return rtrim((string) $p, '/') ?: '/';
    }

    private function isPublic(string $path): bool
    {
        return CommonService::isExcludedUri($path, $this->publicUris);
    }

    private function isM2M(string $path): bool
    {
        return CommonService::isExcludedUri($path, $this->m2mUris);
    }

    private function isAjax(ServerRequestInterface $request): bool
    {
        $xrq = strtolower($request->getHeaderLine('X-Requested-With'));
        if ($xrq === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));
        if ($accept !== '') {
            $hasJson = str_contains($accept, 'application/json') || str_contains($accept, 'text/json');
            $hasHtml = str_contains($accept, 'text/html');
            if ($hasJson && !$hasHtml) {
                return true;
            }
        }

        return (bool) CommonService::isAjaxRequest($request);
    }

    private function jsonExpired(): JsonResponse
    {
        return new JsonResponse(
            [
                'error' => 'session_expired',
                'redirect' => '/login/login.php',
            ],
            401,
            ['Cache-Control' => 'no-store, no-cache, must-revalidate']
        );
    }

    private function forbiddenJsonOrRedirect(bool $ajax): ResponseInterface
    {
        if ($ajax) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        return new RedirectResponse('/login/login.php?e=forbidden');
    }

    private function expireSession(): void
    {
        if (CommonService::isSessionActive()) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => (bool) $params['secure'], 'httponly' => (bool) $params['httponly']]
                );
            }
            @session_unset();
            @session_destroy();
        }
    }
}
