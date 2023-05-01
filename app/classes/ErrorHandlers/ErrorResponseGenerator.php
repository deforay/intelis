<?php

namespace App\ErrorHandlers;

use Throwable;
use Whoops\Run;
use Laminas\Diactoros\Response;
use Whoops\Handler\PrettyPageHandler;
use Psr\Http\Message\ResponseInterface;
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
            ob_start();
            $httpCode = http_response_code() == 200 ? '' : http_response_code() . " - ";
            $errorMessage = $exception->getMessage() ?? 'Sorry, something went wrong. Please try again later.';
            require(APPLICATION_PATH . '/error/error.php');
            $responseBody = ob_get_clean();
        }

        $response = new Response();
        $response->getBody()->write($responseBody);
        return $response->withStatus(500);
    }
}
