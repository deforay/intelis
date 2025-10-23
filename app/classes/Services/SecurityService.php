<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use App\Exceptions\SystemException;
use Laminas\Diactoros\ServerRequest;

final class SecurityService
{
    //public static $expiryTime = 3600; // 60 minutes
    public function __construct() {}

    public static function resetSession(): void
    {
        // Clear all session variables
        $_SESSION = [];

        // Remove session cookie if present
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy session
        session_destroy();
    }

    public static function restartSession(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {

            session_name(INTELIS_SESSION_NAME);

            $isSecure = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '0')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            );

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

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

    public static function checkCSRF(ServerRequest $request, bool $rotateCSRF = false): void
    {
        // Retrieve CSRF token from header or body
        $csrfToken = $request->getHeaderLine('X-CSRF-Token')
            ?: $request->getParsedBody()['csrf_token'] ?? null;

        // Validate the CSRF token
        if ($csrfToken !== null && (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken))) {
            if (CommonService::isAjaxRequest($request) === false) {
                $_SESSION['alertMsg'] = _translate('Your session has expired or is invalid. Please try again.');
                header("Location: /login/login.php");
            }
            exit;
        }


        // Optionally rotate the CSRF token after successful use
        if (CommonService::isAjaxRequest($request) === false && $rotateCSRF) {
            self::rotateCSRF();
        }
    }
    public static function rotateCSRF(): void
    {
        self::invalidateCSRF();
        self::generateCSRF();
    }
    public static function generateCSRF(): void
    {
        $_SESSION['csrf_token'] ??= MiscUtility::generateRandomString();
        $_SESSION['csrf_token_time'] = time();
    }

    private static function invalidateCSRF()
    {
        if (isset($_SESSION['csrf_token'])) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
        }
    }

    public static function redirect(string $url, $rotateCSRF = false): void
    {
        if ($rotateCSRF) {
            self::rotateCSRF();
        }
        if (str_contains(strtolower($url), 'location:')) {
            header($url);
        } else {
            header("Location: $url");
        }
        exit;
    }

    public static function checkLoginAttempts($ipAddress)
    {
        $lockoutPeriod = 15 * 60; // Lockout period in seconds (15 minutes)

        if (session_status() !== PHP_SESSION_NONE) {
            $_SESSION[$ipAddress] ??= [
                'failedAttempts' => 0,
                'lastFailedLogin' => null
            ];
            // Check if the user is locked out
            if ($_SESSION[$ipAddress]['failedAttempts'] >= 10) {
                $lastFailedLoginTimestamp = strtotime($_SESSION[$ipAddress]['lastFailedLogin']);
                $timeSinceLastFail = time() - $lastFailedLoginTimestamp;

                if ($timeSinceLastFail < $lockoutPeriod) {
                    // User is still within the lockout period
                    throw new SystemException(
                        "Too many failed login attempts. Please try again after " .
                            ceil(($lockoutPeriod - $timeSinceLastFail) / 60) . " minutes.",
                        403
                    );
                } else {
                    // Lockout period has expired; reset failed attempts
                    $_SESSION[$ipAddress] = [
                        'failedAttempts' => 0,
                        'lastFailedLogin' => null
                    ];
                }
            }
        }
    }
}
