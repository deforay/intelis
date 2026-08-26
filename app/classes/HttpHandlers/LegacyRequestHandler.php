<?php

namespace App\HttpHandlers;

use Override;
use Throwable;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use Slim\Psr7\Response;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LegacyRequestHandler implements RequestHandlerInterface
{
    public function __construct(private readonly DatabaseService $dbService, private readonly CommonService $commonService)
    {
    }
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $filePath = null;
        $bufferDepth = ob_get_level();
        try {

            $filePath = $this->sanitizePath($request);

            // The pages read the request from the registry rather than from an
            // argument, 523 of them, so it has to be there before one is required.
            // Setting it here rather than relying on the front controller having done
            // it makes this handler answer for the request it was actually given: the
            // middleware that sets it runs before authentication, so anything a later
            // middleware puts on the request was invisible to every page until now.
            AppRegistry::set('request', $request);

            // Capture output buffer to prevent it from being sent directly
            ob_start();

            // Creating $db and $general variables to make them available in the included file
            $db = $this->dbService;
            $general = $this->commonService;

            (function () use ($filePath, $db, $general): void{
                require_once $filePath;
            })();

            // Get the output buffer content and clean the buffer
            $output = ob_get_clean();
            return $this->createResponse($output);
        } catch (Throwable $e) {
            // Unwind only buffers opened above, and only if any were. sanitizePath()
            // throws before ob_start() -- a 404 for a missing asset is its commonest
            // outcome -- and an unconditional ob_end_clean() there discarded whichever
            // buffer the caller had open instead.
            while (ob_get_level() > $bufferDepth) {
                ob_end_clean();
            }

            // sanitizePath() has already logged a 404 against the path that was
            // requested. Re-logging it here only adds a Slim stack trace, which
            // describes the router rather than the missing file, and doubles the
            // volume: one absent icon referenced from a stylesheet wrote two
            // ERROR entries per page view on every instance in the fleet.
            if ($e instanceof SystemException && (int) $e->getCode() === 404) {
                throw $e;
            }

            $fileContext = $filePath ?? $request->getUri()->getPath();
            LoggerUtility::logError("Error in $fileContext : " . $e->getFile() . ":" . $e->getLine() . ":" . $e->getMessage(), [
                'request' => $request->getUri()->getPath(),
                'trace' => $e->getTraceAsString(),
                'code' => $e->getCode(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            throw new SystemException($e->getMessage(), $e->getCode() ?? 500, $e);
        }
    }


    private function sanitizePath(ServerRequestInterface $request): string
    {
        $uri = $request->getUri()->getPath();
        $uri = filter_var($uri, FILTER_SANITIZE_URL);
        $uri = trim(parse_url($uri, PHP_URL_PATH), "/");

        if ($uri === '' || $uri === null) {
            return APPLICATION_PATH . '/index.php';
        }
        // Resolve the absolute path and ensure it's within the APPLICATION_PATH
        $resolvedPath = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri);
        $resolvedPath = is_dir($resolvedPath) ? "$resolvedPath/index.php" : $resolvedPath;
        if (!$resolvedPath || !str_starts_with($resolvedPath, realpath(APPLICATION_PATH)) || !is_readable($resolvedPath)) {
            // Log what was asked for, not what it resolved to: realpath() returns
            // false for anything that does not exist, which is the common case
            // here, and interpolating false produced "Invalid Request : " with
            // the one useful detail missing.
            //
            // Warning, not error. A request for a file that is not there is a
            // 404, not a fault in the application, and at ERROR it competes for
            // attention with the failures that are.
            LoggerUtility::logWarning('Request for a path that does not exist: ' . $uri);
            throw new SystemException(_translate('Sorry! We could not find this page or resource'), 404);
        }

        return $resolvedPath;
    }


    private function createResponse(string|bool $output): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($output);

        return $this->manageHeaders($response);
    }

    private function manageHeaders(ResponseInterface $response): ResponseInterface
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, strlen('Location:')));
                header_remove('Location');
                return (new Response(302))->withHeader('Location', $location);
            }
        }

        return $response;
    }
}
