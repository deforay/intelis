<?php

namespace App\Middleware\Api;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class ApiAuthMiddleware implements MiddlewareInterface
{
    private \App\Services\UserService $userModel;

    public function __construct($userModel)
    {
        $this->userModel = $userModel;
    }
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): Response
    {
        
        if ($this->shouldExcludeFromAuthCheck($request)) {

            // Skip the authentication check if the request is an AJAX request,
            // a CLI request, or if the requested URI is excluded from the
            // authentication check
            return $handler->handle($request);
        }
        $authorization = $request->getHeaderLine('Authorization');
        $token = $this->getTokenFromAuthorizationHeader($authorization);

        $tokenValidation = $this->validateToken($token);

        if (false === $tokenValidation) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401);
        }

        // If the token is valid, proceed to the next middleware
        return $handler->handle($request);
    }

    private function getTokenFromAuthorizationHeader(string $authorization): ?string
    {
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function validateToken(string $token): bool
    {
        if ($token === false) {
            return false;
        }

        return $this->userModel->validateAuthToken($token);
    }

    private function shouldExcludeFromAuthCheck(ServerRequestInterface $request): bool
    {
        // Get the requested URI
        $uri = $request->getUri()->getPath();

        // Clean up the URI
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);


        //error_log($uri);

        $excludedRoutes = [
            '/api/v1.1/user/login.php',
            // Add other routes to exclude from the authentication check here
        ];

        return in_array($uri, $excludedRoutes, true);
    }
}
