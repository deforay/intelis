<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use App\Exceptions\SystemException;
use Laminas\Diactoros\ServerRequest;

final class SecurityService
{
    public function __construct() {}

    public static function checkContentLength(ServerRequest $request)
    {
        // Only check Content-Length for POST, PUT, and PATCH requests
        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return;
        }

        $contentLength = $request->getHeaderLine('Content-Length');

        // Check for multipart/form-data specifically
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        // For non-multipart forms, compare the length of the body
        $body = $request->getBody();
        $bodyContents = $body->getContents();
        $body->rewind(); // Rewind after reading

        if (
            !str_contains($contentType, 'multipart/form-data') &&
            $contentLength &&
            strlen($bodyContents) !== (int)$contentLength
        ) {
            throw new SystemException(_translate('Invalid Request. Please try again.'));
        }
    }

    public static function checkCSRF(ServerRequest $request, bool $invalidate = false): void
    {
        // Retrieve CSRF token from header or body
        $csrfToken = $request->getHeaderLine('X-CSRF-Token')
            ?: $request->getParsedBody()['csrf_token'] ?? null;

        // Check if CSRF token has expired (1 hour default expiration)
        if (CommonService::isAjaxRequest($request) === false && !empty($_SESSION['csrf_token_time']) && time() - $_SESSION['csrf_token_time'] > 3600) {
            self::rotateCSRF($request);
            throw new SystemException(_translate('Request token expired. Please refresh the page and try again.'));
        }

        // Validate the CSRF token
        if (!$csrfToken || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            throw new SystemException(_translate('Invalid Request token. Please refresh the page and try again.'));
        }

        // Optionally invalidate and regenerate the CSRF token after successful use
        if ($invalidate) {
            self::rotateCSRF($request);
        }
    }
    public static function rotateCSRF($request): void
    {
        if (CommonService::isAjaxRequest($request) === false) {
            self::invalidateCSRF();
            self::generateCSRF($request);
        }
    }
    public static function generateCSRF($request): void
    {
        if (CommonService::isAjaxRequest($request) === false) {
            $_SESSION['csrf_token_time'] = time();
            $_SESSION['csrf_token'] ??= MiscUtility::generateRandomString();
        }
    }

    private static function invalidateCSRF()
    {
        if (isset($_SESSION['csrf_token']) || time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
        }
    }
}
