<?php

declare(strict_types=1);

namespace App\Middlewares\Api;

use App\Exceptions\InterfaceApiException;
use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceInstallationService;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class InterfaceInstallationAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private InterfaceInstallationService $installations,
        private string $requiredScope = 'connection:read'
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $authorization = $request->getHeaderLine('Authorization');
            $token = preg_match('/^Bearer\s+(\S+)$/iD', $authorization, $matches) === 1 ? $matches[1] : '';
            $installation = $this->installations->authenticate($token, $this->requiredScope);

            return $handler->handle($request->withAttribute('interfaceInstallation', $installation));
        } catch (InterfaceApiException $exception) {
            return InterfaceApiResponse::error(
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getHttpStatus()
            );
        }
    }
}
