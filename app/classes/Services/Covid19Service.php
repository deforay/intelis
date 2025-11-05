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

final class Covid19Service extends AbstractTestService
{
    public string $testType = 'covid19';

    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            throw new SystemException("Sample Collection Date is required to generate Sample ID", 400);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['covid19_sample_code'] ?? 'MMYY';
            $params['prefix'] ??= $globalConfig['covid19_sample_code_prefix'] ?? $this->shortCode;

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


    public function getCovid19SampleTypes($updatedDateTime = null): array
    {
        $query = "SELECT * FROM r_covid19_sample_type where status='active' ";
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

    public function getCovid19SampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_covid19_sample_type where status='active' $where";
        return $this->db->rawQuery($query);
    }

    public function insertCovid19Tests($covid19SampleId, $testKitName = null, $labId = null, $sampleTestedDatetime = null, $result = null): bool
    {
        $covid19TestData = array(
            'covid19_id' => $covid19SampleId,
            'test_name' => $testKitName,
            'facility_id' => $labId,
            'sample_tested_datetime' => $sampleTestedDatetime,
            'result' => $result
        );
        return $this->db->insert("covid19_tests", $covid19TestData);
    }

    public function checkAllCovid19TestsForPositive($covid19SampleId): bool
    {
        if (empty($covid19SampleId)) {
            return false;
        }

        $response = $this->db->rawQuery("SELECT * FROM covid19_tests
                        WHERE `covid19_id` = $covid19SampleId
                        ORDER BY test_id ASC");

        foreach ($response as $row) {
            if ($row['result'] == 'positive') {
                return true;
            }
        }

        return false;
    }


    public function getCovid19Results($updatedDateTime = null): array
    {
        $query = "SELECT result_id,result FROM r_covid19_results where status='active' ";
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

    public function getCovid19ReasonsForTesting($updatedDateTime = null): array
    {
        $query = "SELECT test_reason_id,test_reason_name
                        FROM r_covid19_test_reasons
                        WHERE `test_reason_status` LIKE 'active'";
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

    public function getCovid19ReasonsForTestingDRC(): array
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name
                                            FROM r_covid19_test_reasons
                                                WHERE `test_reason_status` LIKE 'active'
                                                AND (parent_reason IS NULL OR parent_reason = 0)");
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }
    public function getCovid19Symptoms($updatedDateTime = null): array
    {
        $query = "SELECT symptom_id,symptom_name
                    FROM r_covid19_symptoms WHERE `symptom_status` LIKE 'active'";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['symptom_id']] = $row['symptom_name'];
        }
        return $response;
    }

    public function getCovid19SymptomsDRC(): array
    {
        $results = $this->db->rawQuery("SELECT symptom_id,symptom_name
                                            FROM r_covid19_symptoms
                                            WHERE `symptom_status` LIKE 'active'
                                            AND (parent_symptom IS NULL OR parent_symptom = 0)");
        $response = [];
        foreach ($results as $row) {
            $response[$row['symptom_id']] = $row['symptom_name'];
        }
        return $response;
    }

    public function getCovid19Comorbidities($updatedDateTime = null): array
    {
        $query = "SELECT comorbidity_id,comorbidity_name
                    FROM r_covid19_comorbidities
                    WHERE `comorbidity_status` LIKE 'active'";
        if ($updatedDateTime) {
            $query .= " AND updated_datetime >= '$updatedDateTime' ";
        }
        $results = $this->db->rawQuery($query);
        $response = [];
        foreach ($results as $row) {
            $response[$row['comorbidity_id']] = $row['comorbidity_name'];
        }
        return $response;
    }


    public function getCovid19TestsByFormId(string|array $c19Ids = []): array
    {
        $response = [];

        $c19Ids = !is_array($c19Ids) ? [$c19Ids] : $c19Ids;
        if (!empty($c19Ids)) {
            $this->db->where('covid19_id', $c19Ids, 'IN');
            $results = $this->db->get('covid19_tests');

            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['test_id']] = $row;
            }
        }

        return $response;
    }
    public function getCovid19SymptomsByFormId($c19Id, $allData = false, $api = false)
    {
        if (empty($c19Id)) {
            return null;
        }
        if ($api) {
            if (is_array($c19Id)) {
                return $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms
                                                WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            } else {
                return $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms
                                                WHERE `covid19_id` = $c19Id");
            }
        }
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id)) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms
                                                WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");


            if ($allData) {
                return $results;
            }

            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['symptom_id']] = $row['symptom_detected'];
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms
                                                WHERE `covid19_id` = $c19Id");

            if ($allData) {
                return $results;
            }

            foreach ($results as $row) {
                $response[$row['symptom_id']] = $row['symptom_detected'];
            }
        }

        return $response;
    }


    public function getCovid19ComorbiditiesByFormId($c19Id, $allData = false, $api = false)
    {
        if (empty($c19Id)) {
            return null;
        }
        if ($api) {
            if (is_array($c19Id)) {
                return $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities
                                                WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            } else {
                return $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities
                                                WHERE `covid19_id` = $c19Id");
            }
        }
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id)) {

            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities
                                                WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities
                                                WHERE `covid19_id` = $c19Id");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        }


        return $response;
    }

    public function getCovid19ReasonsForTestingByFormId($c19Id, $allData = false, $api = false)
    {
        if (empty($c19Id)) {
            return null;
        }
        if ($api) {
            if (is_array($c19Id)) {
                return $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing
                                                WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            } else {
                return $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing
                                                WHERE `covid19_id` = $c19Id");
            }
        }
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id)) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing
                                                WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['reasons_id']] = $row['reasons_detected'];
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing
                                                WHERE `covid19_id` = $c19Id");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['reasons_id']] = $row['reasons_detected'];
            }
        }

        return $response;
    }

    public function getCovid19ReasonsDetailsForTestingByFormId($c19Id, $onlyValues = false)
    {
        if (empty($c19Id)) {
            return null;
        }
        /* if (!$onlyValues) {
            return $this->db->rawQueryOne("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = ?", [$c19Id]);
        } */
        $result = $this->db->rawQueryOne("
            SELECT * FROM covid19_reasons_for_testing AS crft
            INNER JOIN r_covid19_test_reasons AS rctr ON crft.reasons_id = rctr.test_reason_id
            WHERE crft.`covid19_id` = ?
        ", [$c19Id]);
        $response = [];
        if ($result) {
            foreach (json_decode($result['reason_details'], true) as $row) {
                $response[] = array(
                    'reasons_detected' => $result['test_reason_name'],
                    'reason_details' => $row,
                );
            }
        }
        return $response;
    }

    public function fetchAllDetailsBySampleCode($sampleCode)
    {
        if (empty($sampleCode)) {
            return null;
        }
        $sQuery = "SELECT * FROM $this->table
                    WHERE sample_code like '$sampleCode%' OR remote_sample_code LIKE '$sampleCode%'";
        return $this->db->rawQueryOne($sQuery);
    }

    public function insertSample($params, $returnSampleData = false)
    {
        try {
            // Start a new transaction (this starts a new transaction if not already started)
            // see the beginTransaction() function implementation to understand how this works
            $this->db->beginTransaction();

            $formId = (int) $this->commonService->getGlobalConfig('vl_form');
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = (!empty($params['sampleCollectionDate'])) ? $params['sampleCollectionDate'] : null;

            // PNG FORM CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false || ($formId == COUNTRY\PNG && empty($provinceId))) {
                return 0;
            }

            $id = 0;
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
                'unique_id' => $uniqueId,
                'facility_id' => $params['facilityId'] ?? null,
                'lab_id' => $params['labId'] ?? null,
                'app_sample_code' => $params['appSampleCode'] ?? null,
                'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                'province_id' => _castVariable($provinceId, 'int'),
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'form_attributes' => $params['formAttributes'] ?? "{}",
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

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

            LoggerUtility::logError('Insert Covid-19 Sample : ' . $e->getMessage(), [
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

    public function getCovid19TestsByC19Id($c19Id)
    {
        if (empty($c19Id)) {
            return null;
        }
        return $this->db->rawQuery(
            "SELECT test_id as testId,
                covid19_id as covid19Id,
                facility_id as facilityId,
                test_name as testName,
                kit_lot_no as kitLotNo,
                kit_expiry_date as kitExpiryDate,
                tested_by as testedBy,
                sample_tested_datetime as testDate,
                testing_platform as testingPlatform,
                result as testResult
                FROM covid19_tests
                WHERE `covid19_id` = ? ORDER BY test_id ASC",
            [$c19Id]
        );
    }

    public function generateCovid19QcCode(): array
    {
        $exist = $this->db->rawQueryOne("SELECT DISTINCT qc_code_key
                                            FROM qc_covid19 ORDER BY qc_id desc limit 1");
        if (empty($exist['qc_code_key'])) {
            $number = 001;
        } else {
            $number = ($exist['qc_code_key'] + 1);
        }
        $sampleCodeGenerator = "C19QC" . substr(date("Y"), -2) . date("md") . substr(str_repeat(0, 3) . $number, -3);
        return ["code" => $sampleCodeGenerator, "key" => substr(str_repeat(0, 3) . $number, -3)];
    }
}
