<?php

namespace App\Middleware;

use App\Models\General;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LegacyFallbackMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $db = \MysqliDb::getInstance();
        $general = new General();

        // First, try to handle the request with the next middleware in the pipeline
        $response = $handler->handle($request);

        // If the response status is 404 (not found), it means no matching Slim route was found
        if ($response->getStatusCode() === 404) {
            // Get the requested URI
            $uri = $request->getUri()->getPath();

            // Clean up the URI
            $uri = preg_replace('/([\/.])\1+/', '$1', $uri);
            $uri = trim(parse_url($uri, PHP_URL_PATH), "/");

            //error_log("RequestHandler 1 :::" . $uri);

            switch ($uri) {
                case null:
                case '':
                    $fileToInclude = APPLICATION_PATH . '/index.php';
                    break;
                default:
                    if (is_dir(APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri)) {
                        $fileToInclude = (APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri . '/index.php');
                    } elseif (is_file(APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri)) {
                        $fileToInclude = (APPLICATION_PATH . DIRECTORY_SEPARATOR . $uri);
                    } else {
                        http_response_code(404);
                        $fileToInclude = APPLICATION_PATH . '/error/404.php';
                    }
                    break;
            }

            // Capture output buffer to prevent it from being sent directly
            ob_start();
            require_once $fileToInclude;
            // Get the output buffer content and clean the buffer
            $output = ob_get_clean();

            // Check if there's a Location header in the headers_list()
            $location = null;
            foreach (headers_list() as $header) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, strlen('Location:')));
                    break;
                }
            }

            // If a Location header is found, create a new RedirectResponse
            if ($location !== null) {
                header_remove('Location');
                return new RedirectResponse($location);
            } else {
                // Create a new response with the captured output
                $response = new Response();

                // Update the response status to 200 (OK) since the legacy code was executed
                $response = $response->withStatus(200);
                $response->getBody()->write($output);

                return $response;
            }
        }

        return $response;
    }
}
