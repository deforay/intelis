<?php

namespace Vlsm\Models;

/**
 * General functions
 *
 * @author Amit
 */

class Tb
{

    protected $db = null;
    protected $table = 'form_tb';
    protected $shortCode = 'TB';

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function generateTbSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        $general = new \Vlsm\Models\General($this->db);

        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();

        $dateObj = new \DateTime($sampleCollectionDate);

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
        // if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = isset($globalConfig['tb_sample_code']) ? $globalConfig['tb_sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['tb_sample_code_prefix']) ? $globalConfig['tb_sample_code_prefix'] : '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } else if ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if ($maxCodeKeyVal === null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $geoLocations = new \Vlsm\Models\GeoLocations($this->db);
                    $provinceId = $geoLocations->getProvinceIDFromCode($provinceCode);
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
            return $this->generateTbSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $checkResult[$sampleCodeKeyCol], $user);
        }

        return json_encode($sCodeKey);
    }


    public function getTbSampleTypes()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_tb_sample_type where status='active'");
        $response = array();
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
            'tb_id'            => $tbSampleId,
            'test_name'                => $testKitName,
            'facility_id'           => $labId,
            'sample_tested_datetime' => $sampleTestedDatetime,
            'result'                => $result
        );
        return $this->db->insert("tb_tests", $tbTestData);
    }

    public function checkAllTbTestsForPositive($tbSampleId)
    {
        $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbSampleId ORDER BY test_id ASC");

        foreach ($response as $row) {
            if ($row['result'] == 'positive') return true;
        }

        return false;
    }


    public function getTbResults($type = null)
    {
        if ($type != null) {
            $results = $this->db->rawQuery("SELECT result_id,result FROM r_tb_results where status='active' AND result_type = '" . $type . "' ORDER BY result_id DESC");
        } else {
            $results = $this->db->rawQuery("SELECT result_id,result FROM r_tb_results where status='active' ORDER BY result_id DESC");
        }
        $response = array();
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getTbReasonsForTesting()
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_tb_test_reasons WHERE `test_reason_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }

    public function getTbReasonsForTestingDRC()
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_tb_test_reasons WHERE `test_reason_status` LIKE 'active' AND (parent_reason IS NULL OR parent_reason = 0)");
        $response = array();
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }
    public function getTbSymptoms()
    {
        $results = $this->db->rawQuery("SELECT symptom_id,symptom_name FROM r_tb_symptoms WHERE `symptom_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['symptom_id']] = $row['symptom_name'];
        }
        return $response;
    }

    public function getTbSymptomsDRC()
    {
        $results = $this->db->rawQuery("SELECT symptom_id,symptom_name FROM r_tb_symptoms WHERE `symptom_status` LIKE 'active' AND (parent_symptom IS NULL OR parent_symptom = 0)");
        $response = array();
        foreach ($results as $row) {
            $response[$row['symptom_id']] = $row['symptom_name'];
        }
        return $response;
    }

    public function getTbComorbidities()
    {
        $results = $this->db->rawQuery("SELECT comorbidity_id,comorbidity_name FROM r_tb_comorbidities WHERE `comorbidity_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['comorbidity_id']] = $row['comorbidity_name'];
        }
        return $response;
    }


    public function getTbTestsByFormId($tbId = "")
    {
        $response = array();

        // Using this in sync requests/results
        if (isset($tbId) && is_array($tbId) && count($tbId) > 0) {
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

    public function insertSampleCode($params)
    {
        $general = new \Vlsm\Models\General();

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


            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?: null;
            $sampleJson = $this->generateTbSampleCode($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);

            $sampleCollectionDate = $general->isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];
            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }

            $tbData = array(
                'vlsm_country_id' => $params['countryId'],
                'sample_collection_date' => $sampleCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'],
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'],
                'request_created_datetime' => $this->db->now(),
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => $this->db->now()
            );

            $oldSampleCodeKey = null;

            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $tbData['remote_sample_code'] = $sampleData['sampleCode'];
                $tbData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $tbData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $tbData['remote_sample'] = 'yes';
                $tbData['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
                    $tbData['sample_code'] = $sampleData['sampleCode'];
                    $tbData['result_status'] = 6;
                }
            } else {
                $tbData['sample_code'] = $sampleData['sampleCode'];
                $tbData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $tbData['sample_code_key'] = $sampleData['sampleCodeKey'];
                $tbData['remote_sample'] = 'no';
                $tbData['result_status'] = 6;
            }
            // echo "<pre>";
            // print_r($tbData);die;

            $sQuery = "SELECT tb_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_tb ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            /* Update version in form attributes */
            $version = $general->getSystemConfig('sc_version');
            if (isset($version) && !empty($version)) {
                $ipaddress = '';
                if (isset($_SERVER['HTTP_CLIENT_IP'])) {
                    $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
                } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
                    $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
                } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
                    $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
                } else if (isset($_SERVER['HTTP_FORWARDED'])) {
                    $ipaddress = $_SERVER['HTTP_FORWARDED'];
                } else if (isset($_SERVER['REMOTE_ADDR'])) {
                    $ipaddress = $_SERVER['REMOTE_ADDR'];
                } else {
                    $ipaddress = 'UNKNOWN';
                }
                $formAttributes = array(
                    'applicationVersion'  => $version,
                    'ip_address'    => $ipaddress
                );
                $tbData['form_attributes'] = json_encode($formAttributes);
            }

            $id = 0;
            if ($rowData) {
                // $this->db = $this->db->where('tb_id', $rowData['tb_id']);
                // $id = $this->db->update("form_tb", $tbData);

                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            } else {
                if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                    $tbData['unique_id'] = $general->generateUUID();
                    $id = $this->db->insert("form_tb", $tbData);
                }
            }

            if ($id > 0) {
                return $id;
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            error_log('Insert TB Sample : ' . $this->db->getLastError());
            error_log('Insert TB Sample : ' . $e->getMessage());
        }
    }
}
