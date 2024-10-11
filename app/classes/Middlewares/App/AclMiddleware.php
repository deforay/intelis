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
        $currentURI = $request->getUri()->getPath();

        // SKIP ACL check for excluded URIs
        // SKIP ACL check for AJAX requests (X-Requested-With: XMLHttpRequest)
        // ALLOW if the current URI is allowed by ACL
        if (
            php_sapi_name() === 'cli' ||
            $this->isExcludedUri($currentURI) ||
            CommonService::isAjaxRequest($request) !== false ||
            _isAllowed($currentURI)
        ) {
            return $handler->handle($request);
        }

        $referer = $request->getHeaderLine('Referer');
        // If current URI is not allowed, check the referer (if it exists and is from the same domain)
        if (
            empty($referer) ||
            $this->isSameDomain($request, $referer) === false ||
            _isAllowed($referer) === false
        ) {
            throw new SystemException(_translate("Sorry") . " {$_SESSION['userName']}. " . _translate('You do not have permission to access this page or resource.'), 401);
        }

        return $handler->handle($request);
    }

    // Helper function to check if the current URI is in the excluded list
    protected function isExcludedUri(string $uri): bool
    {
        foreach ($this->excludedUris as $excludedUri) {
            if (fnmatch($excludedUri, $uri)) {
                return true;
            }
        }
        return false;
    }

    // Helper function to check if referer is from the same domain
    private function isSameDomain(ServerRequestInterface $request, string $referer): bool
    {
        $currentHost = $request->getUri()->getHost();
        $refererHost = parse_url($referer, PHP_URL_HOST);

        return $currentHost === $refererHost;
    }
}
