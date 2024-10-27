<?php

namespace App\Services\STS;

use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Abstracts\AbstractTestService;
use Psr\Http\Message\ServerRequestInterface;

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

    public function createToken(int $facilityId, int $expiryInDays = 90): string
    {
        // Calculate the new expiry time
        $tokenExpiry = date('Y-m-d H:i:s', strtotime("+$expiryInDays days"));

        // Check if a token already exists and if it is expired
        $this->db->where('facility_id', $facilityId);
        $existingTokenData = $this->db->getOne($this->facilitiesTable, ['sts_token', 'sts_token_expiry']);

        if ($existingTokenData && strtotime($existingTokenData['sts_token_expiry']) > time()) {
            // Token exists and is still valid, so return it without updating
            return $existingTokenData['sts_token'];
        }

        // Token does not exist or has expired; generate a new token
        $token = MiscUtility::generateRandomString();

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


    public function validateToken(?string $token, int $facilityId): bool
    {

        if (!empty($token) && !empty($facilityId)) {

            $this->db->where('facility_id', $facilityId);
            $result = $this->db->getOne($this->facilitiesTable, ['sts_token', 'sts_token_expiry']);

            if ($result && $result['sts_token'] === $token) {
                // Directly check if the current time is less than the stored expiry
                if (time() < strtotime($result['sts_token_expiry'])) {
                    return true;
                }
                // Token expired, so generate a new one
                $this->createToken($facilityId);
            }
        }

        return false;
    }
}
