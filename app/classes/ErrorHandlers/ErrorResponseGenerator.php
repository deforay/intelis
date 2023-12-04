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
        if ($this->isDebug) {
            $whoops = new Run();
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);
            $whoops->pushHandler(new PrettyPageHandler());
            $responseBody = $whoops->handleException($exception);
        } else {

            $httpCode = (http_response_code() == 200) ? 500 : http_response_code();

            $errorMessage = $exception->getMessage() ??
                _translate('Sorry, something went wrong. Please try again later.');

            $errorReason = isset($this->errorReasons[$httpCode]) ?
                $this->errorReasons[$httpCode] : _translate('Internal Server Error') . ' - ';

            // Log the error with Monolog, including the file, line, and stack trace
            LoggerUtility::log('error', $errorReason . ' Error: ' . $exception->getMessage(), [
                'exception' => $httpCode . " : " . $exception,
                'file' => $exception->getFile(), // File where the error occurred
                'line' => $exception->getLine(), // Line number of the error
                'stacktrace' => $exception->getTraceAsString()
            ]);

            if (APPLICATION_ENV == 'production') {
                $errorMessage = _translate('Sorry, something went wrong. Please try again later.');
            }

            if (
                str_starts_with($request->getUri()->getPath(), '/api/') ||
                str_starts_with($request->getUri()->getPath(), '/remote/remote/')
            ) {
                $responseBody = json_encode([
                    'error' => [
                        'code' => $httpCode,
                        'timestamp' => time(),
                        'message' => $errorReason . " " . $errorMessage,
                    ],
                ]);
            } else {
                ob_start();
                require(APPLICATION_PATH . '/error/error.php');
                $responseBody = ob_get_clean();
            }
        }

        $response = new Response();
        $response->getBody()->write($responseBody);
        return $response->withStatus(500);
    }
}
