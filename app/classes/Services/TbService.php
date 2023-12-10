<?php

namespace App\Services;

use COUNTRY;
use Exception;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;

class TbService extends AbstractTestService
{

    protected string $table = 'form_tb';
    protected string $shortCode = 'TB';
    protected int $maxTries = 5; // Max tries to insert sample
    protected string $testType = 'tb';

    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            return json_encode([]);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['tb_sample_code'] ?? 'MMYY';
            $params['prefix'] = $params['prefix'] ?? $globalConfig['tb_sample_code_prefix'] ?? $this->shortCode;

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (SystemException $e) {
                LoggerUtility::log('error', 'Generate Sample Code : ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
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
        $tbTestData = array(
            'tb_id' => $tbSampleId,
            'test_name' => $testKitName,
            'facility_id' => $labId,
            'sample_tested_datetime' => $sampleTestedDatetime,
            'result' => $result
        );
        return $this->db->insert("tb_tests", $tbTestData);
    }

    public function checkAllTbTestsForPositive($tbSampleId): bool
    {
        $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbSampleId ORDER BY test_id ASC");

        foreach ($response as $row) {
            if ($row['result'] == 'positive')
                return true;
        }

        return false;
    }



    public function getTbResults($type = null, $updatedDateTime = null): array
    {
        $query = "SELECT result_id,result FROM r_tb_results where status='active' ";
        if (!empty($type)) {
            $query .= " AND result_type = '" . $type . "' ";
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
        return $response;
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
        if (is_array($tbId) && !empty($tbId)) {
            $results = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` IN (" . implode(",", $tbId) . ") ORDER BY tb_test_id ASC");

            foreach ($results as $row) {
                $response[$row['tb_id']][$row['tb_test_id']] = $row;
            }
        } else if (isset($tbId) && $tbId != "" && !is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbId ORDER BY tb_test_id ASC");
        } else if (!is_array($tbId)) {
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

    public function insertSample($params, $returnSampleData = false)
    {
        try {

            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $params['tries'] = $params['tries'] ?? 0;
            if ($params['tries'] >= $this->maxTries) {
                throw new SystemException("Exceeded maximum number of tries ($this->maxTries) for inserting sample");
            }

            $formId = $this->commonService->getGlobalConfig('vl_form');
            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || ($formId == COUNTRY\PNG && empty($provinceId))) {
                return 0;
            }

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
            $sampleCodeParams['provinceCode'] = $provinceCode;
            $sampleCodeParams['provinceId'] = $provinceId;
            $sampleCodeParams['existingMaxId'] = $params['oldSampleCodeKey'] ?? null;
            $sampleCodeParams['insertOperation'] = $params['insertOperation'] ?? false;

            $sampleJson = $this->getSampleCode($sampleCodeParams);
            $sampleData = json_decode((string) $sampleJson, true);


            $sQuery = "SELECT tb_id FROM form_tb ";
            if (!empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            $id = 0;
            if (empty($rowData) && !empty($sampleData['sampleCode'])) {
                $tesRequestData = [
                    'vlsm_country_id' => $formId,
                    'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                    'unique_id' => $params['uniqueId'] ?? $this->commonService->generateUUID(),
                    'facility_id' => $params['facilityId'] ?? null,
                    'lab_id' => $params['labId'] ?? null,
                    'app_sample_code' => $params['appSampleCode'] ?? null,
                    'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                    'province_id' => $provinceId,
                    'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                    'form_attributes' => $params['formAttributes'] ?? "{}",
                    'request_created_datetime' => DateUtility::getCurrentDateTime(),
                    'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                    'last_modified_datetime' => DateUtility::getCurrentDateTime()
                ];

                $accessType = $_SESSION['accessType'] ?? $params['accessType'] ?? null;
                $instanceType = $_SESSION['instanceType'] ?? $params['instanceType'] ?? null;

                if ($instanceType === 'remoteuser') {
                    $tesRequestData['remote_sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'yes';
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                    if ($accessType === 'testing-lab') {
                        $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                        $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                    }
                } else {
                    $tesRequestData['sample_code'] = $sampleData['sampleCode'];
                    $tesRequestData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $tesRequestData['sample_code_key'] = $sampleData['sampleCodeKey'];
                    $tesRequestData['remote_sample'] = 'no';
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                }
                $formAttributes = [
                    'applicationVersion' => $this->commonService->getSystemConfig('sc_version'),
                    'ip_address' => $this->commonService->getClientIpAddress()
                ];
                $tesRequestData['form_attributes'] = json_encode($formAttributes);
                $this->db->insert("form_tb", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    throw new SystemException($this->db->getLastErrno() . " | " .  $this->db->getLastError());
                }
            } else {

                LoggerUtility::log('info', 'Sample Code Exists. Trying to regenerate sample code', [
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]);

                // Rollback the current transaction to release locks and undo changes
                $this->db->rollbackTransaction();

                // If this sample id exists, let us regenerate the sample id and insert
                $params['tries']++;
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSample($params);
            }
        } catch (Exception | SystemException $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            LoggerUtility::log('error', 'Insert TB Sample : ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $e->getFile(), // File where the error occurred
                'line' => $e->getLine(), // Line number of the error
                'stacktrace' => $e->getTraceAsString()
            ]);
            $id = 0;
        }
        if ($returnSampleData === true) {
            return [
                'id' => max($id, 0),
                'uniqueId' => $tesRequestData['unique_id'] ?? null,
                'sampleCode' => $tesRequestData['sample_code'] ?? null,
                'remoteSampleCode' => $tesRequestData['remote_sample_code'] ?? null
            ];
        } else {
            return max($id, 0);
        }
    }
}
