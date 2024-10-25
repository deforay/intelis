<?php

namespace App\Services\STS;

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Abstracts\AbstractTestService;

final class TokensService
{
    protected CommonService $commonService;
    protected DatabaseService $db;
    protected string $primaryKeyName;

    /** @var AbstractTestService $testTypeService */
    protected $testTypeService;

    protected $facilitiesTable = 'facility_details';

    public function __construct(DatabaseService $db, CommonService $commonService)
    {
        $this->db = $db ?? ContainerRegistry::get(DatabaseService::class);
        $this->commonService = $commonService;
    }

    public static function generateToken()
    {
        return MiscUtility::generateUUID();
    }

    public function createAndStoreToken(int $facilityId, int $expiryInDays = 90): string
    {
        // Calculate the new expiry time
        $tokenExpiry = date('Y-m-d H:i:s', strtotime("+$expiryInDays days"));

        // Check if a token already exists for this facility
        $this->db->where('facility_id', $facilityId);
        $existingToken = $this->db->getValue($this->facilitiesTable, 'sts_token');

        // If token exists, keep it; otherwise, generate a new one
        $token = $existingToken ?? self::generateToken();

        // Update the token and expiry in the database
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

    public function validateToken(string $token, int $facilityId): bool
    {
        $this->db->where('facility_id', $facilityId);
        $result = $this->db->getOne($this->facilitiesTable, ['sts_token', 'sts_token_expiry']);

        if ($result && $result['sts_token'] === $token) {
            // Directly check if the current time is less than the stored expiry
            if (time() < strtotime($result['sts_token_expiry'])) {
                return true;
            }
            // Token expired, so generate a new one
            $this->createAndStoreToken($facilityId);
        }

        return false;
    }
}
