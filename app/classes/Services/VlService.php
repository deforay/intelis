<?php

namespace App\Services;


use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;

/**
 * General functions
 *
 * @author Amit
 */

class VlService
{

    protected ?MysqliDb $db = null;
    protected string $table = 'form_vl';
    protected string $shortCode = 'VL';

    // keep all these in lower case to make it easier to compare
    protected array $suppressedArray = array(
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

    protected int $suppressionLimit = 1000;

    public function __construct($db = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
    }

    public function generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        if (!empty($maxCodeKeyVal)) {
            error_log(" ===== MAXX Code ====== " . $maxCodeKeyVal);
        }


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

        // if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['sample_code_prefix'] ?? '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } elseif ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if ($maxCodeKeyVal === null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $geoLocations = new GeoLocationsService($this->db);
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

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }

        if ($sampleCodeFormat == 'auto') {
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }
        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " WHERE $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        // if ($checkResult !== null) {
        //     $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
        //     $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
        //     $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
        //     $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId'] + 1);
        // }
        if ($checkResult !== null) {
            error_log("DUP::: Sample Code ====== " . $sCodeKey['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
        return json_encode($sCodeKey);
    }

    public function generateVLSampleIDGenericTest($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        if (!empty($maxCodeKeyVal)) {
            error_log(" ===== MAXX Code ====== " . $maxCodeKeyVal);
        }


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

        // if (isset($user['access_type']) && !empty($user['access_type']) && $user['access_type'] != 'testing-lab') {
        //     $remotePrefix = 'R';
        //     $sampleCodeKeyCol = 'remote_sample_code_key';
        //     $sampleCodeCol = 'remote_sample_code';
        // }

        $mnthYr = $month . $year;
        // Checking if sample code format is empty then we set by default 'MMYY'
        $sampleCodeFormat = $globalConfig['sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['sample_code_prefix'] ?? '';

        if ($sampleCodeFormat == 'MMYY') {
            $mnthYr = $month . $year;
        } elseif ($sampleCodeFormat == 'YY') {
            $mnthYr = $year;
        }

        $autoFormatedString = $year . $month . $day;


        if ($maxCodeKeyVal === null) {
            // If it is PNG form
            if ($globalConfig['vl_form'] == 5) {

                if (empty($provinceId) && !empty($provinceCode)) {
                    $geoLocations = new GeoLocationsService($this->db);
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

        $sCodeKey = (array('maxId' => $maxId, 'mnthYr' => $mnthYr, 'auto' => $autoFormatedString));

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
            $remotePrefix = $remotePrefix . "R";
        }

        if ($sampleCodeFormat == 'auto') {
            $sCodeKey['sampleCode'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeInText'] = ($remotePrefix . $provinceCode . $autoFormatedString . $sCodeKey['maxId']);
            $sCodeKey['sampleCodeFormat'] = ($remotePrefix . $provinceCode . $autoFormatedString);
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        } elseif ($sampleCodeFormat == 'auto2') {
            $sCodeKey['sampleCode'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $year . $provinceCode . $this->shortCode . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $provinceCode . $autoFormatedString;
            $sCodeKey['sampleCodeKey'] = $sCodeKey['maxId'];
        } elseif ($sampleCodeFormat == 'YY' || $sampleCodeFormat == 'MMYY') {
            $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . $sCodeKey['maxId'];
            $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
            $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId']);
        }
        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM form_generic WHERE $sampleCodeCol='" . $sCodeKey['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        // if ($checkResult !== null) {
        //     $sCodeKey['sampleCode'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
        //     $sCodeKey['sampleCodeInText'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'] . ($sCodeKey['maxId'] + 1);
        //     $sCodeKey['sampleCodeFormat'] = $remotePrefix . $prefixFromConfig . $sCodeKey['mnthYr'];
        //     $sCodeKey['sampleCodeKey'] = ($sCodeKey['maxId'] + 1);
        // }
        if ($checkResult !== null) {
            error_log("DUP::: Sample Code ====== " . $sCodeKey['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
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

    public function getVlSampleTypes($updatedDateTime = null)
    {
        $query = "SELECT * FROM r_vl_sample_type where status='active'";
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

    public function getVLResultCategory($resultStatus, $finalResult)
    {

        $vlResultCategory = null;
        $orignalResultValue = $finalResult;
        $finalResult = strtolower(trim($finalResult));
        $finalResult = str_replace(['c/ml', 'cp/ml', 'copies/ml', 'cop/ml', 'copies'], '', $finalResult);
        $finalResult = str_replace('-', '', $finalResult);
        $finalResult = trim(str_replace(['hiv1 detected', 'hiv1 notdetected'], '', $finalResult));

        if (!isset($finalResult) || empty($finalResult)) {
            $vlResultCategory = null;
        } elseif (in_array($finalResult, ['fail', 'failed', 'failure', 'error', 'err'])) {
            $vlResultCategory = 'failed';
        } elseif (in_array($resultStatus, array(1, 2, 3, 10))) {
            $vlResultCategory = null;
        } elseif ($resultStatus == 4) {
            $vlResultCategory = 'rejected';
        } elseif ($resultStatus == 5) {
            $vlResultCategory = 'invalid';
        } else {

            if (is_numeric($finalResult)) {
                $finalResult = (float)$finalResult;
                if ($finalResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } elseif ($finalResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            } else {
                if (in_array(strtolower($orignalResultValue), $this->suppressedArray)) {
                    $textResult = 10;
                } else {
                    $textResult = (float)filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT);
                }

                if ($textResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } elseif ($textResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            }
        }

        return $vlResultCategory;
    }

    public function interpretViralLoadResult($result, $unit = null, $defaultLowVlResultText = null)
    {
        $finalResult = $vlResult = trim($result);
        $vlResult = strtolower($vlResult);
        $vlResult = str_replace(['c/ml', 'cp/ml', 'copies/ml', 'cop/ml', 'copies'], '', $vlResult);
        $vlResult = str_replace('-', '', $vlResult);
        $vlResult = trim(str_replace(['hiv1 detected', 'hiv1 notdetected'], '', $vlResult));

        if ($vlResult == "-1.00") {
            $vlResult = "Not Detected";
        }
        if (is_numeric($vlResult)) {
            //passing only number 
            return $this->interpretViralLoadNumericResult($vlResult, $unit);
        } else {
            //Passing orginal result value for text results
            return $this->interpretViralLoadTextResult($finalResult, $unit, $defaultLowVlResultText);
        }
    }

    public function interpretViralLoadTextResult($result, $unit = null, $defaultLowVlResultText = null)
    {

        // If result is blank, then return null
        if (empty(trim($result))) return null;

        // If result is numeric, then return it as is
        if (is_numeric($result)) {
            $this->interpretViralLoadNumericResult($result, $unit);
        }

        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $interpretAndConvertResult = $general->getGlobalConfig('vl_interpret_and_convert_results');


        if (!empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes') {
            $interpretAndConvertResult = true;
        } else {
            $interpretAndConvertResult = false;
        }

        $resultStatus = null;
        // Some machines and some countries prefer a default text result
        $vlTextResult = $defaultLowVlResultText ?: "Target Not Detected";

        $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;

        $originalResultValue = $result;

        $result = strtolower($result);
        if ($result == 'bdl' || $result == '< 839') {
            $vlResult = $txtVal = 'Below Detection Limit';
        } elseif ($result == 'target not detected' || $result == 'not detected' || $result == 'tnd') {
            $vlResult = $txtVal = $vlTextResult;
        } elseif ($result == '< 2.00E+1' || $result == '< titer min') {
            $absDecimalVal = 20;
            $txtVal = $vlResult = $absVal = "< 20";
        } elseif ($result == '> titer max"') {
            $absDecimalVal = 10000000;
            $txtVal = $vlResult = $absVal = "> 1000000";
        } elseif ($result == '< inf') {
            $absDecimalVal = 839;
            $vlResult = $absVal = 839;
            $logVal = 2.92;
            $txtVal = null;
        } elseif (strpos($result, "<") !== false) {
            $result = (float) trim(str_replace("<", "", $result));
            if (!empty($unit) && strpos($unit, 'Log') !== false) {
                $logVal = $result;
                $absVal = $absDecimalVal = round(round(pow(10, $logVal) * 100) / 100);
                $vlResult = $originalResultValue = "< " . $absDecimalVal;
            } else {
                $vlResult = $absVal = $absDecimalVal = $result;
                $logVal = round(log10($absDecimalVal), 2);
            }
            $txtVal = null;
        } elseif (strpos($result, ">") !== false) {
            $result = (float) trim(str_replace(">", "", $result));
            if (!empty($unit) && strpos($unit, 'Log') !== false) {
                $logVal = $result;
                $absDecimalVal = round(round(pow(10, $logVal) * 100) / 100);
                $vlResult = $originalResultValue = ">" . $absDecimalVal;
            } else {
                $vlResult = $absVal = $absDecimalVal = $result;
                $logVal = round(log10($absDecimalVal), 2);
            }

            $txtVal = null;
        } else {
            $vlResult = $txtVal = $result;
        }

        if ($interpretAndConvertResult) {
            $originalResultValue = $vlResult;
        }

        return array(
            'logVal' => $logVal,
            'result' => $originalResultValue,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'resultStatus' => $resultStatus,
        );
    }

    public function interpretViralLoadNumericResult($result, $unit = null)
    {
        // If result is blank, then return null
        if (empty(trim($result))) {
            return null;
        }

        // If result is NOT numeric, then return it as is
        if (!is_numeric($result)) {
            return $result;
        }

        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $interpretAndConvertResult = $general->getGlobalConfig('vl_interpret_and_convert_results');


        if (!empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes') {
            $interpretAndConvertResult = true;
        } else {
            $interpretAndConvertResult = false;
        }


        $resultStatus = $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;
        $originalResultValue = $result;
        if (strpos($unit, 'Log') !== false && is_numeric($result)) {
            $logVal = $result;
            $originalResultValue = $vlResult = $absVal = $absDecimalVal = round(round(pow(10, $logVal) * 100) / 100);
        } elseif (strpos($unit, '10') !== false) {
            $unitArray = explode(".", $unit);
            $exponentArray = explode("*", $unitArray[0]);
            $multiplier = pow($exponentArray[0], $exponentArray[1]);
            $vlResult = $result * $multiplier;
            $unit = $unitArray[1];
        } elseif (strpos($result, 'E+') !== false || strpos($result, 'E-') !== false) {
            if (strpos($result, '< 2.00E+1') !== false) {
                $vlResult = "< 20";
            } else {
                // incase there are some brackets in the result
                $resultArray = explode("(", $result);

                $absVal = ($resultArray[0]);
                $vlResult = $absDecimalVal = (float) $resultArray[0];
                $logVal = round(log10($absDecimalVal), 2);
            }
        } else {
            $absVal = ($result);
            $vlResult = $absDecimalVal = (float) trim($result);
            $logVal = round(log10($absDecimalVal), 2);
            $txtVal = null;
        }

        if ($interpretAndConvertResult) {
            $originalResultValue = $vlResult;
        }

        return array(
            'logVal' => $logVal,
            'result' => $originalResultValue,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'resultStatus' => $resultStatus
        );
    }


    public function getLowVLResultTextFromImportConfigs($machineFile = null)
    {
        if ($this->db == null) {
            return false;
        }

        if (!empty($machineFile)) {
            $this->db->where('import_machine_file_name', $machineFile);
        }

        $this->db->where("low_vl_result_text", NULL, 'IS NOT');
        $this->db->where("status", 'active', 'like');
        return $this->db->getValue('instruments', 'low_vl_result_text', null);
    }

    public function insertSampleCode($params)
    {
        try {

            /** @var CommonService $general */
            $general = ContainerRegistry::get(CommonService::class);

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
            if ($globalConfig['vl_form'] == 5 && empty($provinceId)) {
                echo 0;
                exit();
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?: null;

            $sampleJson = $this->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);
            $sameplCollectionDate = DateUtility::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];

            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }


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

            $oldSampleCodeKey = null;

            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $vlData['remote_sample_code'] = $sampleData['sampleCode'];
                $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'yes';
                $vlData['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
                    $vlData['sample_code'] = $sampleData['sampleCode'];
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
            /* Update version in form attributes */
            $version = $general->getSystemConfig('sc_version');
            $ipaddress = $general->getClientIpAddress();
            $formAttributes = array(
                'applicationVersion'  => $version,
                'ip_address'    => $ipaddress
            );
            $vlData['form_attributes'] = json_encode($formAttributes);


            $id = 0;
            if ($rowData) {
                // $this->db = $this->db->where('vl_sample_id', $rowData['vl_sample_id']);
                // $id = $this->db->update("form_vl", $vlData);
                // $params['vlSampleId'] = $rowData['vl_sample_id'];


                //error_log('Insert VL Sample : ' . $this->db->getLastQuery());
                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            } else {
                if (isset($params['api']) && $params['api'] = "yes") {
                    $id = $this->db->insert("form_vl", $vlData);
                    $params['vlSampleId'] = $id;
                } else {
                    if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                        $vlData['unique_id'] = $general->generateUUID();
                        $id = $this->db->insert("form_vl", $vlData);
                        error_log($this->db->getLastError());
                    }
                }
            }

            if ($id > 0) {
                return $id;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            error_log('Insert VL Sample : ' . $this->db->getLastErrno());
            error_log('Insert VL Sample : ' . $this->db->getLastError());
            error_log('Insert VL Sample : ' . $this->db->getLastQuery());
            error_log('Insert VL Sample : ' . $e->getMessage());
        }
    }

    public function getReasonForFailure($option = true, $updatedDateTime = null)
    {
        $result = [];
        $this->db->where('status', 'active');
        if ($updatedDateTime) {
            $this->db->where('updated_datetime >= "' . $updatedDateTime . '"');
        }
        $results = $this->db->get('r_vl_test_failure_reasons');
        if ($option) {
            foreach ($results as $row) {
                $result[$row['failure_id']] = $row['failure_reason'];
            }
            return $result;
        } else {
            return $results;
        }
    }

    public function getVlResults($instrumentId = null)
    {

        if (!empty($instrumentId)) {
            $this->db->where("(JSON_SEARCH(available_for_instruments, 'all','$instrumentId') IS NOT NULL)");
        }
        $this->db->where('status', 'active');
        return $this->db->get('r_vl_results');
    }

    public function insertSampleCodeGenericTest($params)
    {
        try {

            /** @var CommonService $general */
            $general = ContainerRegistry::get(CommonService::class);

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
            if ($globalConfig['vl_form'] == 5 && empty($provinceId)) {
                echo 0;
                exit();
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?: null;

            $sampleJson = $this->generateVLSampleIDGenericTest($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);
            $sampleDate = explode(" ", $params['sampleCollectionDate']);
            $sameplCollectionDate = DateUtility::isoDateFormat($sampleDate[0]) . " " . $sampleDate[1];

            if (!isset($params['countryId']) || empty($params['countryId'])) {
                $params['countryId'] = null;
            }

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

            $oldSampleCodeKey = null;

            if ($vlsmSystemConfig['sc_user_type'] === 'remoteuser') {
                $vlData['remote_sample_code'] = $sampleData['sampleCode'];
                $vlData['remote_sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['remote_sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'yes';
                $vlData['result_status'] = 9;
                if ($_SESSION['accessType'] === 'testing-lab') {
                    $vlData['sample_code'] = $sampleData['sampleCode'];
                    $vlData['result_status'] = 6;
                }
            } else {
                $vlData['sample_code'] = $sampleData['sampleCode'];
                $vlData['sample_code_format'] = $sampleData['sampleCodeFormat'];
                $vlData['sample_code_key'] = $sampleData['sampleCodeKey'];
                $vlData['remote_sample'] = 'no';
                $vlData['result_status'] = 6;
            }

            $sQuery = "SELECT vl_sample_id, sample_code, sample_code_format, sample_code_key, remote_sample_code, remote_sample_code_format, remote_sample_code_key FROM form_generic ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);
            /* Update version in form attributes */
            $version = $general->getSystemConfig('sc_version');
            $ipaddress = $general->getClientIpAddress();
            $formAttributes = [
                'applicationVersion'  => $version,
                'ip_address'    => $ipaddress
            ];
            $vlData['form_attributes'] = json_encode($formAttributes);


            $id = 0;
            if ($rowData) {
                // $this->db = $this->db->where('vl_sample_id', $rowData['vl_sample_id']);
                // $id = $this->db->update("form_vl", $vlData);
                // $params['vlSampleId'] = $rowData['vl_sample_id'];


                //error_log('Insert VL Sample : ' . $this->db->getLastQuery());
                // If this sample code exists, let us regenerate
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCodeGenericTest($params);
            } else {
                if (isset($params['api']) && $params['api'] = "yes") {
                    $id = $this->db->insert("form_generic", $vlData);
                    $params['vlSampleId'] = $id;
                } else {
                    if (isset($params['sampleCode']) && $params['sampleCode'] != '' && $params['sampleCollectionDate'] != null && $params['sampleCollectionDate'] != '') {
                        $vlData['unique_id'] = $general->generateUUID();
                        $id = $this->db->insert("form_generic", $vlData);
                        error_log($this->db->getLastError());
                    }
                }
            }

            if ($id > 0) {
                return $id;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            error_log('Insert VL Sample : ' . $this->db->getLastErrno());
            error_log('Insert VL Sample : ' . $this->db->getLastError());
            error_log('Insert VL Sample : ' . $this->db->getLastQuery());
            error_log('Insert VL Sample : ' . $e->getMessage());
        }
    }
}
