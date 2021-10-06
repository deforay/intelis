<?php

namespace Vlsm\Models;

/**
 * General functions
 *
 * @author Amit
 */

class Hepatitis
{

    protected $db = null;
    protected $table = 'form_hepatitis';
    protected $shortCode = 'H';

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function generateHepatitisSampleCode($prefix, $provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null)
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
        $sampleCodeFormat = isset($globalConfig['hepatitis_sample_code']) ? $globalConfig['hepatitis_sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['hepatitis_sample_code_prefix']) ? $globalConfig['hepatitis_sample_code_prefix'] : '';

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
        if (isset($prefix) && $prefix != "") {
            $prefixFromConfig = $prefix;
        }
        if ($sampleCodeFormat == 'auto') {
            //$pNameVal = explode("##", $provinceCode);
            $sCodeKey['sampleCode'] = ($remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } else if ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . date('y', strtotime($sampleCollectionDate)) . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $provinceCode . $autoFormatedString;
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
            return $this->generateHepatitisSampleCode($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $checkResult[$sampleCodeKeyCol]);
        }

        return json_encode($sCodeKey);
    }

    public function getComorbidityByHepatitisId($formId, $allData = false)
    {

        if (empty($formId)) {
            return null;
        }

        $response = array();
        // Using this in sync requests/results
        if (is_array($formId)) {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities WHERE `hepatitis_id` IN (" . implode(",", $formId) . ")");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['hepatitis_id']][$row['comorbidity_id']] = $row['comorbidity_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_patient_comorbidities WHERE `hepatitis_id` = $formId");
            if ($allData) return $results;
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

        $response = array();
        // Using this in sync requests/results
        if (is_array($formId)) {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors WHERE `hepatitis_id` IN (" . implode(",", $formId) . ")");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['hepatitis_id']][$row['riskfactors_id']] = $row['riskfactors_detected'];
            }
        } else {

            $results = $this->db->rawQuery("SELECT * FROM hepatitis_risk_factors WHERE `hepatitis_id` = $formId");
            if ($allData) return $results;
            foreach ($results as $row) {
                $response[$row['riskfactors_id']] = $row['riskfactors_detected'];
            }
        }
        return $response;
    }

    public function getHepatitisResults()
    {
        $results = $this->db->rawQuery("SELECT result_id,result FROM r_hepatitis_results where status='active' ORDER BY result_id DESC");
        $response = array();
        foreach ($results as $row) {
            $response[$row['result_id']] = $row['result'];
        }
        return $response;
    }

    public function getHepatitisReasonsForTesting()
    {
        $results = $this->db->rawQuery("SELECT test_reason_id,test_reason_name FROM r_hepatitis_test_reasons WHERE `test_reason_status` LIKE 'active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['test_reason_id']] = $row['test_reason_name'];
        }
        return $response;
    }
}
