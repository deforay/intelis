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

    /**
     * Registers a tool that has been seen reporting but has never activated, so its
     * telemetry has an installation to belong to. The importer is the reason this
     * exists: a lab that syncs results through bin/interface.php never calls the API,
     * so nothing would otherwise register it and everything it reports would be
     * attributed to the lab as a whole rather than to the machine that sent it.
     *
     * The row is created with no credential and status 'observed'. It grants nothing:
     * it is a label until an activation claims it. Idempotent on the source, so a run
     * that re-reads events already seen does not create a second row.
     *
     * @return string|null the installation now associated with the source, which may
     *                     predate this call; null when the source belongs to another
     *                     facility, whose events must not be attributed here
     */
    public function registerObserved(
        string $proposedInstallationId,
        string $sourceInstallationId,
        int $facilityId,
        string $displayName,
        DateTimeImmutable $now
    ): ?string;

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

    /**
     * What each of a facility's installations has been doing, so a person can tell them
     * apart. A tool registered by the importer is named after itself and nothing else;
     * what makes it recognisable is the analyzer it drives and when it last reported.
     *
     * Kept separate from listInstallations rather than joined into it, because that query
     * also serves an unscoped listing and this aggregate is only bounded when a facility
     * bounds it.
     *
     * @return array<string, array{machines: ?string, last_activity: ?string, events: int}>
     *         keyed by installation_id
     */
    public function activitySummary(int $facilityId): array;
}
