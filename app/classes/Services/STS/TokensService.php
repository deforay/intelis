<?php

namespace App\Services\STS;

use App\Services\ApiService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Abstracts\AbstractTestService;

final class TokensService
{
    protected DatabaseService $db;
    protected string $primaryKeyName;

    /** @var AbstractTestService $testTypeService */
    protected $testTypeService;

    protected $facilitiesTable = 'facility_details';

    private ?string $lastValidationFailure = null;

    public function __construct(DatabaseService $db, protected CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
    }

    public function createToken(int $facilityId, int $expiryInDays = 90): string
    {
        // Calculate the new expiry time
        $tokenExpiry = date('Y-m-d H:i:s', strtotime("+$expiryInDays days"));

        // Check if a token already exists and if it is expired
        $this->db->where('facility_id', $facilityId);
        $existingTokenData = $this->db->getOne($this->facilitiesTable, ['sts_token', 'sts_token_expiry']);

        if ($existingTokenData && strtotime((string) $existingTokenData['sts_token_expiry']) > time()) {
            // Token exists and is still valid, so return it without updating
            return $existingTokenData['sts_token'];
        }

        // Token does not exist or has expired; generate a new token
        $token = ApiService::generateAuthToken('sts');

        // Update the database with the new token and expiry time
        $this->db->where('facility_id', $facilityId);
        $this->db->update(
            $this->facilitiesTable,
            [
                'sts_token' => $token,
                'sts_token_expiry' => $tokenExpiry,
            ]
        );

        return $token;
    }


    /**
     * Constant-time check that $token is THIS facility's stored sts_token, ignoring
     * expiry. Used by get-token.php as "possession proof": a LIS that already holds
     * its token has proven its identity, so it may mint/refresh without the legacy
     * derivable API key. Expiry is intentionally not checked here -- a just-expired
     * token still proves identity, and the caller refreshes it via createToken().
     */
    public function tokenBelongsToFacility(?string $token, int $facilityId): bool
    {
        if (empty($token) || $facilityId <= 0) {
            return false;
        }
        $this->db->where('facility_id', $facilityId);
        $result = $this->db->getOne($this->facilitiesTable, ['sts_token']);

        return $result
            && !empty($result['sts_token'])
            && hash_equals((string) $result['sts_token'], (string) $token);
    }

    public function validateToken(?string $token, int $facilityId): bool
    {
        $this->lastValidationFailure = null;

        if ($facilityId === 0) {
            $this->lastValidationFailure = 'no lab id was sent, so the token could not be checked against a lab';
            return false;
        }

        if ($token === null || $token === '' || $token === '0') {
            $this->lastValidationFailure = "no bearer token was sent for {$this->describeFacility($facilityId)}";
            return false;
        }

        $this->db->where('facility_id', $facilityId);
        $result = $this->db->getOne($this->facilitiesTable, ['facility_name', 'sts_token', 'sts_token_expiry']);

        if (empty($result)) {
            $this->lastValidationFailure = "no lab with id $facilityId exists on this STS";
            return false;
        }

        $lab = $this->describeFacility($facilityId, $result['facility_name'] ?? null);

        if (empty($result['sts_token'])) {
            $this->lastValidationFailure = "$lab has no STS token issued yet";
            return false;
        }

        // Constant-time comparison so token validity can't be probed by timing.
        if (!hash_equals((string) $result['sts_token'], (string) $token)) {
            $this->lastValidationFailure = "the token sent does not match the one issued to $lab";
            return false;
        }

        // Directly check if the current time is less than the stored expiry
        if (time() < strtotime((string) $result['sts_token_expiry'])) {
            return true;
        }

        // Token expired, so generate a new one. The reason is recorded before that
        // happens -- once the new token is stored, an expired token is indistinguishable
        // from a wrong one, and those two need different answers from whoever reads the log.
        $this->lastValidationFailure = sprintf(
            'the token for %s expired on %s; a fresh one has been issued for the next sync',
            $lab,
            $result['sts_token_expiry'] ?: 'an unrecorded date'
        );
        $this->createToken($facilityId);

        return false;
    }

    /**
     * Why the last validateToken() call said no, phrased for whoever is reading the
     * log and has to work out which lab has stopped talking to STS. Never contains
     * the token itself.
     */
    public function getLastValidationFailure(): string
    {
        return $this->lastValidationFailure ?? 'the token could not be validated';
    }

    private function describeFacility(int $facilityId, ?string $facilityName = null): string
    {
        if ($facilityName === null) {
            $this->db->where('facility_id', $facilityId);
            $row = $this->db->getOne($this->facilitiesTable, ['facility_name']);
            $facilityName = $row['facility_name'] ?? null;
        }

        return empty($facilityName)
            ? "lab id $facilityId"
            : "lab '$facilityName' (id $facilityId)";
    }
}
