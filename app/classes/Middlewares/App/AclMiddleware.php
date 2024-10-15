<?php

namespace App\Middlewares\App;

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AclMiddleware implements MiddlewareInterface
{
    protected array $excludedUris = [
        '/',
        '/index.php',
        '/includes/captcha.php',
        '/users/edit-profile-helper.php',
        '/login/*',
        '/setup/*',
        '/remote/remote/*',
        '/system-admin/*',
        '/api/*',
        // Add other routes to exclude from the ACL check here
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $currentURI = AppRegistry::get('currentRequestURI');

        // SKIP ACL check for excluded URIs
        // SKIP ACL check for AJAX requests (X-Requested-With: XMLHttpRequest)
        // ALLOW if the current URI is allowed by ACL
        if (
            $this->shouldExcludeFromAclCheck($request) ||
            _isAllowed($currentURI)
        ) {
            return $handler->handle($request);
        }

        $referer = $request->getHeaderLine('Referer');
        $refererPath = $this->getRefererPath($referer);
        // If current URI is not allowed, check the referer (if it exists and is from the same domain)
        if (
            empty($refererPath) ||
            $this->isSameDomain($request, $referer) === false ||
            _isAllowed($refererPath) === false
        ) {
            throw new SystemException(_translate("Sorry") . " {$_SESSION['userName']}. " . _translate('You do not have permission to access this page or resource.'), 401);
        }

        return $handler->handle($request);
    }

    protected function getRefererPath($referer): string
    {
        $parsedUrl = parse_url($referer);
        $path = $parsedUrl['path'] ?? '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        return $path . $query;
    }

    // Helper function to check if referer is from the same domain
    private function isSameDomain(ServerRequestInterface $request, string $referer): bool
    {
        $currentHost = $request->getUri()->getHost();
        $refererHost = parse_url($referer, PHP_URL_HOST);

        return $currentHost === $refererHost;
    }

    private function shouldExcludeFromAclCheck(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri()->getPath();
        if (
            CommonService::isCliRequest() ||
            CommonService::isAjaxRequest($request) !== false ||
            CommonService::isExcludedUri($uri, $this->excludedUris) === true
        ) {
            return true;
        }
        return false;
    }
}
