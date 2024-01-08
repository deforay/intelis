<?php

namespace App\HttpHandlers;

use Exception;
use Throwable;
use App\Services\CommonService;
use App\Registries\AppRegistry;
use Laminas\Diactoros\Response;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Utilities\LoggerUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class LegacyRequestHandler implements RequestHandlerInterface
{
    private $dbService;
    private $commonService;

    public function __construct(DatabaseService $dbService, CommonService $commonService)
    {
        $this->dbService = $dbService;
        $this->commonService = $commonService;
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $fileToInclude = null;
            // Capture output buffer to prevent it from being sent directly
            ob_start();

            // Creating $db and $general variables to make them available in the included file
            $db = $this->dbService;
            $general = $this->commonService;

            $fileToInclude = $this->determineFileToInclude($request);
            require_once $fileToInclude;

            // Get the output buffer content and clean the buffer
            $output = ob_get_clean();
            return $this->createResponse($output);
        } catch (SystemException | Exception $e) {
            ob_end_clean(); // Clean the buffer in case of an error
            LoggerUtility::log('error', "Error in $fileToInclude : " . $e->getFile() . ":" .  $e->getLine() . ":" . $e->getMessage());
            throw new SystemException("Could not process the request", 500, $e);
        } catch (Throwable $e) {
            ob_end_clean(); // Clean the buffer in case of an error
            LoggerUtility::log('error', "Error in $fileToInclude : " . $e->getFile() . ":" .  $e->getLine() . ":" . $e->getMessage());
            throw new SystemException("Could not process the request", 500);
        }
    }


    private function determineFileToInclude(ServerRequestInterface $request): string
    {
        $uri = $request->getUri()->getPath();
        $uri = filter_var($uri, FILTER_SANITIZE_URL);
        $uri = trim(parse_url($uri, PHP_URL_PATH), "/");

        AppRegistry::set('request', $request);

        if ($uri === '' || $uri === null) {
            return APPLICATION_PATH . '/index.php';
        }

        // Resolve the absolute path and ensure it's within the APPLICATION_PATH
        $resolvedPath = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri);
        if (!$resolvedPath || strpos($resolvedPath, realpath(APPLICATION_PATH)) !== 0) {
            LoggerUtility::log('error', 'Invalid file path: ' . $resolvedPath);
            throw new SystemException('Invalid file path', 403);
        }

        if (is_dir($resolvedPath)) {
            return $resolvedPath . '/index.php';
        } elseif (is_file($resolvedPath)) {
            return $resolvedPath;
        } else {
            throw new SystemException(_translate('Sorry! We could not find this page or resource') . ' - ' . $uri, 404);
        }
    }


    private function createResponse($output): ResponseInterface
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
                return new RedirectResponse($location);
            }
        }

        return $response;
    }
}
