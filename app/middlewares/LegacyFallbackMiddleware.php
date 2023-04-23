<?php

namespace App\Middleware;

use App\Services\CommonService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;

class LegacyFallbackMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {


        try {
            // Try to handle the request with Slim routing
            $response = $handler->handle($request);
        } catch (HttpNotFoundException $e) {
            // If the route is not found in Slim routing, fallback to legacy routes

            $uri = $request->getUri()->getPath();
            $uri = preg_replace('/([\/.])\1+/', '$1', $uri);
            $uri = trim(parse_url($uri, PHP_URL_PATH), "/");

            ob_start();
            require(APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri);
            $output = ob_get_clean();

            // Create a new response object
            $response = new Response();

            // Set the output of the legacy PHP code as the response body
            $response->getBody()->write($output);
        }

        return $response;
    }
}
