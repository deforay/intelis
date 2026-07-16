<?php

declare(strict_types=1);

namespace App\Repositories\InterfaceApi;

use App\Contracts\InterfaceApi\InterfaceInstallationRepositoryInterface;
use App\Exceptions\InterfaceApiException;
use App\Services\DatabaseService;
use DateTimeImmutable;
use Throwable;

final readonly class MySqlInterfaceInstallationRepository implements InterfaceInstallationRepositoryInterface
{
    public function __construct(private DatabaseService $db)
    {
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
        $facility = $this->db->rawQueryOne(
            "SELECT facility_id FROM facility_details WHERE facility_id = ? AND status = 'active' LIMIT 1",
            [$facilityId]
        );
        if (empty($facility)) {
            throw new InterfaceApiException('invalid_facility', 'The facility does not exist or is inactive.', 422);
        }

        if (!in_array($purpose, ['new', 'reconnect'], true)) {
            throw new InterfaceApiException('invalid_code_purpose', 'The connection-code purpose is invalid.', 422);
        }
        if ($purpose === 'reconnect') {
            $target = $this->db->rawQueryOne(
                'SELECT installation_id FROM interface_installations
                  WHERE installation_id = ? AND facility_id = ? LIMIT 1',
                [$targetInstallationId, $facilityId]
            );
            if (empty($target)) {
                throw new InterfaceApiException(
                    'installation_not_found',
                    'The installation does not belong to this facility.',
                    404
                );
            }
        }

        $id = $this->db->insert('interface_activation_codes', [
            'code_hash' => $codeHash,
            'facility_id' => $facilityId,
            'purpose' => $purpose,
            'target_installation_id' => $targetInstallationId,
            'expires_at' => $this->formatDate($expiresAt),
            'created_at' => $this->formatDate($createdAt),
            'created_by' => $createdBy,
        ]);
        if ($id === false) {
            throw new InterfaceApiException('activation_code_not_created', 'Unable to create an activation code.', 500);
        }
        return (int) $id;
    }

    public function revokeActivationCode(
        int $activationCodeId,
        int $facilityId,
        DateTimeImmutable $revokedAt
    ): bool {
        $this->db->where('activation_code_id', $activationCodeId);
        $this->db->where('facility_id', $facilityId);
        $this->db->where('used_at', null, 'IS');
        $this->db->where('revoked_at', null, 'IS');
        $updated = $this->db->update('interface_activation_codes', [
            'revoked_at' => $this->formatDate($revokedAt),
        ]);
        return $updated && $this->db->count > 0;
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
        $this->db->beginTransaction();
        try {
            $code = $this->db->rawQueryOne(
                'SELECT activation_code_id, facility_id, purpose, target_installation_id,
                        expires_at, used_at, revoked_at
                   FROM interface_activation_codes
                  WHERE code_hash = ?
                  FOR UPDATE',
                [$codeHash]
            );
            $this->assertActivationCodeIsUsable($code ?: null, $now);

            $facilityId = (int) $code['facility_id'];
            $facility = $this->db->rawQueryOne(
                "SELECT facility_id FROM facility_details WHERE facility_id = ? AND status = 'active' LIMIT 1",
                [$facilityId]
            );
            if (empty($facility)) {
                throw new InterfaceApiException('facility_unavailable', 'The activation facility is unavailable.', 409);
            }

            $nowSql = $this->formatDate($now);
            $scopesJson = json_encode(array_values($scopes), JSON_THROW_ON_ERROR);
            if (($code['purpose'] ?? 'new') === 'reconnect') {
                $target = $this->lockReconnectTarget($code, $facilityId);
                $installationId = (string) $target['installation_id'];
                $sourceInstallationId = (string) $target['source_installation_id'];
                $displayName = (string) $target['display_name'];
                $this->db->rawQuery(
                    "UPDATE interface_installations
                        SET credential_hash = ?, credential_scopes = ?, credential_version = credential_version + 1,
                            status = 'active', reconnected_at = ?, revoked_at = NULL, updated_at = ?
                      WHERE installation_id = ? AND facility_id = ?",
                    [$credentialHash, $scopesJson, $nowSql, $nowSql, $installationId, $facilityId]
                );
            } else {
                $this->assertNewInstallationInput($sourceInstallationId, $displayName);
                $existing = $this->db->rawQueryOne(
                    'SELECT installation_id, facility_id FROM interface_installations
                      WHERE source_installation_id = ? FOR UPDATE',
                    [$sourceInstallationId]
                );
                if (!empty($existing)) {
                    $errorCode = (int) $existing['facility_id'] === $facilityId
                        ? 'source_already_registered'
                        : 'source_facility_conflict';
                    throw new InterfaceApiException(
                        $errorCode,
                        'This source installation is already registered.',
                        409
                    );
                }
                $created = $this->db->insert('interface_installations', [
                    'installation_id' => $installationId,
                    'source_installation_id' => $sourceInstallationId,
                    'facility_id' => $facilityId,
                    'display_name' => $displayName,
                    'credential_hash' => $credentialHash,
                    'credential_scopes' => $scopesJson,
                    'credential_version' => 1,
                    'status' => 'active',
                    'claimed_at' => $nowSql,
                    'created_at' => $nowSql,
                    'updated_at' => $nowSql,
                ]);
                if ($created === false) {
                    throw new InterfaceApiException('activation_failed', 'Unable to activate this installation.', 500);
                }
            }

            $this->db->rawQuery(
                'UPDATE interface_activation_codes
                    SET used_at = ?, used_by_installation_id = ?
                  WHERE activation_code_id = ? AND used_at IS NULL',
                [$nowSql, $installationId, (int) $code['activation_code_id']]
            );

            $installation = $this->db->rawQueryOne(
                'SELECT * FROM interface_installations WHERE installation_id = ? LIMIT 1',
                [$installationId]
            );
            $this->db->commitTransaction();

            return $installation ?: throw new InterfaceApiException(
                'activation_failed',
                'Unable to activate this installation.',
                500
            );
        } catch (Throwable $exception) {
            $this->db->rollbackTransaction();
            throw $exception;
        }
    }

    public function findInstallation(string $installationId): ?array
    {
        $row = $this->db->rawQueryOne(
            'SELECT installation_id, source_installation_id, facility_id, display_name,
                    credential_hash, credential_scopes, credential_version, status,
                    claimed_at, reconnected_at, created_at, last_seen_at, revoked_at
               FROM interface_installations
              WHERE installation_id = ?
              LIMIT 1',
            [$installationId]
        );

        return $row ?: null;
    }

    public function touchLastSeen(string $installationId, DateTimeImmutable $seenAt): void
    {
        $threshold = $seenAt->modify('-5 minutes');
        $this->db->rawQuery(
            'UPDATE interface_installations
                SET last_seen_at = ?, updated_at = ?
              WHERE installation_id = ?
                AND (last_seen_at IS NULL OR last_seen_at < ?)',
            [
                $this->formatDate($seenAt),
                $this->formatDate($seenAt),
                $installationId,
                $this->formatDate($threshold),
            ]
        );
    }

    public function revoke(string $installationId, DateTimeImmutable $revokedAt): bool
    {
        if ($this->findInstallation($installationId) === null) {
            return false;
        }

        $this->db->where('installation_id', $installationId);
        $updated = $this->db->update('interface_installations', [
            'status' => 'revoked',
            'revoked_at' => $this->formatDate($revokedAt),
            'updated_at' => $this->formatDate($revokedAt),
        ]);
        return $updated && $this->db->count > 0;
    }

    public function revokeForFacility(
        string $installationId,
        int $facilityId,
        DateTimeImmutable $revokedAt
    ): bool {
        $this->db->where('installation_id', $installationId);
        $this->db->where('facility_id', $facilityId);
        $updated = $this->db->update('interface_installations', [
            'status' => 'revoked',
            'revoked_at' => $this->formatDate($revokedAt),
            'updated_at' => $this->formatDate($revokedAt),
        ]);
        return $updated && $this->db->count > 0;
    }

    public function listInstallations(?int $facilityId = null): array
    {
        $params = [];
        $where = '';
        if ($facilityId !== null) {
            $where = ' WHERE i.facility_id = ?';
            $params[] = $facilityId;
        }

        return $this->db->rawQuery(
            'SELECT i.installation_id, i.source_installation_id, i.facility_id,
                    f.facility_code, f.facility_name, i.display_name, i.status,
                    i.credential_scopes, i.credential_version, i.created_at,
                    i.claimed_at, i.reconnected_at, i.last_seen_at, i.revoked_at
               FROM interface_installations i
               JOIN facility_details f ON f.facility_id = i.facility_id' . $where . '
              ORDER BY i.facility_id, i.display_name, i.installation_id',
            $params ?: null
        ) ?: [];
    }

    /** @param array<string, mixed>|null $code */
    private function assertActivationCodeIsUsable(?array $code, DateTimeImmutable $now): void
    {
        if ($code === null) {
            throw new InterfaceApiException('invalid_activation_code', 'The activation code is invalid.', 422);
        }
        if (!empty($code['revoked_at'])) {
            throw new InterfaceApiException('activation_code_revoked', 'The activation code has been revoked.', 409);
        }
        if (!empty($code['used_at'])) {
            throw new InterfaceApiException('activation_code_used', 'The activation code has already been used.', 409);
        }
        $expiresAt = new DateTimeImmutable((string) $code['expires_at']);
        if ($expiresAt <= $now) {
            throw new InterfaceApiException('activation_code_expired', 'The activation code has expired.', 410);
        }
    }

    /** @param array<string, mixed> $code @return array<string, mixed> */
    private function lockReconnectTarget(array $code, int $facilityId): array
    {
        $targetInstallationId = (string) ($code['target_installation_id'] ?? '');
        $target = $this->db->rawQueryOne(
            'SELECT installation_id, source_installation_id, facility_id, display_name
               FROM interface_installations
              WHERE installation_id = ?
              FOR UPDATE',
            [$targetInstallationId]
        );
        if (empty($target) || (int) $target['facility_id'] !== $facilityId) {
            throw new InterfaceApiException(
                'reconnect_facility_conflict',
                'The reconnect code is not valid for this facility installation.',
                409
            );
        }
        return $target;
    }

    private function assertNewInstallationInput(?string $sourceInstallationId, ?string $displayName): void
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', (string) $sourceInstallationId) !== 1) {
            throw new InterfaceApiException(
                'invalid_source_installation_id',
                'The source installation ID is invalid.',
                422
            );
        }
        if ($displayName === null || $displayName === '' || mb_strlen($displayName) > 150) {
            throw new InterfaceApiException(
                'invalid_display_name',
                'The display name must be between 1 and 150 characters.',
                422
            );
        }
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
