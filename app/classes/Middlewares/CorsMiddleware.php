<?php

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Laminas\Diactoros\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            "origin" => ["*"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
            "headers.allow" => ["Content-Type", "Authorization", "Accept"],
            "headers.expose" => [],
            "credentials" => false,
            "cache" => 86400,
        ], $options);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $origin = $request->getHeader('Origin');
        $origin = $origin ? $origin[0] : '*';

        if (in_array('*', $this->options['origin']) || in_array($origin, $this->options['origin'])) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->options['methods']))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->options['headers.allow']))
                ->withHeader('Access-Control-Expose-Headers', implode(', ', $this->options['headers.expose']))
                ->withHeader('Access-Control-Allow-Credentials', $this->options['credentials'] ? 'true' : 'false')
                ->withHeader('Access-Control-Max-Age', $this->options['cache']);
        }

        if ($request->getMethod() === 'OPTIONS') {
            return new Response\EmptyResponse(200, $response->getHeaders());
        }

        return $response;
    }
}
