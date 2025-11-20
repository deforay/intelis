<?php

namespace App\Middlewares\Api;

use Override;
use Throwable;
use Slim\Psr7\Response;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiLegacyFallbackMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException) {
            return $this->handleLegacyCode($request);
        } catch (Throwable $e) {
            // Log the exception here if needed
            throw new SystemException("Error in processing request: " . $e->getMessage(), 500, $e);
        }
    }

    private function handleLegacyCode(ServerRequestInterface $request): ResponseInterface
    {

        $filePath = $this->sanitizePath($request->getUri()->getPath());

        if (!is_readable($filePath)) {
            throw new SystemException("Could not resolve API request", 400);
        }

        ob_start();
        try {
            (function () use ($filePath): void{
                require_once $filePath;
            })();
            $output = ob_get_clean();
            $response = new Response();
            $response = $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
            $response->getBody()->write($output);
            return $response;
        } catch (Throwable $e) {
            ob_end_clean();
            //LoggerUtility::logError("API Error : " . $e->getMessage(), ['exception' => $e]);
            throw new SystemException("API Error : " . $e->getMessage(), 500, $e);
        }
    }

    private function sanitizePath(string $uriPath): string
    {
        $uriPath = preg_replace('/([\/.])\1+/', '$1', $uriPath);
        $uriPath = trim(parse_url((string) $uriPath, PHP_URL_PATH), "/");
        $filePath = APPLICATION_PATH . DIRECTORY_SEPARATOR . $uriPath;
        $resolvedPath = realpath($filePath);
        if (!$resolvedPath || is_dir($resolvedPath) || !str_starts_with($resolvedPath, realpath(APPLICATION_PATH)) || !is_readable($resolvedPath)) {
            LoggerUtility::logError('Invalid API Request : ' . $resolvedPath);
            throw new SystemException(_translate('Sorry! We could not resolve this request'), 404);
        }

        return $resolvedPath;
    }
}
