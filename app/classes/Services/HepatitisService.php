<?php

namespace App\Services;

use App\Utilities\MiscUtility;
use COUNTRY;
use Throwable;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Abstracts\AbstractTestService;

final class HepatitisService extends AbstractTestService
{
    public array $suppressedArray = [
        'hiv-1 not detected',
        'target not detected',
        'tnd',
        'not detected',
        'below detection limit',
        'below detection level',
        'bdl',
        'suppressed',
        '< 20',
        '<20',
        '< 40',
        '<40',
        '< 839',
        '<839',
        '-1.00',
        '< titer min',
        'negative',
        'negat'
    ];

    public string $testType = 'hepatitis';


    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['hepatitis_sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $globalConfig['hepatitis_sample_code_prefix'] ?? $this->shortCode;

            try {
                return $this->generateSampleCode($this->table, $params);
            } catch (Throwable $e) {
                LoggerUtility::logError('Unable to generate Sample ID : ' . $e->getMessage(), [
                    'exception' => $e,
                    'file' => $e->getFile(), // File where the error occurred
                    'line' => $e->getLine(), // Line number of the error
                    'stacktrace' => $e->getTraceAsString()
                ]);
                return json_encode([]);
            }
        }
    }

    public function getComorbidityByHepatitisId($formId, $allData = false)
    {

        if (empty($formId)) {
            return null;
        }

        $response = [];
        // Using this in sync requests/results
        if (is_array($formId)) {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities
                                                WHERE `hepatitis_id` IN (" . implode(",", $formId) . ")");
            if ($allData) {
                $response = $results;
            } else {
                foreach ($results as $row) {
                    $response[$row['hepatitis_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
                }
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities
                                                WHERE `hepatitis_id` = $formId");
            if ($allData) {
                $response = $results;
            } else {
                foreach ($results as $row) {
                    $response[$row['comorbidity_id']] = $row['comorbidity_detected'];
                }
            }
        }
        return $response;
    }

    public function getRiskFactorsByHepatitisId($formId, $allData = false)
    {

        if (empty($formId)) {
            return null;
        }

        $response = [];
        // Using this in sync requests/results
        if (is_array($formId)) {
            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors
                                                WHERE `hepatitis_id` IN (" . implode(",", $formId) . ")");
            if ($allData) {
                $response = $results;
            } else {
                foreach ($results as $row) {
                    $response[$row['hepatitis_id']][$row['riskfactors_id']] = $row['riskfactors_detected'];
                }
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors
                                                WHERE `hepatitis_id` = $formId");
            if ($allData) {
                $response = $results;
            } else {
                foreach ($results as $row) {
                    $response[$row['riskfactors_id']] = $row['riskfactors_detected'];
                }
            }
        }
        return $response;
    }

    public function getHepatitisResults(): array
    {
        $results = $this->db->rawQuery("SELECT result_id,result
                                            FROM r_hepatitis_results
                                            WHERE `status`='active' ORDER BY result_id DESC");
        $response = [];
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getHepatitisSampleTypes(): array
    {
        $results = $this->db->rawQuery("SELECT * FROM r_hepatitis_sample_type
                                            WHERE status='active'");
        $response = [];
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function getHepatitisReasonsForTesting(): array
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name
                                            FROM r_hepatitis_test_reasons
                                            WHERE `test_reason_status` LIKE 'active'");
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getHepatitisComorbidities(): array
    {
        $comorbidityData = [];
        $comorbidityQuery = "SELECT DISTINCT comorbidity_id, comorbidity_name
                                    FROM r_hepatitis_comorbidities
                                    WHERE comorbidity_status ='active'";
        $comorbidityResult = $this->db->rawQuery($comorbidityQuery);
        foreach ($comorbidityResult as $comorbidity) {
            $comorbidityData[$comorbidity['comorbidity_id']] = ($comorbidity['comorbidity_name']);
        }

        return $comorbidityData;
    }

    public function getHepatitisRiskFactors(): array
    {
        $riskFactorsData = [];
        $riskFactorsQuery = "SELECT DISTINCT riskfactor_id, riskfactor_name
                                FROM r_hepatitis_risk_factors
                                WHERE riskfactor_status ='active'";
        $riskFactorsResult = $this->db->rawQuery($riskFactorsQuery);
        foreach ($riskFactorsResult as $riskFactors) {
            $riskFactorsData[$riskFactors['riskfactor_id']] = ($riskFactors['riskfactor_name']);
        }

        return $riskFactorsData;
    }

    public function insertSample($params, $returnSampleData = false)
    {
        $formId = (int) $this->commonService->getGlobalConfig('vl_form');

        try {

            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $prefix = $params['prefix'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false || ($formId == COUNTRY\PNG && empty($provinceId))) {
                return 0;
            }

            $uniqueId = $params['uniqueId'] ?? MiscUtility::generateULID();
            $accessType = $params['accessType'] ?? $_SESSION['accessType'] ?? null;

            // Insert into the Code Generation Queue
            $this->testRequestsService->addToSampleCodeQueue(
                $uniqueId,
                $this->testType,
                DateUtility::isoDateFormat($sampleCollectionDate, true),
                $params['provinceCode'] ?? null,
                $params['sampleCodeFormat'] ?? null,
                $params['prefix'] ?? $this->shortCode,
                $accessType
            );

            $id = 0;
            $tesRequestData = [
                'vlsm_country_id' => $formId,
                'sample_reordered' => $params['sampleReordered'] ?? 'no',
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => _castVariable($provinceId, 'int'),
                'hepatitis_test_type' => $prefix,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'form_attributes' => $params['formAttributes'] ?? "{}",
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

            $accessType = $params['accessType'] ?? $_SESSION['accessType'] ?? null;

            if ($this->commonService->isSTSInstance()) {
                $tesRequestData['remote_sample'] = 'yes';
                $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_CLINIC;
                if ($accessType === 'testing-lab') {
                    $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
                }
            } else {
                $tesRequestData['remote_sample'] = 'no';
                $tesRequestData['result_status'] = SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
            }

            $formAttributes = [
                'applicationVersion' => $this->commonService->getAppVersion(),
                'ip_address' => $this->commonService->getClientIpAddress()
            ];
            $tesRequestData['form_attributes'] = json_encode($formAttributes);
            $this->db->insert($this->table, $tesRequestData);
            $id = $this->db->getInsertId();
            if ($this->db->getLastErrno() > 0) {
                throw new SystemException($this->db->getLastErrno() . " | " .  $this->db->getLastError());
            }
            // Commit the transaction after the successful insert
            $this->db->commitTransaction();
        } catch (Throwable $e) {
            // Rollback the current transaction to release locks and undo changes
            $this->db->rollbackTransaction();

            LoggerUtility::logError('Insert Hepatitis Sample : ' . $e->getMessage(), [
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
                'uniqueId' => $uniqueId
            ];
        } else {
            return max($id, 0);
        }
    }
}
