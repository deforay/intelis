<?php

namespace App\Middlewares\App;

use Override;
use Throwable;
use RuntimeException;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AclMiddleware implements MiddlewareInterface
{
    private array $excludedUris = [
        '/',
        '/index.php',
        '/api/*',
        '/login/*',
        '/setup/*',
        '/remote/*',
        '/system-admin/*',
        '/includes/captcha.php',
        '/users/edit-profile-helper.php',
        '/health-check',
        '/status'
    ];

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = CommonService::getClientIpAddress($request);
        $currentURI = null;
        $user = null;

        try {
            $currentURI = $this->getCurrentRequestUri();
            $user = $_SESSION['userName'] ?? null;

            if ($this->isAccessAllowed($request, $currentURI, $user)) {
                return $handler->handle($request);
            }

            if ($this->isRefererAccessAllowed($request, $currentURI)) {
                return $handler->handle($request);
            }

            // Access denied - handleAccessDenied always throws, but IDE needs explicit indication
            $this->handleAccessDenied($currentURI, $user, $request);
            throw new RuntimeException('This line should is never reached'); // Satisfies static analysis
        } catch (SystemException $e) {
            throw $e;
        } catch (Throwable $e) {
            LoggerUtility::logError('ACL Middleware error: ' . $e->getMessage(), [
                'exception' => $e,
                'uri' => $currentURI ?? 'unknown',
                'user' => $user ?? 'unknown',
                'ip' => $ip,
            ]);
            throw new SystemException(_translate('Access control error occurred'), 500);
        }
    }

    private function isAccessAllowed(ServerRequestInterface $request, string $currentURI, ?string $user): bool
    {
        if ($this->shouldExcludeFromAclCheck($request)) {
            return true;
        }

        // Direct ACL check without caching
        return _isAllowed($currentURI);
    }

    private function isRefererAccessAllowed(ServerRequestInterface $request, string $currentURI): bool
    {
        $referer = $request->getHeaderLine('Referer');

        if (!$this->isValidReferer($referer)) {
            return false;
        }

        $refererPath = $this->getRefererPath($referer);

        if (!$this->isSameDomain($request, $referer) || ($refererPath === '' || $refererPath === '0')) {
            return false;
        }

        // Direct ACL check for referer without caching
        return _isAllowed($refererPath);
    }

    private function isValidReferer(string $referer): bool
    {
        if ($referer === '' || $referer === '0' || strlen($referer) > 2048) {
            return false;
        }

        if (!filter_var($referer, FILTER_VALIDATE_URL)) {
            return false;
        }

        $suspiciousPatterns = [
            '/javascript:/i',
            '/data:/i',
            '/vbscript:/i',
            '/file:/i'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $referer)) {
                return false;
            }
        }

        return true;
    }

    private function getRefererPath(string $referer): string
    {
        $parsedUrl = parse_url($referer);

        if ($parsedUrl === false) {
            return '';
        }

        $path = $parsedUrl['path'] ?? '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';

        return $this->sanitizePath($path) . $query;
    }

    private function sanitizePath(string $path): string
    {
        $path = preg_replace('/\/+/', '/', $path);
        $path = str_replace(['../', '..\\'], '', $path);

        if (!str_starts_with($path, '/')) {
            $path = "/$path";
        }

        return $path;
    }

    private function isSameDomain(ServerRequestInterface $request, string $referer): bool
    {
        $currentHost = strtolower($request->getUri()->getHost());
        $refererHost = strtolower(parse_url($referer, PHP_URL_HOST) ?? '');

        return $currentHost !== '' && $currentHost !== '0' && ($refererHost !== '' && $refererHost !== '0') && $currentHost === $refererHost;
    }

    private function shouldExcludeFromAclCheck(ServerRequestInterface $request): bool
    {
        if (CommonService::isCliRequest()) {
            return true;
        }

        if (CommonService::isAjaxRequest($request)) {
            return true;
        }

        $uri = $request->getUri()->getPath();
        return CommonService::isExcludedUri($uri, $this->excludedUris ?? []);
    }

    private function getCurrentRequestUri(): string
    {
        $uri = AppRegistry::get('currentRequestURI');
        if (empty($uri)) {
            throw new RuntimeException('Current request URI not found in AppRegistry');
        }
        return $uri;
    }

    private function handleAccessDenied(string $uri, ?string $user, ServerRequestInterface $request): never
    {
        LoggerUtility::logWarning('Access denied', [
            'code' => 403,
            'user' => $user,
            'uri' => $uri,
            'method' => $request->getMethod(),
            'referer' => $request->getHeaderLine('Referer'),
            'userAgent' => $request->getHeaderLine('User-Agent'),
            'ip' => CommonService::getClientIpAddress($request),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $userName = $user ?? _translate('Guest');
        throw new SystemException(
            _translate("Sorry") . " {$userName}. " .
            _translate('You do not have permission to access this page or resource.'),
            403
        );
    }
}
