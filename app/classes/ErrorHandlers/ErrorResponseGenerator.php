<?php

namespace App\ErrorHandlers;

use Throwable;
use Whoops\Run;
use Laminas\Diactoros\Response;
use App\Utilities\LoggerUtility;
use Whoops\Handler\PrettyPageHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ErrorResponseGenerator
{
    private readonly bool $isDebug;
    private array $errorReasons = [];

    public function __construct(bool $isDebug)
    {
        $this->isDebug = $isDebug;
        $this->errorReasons = [
            500 => _translate('Internal Server Error'),
            404 => _translate('Not Found'),
            403 => _translate('Forbidden'),
            401 => _translate('Unauthorized')
        ];
    }

    public function __invoke(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response();

        if ($this->isDebug) {
            return $this->handleDebugMode($exception, $response);
        }

        $httpCode  = $this->determineHttpCode($exception);
        $this->logError($exception, $request);

        if (
            str_starts_with($request->getUri()->getPath(), '/api/') ||
            str_starts_with($request->getUri()->getPath(), '/remote/remote/')
        ) {
            return $this->handleApiErrorResponse($exception, $response, $httpCode);
        }

        return $this->handleGenericErrorResponse($exception, $response, $httpCode);
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

    private function handleDebugMode(Throwable $exception, ResponseInterface $response): ResponseInterface
    {
        $whoops = new Run();
        $whoops->allowQuit(false);
        $whoops->writeToOutput(false);
        $whoops->pushHandler(new PrettyPageHandler());
        $responseBody = $whoops->handleException($exception);
        $response->getBody()->write($responseBody);
        $httpCode  = $this->determineHttpCode($exception);
        return $response->withStatus($httpCode);
    }

    private function logError(Throwable $exception, ServerRequestInterface $request): void
    {
        $httpCode  = $this->determineHttpCode($exception);
        $errorReason = $this->errorReasons[$httpCode] ?? _translate('Internal Server Error');
        LoggerUtility::log('error', $errorReason . ' : ' . $exception->getCode() . ' : ' . $request->getUri() . ': ' . $exception->getMessage(), [
            'exception' => $exception,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stacktrace' => $exception->getTraceAsString()
        ]);
    }

    private function handleApiErrorResponse(Throwable $exception, ResponseInterface $response, int $httpCode): ResponseInterface
    {
        $errorReason = $this->errorReasons[$httpCode] ?? _translate('Internal Server Error');
        $errorMessage = (APPLICATION_ENV === 'production')
            ? _translate('Sorry, something went wrong. Please try again later.')
            : $exception->getMessage();

        $responseBody = json_encode([
            'error' => [
                'code' => $httpCode,
                'timestamp' => time(),
                'message' => $errorReason . " " . $errorMessage,
            ],
        ]);

        $response->getBody()->write($responseBody);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($httpCode);
    }

    private function handleGenericErrorResponse(Throwable $exception, ResponseInterface $response, int $httpCode): ResponseInterface
    {
        ob_start();
        // $errorReason and $errorMessage are used in error.php so retain them here
        $errorReason = $this->errorReasons[$httpCode] ?? _translate('Internal Server Error');
        $errorMessage = (APPLICATION_ENV === 'production')
            ? _translate('Sorry, something went wrong. Please try again later.')
            : $exception->getMessage();
        require_once(APPLICATION_PATH . '/error/error.php');
        $responseBody = ob_get_clean();

        $response->getBody()->write($responseBody);
        return $response->withStatus($httpCode);
    }
}
