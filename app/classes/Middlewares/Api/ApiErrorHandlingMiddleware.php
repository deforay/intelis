<?php

namespace App\Middlewares\Api;

use App\ErrorHandlers\ErrorResponseGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiErrorHandlingMiddleware implements MiddlewareInterface
{
    private ErrorResponseGenerator $errorResponseGenerator;

    public function __construct(ErrorResponseGenerator $errorResponseGenerator)
    {
        $this->errorResponseGenerator = $errorResponseGenerator;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $exception) {
            return ($this->errorResponseGenerator)($exception, $request);
        }
    }
}
