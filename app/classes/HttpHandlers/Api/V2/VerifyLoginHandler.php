<?php

declare(strict_types=1);

namespace App\HttpHandlers\Api\V2;

use App\Http\ApiV2Response;
use App\Registries\ContainerRegistry;
use App\Services\ApiService;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Utilities\LoggerUtility;
use App\Utilities\RateLimitUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * POST /api/v2/user/verify-login
 *
 * Lets Recency sign its users in with their InteLIS login and password, so a
 * user has one password for both apps and InteLIS is the only place it is kept.
 * Recency sends {"loginId": "...", "password": "..."} and learns whether they
 * are right; nothing else changes here. No session is started, and no API token
 * is issued or rotated.
 *
 * Off unless the install sets recency.authKey, so other installs answer 404.
 * The caller proves it is Recency by sending that key as its Bearer token.
 *
 * A wrong login and a wrong password are one answer. Failures go into the same
 * login history as the web sign-in, so they count towards its captcha, and five
 * since the last successful sign-in within 15 minutes lock the login here.
 */
final readonly class VerifyLoginHandler
{
    /** A shorter key is treated as no key: the endpoint stays off. */
    public const int MIN_KEY_LENGTH = 32;

    public const int LOCK_AFTER_FAILURES = 5;
    public const int LOCK_MINUTES = 15;

    /** Requests a minute from one address, valid or not. */
    public const int MAX_REQUESTS_PER_MINUTE = 60;

    public function __construct(
        private UsersService $users,
        private DatabaseService $db,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $key = (string) (ContainerRegistry::get('applicationConfig')['recency']['authKey'] ?? '');
        if (strlen($key) < self::MIN_KEY_LENGTH) {
            return ApiV2Response::error('not_found', _translate('Not found.'), 404);
        }

        $bucket = 'verify-login:' . RateLimitUtility::clientIp();
        if (RateLimitUtility::exceeded($bucket, self::MAX_REQUESTS_PER_MINUTE, 60)) {
            return ApiV2Response::error('rate_limited', _translate('Too many requests. Please try again later.'), 429);
        }

        $token = (string) (ApiService::extractBearerToken($request) ?? '');
        if (!hash_equals($key, $token)) {
            return ApiV2Response::error('invalid_credential', _translate('A valid key is required.'), 401);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $decoded = json_decode((string) $request->getBody(), true);
            $body = is_array($decoded) ? $decoded : [];
        }
        $loginId = is_string($body['loginId'] ?? null) ? trim($body['loginId']) : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        if ($loginId === '' || $password === '') {
            return ApiV2Response::error('invalid_request', _translate('loginId and password are required.'), 400);
        }

        try {
            return ApiV2Response::success($this->verify($loginId, $password));
        } catch (Throwable $e) {
            LoggerUtility::logError('v2 verify-login: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return ApiV2Response::error(
                'server_error',
                _translate('The login could not be checked. Please try again later.'),
                500
            );
        }
    }

    /** @return array<string, mixed> */
    private function verify(string $loginId, string $password): array
    {
        $failures = $this->users->failedLoginsSinceLastSuccess($loginId, self::LOCK_MINUTES);
        if ($failures['count'] >= self::LOCK_AFTER_FAILURES) {
            // The password is not checked at all, so guessing gets no further
            return ['verified' => false, 'reason' => 'locked'];
        }

        $users = $this->db->rawQuery(
            "SELECT user_id, user_name, email, password, force_password_reset
            FROM user_details WHERE login_id = ? AND status = 'active'",
            [$loginId]
        );
        // login_id is not unique in the table: two active users with one login
        // cannot say whose password this is
        $user = count($users) === 1 ? $users[0] : null;

        if ($user === null || !$this->users->passwordVerify($loginId, $password, (string) $user['password'])) {
            $this->record($loginId, 'failed', $user['user_id'] ?? null);
            return ['verified' => false, 'reason' => 'invalid_credentials'];
        }

        if ((int) ($user['force_password_reset'] ?? 0) === 1) {
            // The user has to set a new password in InteLIS first
            return ['verified' => false, 'reason' => 'password_reset_required'];
        }

        $this->record($loginId, 'successful', $user['user_id']);
        return [
            'verified' => true,
            'user' => [
                'loginId' => $loginId,
                'name' => (string) ($user['user_name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
            ],
        ];
    }

    private function record(string $loginId, string $status, ?string $userId): void
    {
        // The answer stands even if the history row cannot be written
        try {
            $this->users->recordLoginAttempt($loginId, $status, $userId);
        } catch (Throwable $e) {
            LoggerUtility::logError('v2 verify-login could not record the attempt: ' . $e->getMessage());
        }
    }
}
