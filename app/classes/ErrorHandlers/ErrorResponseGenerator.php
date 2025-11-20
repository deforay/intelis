<?php

namespace App\ErrorHandlers;

use Throwable;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use Slim\Psr7\Response;
use App\Utilities\LoggerUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ErrorResponseGenerator
{
    private array $errorReasons = [];
    private array $safeErrorMessages = [];

    public function __construct(private readonly bool $isDebug)
    {
        $this->errorReasons = [
            500 => _translate('Internal Server Error'),
            404 => _translate('Not Found'),
            403 => _translate('Forbidden'),
            401 => _translate('Unauthorized'),
            400 => _translate('Bad Request'),
            503 => _translate('Service Unavailable'),
            504 => _translate('Gateway Timeout'),
        ];

        // Safe, user-friendly messages for common error scenarios
        $this->safeErrorMessages = [
            500 => _translate('We encountered an unexpected problem while processing your request.'),
            404 => _translate('The page or resource you requested could not be found.'),
            403 => _translate('You do not have permission to access this page or resource.'),
            401 => _translate('Please log in to access this resource.'),
            400 => _translate('The request contains invalid or missing information.'),
            503 => _translate('The service is temporarily unavailable. Please try again later.'),
            504 => _translate('The request took too long to process. Please try again.'),
        ];
    }

    public function __invoke(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        $httpCode = $this->determineHttpCode($exception);
        $errorId = $this->logError($exception, $request);

        if (
            str_starts_with($request->getUri()->getPath(), '/api/') ||
            str_starts_with($request->getUri()->getPath(), '/remote/')
        ) {
            return $this->handleApiErrorResponse($exception, $response, $httpCode, $errorId);
        }

        return $this->handleGenericErrorResponse($exception, $response, $httpCode, $errorId, $request);
    }

    private function determineHttpCode(Throwable $exception): int
    {
        $originalExceptionCode = $exception->getCode();
        $httpCode = $originalExceptionCode ?: 500;

        if ($httpCode < 100 || $httpCode > 599) {
            $httpCode = 500;
        }

        return $httpCode;
    }

    private function logError(Throwable $exception, ServerRequestInterface $request): string
    {
        $httpCode = $this->determineHttpCode($exception);
        $errorReason = $this->errorReasons[$httpCode] ?? _translate('Internal Server Error');

        // Generate a unique error ID for tracking
        $errorId = MiscUtility::generateErrorId();

        LoggerUtility::logError($errorReason . ' : ' . $exception->getCode() . ' : ' . ($request->getUri() ?? 'UNABLE TO GET URI') . ': ' . $exception->getMessage(), [
            'error_id' => $errorId,
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stacktrace' => $exception->getTraceAsString(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'ip_address' => CommonService::getClientIpAddress($request),
            'session_id' => session_id() ?: 'no-session'
        ]);

        return $errorId;
    }

    private function handleApiErrorResponse(Throwable $exception, ResponseInterface $response, int $httpCode, string $errorId): ResponseInterface
    {
        $errorReason = $this->errorReasons[$httpCode] ?? _translate('Internal Server Error');
        $safeMessage = $this->safeErrorMessages[$httpCode] ?? _translate('Sorry, something went wrong. Please try again later.');

        // For production, use safe message; for non-production, show actual exception message
        $errorMessage = (APPLICATION_ENV === 'production' && $this->isDebug === false)
            ? ($_SESSION['errorDisplayMessage'] ?? $safeMessage)
            : $exception->getMessage();

        unset($_SESSION['errorDisplayMessage']);

        $responseBody = json_encode([
            'error' => [
                'code' => $httpCode,
                'timestamp' => time(),
                'message' => "$errorReason | $errorMessage",
                'error_id' => $errorId,
                'support_info' => [
                    'contact' => _translate('Please contact support with the error ID above.'),
                    'retry' => $httpCode >= 500 ? _translate('This appears to be a temporary issue. Please try again.') : null
                ]
            ],
        ], JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($responseBody);
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($httpCode);
    }

    private function handleGenericErrorResponse(Throwable $exception, ResponseInterface $response, int $httpCode, string $errorId, ServerRequestInterface $request): ResponseInterface
    {
        ob_start();

        // Variables used in error.php
        $errorReason = $this->errorReasons[$httpCode] ?? _translate('Internal Server Error');
        $safeMessage = $this->safeErrorMessages[$httpCode] ?? _translate('Sorry, something went wrong. Please try again later.');

        $errorMessage = (APPLICATION_ENV === 'production') ? $safeMessage : $exception->getMessage();

        // Additional safe information for the error page
        $errorInfo = [
            'error_id' => $errorId,
            'timestamp' => date('Y-m-d H:i:s'),
            'can_retry' => $httpCode >= 500, // Server errors might be temporary
            'suggested_actions' => $this->getSuggestedActions($httpCode),
            'is_api_request' => str_starts_with($request->getUri()->getPath(), '/api/'),
        ];

        require_once APPLICATION_PATH . '/error/error.php';
        $responseBody = ob_get_clean();

        $response->getBody()->write($responseBody);
        return $response->withStatus($httpCode);
    }

    private function getSuggestedActions(int $httpCode): array
    {
        $actions = [];

        switch ($httpCode) {
            case 401:
                $actions[] = _translate('Try logging in again');
                $actions[] = _translate('Check if your session has expired');
                break;
            case 403:
                $actions[] = _translate('Contact your administrator for access');
                $actions[] = _translate('Verify you have the required permissions');
                break;
            case 404:
                $actions[] = _translate('Check the URL for typos');
                $actions[] = _translate('Use the navigation menu to find what you need');
                break;
            case 500:
            case 502:
            case 503:
            case 504:
                $actions[] = _translate('Wait a few minutes and try again');
                $actions[] = _translate('Contact support if the problem persists');
                break;
            default:
                $actions[] = _translate('Please check your request and try again.');
                break;
        }

        return $actions;
    }
}
