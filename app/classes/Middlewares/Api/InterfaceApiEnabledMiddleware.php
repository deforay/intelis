<?php

declare(strict_types=1);

namespace App\Middlewares\Api;

use App\Http\InterfaceApiResponse;
use App\Services\CommonService;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class InterfaceApiEnabledMiddleware implements MiddlewareInterface
{
    public function __construct(private CommonService $commonService)
    {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtolower((string) $this->commonService->getGlobalConfig('interface_api_enabled')) !== 'yes') {
            return InterfaceApiResponse::error(
                'interface_api_disabled',
                'The Interface API is not enabled on this server.',
                503
            );
        }

        return $handler->handle($request);
    }
}
