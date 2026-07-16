<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\InterfaceApi\InterfaceInstallationRepositoryInterface;
use App\Exceptions\InterfaceApiException;
use DateTimeImmutable;

final class InMemoryInterfaceInstallationRepository implements InterfaceInstallationRepositoryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $codes = [];

    /** @var array<string, array<string, mixed>> */
    private array $installations = [];

    public function observe(string $installationId, string $sourceInstallationId, int $facilityId): void
    {
        $this->installations[$installationId] = [
            'installation_id' => $installationId,
            'source_installation_id' => $sourceInstallationId,
            'facility_id' => $facilityId,
            'display_name' => 'Relayed installation',
            'credential_hash' => null,
            'credential_scopes' => null,
            'status' => 'observed',
            'claimed_at' => null,
            'last_seen_at' => null,
            'revoked_at' => null,
        ];
    }

    /** @param list<string> $scopes */
    public function setScopes(string $installationId, array $scopes): void
    {
        $this->installations[$installationId]['credential_scopes'] = $scopes;
    }

    public function createActivationCode(
        int $facilityId,
        string $codeHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
        string $createdBy
    ): void {
        $this->codes[$codeHash] = [
            'facility_id' => $facilityId,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'revoked_at' => null,
            'created_at' => $createdAt,
            'created_by' => $createdBy,
        ];
    }

    public function activate(
        string $codeHash,
        string $installationId,
        string $sourceInstallationId,
        string $displayName,
        string $credentialHash,
        array $scopes,
        DateTimeImmutable $now
    ): array {
        $code = $this->codes[$codeHash] ?? null;
        if ($code === null) {
            throw new InterfaceApiException('invalid_activation_code', 'The activation code is invalid.', 422);
        }
        if ($code['used_at'] !== null) {
            throw new InterfaceApiException('activation_code_used', 'The activation code has already been used.', 409);
        }
        if ($code['revoked_at'] !== null) {
            throw new InterfaceApiException('activation_code_revoked', 'The activation code has been revoked.', 409);
        }
        if ($code['expires_at'] <= $now) {
            throw new InterfaceApiException('activation_code_expired', 'The activation code has expired.', 410);
        }

        $existing = $this->findBySource($sourceInstallationId);
        if ($existing !== null && (int) $existing['facility_id'] !== (int) $code['facility_id']) {
            throw new InterfaceApiException(
                'source_facility_conflict',
                'This source installation is already registered to another facility.',
                409
            );
        }

        if ($existing !== null) {
            $installationId = (string) $existing['installation_id'];
        }

        $installation = [
            'installation_id' => $installationId,
            'source_installation_id' => $sourceInstallationId,
            'facility_id' => (int) $code['facility_id'],
            'display_name' => $displayName,
            'credential_hash' => $credentialHash,
            'credential_scopes' => array_values($scopes),
            'status' => 'active',
            'claimed_at' => $now,
            'last_seen_at' => null,
            'revoked_at' => null,
        ];
        $this->installations[$installationId] = $installation;
        $this->codes[$codeHash]['used_at'] = $now;

        return $installation;
    }

    public function findInstallation(string $installationId): ?array
    {
        return $this->installations[$installationId] ?? null;
    }

    public function touchLastSeen(string $installationId, DateTimeImmutable $seenAt): void
    {
        if (isset($this->installations[$installationId])) {
            $this->installations[$installationId]['last_seen_at'] = $seenAt;
        }
    }

    public function revoke(string $installationId, DateTimeImmutable $revokedAt): bool
    {
        if (!isset($this->installations[$installationId])) {
            return false;
        }
        $this->installations[$installationId]['status'] = 'revoked';
        $this->installations[$installationId]['revoked_at'] = $revokedAt;
        return true;
    }

    public function listInstallations(?int $facilityId = null): array
    {
        return array_values(array_filter(
            $this->installations,
            static fn(array $installation): bool => $facilityId === null
                || (int) $installation['facility_id'] === $facilityId
        ));
    }

    /** @return array<string, mixed>|null */
    private function findBySource(string $sourceInstallationId): ?array
    {
        foreach ($this->installations as $installation) {
            if ($installation['source_installation_id'] === $sourceInstallationId) {
                return $installation;
            }
        }
        return null;
    }
}
