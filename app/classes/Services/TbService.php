<?php

namespace App\Services;

use Override;
use const COUNTRY\PNG;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Utilities\MiscUtility;
use Throwable;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;

final class TbService extends AbstractTestService
{
    public string $testType = 'tb';


    #[Override]
    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['tb_sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $globalConfig['tb_sample_code_prefix'] ?? $this->shortCode;
            $postFix = '';
            if ($this->commonService->isLISInstance()) {
                $postFix = "-" . $this->commonService->getSystemConfig('sc_testing_lab_id') ?? '';
            }
            $params['postfix'] ??= $postFix;

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::logError('Generate Sample ID : ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
                    'last_db_error' => $this->db->getLastError(),
                    'last_db_query' => $this->db->getLastQuery(),
                    'stacktrace' => $e->getTraceAsString()
                ]);
                return json_encode([]);
            }
        }
    }

    public function getTbSampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_tb_sample_type where status='active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function getTbSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_tb_sample_type where status='active'$where";
        return $this->db->rawQuery($query);
    }

    public function insertTbTests($tbSampleId, $testKitName = null, $labId = null, $sampleTestedDatetime = null, $result = null)
    {
        $tbTestData = ['tb_id' => $tbSampleId, 'test_name' => $testKitName, 'facility_id' => $labId, 'sample_tested_datetime' => $sampleTestedDatetime, 'result' => $result];
        return $this->db->insert("tb_tests", $tbTestData);
    }

    public function checkAllTbTestsForPositive($tbSampleId): bool
    {
        $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbSampleId ORDER BY test_id ASC");

        foreach ($response as $row) {
            if ($row['result'] == 'positive') {
                return true;
            }
        }

        return false;
    }



    public function getTbResults($type = null, $updatedDateTime = null): array
    {
        $query = "SELECT result_id,result FROM r_tb_results where status='active' ";
        if (!empty($type)) {
            $query .= " AND result_type = '$type' ";
        }
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $query .= " ORDER BY result_id DESC";
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return array_unique($response);
    }

    public function getTbReasonsForTesting($updatedDateTime = null): array
    {
        $query = "SELECT test_reason_id,test_reason_name FROM r_tb_test_reasons WHERE `test_reason_status` LIKE 'active' ";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getTbReasonsForTestingDRC(): array
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_tb_test_reasons WHERE `test_reason_status` LIKE 'active' AND (parent_reason IS NULL OR parent_reason = 0)");
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getTbTestsByFormId($tbId = "")
    {
        $response = [];
        // Using this in sync requests/results
        if (is_array($tbId) && $tbId !== []) {
            $results = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` IN (" . implode(",", $tbId) . ") ORDER BY tb_test_id ASC");
            foreach ($results as $row) {
                $response[$row['tb_id']][$row['tb_test_id']] = $row;
            }
        } elseif (isset($tbId) && $tbId != "" && !is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbId ORDER BY tb_test_id ASC");
        } elseif (!is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests ORDER BY tb_test_id ASC");
        }
        return $response;
    }

    public function fetchAllDetailsBySampleCode($sampleCode)
    {
        if (empty($sampleCode)) {
            return null;
        }
        $sQuery = "SELECT * FROM form_tb WHERE sample_code like '$sampleCode%' OR remote_sample_code LIKE '$sampleCode%'";
        return $this->db->rawQueryOne($sQuery);
    }

    #[Override]
    public function insertSample($params, $returnSampleData = false)
    {
        try {
            // Start transaction
            $this->db->beginTransaction();

            // Get form configuration and extract parameters
            $formId = (int) $this->commonService->getGlobalConfig('vl_form');
            $params['provinceId'] ??= $params['province'];
            if (str_contains((string) $params['provinceId'], '##')) {
                $parray = explode('##', (string) $params['provinceId']);
                $provinceId = $parray[0];
            } else {

                $provinceId = $params['provinceId'] ?? null;
            }
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // Validate required fields
            if (
                empty($sampleCollectionDate) ||
                !DateUtility::isDateValid($sampleCollectionDate) ||
                ($formId == PNG && empty($provinceId))
            ) {
                return $returnSampleData ? ['id' => 0, 'uniqueId' => null] : 0;
            }

            // Generate unique ID and get access type
            $uniqueId = $params['uniqueId'] ?? MiscUtility::generateULID();
            $accessType = $params['accessType'] ?? $_SESSION['accessType'] ?? null;
            $userId = $_SESSION['userId'] ?? $params['userId'] ?? null;
            $currentDateTime = DateUtility::getCurrentDateTime();

            // Add to sample code queue
            $this->testRequestsService->addToSampleCodeQueue(
                $uniqueId,
                $this->testType,
                DateUtility::isoDateFormat($sampleCollectionDate, true),
                $params['provinceCode'] ?? null,
                $params['sampleCodeFormat'] ?? null,
                $params['prefix'] ?? $this->shortCode,
                $accessType
            );

            // Prepare test request data
            $testRequestData = [
                'vlsm_country_id' => $formId,
                'sample_reordered' => $params['sampleReordered'] ?? 'no',
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? $params['testResult']['labId'][0] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId(),
                'province_id' => _castVariable($provinceId, 'int'),
                'request_created_by' => $userId,
                'request_created_datetime' => $currentDateTime,
                'last_modified_by' => $userId,
                'last_modified_datetime' => $currentDateTime
            ];
            // Set remote sample and result status based on instance type
            if ($this->commonService->isSTSInstance()) {
                $testRequestData['remote_sample'] = 'yes';
                $testRequestData['result_status'] = ($accessType === 'testing-lab')
                    ? RECEIVED_AT_TESTING_LAB
                    : RECEIVED_AT_CLINIC;
            } else {
                $testRequestData['remote_sample'] = 'no';
                $testRequestData['result_status'] = RECEIVED_AT_TESTING_LAB;
            }

            // Add form attributes
            $formAttributes = [
                'applicationVersion' => $this->commonService->getAppVersion(),
                'ip_address' => $this->commonService->getClientIpAddress()
            ];
            $testRequestData['form_attributes'] = json_encode($formAttributes);

            // Insert data and get ID
            $this->db->insert($this->table, $testRequestData);
            $id = $this->db->getInsertId();

            // Check for database errors
            if ($this->db->getLastErrno() > 0) {
                throw new SystemException($this->db->getLastErrno() . " | " . $this->db->getLastError());
            }

            // Commit transaction
            $this->db->commitTransaction();

            return $returnSampleData
                ? ['id' => $id, 'uniqueId' => $uniqueId]
                : $id;
        } catch (Throwable $e) {
            // Rollback transaction
            $this->db->rollbackTransaction();

            // Log error with context
            LoggerUtility::logError('Insert Sample Error: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stacktrace' => $e->getTraceAsString(),
                'params' => $params
            ]);

            return $returnSampleData
                ? ['id' => 0, 'uniqueId' => $uniqueId ?? null]
                : 0;
        }
    }
}
