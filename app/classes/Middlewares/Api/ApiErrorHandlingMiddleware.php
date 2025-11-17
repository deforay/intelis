<?php

namespace App\Middlewares\Api;

use Override;
use App\ErrorHandlers\ErrorResponseGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

readonly class ApiErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(private ErrorResponseGenerator $errorResponseGenerator)
    {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return ($this->errorResponseGenerator)($exception, $request);
        }
    }
}
