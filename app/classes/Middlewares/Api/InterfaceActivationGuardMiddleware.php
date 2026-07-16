<?php

declare(strict_types=1);

namespace App\Middlewares\Api;

use App\Http\InterfaceApiResponse;
use App\Services\InterfaceApi\InterfaceConnectionService;
use App\Utilities\RateLimitUtility;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class InterfaceActivationGuardMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            $request->getMethod() !== 'POST'
            || $request->getUri()->getPath() !== '/api/v1/interface/activate'
        ) {
            return $handler->handle($request);
        }

        $contentLength = (int) $request->getHeaderLine('Content-Length');
        $streamSize = $request->getBody()->getSize() ?? 0;
        if (max($contentLength, $streamSize) > InterfaceConnectionService::ACTIVATION_MAX_BODY_BYTES) {
            return InterfaceApiResponse::error('payload_too_large', 'The activation request is too large.', 413);
        }

        $bucket = 'interface-activation:' . RateLimitUtility::clientIp();
        if (
            RateLimitUtility::exceeded(
                $bucket,
                InterfaceConnectionService::ACTIVATION_MAX_ATTEMPTS,
                InterfaceConnectionService::ACTIVATION_WINDOW_SECONDS
            )
        ) {
            return InterfaceApiResponse::error(
                'rate_limit_exceeded',
                'Too many activation attempts. Please retry shortly.',
                429
            )->withHeader('Retry-After', (string) InterfaceConnectionService::ACTIVATION_WINDOW_SECONDS);
        }

        return $handler->handle($request);
    }
}
