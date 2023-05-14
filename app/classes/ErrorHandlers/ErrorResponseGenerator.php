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

    public function __construct(bool $isDebug)
    {
        $this->isDebug = $isDebug;
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
                _('Sorry, something went wrong. Please try again later.');

            // Log the error with Monolog, including the stack trace
            $logger->error('Error: ' . $exception->getMessage(), [
                'exception' => $httpCode . " : " . $exception,
                'stacktrace' => $exception->getTraceAsString()
            ]);

            if (strpos($request->getUri()->getPath(), '/api/') === 0) {
                $responseBody = json_encode([
                    'error' => [
                        'code' => $httpCode,
                        'timestamp' => time(),
                        'message' => $errorMessage,
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
