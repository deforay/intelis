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

    public function __construct(
        private InterfaceInstallationRepositoryInterface $repository,
        private InterfaceCredentialService $credentials
    ) {
    }

    /** @return array{activationCode: string, expiresAt: string} */
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

        $this->repository->createActivationCode(
            $facilityId,
            $this->activationCodeHash($plainCode),
            $expiresAt,
            $now,
            mb_substr($createdBy, 0, 255)
        );

        return [
            'activationCode' => $this->formatActivationCode($plainCode),
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ];
    }

    /**
     * @return array{
     *     installationId: string,
     *     sourceInstallationId: string,
     *     displayName: string,
     *     credential: string,
     *     scopes: list<string>
     * }
     */
    public function activate(
        string $activationCode,
        string $sourceInstallationId,
        string $displayName,
        ?DateTimeImmutable $now = null
    ): array {
        $sourceInstallationId = trim($sourceInstallationId);
        $displayName = trim($displayName);
        $this->validateActivationInput($activationCode, $sourceInstallationId, $displayName);

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

    /** @return list<array<string, mixed>> */
    public function listInstallations(?int $facilityId = null): array
    {
        return $this->repository->listInstallations($facilityId);
    }

    private function validateActivationInput(string $code, string $sourceId, string $displayName): void
    {
        if (strlen($this->normalizeActivationCode($code)) !== 32) {
            throw new InterfaceApiException('invalid_activation_code', 'The activation code is invalid.', 422);
        }
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $sourceId) !== 1) {
            throw new InterfaceApiException(
                'invalid_source_installation_id',
                'The source installation ID is invalid.',
                422
            );
        }
        if ($displayName === '' || mb_strlen($displayName) > 150) {
            throw new InterfaceApiException(
                'invalid_display_name',
                'The display name must be between 1 and 150 characters.',
                422
            );
        }
    }

    private function generateActivationCode(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $code = '';
        for ($i = 0; $i < 32; $i++) {
            $code .= $alphabet[random_int(0, 31)];
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
