<?php

declare(strict_types=1);

namespace App\Services\InterfaceApi;

use App\Contracts\InterfaceApi\InterfaceInstallationRepositoryInterface;
use App\Exceptions\InterfaceApiException;
use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class InterfaceInstallationService
{
    public const SCOPES = [
        'connection:read',
        'results:write',
        'telemetry:write',
    ];

    /**
     * Connection Codes are short enough to read off a screen and type by hand. Twelve
     * Crockford Base32 characters give ~60 bits of entropy, which is only safe because
     * the surrounding controls bound how many guesses an attacker gets:
     *
     * - single use, consumed atomically by the repository;
     * - a 30 minute expiry;
     * - InterfaceActivationGuardMiddleware rate limits /activate per client IP
     *   (InterfaceConnectionService::ACTIVATION_MAX_ATTEMPTS per ACTIVATION_WINDOW_SECONDS).
     *
     * Do not lengthen the expiry, relax the rate limit or shorten the code without
     * redoing that arithmetic.
     */
    public const ACTIVATION_CODE_LENGTH = 12;
    public const ACTIVATION_CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(
        private InterfaceInstallationRepositoryInterface $repository,
        private InterfaceCredentialService $credentials
    ) {
    }

    /** @return array{activationCodeId: int, activationCode: string, expiresAt: string, purpose: string} */
    public function createActivationCode(
        int $facilityId,
        int $ttlMinutes = 30,
        string $createdBy = 'cli',
        ?DateTimeImmutable $now = null
    ): array {
        if ($facilityId <= 0) {
            throw new InterfaceApiException('invalid_facility', 'A valid facility is required.', 422);
        }

        $now ??= new DateTimeImmutable();
        $ttlMinutes = max(5, min($ttlMinutes, 1440));
        $plainCode = $this->generateActivationCode();
        $expiresAt = $now->add(new DateInterval('PT' . $ttlMinutes . 'M'));

        $activationCodeId = $this->repository->createActivationCode(
            $facilityId,
            $this->activationCodeHash($plainCode),
            $expiresAt,
            $now,
            mb_substr($createdBy, 0, 255),
            'new'
        );

        return [
            'activationCodeId' => $activationCodeId,
            'activationCode' => $this->formatActivationCode($plainCode),
            'expiresAt' => $expiresAt->format(DATE_ATOM),
            'purpose' => 'new',
        ];
    }

    /** @return array{activationCodeId: int, activationCode: string, expiresAt: string, purpose: string} */
    public function createReconnectCode(
        int $facilityId,
        string $installationId,
        int $ttlMinutes = 30,
        string $createdBy = 'cli',
        ?DateTimeImmutable $now = null
    ): array {
        if ($facilityId <= 0 || !$this->isUuid($installationId)) {
            throw new InterfaceApiException('invalid_installation', 'A valid installation is required.', 422);
        }

        $now ??= new DateTimeImmutable();
        $ttlMinutes = max(5, min($ttlMinutes, 60));
        $plainCode = $this->generateActivationCode();
        $expiresAt = $now->add(new DateInterval('PT' . $ttlMinutes . 'M'));
        $activationCodeId = $this->repository->createActivationCode(
            $facilityId,
            $this->activationCodeHash($plainCode),
            $expiresAt,
            $now,
            mb_substr($createdBy, 0, 255),
            'reconnect',
            $installationId
        );

        return [
            'activationCodeId' => $activationCodeId,
            'activationCode' => $this->formatActivationCode($plainCode),
            'expiresAt' => $expiresAt->format(DATE_ATOM),
            'purpose' => 'reconnect',
        ];
    }

    /**
     * @return array{
     *     installationId: string,
     *     sourceInstallationId: string,
     *     displayName: string,
     *     credential: string,
     *     scopes: list<string>,
     *     credentialVersion: int
     * }
     */
    public function activate(
        string $activationCode,
        ?string $sourceInstallationId,
        ?string $displayName,
        ?DateTimeImmutable $now = null
    ): array {
        $sourceInstallationId = $sourceInstallationId !== null ? trim($sourceInstallationId) : null;
        $displayName = $displayName !== null ? trim($displayName) : null;
        $this->validateActivationCode($activationCode);

        $now ??= new DateTimeImmutable();
        $proposedInstallationId = Uuid::v4()->toRfc4122();
        $credential = $this->credentials->issueSecret();

        $installation = $this->repository->activate(
            $this->activationCodeHash($activationCode),
            $proposedInstallationId,
            $sourceInstallationId,
            $displayName,
            $credential['hash'],
            self::SCOPES,
            $now
        );

        $installationId = (string) $installation['installation_id'];

        return [
            'installationId' => (string) $installation['installation_id'],
            'sourceInstallationId' => (string) $installation['source_installation_id'],
            'displayName' => (string) $installation['display_name'],
            'credential' => $this->credentials->formatToken($installationId, $credential['secret']),
            'scopes' => self::SCOPES,
            'credentialVersion' => (int) ($installation['credential_version'] ?? 1),
        ];
    }

    /** @return array<string, mixed> */
    public function authenticate(string $token, string $requiredScope): array
    {
        $parsed = $this->credentials->parse(trim($token));
        if ($parsed === null) {
            throw $this->unauthorized();
        }

        $installation = $this->repository->findInstallation($parsed['installationId']);
        if ($installation === null || ($installation['status'] ?? null) !== 'active') {
            throw $this->unauthorized();
        }

        $presentedHash = hash('sha256', $parsed['secret']);
        if (!hash_equals((string) $installation['credential_hash'], $presentedHash)) {
            throw $this->unauthorized();
        }

        $scopes = $this->decodeScopes($installation['credential_scopes'] ?? []);
        if (!in_array($requiredScope, $scopes, true)) {
            throw new InterfaceApiException(
                'insufficient_scope',
                'The credential does not permit this operation.',
                403
            );
        }

        $this->repository->touchLastSeen((string) $installation['installation_id'], new DateTimeImmutable());
        $installation['credential_scopes'] = $scopes;

        return $installation;
    }

    public function revoke(string $installationId, ?DateTimeImmutable $now = null): bool
    {
        return $this->repository->revoke($installationId, $now ?? new DateTimeImmutable());
    }

    public function revokeForFacility(
        string $installationId,
        int $facilityId,
        ?DateTimeImmutable $now = null
    ): bool {
        if ($facilityId <= 0 || !$this->isUuid($installationId)) {
            return false;
        }
        return $this->repository->revokeForFacility(
            $installationId,
            $facilityId,
            $now ?? new DateTimeImmutable()
        );
    }

    public function revokeActivationCode(
        int $activationCodeId,
        int $facilityId,
        ?DateTimeImmutable $now = null
    ): bool {
        if ($activationCodeId <= 0 || $facilityId <= 0) {
            return false;
        }
        return $this->repository->revokeActivationCode(
            $activationCodeId,
            $facilityId,
            $now ?? new DateTimeImmutable()
        );
    }

    /** @return list<array<string, mixed>> */
    public function listInstallations(?int $facilityId = null): array
    {
        $installations = $this->repository->listInstallations($facilityId);
        foreach ($installations as &$installation) {
            $installation['credential_scopes'] = $this->decodeScopes(
                $installation['credential_scopes'] ?? []
            );
        }
        unset($installation);
        return $installations;
    }

    /** @return array<string, mixed>|null */
    public function getInstallation(string $installationId): ?array
    {
        return $this->repository->findInstallation($installationId);
    }

    private function validateActivationCode(string $code): void
    {
        // Accept grouped, ungrouped, lowercase and look-alike input, but reject stray
        // punctuation outright rather than silently stripping it during normalization.
        if (preg_match('/^[0-9A-Za-z]{4}(?:[- ]?[0-9A-Za-z]{4}){2}$/D', trim($code)) !== 1) {
            throw new InterfaceApiException('invalid_activation_code', 'The activation code is invalid.', 422);
        }

        $normalized = $this->normalizeActivationCode($code);
        $canonical = '/^[' . preg_quote(self::ACTIVATION_CODE_ALPHABET, '/') . ']{'
            . self::ACTIVATION_CODE_LENGTH . '}$/D';

        if (preg_match($canonical, $normalized) !== 1) {
            throw new InterfaceApiException('invalid_activation_code', 'The activation code is invalid.', 422);
        }
    }

    private function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',
            $value
        ) === 1;
    }

    private function generateActivationCode(): string
    {
        $code = '';
        $maxIndex = strlen(self::ACTIVATION_CODE_ALPHABET) - 1;
        for ($i = 0; $i < self::ACTIVATION_CODE_LENGTH; $i++) {
            $code .= self::ACTIVATION_CODE_ALPHABET[random_int(0, $maxIndex)];
        }
        return $code;
    }

    private function normalizeActivationCode(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $code) ?? '');
        return strtr($normalized, ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);
    }

    private function activationCodeHash(string $code): string
    {
        return hash('sha256', $this->normalizeActivationCode($code));
    }

    private function formatActivationCode(string $code): string
    {
        return trim(chunk_split($code, 4, '-'), '-');
    }

    /** @return list<string> */
    private function decodeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $scopes = json_decode($scopes, true);
        }

        return is_array($scopes)
            ? array_values(array_filter($scopes, static fn(mixed $scope): bool => is_string($scope)))
            : [];
    }

    private function unauthorized(): InterfaceApiException
    {
        return new InterfaceApiException(
            'invalid_credential',
            'The installation credential is invalid or revoked.',
            401
        );
    }
}
