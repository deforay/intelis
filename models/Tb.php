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

    public function generateTbSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null)
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
        $sampleCodeFormat = isset($globalConfig['tb_sample_code']) ? $globalConfig['tb_sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['tb_sample_code_prefix']) ? $globalConfig['tb_sample_code_prefix'] : '';

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
            return $this->generateTbSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $checkResult[$sampleCodeKeyCol]);
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
            $results = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` IN (" . implode(",", $tbId) . ") ORDER BY test_id ASC");

            foreach ($results as $row) {
                $response[$row['tb_id']][$row['test_id']] = $row;
            }
        } else if (isset($tbId) && $tbId != "" && !is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests WHERE `tb_id` = $tbId ORDER BY test_id ASC");
        } else if (!is_array($tbId)) {
            $response = $this->db->rawQuery("SELECT * FROM tb_tests ORDER BY test_id ASC");
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
}
