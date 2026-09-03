<?php

declare(strict_types=1);

namespace App\HttpHandlers\Api\V2;

use App\Http\ApiV2Response;
use App\Services\ApiService;
use App\Services\STS\TokensService;
use App\Services\UserProfileSyncService;
use App\Services\UsersService;
use App\Utilities\LoggerUtility;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /api/v2/user/profile
 *
 * The strict replacement for /api/v1.1/user/save-user-profile.php, which
 * skipped authentication for any body carrying an x-api-key. A caller is one
 * of two things, tried strongest first:
 *
 *   a lab   Bearer is the lab's STS token and the body carries its labId;
 *           validated the way results.php validates a results sync.
 *   a user  Bearer is an API user's token.
 *
 * Anything else is 401. The body is JSON: {"labId": 12, "profile": {...}}.
 * What a profile may change is decided by UserProfileSyncService, not here.
 */
final readonly class SaveUserProfileHandler
{
    public function __construct(
        private UsersService $users,
        private TokensService $tokens,
        private UserProfileSyncService $profiles,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $decoded = json_decode((string) $request->getBody(), true);
            $body = is_array($decoded) ? $decoded : [];
        }

        $token = ApiService::extractBearerToken($request);
        $labId = (int) ($body['labId'] ?? 0);

        $caller = $this->authenticate($token, $labId);
        if ($caller === null) {
            return ApiV2Response::error(
                'invalid_credential',
                'A valid user token, or a lab token with its labId, is required.',
                401
            );
        }

        $profile = $body['profile'] ?? null;
        if (!is_array($profile) || $profile === []) {
            return ApiV2Response::error('invalid_request', 'profile is required.', 400);
        }

        try {
            $result = $this->profiles->receive($profile);
        } catch (InvalidArgumentException $e) {
            return ApiV2Response::error('invalid_request', $e->getMessage(), 400);
        } catch (Throwable $e) {
            LoggerUtility::logError('v2 user profile: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiV2Response::error('server_error', 'The profile could not be saved. Please try again later.', 500);
        }

        return ApiV2Response::success($result + ['caller' => $caller]);
    }

    /** @return string|null 'user' or 'lab', null when neither credential holds */
    private function authenticate(?string $token, int $labId): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }
        if ($this->users->validateAuthToken($token)) {
            return 'user';
        }
        if ($labId > 0 && $this->tokens->validateToken($token, $labId)) {
            return 'lab';
        }
        return null;
    }
}
