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

/**
 * Bounds the two Interface API endpoints that accept a request body.
 *
 * /activate is unauthenticated, so it is rate limited per client IP and held to a
 * small body. /results is authenticated and carries a whole run, so it only needs a
 * size ceiling: an installation that floods it is already identifiable and revocable.
 */
final class InterfaceRequestGuardMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        return match ($request->getUri()->getPath()) {
            '/api/v1/interface/activate' => $this->guardActivation($request, $handler),
            '/api/v1/interface/results' => $this->guardBodySize(
                $request,
                $handler,
                InterfaceConnectionService::RESULTS_MAX_BODY_BYTES,
                'The results request is too large. Send the run in smaller batches.'
            ),
            '/api/v1/interface/activity' => $this->guardBodySize(
                $request,
                $handler,
                InterfaceConnectionService::ACTIVITY_MAX_BODY_BYTES,
                'The activity request is too large. Send fewer events per batch.'
            ),
            '/api/v1/interface/usage-statistics' => $this->guardBodySize(
                $request,
                $handler,
                InterfaceConnectionService::USAGE_STATISTICS_MAX_BODY_BYTES,
                'The usage statistics request is too large. Send fewer summaries per batch.'
            ),
            default => $handler->handle($request),
        };
    }

    private function guardActivation(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($this->bodySize($request) > InterfaceConnectionService::ACTIVATION_MAX_BODY_BYTES) {
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

    private function guardBodySize(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        int $maxBytes,
        string $message
    ): ResponseInterface {
        if ($this->bodySize($request) > $maxBytes) {
            return InterfaceApiResponse::error('payload_too_large', $message, 413);
        }

        return $handler->handle($request);
    }

    private function bodySize(ServerRequestInterface $request): int
    {
        return max(
            (int) $request->getHeaderLine('Content-Length'),
            $request->getBody()->getSize() ?? 0
        );
    }
}
