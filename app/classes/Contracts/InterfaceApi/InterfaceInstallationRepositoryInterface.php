<?php

declare(strict_types=1);

namespace App\Contracts\InterfaceApi;

use DateTimeImmutable;

interface InterfaceInstallationRepositoryInterface
{
    public function createActivationCode(
        int $facilityId,
        string $codeHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        string $createdBy,
        string $purpose = 'new',
        ?string $targetInstallationId = null
    ): int;

    public function revokeActivationCode(
        int $activationCodeId,
        int $facilityId,
        DateTimeImmutable $revokedAt
    ): bool;

    /**
     * Atomically consumes an activation code and creates or claims an installation.
     *
     * @param list<string> $scopes
     * @return array<string, mixed>
     */
    public function activate(
        string $codeHash,
        string $installationId,
        ?string $sourceInstallationId,
        ?string $displayName,
        string $credentialHash,
        array $scopes,
        DateTimeImmutable $now
    ): array;

    /** @return array<string, mixed>|null */
    public function findInstallation(string $installationId): ?array;

    public function touchLastSeen(string $installationId, DateTimeImmutable $seenAt): void;

    public function revoke(string $installationId, DateTimeImmutable $revokedAt): bool;

    public function revokeForFacility(
        string $installationId,
        int $facilityId,
        DateTimeImmutable $revokedAt
    ): bool;

    /** @return list<array<string, mixed>> */
    public function listInstallations(?int $facilityId = null): array;
}
