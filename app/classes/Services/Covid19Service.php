<?php

namespace App\Services;

use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/**
 * General functions
 *
 * @author Amit
 */

class Covid19Service
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_covid19';
    protected string $shortCode = 'C19';

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function generateCovid19SampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        if (DateUtility::verifyIfDateValid($sampleCollectionDate) === false) {
            $sampleCollectionDate = 'now';
        }
        $dateObj = new DateTimeImmutable($sampleCollectionDate);

        $year = $dateObj->format('y');
        $month = $dateObj->format('m');
        $day = $dateObj->format('d');

        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['covid19_sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['covid19_sample_code_prefix'] ?? '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } elseif ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if (empty($maxCodeKeyVal)) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    /** @var GeoLocations $geoLocations */
                    $geoLocationsService = ContainerRegistry::get(GeoLocationsService::class);
                    $provinceId = $geoLocationsService->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('YEAR(sample_collection_date) = ?', array($dateObj->format('Y')));
            $maxCodeKeyVal = $this->db->getValue($this->table, "MAX($sampleCodeKeyCol)");
        }


        if (!empty($maxCodeKeyVal) && $maxCodeKeyVal > 0) {
            $maxId = $maxCodeKeyVal + 1;
        } else {
            $maxId = 1;
        }

        $maxId = sprintf("%04d", (int) $maxId);


        $sampleCodeGenerator = [
            'sampleCode' => '',
            'sampleCodeInText' => '',
            'sampleCodeFormat' => '',
            'sampleCodeKey' => '',
            'maxId' => $maxId,
            'mnthYr' => $mnthYr,
            'auto' => $autoFormatedString
        ];

        // PNG format has an additional R in prefix
        if ($globalConfig['vl_form'] == 5) {
            $remotePrefix = $remotePrefix . "R";
        }


        if ($sampleCodeFormat == 'auto') {
            $sampleCodeGenerator['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sampleCodeGenerator['maxId']);
            $sampleCodeGenerator['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sampleCodeGenerator['sampleCodeKey'] = $sampleCodeGenerator['maxId'];
        } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sampleCodeGenerator['sampleCode'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'] . $sampleCodeGenerator['maxId'];
            $sampleCodeGenerator['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sampleCodeGenerator['mnthYr'];
            $sampleCodeGenerator['sampleCodeKey'] = ($sampleCodeGenerator['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol
                        FROM $this->table
                        WHERE $sampleCodeCol= ?";
        $checkResult = $this->db->rawQueryOne($checkQuery, [$sampleCodeGenerator['sampleCode']]);
        if (!empty($checkResult)) {
            return $this->generateCovid19SampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }

        return json_encode($sampleCodeGenerator);
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
            'covid19_id'            => $covid19SampleId,
            'test_name'                => $testKitName,
            'facility_id'           => $labId,
            'sample_tested_datetime' => $sampleTestedDatetime,
            'result'                => $result
        );
        return $this->db->insert("covid19_tests", $covid19TestData);
    }

    public function checkAllCovid19TestsForPositive($covid19SampleId): bool
    {
        if (empty($covid19SampleId)) {
            return false;
        }

        $response = $this->db->rawQuery("SELECT * FROM covid19_tests WHERE `covid19_id` = $covid19SampleId ORDER BY test_id ASC");

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
        $query = "SELECT test_reason_id,test_reason_name FROM r_covid19_test_reasons WHERE `test_reason_status` LIKE 'active'";
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
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_covid19_test_reasons WHERE `test_reason_status` LIKE 'active' AND (parent_reason IS NULL OR parent_reason = 0)");
        $response = [];
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }
    public function getCovid19Symptoms($updatedDateTime = null): array
    {
        $query = "SELECT symptom_id,symptom_name FROM r_covid19_symptoms WHERE `symptom_status` LIKE 'active'";
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
        $results = $this->db->rawQuery("SELECT symptom_id,symptom_name FROM r_covid19_symptoms WHERE `symptom_status` LIKE 'active' AND (parent_symptom IS NULL OR parent_symptom = 0)");
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


    public function getCovid19TestsByFormId($c19Id = ""): array
    {
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id) && !empty($c19Id)) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_tests WHERE `covid19_id` IN (" . implode(",", $c19Id) . ") ORDER BY test_id ASC");

            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['test_id']] = $row;
            }
        } elseif (!empty($c19Id) && $c19Id != "" && !is_array($c19Id)) {
            $response = $this->db->rawQuery("SELECT * FROM covid19_tests WHERE `covid19_id` = $c19Id ORDER BY test_id ASC");
        } elseif (!is_array($c19Id)) {
            $response = $this->db->rawQuery("SELECT * FROM covid19_tests ORDER BY test_id ASC");
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
                return $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            } else {
                return $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` = $c19Id");
            }
        }
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id)) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");


            if ($allData) {
                return $results;
            }

            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['symptom_id']] = $row['symptom_detected'];
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` = $c19Id");

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
                return $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            } else {
                return $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities WHERE `covid19_id` = $c19Id");
            }
        }
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id)) {

            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities WHERE `covid19_id` = $c19Id");
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
                return $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            } else {
                return $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = $c19Id");
            }
        }
        $response = [];

        // Using this in sync requests/results
        if (is_array($c19Id)) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['reasons_id']] = $row['reasons_detected'];
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = $c19Id");
            if ($allData) {
                return $results;
            }
            foreach ($results as $row) {
                $response[$row['reasons_id']] = $row['reasons_detected'];
            }
        }

        return $response;
    }

    public function getCovid19ReasonsDetailsForTestingByFormId($c19Id)
    {
        if (empty($c19Id)) {
            return null;
        }
        return $this->db->rawQueryOne("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = ?", [$c19Id]);
    }

    public function fetchAllDetailsBySampleCode($sampleCode)
    {
        if (empty($sampleCode)) {
            return null;
        }
        $sQuery = "SELECT * FROM form_covid19 WHERE sample_code like '$sampleCode%' OR remote_sample_code LIKE '$sampleCode%'";
        return $this->db->rawQueryOne($sQuery);
    }

    public function insertSampleCode($params)
    {
        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);
        $patientsModel = new PatientsService();

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        $patientCodePrefix = 'P';

        try {
            $provinceCode = (!empty($params['provinceCode'])) ? $params['provinceCode'] : null;
            $provinceId = (!empty($params['provinceId'])) ? $params['provinceId'] : null;
            $sampleCollectionDate = (!empty($params['sampleCollectionDate'])) ? $params['sampleCollectionDate'] : null;

            if (empty($sampleCollectionDate)) {
                return 0;
            }

            // PNG FORM CANNOT HAVE PROVINCE EMPTY
            if ($globalConfig['vl_form'] == 5 && empty($provinceId)) {
                return 0;
            }


            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;
            $sampleJson = $this->generateCovid19SampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);

            $sampleCollectionDate = DateUtility::isoDateFormat($sampleCollectionDate, true);

            $covid19Data = [
                'vlsm_country_id' => $globalConfig['vl_form'],
                'sample_collection_date' => $sampleCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $params['instanceId'] ?? null,
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $covid19Data['remote_sample_code'] = $sampleData['sampleCode'];
                $covid19Data['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $covid19Data['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $covid19Data['remote_sample'] = 'yes';
                $covid19Data['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
                    $covid19Data['sample_code'] = $sampleData['sampleCode'];
                    $covid19Data['result_status'] = 6;
                }
            } else {
                $covid19Data['sample_code'] = $sampleData['sampleCode'];
                $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
                $covid19Data['remote_sample'] = 'no';
                $covid19Data['result_status'] = 6;
            }


            $generateAutomatedPatientCode = $general->getGlobalConfig('covid19_generate_patient_code');
            if (!empty($generateAutomatedPatientCode) && $generateAutomatedPatientCode == 'yes') {
                $patientCodePrefix = $general->getGlobalConfig('covid19_patient_code_prefix');
                if (empty($patientCodePrefix)) {
                    $patientCodePrefix = 'P';
                }
                $generateAutomatedPatientCode = true;
                $patientCodeJson = $patientsModel->generatePatientId($patientCodePrefix);
                $patientCodeArray = json_decode($patientCodeJson, true);
            } else {
                $generateAutomatedPatientCode = false;
            }

            $patientCode = $params['patientId'];
            //saving this patient into patients table
            if (!empty($patientCodeArray['patientCodeKey'])) {
                $patientData['patientCodePrefix'] = $patientCodePrefix;
                $patientData['patientCodeKey'] = $patientCodeArray['patientCodeKey'];
                $patientCode = $patientCodeArray['patientCode'];
            }
            $patientData['patientId'] = $patientCode;
            $patientData['patientFirstName'] = $params['firstName'];
            $patientData['patientLastName'] = $params['lastName'];
            $patientData['patientGender'] = $params['patientGender'];
            $patientData['registeredBy'] = $_SESSION['userId'];
            $patientsModel->savePatient($patientData);


            $covid19Data['patient_id'] = $patientCode;
            $sQuery = "SELECT covid19_id,
                                sample_code,
                                sample_code_format,
                                sample_code_key,
                                remote_sample_code,
                                remote_sample_code_format,
                                remote_sample_code_key
                        FROM form_covid19 ";
            if (!empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            $formAttributes = array(
                'applicationVersion'  => $general->getSystemConfig('sc_version'),
                'ip_address'    => $general->getClientIpAddress()
            );
            $covid19Data['form_attributes'] = json_encode($formAttributes);

            $id = 0;
            if (!empty($rowData)) {
                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            } else {
                if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                    $covid19Data['unique_id'] = $general->generateUUID();
                    $id = $this->db->insert("form_covid19", $covid19Data);
                }
            }

            return ($id > 0) ? $id : 0;
        } catch (Exception $e) {
            error_log('Insert Covid-19 Sample : ' . $this->db->getLastErrno());
            error_log('Insert Covid-19 Sample : ' . $this->db->getLastError());
            error_log('Insert Covid-19 Sample : ' . $this->db->getLastQuery());
            error_log('Insert Covid-19 Sample : ' . $e->getMessage());
            return 0;
        }
    }

    public function getCovid19TestsByC19Id($c19Id)
    {
        if (empty($c19Id)) {
            return null;
        }
        return $this->db->rawQuery("SELECT test_id as testId, covid19_id as covid19Id, facility_id as facilityId, test_name as testName, kit_lot_no as kitLotNo, kit_expiry_date as kitExpiryDate, tested_by as testedBy, sample_tested_datetime as testDate, testing_platform as testingPlatform, result as testResult FROM covid19_tests WHERE `covid19_id` = $c19Id ORDER BY test_id ASC");
    }

    public function generateCovid19QcCode(): array
    {
        $exist = $this->db->rawQueryOne("SELECT DISTINCT qc_code_key from qc_covid19 order by qc_id desc limit 1");
        if (empty($exist['qc_code_key'])) {
            $number = 001;
        } else {
            $number = ($exist['qc_code_key'] + 1);
        }
        $sampleCodeGenerator = "C19QC" . substr(date("Y"), -2) . date("md") . substr(str_repeat(0, 3) . $number, -3);
        return array("code" => $sampleCodeGenerator, "key" => substr(str_repeat(0, 3) . $number, -3));
    }
}
