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

    private int $nextCodeId = 1;

    public function observe(string $installationId, string $sourceInstallationId, int $facilityId): void
    {
        $this->installations[$installationId] = [
            'installation_id' => $installationId,
            'source_installation_id' => $sourceInstallationId,
            'facility_id' => $facilityId,
            'display_name' => 'Relayed installation',
            'credential_hash' => null,
            'credential_scopes' => null,
            'credential_version' => 1,
            'status' => 'observed',
            'claimed_at' => null,
            'reconnected_at' => null,
            'created_at' => null,
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
        string $createdBy,
        string $purpose = 'new',
        ?string $targetInstallationId = null
    ): int {
        if ($purpose === 'reconnect') {
            $target = $this->installations[$targetInstallationId ?? ''] ?? null;
            if ($target === null || (int) $target['facility_id'] !== $facilityId) {
                throw new InterfaceApiException(
                    'installation_not_found',
                    'The installation does not belong to this facility.',
                    404
                );
            }
        }
        $codeId = $this->nextCodeId++;
        $this->codes[$codeHash] = [
            'activation_code_id' => $codeId,
            'facility_id' => $facilityId,
            'purpose' => $purpose,
            'target_installation_id' => $targetInstallationId,
            'expires_at' => $expiresAt,
            'used_at' => null,
            'revoked_at' => null,
            'created_at' => $createdAt,
            'created_by' => $createdBy,
        ];
        return $codeId;
    }

    public function revokeActivationCode(
        int $activationCodeId,
        int $facilityId,
        DateTimeImmutable $revokedAt
    ): bool {
        foreach ($this->codes as &$code) {
            if (
                $code['activation_code_id'] === $activationCodeId
                && $code['facility_id'] === $facilityId
                && $code['used_at'] === null
                && $code['revoked_at'] === null
            ) {
                $code['revoked_at'] = $revokedAt;
                unset($code);
                return true;
            }
        }
        unset($code);
        return false;
    }

    public function activate(
        string $codeHash,
        string $installationId,
        ?string $sourceInstallationId,
        ?string $displayName,
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

        if ($code['purpose'] === 'reconnect') {
            $target = $this->installations[(string) $code['target_installation_id']] ?? null;
            if ($target === null || (int) $target['facility_id'] !== (int) $code['facility_id']) {
                throw new InterfaceApiException(
                    'reconnect_facility_conflict',
                    'The reconnect code is not valid for this facility installation.',
                    409
                );
            }
            $installationId = (string) $target['installation_id'];
            $sourceInstallationId = (string) $target['source_installation_id'];
            $displayName = (string) $target['display_name'];
            $target['credential_hash'] = $credentialHash;
            $target['credential_scopes'] = array_values($scopes);
            $target['credential_version'] = (int) $target['credential_version'] + 1;
            $target['status'] = 'active';
            $target['reconnected_at'] = $now;
            $target['revoked_at'] = null;
            $this->installations[$installationId] = $target;
            $this->codes[$codeHash]['used_at'] = $now;
            return $target;
        }

        if (
            preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', (string) $sourceInstallationId) !== 1
            || $displayName === null
            || $displayName === ''
        ) {
            throw new InterfaceApiException('invalid_activation_input', 'The activation input is invalid.', 422);
        }
        $existing = $this->findBySource($sourceInstallationId);
        if ($existing !== null) {
            throw new InterfaceApiException(
                (int) $existing['facility_id'] === (int) $code['facility_id']
                    ? 'source_already_registered'
                    : 'source_facility_conflict',
                'This source installation is already registered.',
                409
            );
        }

        $installation = [
            'installation_id' => $installationId,
            'source_installation_id' => $sourceInstallationId,
            'facility_id' => (int) $code['facility_id'],
            'display_name' => $displayName,
            'credential_hash' => $credentialHash,
            'credential_scopes' => array_values($scopes),
            'credential_version' => 1,
            'status' => 'active',
            'claimed_at' => $now,
            'reconnected_at' => null,
            'created_at' => $now,
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

    public function revokeForFacility(
        string $installationId,
        int $facilityId,
        DateTimeImmutable $revokedAt
    ): bool {
        if (
            !isset($this->installations[$installationId])
            || (int) $this->installations[$installationId]['facility_id'] !== $facilityId
        ) {
            return false;
        }
        return $this->revoke($installationId, $revokedAt);
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
