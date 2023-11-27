<?php

namespace App\ErrorHandlers;

use Throwable;
use Whoops\Run;
use Monolog\Logger;
use Laminas\Diactoros\Response;
use Whoops\Handler\PrettyPageHandler;
use Psr\Http\Message\ResponseInterface;
use Monolog\Handler\RotatingFileHandler;
use Psr\Http\Message\ServerRequestInterface;

class ErrorResponseGenerator
{
    private bool $isDebug;
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
            $logger = new Logger('error_logger');

            $handler = new RotatingFileHandler(ROOT_PATH . '/logs/logfile.log', 30, Logger::ERROR, true, 0777);
            $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
            $logger->pushHandler($handler);

            $httpCode = (http_response_code() == 200) ? 500 : http_response_code();

            $errorMessage = $exception->getMessage() ??
                _translate('Sorry, something went wrong. Please try again later.');

            // Include file and line where the error was thrown
            $errorFile = $exception->getFile();
            $errorLine = $exception->getLine();

            $errorReason = isset($this->errorReasons[$httpCode]) ?
                $this->errorReasons[$httpCode] : _translate('Internal Server Error') . ' - ';

            // Log the error with Monolog, including the file, line, and stack trace
            $logger->error($errorReason . ' Error: ' . $exception->getMessage(), [
                'exception' => $httpCode . " : " . $exception,
                'file' => $errorFile, // File where the error occurred
                'line' => $errorLine, // Line number of the error
                'stacktrace' => $exception->getTraceAsString()
            ]);

            if (APPLICATION_ENV == 'production') {
                $errorMessage = _translate('Sorry, something went wrong. Please try again later.');
            }

            if (strpos($request->getUri()->getPath(), '/api/') === 0) {
                $responseBody = json_encode([
                    'error' => [
                        'code' => $httpCode,
                        'timestamp' => time(),
                        'message' => $errorReason . $errorMessage,
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
