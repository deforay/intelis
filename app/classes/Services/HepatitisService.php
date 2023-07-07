<?php

namespace App\Services;

use Exception;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Abstracts\AbstractTestService;

class HepatitisService extends AbstractTestService
{

    protected string $table = 'form_hepatitis';
    protected string $shortCode = 'HEP';


    public function getSampleCode($params)
    {
        $globalConfig = $this->commonService->getGlobalConfig();
        $params['sampleCodeFormat'] = $globalConfig['hepatitis_sample_code'] ?? 'MMYY';
        $params['prefix'] = $params['prefix'] ?? $globalConfig['hepatitis_sample_code_prefix'] ?? $this->shortCode;
        return $this->generateSampleCode($this->table, $params);
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
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['hepatitis_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities
                                                WHERE `hepatitis_id` = $formId");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['comorbidity_id']] = $row['comorbidity_detected'];
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
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['hepatitis_id']][$row['riskfactors_id']] = $row['riskfactors_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors
                                                WHERE `hepatitis_id` = $formId");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['riskfactors_id']] = $row['riskfactors_detected'];
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
        $formId = $this->commonService->getGlobalConfig('vl_form');

        try {

            $prefix = $params['prefix'] ?? null;
            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || ($formId == 5 && empty($provinceId))) {
                return 0;
            }

            $sampleCodeParams = [];
            $sampleCodeParams['prefix'] = $prefix;
            $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
            $sampleCodeParams['provinceCode'] = $provinceCode;
            $sampleCodeParams['provinceId'] = $provinceId;
            $sampleCodeParams['maxCodeKeyVal'] = $params['oldSampleCodeKey'] ?? null;

            $sampleJson = $this->getSampleCode($sampleCodeParams);
            $sampleData = json_decode($sampleJson, true);

            $sampleCollectionDate = DateUtility::isoDateFormat($sampleCollectionDate, true);


            $sQuery = "SELECT hepatitis_id FROM form_hepatitis ";
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
                    'hepatitis_test_type' => $prefix,
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
                $this->db->insert("form_hepatitis", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                }
            } else {
                // If this sample code exists, let us regenerate the sample code and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSample($params);
            }
        } catch (Exception $e) {

            error_log('Insert Hepatitis Sample : ' . $this->db->getLastErrno());
            error_log('Insert Hepatitis Sample : ' . $this->db->getLastError());
            error_log('Insert Hepatitis Sample : ' . $this->db->getLastQuery());
            error_log('Insert Hepatitis Sample : ' . $e->getMessage());
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
