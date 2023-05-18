<?php

namespace App\Middlewares\Api;

use App\Services\UsersService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class ApiAuthMiddleware implements MiddlewareInterface
{
    private UsersService $userModel;

    public function __construct($userModel)
    {
        $this->userModel = $userModel;
    }
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        if ($this->shouldExcludeFromAuthCheck($request) === true) {

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
        $response = $handler->handle($request);


        // Check if the token needs to be reset and get the new token
        $newToken = $this->checkAndResetTokenIfNeeded($token);

        if ($newToken !== null) {
            // Add the new_token to the response object
            $responseBody = json_decode($response->getBody(), true);
            $responseBody['new_token'] = $newToken;
            $responseBody['token_updated'] = true;
            $response->getBody()->rewind();
            $response->getBody()->write(json_encode($responseBody));
        }

        return $response->withStatus(200);
    }

    private function getTokenFromAuthorizationHeader(string $authorization): ?string
    {
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function validateToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        return $this->userModel->validateAuthToken($token);
    }

    private function checkAndResetTokenIfNeeded(string $token): ?string
    {
        $user = $this->userModel->getAuthToken($token);
        if (isset($user['token_updated']) && $user['token_updated'] === true) {
            return $user['new_token'];
        } else {
            return null;
        }
    }

    private function shouldExcludeFromAuthCheck(ServerRequestInterface $request): bool
    {
        // Get the requested URI
        $uri = $request->getUri()->getPath();

        // Clean up the URI
        $uri = preg_replace('/([\/.])\1+/', '$1', $uri);

        $excludedRoutes = [
            '/api/v1.1/user/login.php',
            '/api/v1.1/version.php',
            '/api/version.php',
            // Add other routes to exclude from the authentication check here
        ];


        if (in_array($uri, $excludedRoutes, true)) {
            return true;
        }

        $input = $request->getParsedBody();
        if ($uri === '/api/v1.1/user/save-user-profile.php' && !empty($input['x-api-key'])) {
            return true;
        }

        return false;
    }
}
