<?php

namespace Vlsm\Models;

/**
 * General functions
 *
 * @author Amit
 */

class Covid19
{

    protected $db = null;
    protected $table = 'form_covid19';
    protected $shortCode = 'C19';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function generateCovid19SampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null)
    {

        $general = new \Vlsm\Models\General($this->db);

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();
        $sampleID = '';


        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }
        $sampleColDateTimeArray = explode(" ", $sampleCollectionDate);
        $sampleCollectionDate = $general->dateFormat($sampleColDateTimeArray[0]);
        $sampleColDateArray = explode("-", $sampleCollectionDate);
        $samColDate = substr($sampleColDateArray[0], -2);
        $start_date = $sampleColDateArray[0] . '-01-01';
        $end_date = $sampleColDateArray[0] . '-12-31';
        $mnthYr = $samColDate[0];
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = isset($globalConfig['covid19_sample_code']) ? $globalConfig['covid19_sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['covid19_sample_code_prefix']) ? $globalConfig['covid19_sample_code_prefix'] : '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $sampleColDateArray[1] . $samColDate;
        } else if ($sampleCodeFormat == 'YY') {
            $mnthYr = $samColDate;
        }

        $autoFormatedString = $samColDate . $sampleColDateArray[1] . $sampleColDateArray[2];


        if ($maxCodeKeyVal == null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $provinceId = $general->getProvinceIDFromCode($provinceCode);
                }

                if (!empty($provinceId)) {
                    $this->db->where('province_id', $provinceId);
                }
            }

            $this->db->where('DATE(sample_collection_date)', array($start_date, $end_date), 'BETWEEN');
            $this->db->where($sampleCodeCol, NULL, 'IS NOT');
            $this->db->orderBy($sampleCodeKeyCol, "DESC");
            $svlResult = $this->db->getOne($this->table, array($sampleCodeKeyCol));
            if ($svlResult) {
                $maxCodeKeyVal = $svlResult[$sampleCodeKeyCol];
            } else {
                $maxCodeKeyVal = null;
            }
        }


        if (!empty($maxCodeKeyVal)) {
            $maxId = $maxCodeKeyVal + 1;
            $strparam = strlen($maxId);
            $zeros = (isset($sampleCodeFormat) && trim($sampleCodeFormat) == 'auto2') ? substr("0000", $strparam) : substr("000", $strparam);
            $maxId = $zeros . $maxId;
        } else {
            $maxId = (isset($sampleCodeFormat) && trim($sampleCodeFormat) == 'auto2') ? '0001' : '001';
        }

        //error_log($maxCodeKeyVal);

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));



        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
            //$sampleCodeFormat = 'auto2';
        }


        if ($sampleCodeFormat == 'auto') {
            //$pNameVal = explode("##", $provinceCode);
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } else if ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } else if ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }

        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " where $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if ($checkResult !== null) {
            return $this->generateCovid19SampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $checkResult[$sampleCodeKeyCol]);
        }

        return json_encode($sCodeKey);
    }


    public function getCovid19SampleTypes()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_covid19_sample_type where status='active'");
        $response = array();
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

    public function insertCovid19Tests($covid19SampleId, $testKitName = null, $labId = null, $sampleTestedDatetime = null, $result = null)
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

    public function checkAllCovid19TestsForPositive($covid19SampleId)
    {
        if (empty($covid19SampleId)) return false;
        $response = $this->db->rawQuery("SELECT * FROM covid19_tests WHERE `covid19_id` = $covid19SampleId ORDER BY test_id ASC");

        foreach ($response as $row) {
            if ($row['result'] == 'positive') return true;
        }

        return false;
    }


    public function getCovid19Results()
    {
        $results = $this->db->rawQuery("SELECT result_id,result FROM r_covid19_results where status='active' ORDER BY result_id DESC");
        $response = array();
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getCovid19ReasonsForTesting()
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_covid19_test_reasons WHERE `test_reason_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getCovid19ReasonsForTestingDRC()
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_covid19_test_reasons WHERE `test_reason_status` LIKE 'active' AND (parent_reason IS NULL OR parent_reason = 0)");
        $response = array();
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }
    public function getCovid19Symptoms()
    {
        $results = $this->db->rawQuery("SELECT symptom_id,symptom_name FROM r_covid19_symptoms WHERE `symptom_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['symptom_id']] = $row['symptom_name'];
        }
        return $response;
    }

    public function getCovid19SymptomsDRC()
    {
        $results = $this->db->rawQuery("SELECT symptom_id,symptom_name FROM r_covid19_symptoms WHERE `symptom_status` LIKE 'active' AND (parent_symptom IS NULL OR parent_symptom = 0)");
        $response = array();
        foreach ($results as $row) {
            $response[$row['symptom_id']] = $row['symptom_name'];
        }
        return $response;
    }

    public function getCovid19Comorbidities()
    {
        $results = $this->db->rawQuery("SELECT comorbidity_id,comorbidity_name FROM r_covid19_comorbidities WHERE `comorbidity_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['comorbidity_id']] = $row['comorbidity_name'];
        }
        return $response;
    }


    public function getCovid19TestsByFormId($c19Id = "")
    {
        $response = array();

        // Using this in sync requests/results
        if (isset($c19Id) && is_array($c19Id) && count($c19Id) > 0) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_tests WHERE `covid19_id` IN (" . implode(",", $c19Id) . ") ORDER BY test_id ASC");

            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['test_id']] = $row;
            }
        } else if (isset($c19Id) && $c19Id != "" && !is_array($c19Id)) {
            $response = $this->db->rawQuery("SELECT * FROM covid19_tests WHERE `covid19_id` = $c19Id ORDER BY test_id ASC");
        } else if (!is_array($c19Id)) {
            $response = $this->db->rawQuery("SELECT * FROM covid19_tests ORDER BY test_id ASC");
        }

        return $response;
    }
    public function getCovid19SymptomsByFormId($c19Id, $allData = false)
    {
        if (empty($c19Id)) {
            return null;
        }

        $response = array();

        // Using this in sync requests/results
        if (isset($c19Id) && is_array($c19Id) && count($c19Id) > 0) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");


            if ($allData) return $results;

            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['symptom_id']] = $row['symptom_detected'];
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_symptoms WHERE `covid19_id` = $c19Id");

            if ($allData) return $results;

            foreach ($results as $row) {
                $response[$row['symptom_id']] = $row['symptom_detected'];
            }
        }

        return $response;
    }


    public function getCovid19ComorbiditiesByFormId($c19Id, $allData = false)
    {
        if (empty($c19Id)) {
            return null;
        }

        $response = array();

        // Using this in sync requests/results
        if (isset($c19Id) && is_array($c19Id) && count($c19Id) > 0) {

            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM covid19_patient_comorbidities WHERE `covid19_id` = $c19Id");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        }


        return $response;
    }

    public function getCovid19ReasonsForTestingByFormId($c19Id, $allData = false)
    {
        if (empty($c19Id)) {
            return null;
        }

        $response = array();

        // Using this in sync requests/results
        if (isset($c19Id) && is_array($c19Id) && count($c19Id) > 0) {
            $results = $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` IN (" . implode(",", $c19Id) . ")");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['covid19_id']][$row['reasons_id']] = $row['reasons_detected'];
            }
        } else {
            $results = $this->db->rawQuery("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = $c19Id");
            if ($allData) return $results;
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
        return $this->db->rawQueryOne("SELECT * FROM covid19_reasons_for_testing WHERE `covid19_id` = ?", array($c19Id));
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
        $general = new \Vlsm\Models\General();
        $patientsModel = new \Vlsm\Models\Patients();

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        try {
            $provinceCode = (isset($params['provinceCode']) && !empty($params['provinceCode'])) ? $params['provinceCode'] : null;
            $provinceId = (isset($params['provinceId']) && !empty($params['provinceId'])) ? $params['provinceId'] : null;
            $sampleCollectionDate = (isset($params['sampleCollectionDate']) && !empty($params['sampleCollectionDate'])) ? $params['sampleCollectionDate'] : null;

            if (empty($sampleCollectionDate)) {
                echo 0;
                exit();
            }

            // PNG FORM CANNOT HAVE PROVINCE EMPTY
            if ($globalConfig['vl_form'] == 5) {
                if (empty($provinceId)) {
                    echo 0;
                    exit();
                }
            }


            $sampleJson = $this->generateCovid19SampleCode($provinceCode, $sampleCollectionDate, null, $provinceId);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);

            $params['sampleCollectionDate'] = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];
            if (!isset($params['countryId']) || $params['countryId'] == '') {
                $params['countryId'] = '';
            }

            $covid19Data = array(
                'vlsm_country_id' => $params['countryId'],
                'sample_collection_date' => $params['sampleCollectionDate'],
                'vlsm_instance_id' => $_SESSION['instanceId'],
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'],
                'request_created_datetime' => $general->getDateTime(),
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => $general->getDateTime()
            );

            if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
                $covid19Data['remote_sample_code'] = $sampleData['sampleCode'];
                $covid19Data['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $covid19Data['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $covid19Data['remote_sample'] = 'yes';
                $covid19Data['result_status'] = 9;
                if ($_SESSION['accessType'] == 'testing-lab') {
                    $covid19Data['sample_code'] = $sampleData['sampleCode'];
                    $covid19Data['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $covid19Data['sample_code_key'] = $sampleData['sampleCodeKey'];
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
                if (empty($patientCodePrefix)) $patientCodePrefix = 'P';
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
            $sQuery = "SELECT covid19_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_covid19 ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= "where (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= "limit 1";
            $rowData = $this->db->rawQueryOne($sQuery);
            $id = 0;
            if ($rowData) {
                $this->db = $this->db->where('covid19_id', $rowData['covid19_id']);
                $id = $this->db->update("form_covid19", $covid19Data);
            } else {
                if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                    $covid19Data['unique_id'] = $general->generateRandomString(32);
                    $id = $this->db->insert("form_covid19", $covid19Data);
                }
            }

            if ($id > 0) {
                return  $id;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            error_log('Insert Covid-19 Sample : ' . $this->db->getLastError());
            error_log('Insert Covid-19 Sample : ' . $e->getMessage());
        }
    }
}
