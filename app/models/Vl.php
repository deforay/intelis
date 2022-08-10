<?php

namespace Vlsm\Models;


/**
 * General functions
 *
 * @author Amit
 */

class Vl
{

    protected $db = null;
    protected $table = 'form_vl';
    protected $shortCode = 'VL';

    // keep all these in lower case to make it easier to compare
    protected $suppressedArray = array(
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
        '< titer min',
        'negative',
        'negat'
    );

    protected $suppressionLimit = 1000;

    public function __construct($db = null)
    {
        $this->db = !empty($db) ? $db : \MysqliDb::getInstance();
    }

    public function generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        $general = new \Vlsm\Models\General($this->db);
        $globalConfig = $general->getGlobalConfig();
        $vlsmSystemConfig = $general->getSystemConfig();
        // $humanFormatSampleCollectionDate = $sampleCollectionDate;

        $remotePrefix = '';
        $sampleCodeKeyCol = 'sample_code_key';
        $sampleCodeCol = 'sample_code';
        if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
            $remotePrefix = 'R';
            $sampleCodeKeyCol = 'remote_sample_code_key';
            $sampleCodeCol = 'remote_sample_code';
        }

        if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
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
        $sampleCodeFormat = isset($globalConfig['sample_code']) ? $globalConfig['sample_code'] : 'MMYY';
        $prefixFromConfig = isset($globalConfig['sample_code_prefix']) ? $globalConfig['sample_code_prefix'] : '';

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
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId'] + 1);
        }
        /* if ($checkResult !== null) {
            return $this->generateVLSampleID($provinceCode, $humanFormatSampleCollectionDate, $sampleFrom, $provinceId, $checkResult[$sampleCodeKeyCol], $user);
        } */
        return json_encode($sCodeKey);
    }

    public function getVlSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_vl_sample_type where status='active'$where";
        return $this->db->rawQuery($query);
    }

    public function getVlSampleTypes()
    {
        $results = $this->db->rawQuery("SELECT * FROM r_vl_sample_type where status='active'");
        $response = array();
        foreach ($results as $row) {
            $response[$row['sample_id']] = $row['sample_name'];
        }
        return $response;
    }

    public function getVLResultCategory($resultStatus, $finalResult)
    {

        $vlResultCategory = null;

        if (!isset($finalResult) || empty($finalResult)) {
            $vlResultCategory = null;
        } else if (in_array($resultStatus, array(1, 2, 3, 10))) {
            $vlResultCategory = null;
        } else if ($resultStatus == 4) {
            $vlResultCategory = 'rejected';
        } else if ($resultStatus == 5) {
            $vlResultCategory = 'invalid';
        } else {

            if (is_numeric($finalResult) && (float)$finalResult >= 0) {
                $finalResult = (float)$finalResult;
                if ($finalResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } else if ($finalResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            } else {

                $textResult = NULL;

                if (in_array(strtolower($finalResult), $this->suppressedArray)) {
                    $textResult = 10;
                } else {
                    $textResult = (float)filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT);
                }

                if ($textResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } else if ($textResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            }
        }

        return $vlResultCategory;
    }

    public function interpretViralLoadTextResult($result, $unit = false, $lowVlResultText = null)
    {

        // If result is blank, then return null
        if (empty(trim($result))) return null;

        // If result is numeric, then return it as is
        if (is_numeric($result)) return $result;

        $defaultVLTextResult = "Target Not Detected";
        if (!empty($lowVlResultText)) {
            $defaultVLTextResult = $lowVlResultText;
        }

        $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;

        $result = strtolower($result);
        if ($result == 'bdl' || $result == '< 839') {
            $vlResult = $txtVal = 'Below Detection Limit';
        } else if ($result == 'target not detected' || $result == 'not detected' || $result == 'tnd') {
            $vlResult = $txtVal = $defaultVLTextResult;
        } else if ($result == '< 2.00E+1') {
            $absDecimalVal = 20;
            $txtVal = $vlResult = $absVal = "< 20";
        } else if ($result == '< titer min') {
            $absDecimalVal = 20;
            $txtVal = $vlResult = $absVal = "< 20";
        } else if ($result == '> titer max"') {
            $absDecimalVal = 10000000;
            $txtVal = $vlResult = $absVal = "> 1000000";
        } else if ($result == '< inf') {
            $absDecimalVal = 839;
            $vlResult = $absVal = 839;
            $logVal = 2.92;
            $txtVal = null;
        } else if (strpos($result, "<") !== false) {
            if (!empty($unit)) {
                if (strpos($unit, 'Log') !== false) {
                    $logVal = (float) trim(str_replace("<", "", $result));
                    $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                } else {
                    $absDecimalVal = (float) trim(str_replace("<", "", $result));
                    $logVal = round(log10($absDecimalVal), 2);
                }
            }

            $txtVal = $vlResult = $absVal = "< " . trim($absDecimalVal);
        } else if (strpos($result, ">") !== false) {
            if (!empty($unit)) {
                if (strpos($unit, 'Log') !== false) {
                    $logVal = (float) trim(str_replace(">", "", $result));
                    $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
                } else {
                    $absDecimalVal = (float) trim(str_replace(">", "", $result));
                    $logVal = round(log10($absDecimalVal), 2);
                }
            }
            $txtVal = $vlResult = $absVal = "> " . trim($absDecimalVal);
        } else {
            $vlResult = $txtVal = $result;
        }

        return array(
            'logVal' => $logVal,
            'result' => $vlResult,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal
        );
    }

    public function interpretViralLoadNumericResult($result, $unit = null)
    {


        // If result is blank, then return null
        if (empty(trim($result))) return null;

        // If result is NOT numeric, then return it as is
        if (!is_numeric($result)) return $result;

        $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;

        if (strpos($unit, '10') !== false) {
            $unitArray = explode(".", $unit);
            $exponentArray = explode("*", $unitArray[0]);
            $multiplier = pow($exponentArray[0], $exponentArray[1]);
            $vlResult = $result * $multiplier;
            $unit = $unitArray[1];
        } else if (strpos($unit, 'Log') !== false && is_numeric($result)) {
            $logVal = $result;
            $vlResult = $absVal = $absDecimalVal = round((float) round(pow(10, $logVal) * 100) / 100);
        } else if (strpos($result, 'E+') !== false || strpos($result, 'E-') !== false) {
            if (strpos($result, '< 2.00E+1') !== false) {
                $vlResult = "< 20";
            } else {
                $resultArray = explode("(", $result);
                $exponentArray = explode("E", $resultArray[0]);
                $vlResult = (float) $resultArray[0];
                $absDecimalVal = (float) trim($vlResult);
                $logVal = round(log10($absDecimalVal), 2);
            }
        } else {
            $absDecimalVal = (float) trim($result);
            $logVal = round(log10($absDecimalVal), 2);
            $txtVal = null;
            $vlResult = $absDecimalVal;
        }

        return array(
            'logVal' => $logVal,
            'result' => $vlResult,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal
        );
    }

    public function insertSampleCode($params)
    {
        try {

            $general = new \Vlsm\Models\General();

            $globalConfig = $general->getGlobalConfig();
            $vlsmSystemConfig = $general->getSystemConfig();

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

            $sampleJson = $this->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);
            $sameplCollectionDate = $general->dateFormat($sampleDate[0]) . " " . $sampleDate[1];

            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }
            $vlData = array();

            $vlData = array(
                'vlsm_country_id' => $params['countryId'],
                'sample_collection_date' => $sameplCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'],
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'],
                'request_created_datetime' => $this->db->now(),
                'last_modified_by' => $_SESSION['userId'],
                'last_modified_datetime' => $this->db->now()
            );

            if ($vlsmSystemConfig['sc_user_type'] == 'remoteuser') {
                $vlData['remote_sample_code'] = $sampleData['sampleCode'];
                $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'yes';
                $vlData['result_status'] = 9;

                if ($_SESSION['accessType'] == 'testing-lab') {
                    $vlData['sample_code'] = $sampleData['sampleCode'];
                    $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                    $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
                    $vlData['result_status'] = 6;
                }
            } else {
                $vlData['sample_code'] = $sampleData['sampleCode'];
                $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'no';
                $vlData['result_status'] = 6;
            }

            $sQuery = "SELECT vl_sample_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_vl ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            $id = 0;
            if ($rowData) {
                // $this->db = $this->db->where('vl_sample_id', $rowData['vl_sample_id']);
                // $id = $this->db->update("form_vl", $vlData);
                // $params['vlSampleId'] = $rowData['vl_sample_id'];

                // If this sample code exists, let us regenerate
                return $this->insertSampleCode($params);
            } else {
                if (isset($params['api']) && $params['api'] = "yes") {
                    $id = $this->db->insert("form_vl", $vlData);
                    $params['vlSampleId'] = $id;
                } else {
                    if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                        $vlData['unique_id'] = $general->generateRandomString(32);
                        $id = $this->db->insert("form_vl", $vlData);
                    }
                }
            }

            if ($id > 0) {
                return $id;
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            error_log('Insert VL Sample : ' . $this->db->getLastError());
            error_log('Insert VL Sample : ' . $e->getMessage());
        }
    }
}
