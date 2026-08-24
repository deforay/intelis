<?php

namespace App\Services;

use App\Utilities\LoggerUtility;

class SmartConnectService
{
    /** @var DatabaseService */
    private $db;

    /** @var CommonService */
    private $general;

    /** @var ApiService */
    private $apiService;

    public function __construct(DatabaseService $db, CommonService $general, ApiService $apiService)
    {
        $this->db = $db;
        $this->general = $general;
        $this->apiService = $apiService;
    }

    /**
     * Right-trimmed vldashboard_url.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->general->getGlobalConfig('vldashboard_url'), '/');
    }

    /**
     * GET /api/v2/health returns 200.
     */
    public function supportsV2(): bool
    {
        $result = $this->apiService->checkConnectivity($this->baseUrl() . '/api/v2/health', true);
        $status = $result['httpStatusCode'] ?? null;

        return $status === 200;
    }

    /**
     * Reads sc_api_token. Enrolls when empty.
     */
    public function token(): ?string
    {
        $token = $this->db->getValue('s_vlsm_instance', 'sc_api_token');

        if (!empty($token)) {
            return $token;
        }

        return $this->enroll();
    }

    /**
     * Posts to /api/v2/enroll. Stores the token.
     *
     * 401 means the key is wrong. 403 means the dashboard has no key set.
     * 422 means instance_uuid is empty.
     */
    public function enroll(): ?string
    {
        $payload = [
            'enrollment_key' => $this->general->getGlobalConfig('smart_connect_enrollment_key') ?: null,
            'instance_uuid' => $this->general->getInstanceId(),
            'lab_id' => $this->general->getSystemConfig('sc_testing_lab_id') ?: null,
            'facility_code' => $this->db->getValue('s_vlsm_instance', 'instance_facility_code'),
            'label' => $this->db->getValue('s_vlsm_instance', 'instance_facility_name'),
        ];

        // Fourth argument returns httpStatusCode alongside the body.
        $result = $this->apiService->post($this->baseUrl() . '/api/v2/enroll', $payload, false, true);

        if (($result['httpStatusCode'] ?? 0) !== 201) {
            LoggerUtility::logError('Smart Connect enrollment failed', [
                'status' => $result['httpStatusCode'] ?? null,
                'body' => $result['body'] ?? null,
            ]);
            return null;
        }

        $token = json_decode((string) $result['body'], true)['data']['token'] ?? null;

        if (!empty($token)) {
            $this->db->update('s_vlsm_instance', ['sc_api_token' => $token]);
        }

        return $token;
    }

    /**
     * Nulls sc_api_token.
     */
    public function forgetToken(): void
    {
        $this->db->update('s_vlsm_instance', ['sc_api_token' => null]);
    }
}