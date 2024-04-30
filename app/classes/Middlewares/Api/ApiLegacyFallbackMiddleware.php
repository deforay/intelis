<?php

namespace App\Middlewares\Api;

use Exception;
use Throwable;
use App\Registries\AppRegistry;
use Laminas\Diactoros\Response;
use App\Exceptions\SystemException;
use App\Utilities\LoggerUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiLegacyFallbackMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException $e) {
            return $this->handleLegacyCode($request);
        } catch (Throwable $e) {
            // Log the exception here if needed
            throw new SystemException("Error in processing request: " . $e->getMessage(), 500, $e);
        }
    }

    private function handleLegacyCode(ServerRequestInterface $request): ResponseInterface
    {
        $uri = $this->sanitizeUri($request->getUri()->getPath());
        AppRegistry::set('request', $request);

        ob_start();
        try {
            require_once APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri;
            $output = ob_get_clean();
            $response = new Response('php://memory', 200);
            $response->getBody()->write($output);
            return $response;
        } catch (Throwable $e) {
            ob_end_clean();
            LoggerUtility::log('error', "API Error : " . $e->getMessage(), ['exception' => $e]);
            throw new SystemException("API Error : " . $e->getMessage(), 500, $e);
        }
    }

    private function sanitizeUri(string $uri): string
    {
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);
        return trim(parse_url($uri, PHP_URL_PATH), "/");
    }
}
