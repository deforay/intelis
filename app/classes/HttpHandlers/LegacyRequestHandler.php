<?php

namespace App\HttpHandlers;

use Override;
use Throwable;
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
        try {

            $filePath = $this->sanitizePath($request);

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
            ob_end_clean(); // Clean the buffer in case of an error
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
            LoggerUtility::logError("Invalid Request : $resolvedPath");
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
