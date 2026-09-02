<?php

namespace App\Middlewares\Api;

use Override;
use App\Services\UsersService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

readonly class ApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private UsersService $usersService)
    {
    }
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
            $response->getBody()->write(json_encode([
                'error' => _translate('Unauthorized Access. Please contact your system administrator.'),
                'timestamp' => time()
            ]));
            return $response->withStatus(401);
        }

        // If the token is valid, proceed to the next middleware
        $response = $handler->handle($request);


        // Check if the token needs to be reset and get the new token
        $newToken = $this->checkAndResetTokenIfNeeded($token);

        if ($newToken !== null) {
            $response = self::withRotatedToken($response, $newToken);
        }

        return $response->withStatus(200);
    }

    /**
     * Put a rotated token into an already-built JSON response.
     *
     * InteLIS Mobile reads the top-level `token` key; `new_token` and
     * `token_updated` stay for anything written against the older shape. The body
     * is replaced rather than overwritten in place, because writing a longer JSON
     * over the old stream left the old Content-Length behind and the client cut
     * the payload short. A body that is not a JSON object is left untouched.
     */
    public static function withRotatedToken(ResponseInterface $response, string $newToken): ResponseInterface
    {
        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            return $response;
        }

        $decoded['token'] = $newToken;
        $decoded['new_token'] = $newToken;
        $decoded['token_updated'] = true;

        $json = json_encode($decoded);
        if ($json === false) {
            return $response;
        }

        return $response
            ->withBody((new StreamFactory())->createStream($json))
            ->withHeader('Content-Length', (string) strlen($json));
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
        if ($token === null || $token === '' || $token === '0') {
            return false;
        }

        return $this->usersService->validateAuthToken($token);
    }

    private function checkAndResetTokenIfNeeded(string $token): ?string
    {
        $user = $this->usersService->handleTokenAuthentication($token);
        if ($user !== null && $user !== [] && isset($user['token_updated']) && $user['token_updated'] === true) {
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
            '/api/v1.1/health',
            '/api/v1.1/user/login.php',
            '/api/v1.1/version.php',
            '/api/version.php',
            // Add other routes to exclude from the authentication check here
        ];


        // The Interface API has independent per-installation authentication.
        // Never route these credentials through legacy user/STS token handling.
        if ($uri === '/api/v1/interface' || str_starts_with($uri, '/api/v1/interface/')) {
            return true;
        }


        if (in_array($uri, $excludedRoutes, true)) {
            return true;
        }

        $input = $request->getParsedBody();
        return $uri === '/api/v1.1/user/save-user-profile.php' && !empty($input['x-api-key']);
    }
}
