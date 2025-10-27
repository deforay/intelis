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
        // Figure out who is asking so we can decide whether to include CORS headers.
        $originHeader = $request->getHeaderLine('Origin');
        $origin       = $originHeader !== '' ? $originHeader : '*';

        $allowOrigin   = null;
        $allowedOrigin = in_array('*', $this->options['origin'], true) || in_array($origin, $this->options['origin'], true);
        if ($allowedOrigin) {
            // When "*" is configured we reflect the caller’s origin, otherwise we honour the allowlist entry.
            $allowOrigin = in_array('*', $this->options['origin'], true) ? $origin : $origin;
        }

        $baseHeaders = [];
        if ($allowOrigin !== null) {
            // Build the common header set once so we can reuse it for preflight and the actual response.
            $baseHeaders = [
                'Access-Control-Allow-Origin'      => $allowOrigin,
                'Access-Control-Allow-Methods'     => implode(', ', $this->options['methods']),
                'Access-Control-Allow-Headers'     => implode(', ', $this->options['headers.allow']),
                'Access-Control-Expose-Headers'    => implode(', ', $this->options['headers.expose']),
                'Access-Control-Allow-Credentials' => $this->options['credentials'] ? 'true' : 'false',
                'Access-Control-Max-Age'           => (string) $this->options['cache'],
                'Vary'                              => 'Origin',
            ];
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            // Browser preflight (OPTIONS) checks if cross-origin request would be permitted.
            // We answer it directly so the real request only runs when the policy allows it.
            return new Response\EmptyResponse(204, $baseHeaders);
        }

        $response = $handler->handle($request);
        foreach ($baseHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
