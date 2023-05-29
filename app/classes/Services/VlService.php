<?php

namespace App\Services;


use MysqliDb;
use Exception;
use DateTimeImmutable;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

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
    protected CommonService $commonService;

    // keep all these in lower case to make it easier to compare
    protected array $suppressedArray = [
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
    ];

    protected int $suppressionLimit = 1000;

    public function __construct($db = null, $commonService = null)
    {
        $this->db = $db ?? ContainerRegistry::get('db');
        $this->commonService = $commonService;
    }

    public function generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom = null, $provinceId = '', $maxCodeKeyVal = null, $user = null)
    {

        if (!empty($maxCodeKeyVal)) {
            error_log(" ===== MAXX Code ====== " . $maxCodeKeyVal);
        }

        $globalConfig = $this->commonService->getGlobalConfig();
        $vlsmSystemConfig = $this->commonService->getSystemConfig();

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
        $sampleCodeFormat = $globalConfig['sample_code'] ?? 'MMYY';
        $prefixFromConfig = $globalConfig['sample_code_prefix'] ?? '';

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
            'maxId' => $maxId,
            'mnthYr' => $mnthYr,
            'auto' => $autoFormatedString
        ];

        if ($globalConfig['vl_form'] == 5) {
            // PNG format has an additional R in prefix
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
        $checkQuery = "SELECT $sampleCodeCol, $sampleCodeKeyCol FROM " . $this->table . " WHERE $sampleCodeCol='" . $sampleCodeGenerator['sampleCode'] . "'";
        $checkResult = $this->db->rawQueryOne($checkQuery);
        if (!empty($checkResult)) {
            error_log("DUP::: Sample Code ====== " . $sampleCodeGenerator['sampleCode']);
            error_log("DUP::: Sample Key Code ====== " . $maxId);
            error_log('DUP::: ' . $this->db->getLastQuery());
            return $this->generateVLSampleID($provinceCode, $sampleCollectionDate, $sampleFrom, $provinceId, $maxId, $user);
        }
        return json_encode($sampleCodeGenerator);
    }

    public function getVlSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_vl_sample_type where status='active' $where";
        return $this->db->rawQuery($query);
    }

    public function getVlSampleTypes($updatedDateTime = null): array
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

    public function getVLResultCategory($resultStatus, $finalResult): ?string
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

    public function interpretViralLoadResult($result, $unit = null, $defaultLowVlResultText = null): ?array
    {
        $finalResult = $vlResult = trim(htmlspecialchars_decode($result));
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

    public function interpretViralLoadTextResult($result, $unit = null, $defaultLowVlResultText = null): ?array
    {

        // If result is blank, then return null
        if (empty(trim($result))) {
            return null;
        }

        // If result is numeric, then process it as a number
        if (is_numeric($result)) {
            $this->interpretViralLoadNumericResult($result, $unit);
        }

        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results');


        if (!empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes') {
            $interpretAndConvertResult = true;
        } else {
            $interpretAndConvertResult = false;
        }

        $resultStatus = null;
        // Some machines and some countries prefer a default text result
        $vlTextResult = $defaultLowVlResultText ?? "Target Not Detected";

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

    public function interpretViralLoadNumericResult(string $result, ?string $unit = null): ?array
    {
        // If result is blank, then return null
        if (empty($result)) {
            return null;
        }

        $resultStatus = $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;
        $originalResultValue = $result;


        if (is_numeric($result)) {

            $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results');

            $interpretAndConvertResult = (!empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes') ? true : false;

            if (!empty($unit) && strpos($unit, 'Log') !== false && is_numeric($result)) {
                $logVal = (float) $result;
                $originalResultValue =
                    $vlResult = $absVal =
                    $absDecimalVal = round(round(pow(10, $logVal) * 100) / 100);
            } elseif (!empty($unit) && strpos($unit, '10') !== false) {
                $unitArray = explode(".", $unit);
                $exponentArray = explode("*", $unitArray[0]);
                $multiplier = pow((float)$exponentArray[0], (float)$exponentArray[1]);
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

        $this->db->where("low_vl_result_text", null, 'IS NOT');
        $this->db->where("status", 'active', 'like');
        return $this->db->getValue('instruments', 'low_vl_result_text', null);
    }

    public function insertSampleCode($params)
    {
        try {

            $globalConfig = $this->commonService->getGlobalConfig();
            $vlsmSystemConfig = $this->commonService->getSystemConfig();

            $provinceCode = $params['provinceCode'] ?? null;
            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            if (empty($sampleCollectionDate) || ($globalConfig['vl_form'] == 5 && empty($provinceId))) {
                return 0;
            }

            $oldSampleCodeKey = $params['oldSampleCodeKey'] ?? null;

            $sampleJson = $this->generateVLSampleID($provinceCode, $sampleCollectionDate, null, $provinceId, $oldSampleCodeKey);
            $sampleData = json_decode($sampleJson, true);
            $sampleCollectionDate = DateUtility::isoDateFormat($sampleCollectionDate, true);

            $vlData = [
                'vlsm_country_id' => $globalConfig['vl_form'],
                'sample_collection_date' => $sampleCollectionDate,
                'vlsm_instance_id' => $_SESSION['instanceId'] ?? $params['instanceId'] ?? null,
                'province_id' => $provinceId,
                'request_created_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'request_created_datetime' => DateUtility::getCurrentDateTime(),
                'last_modified_by' => $_SESSION['userId'] ?? $params['userId'] ?? null,
                'last_modified_datetime' => DateUtility::getCurrentDateTime()
            ];

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

            $sQuery = "SELECT vl_sample_id,
                        sample_code,
                        sample_code_format,
                        sample_code_key,
                        remote_sample_code,
                        remote_sample_code_format,
                        remote_sample_code_key
                        FROM form_vl ";
            if (isset($sampleData['sampleCode']) && !empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            $id = 0;
            if (empty($rowData)) {
                $formAttributes = [
                    'applicationVersion'  => $this->commonService->getSystemConfig('sc_version'),
                    'ip_address'    => $this->commonService->getClientIpAddress()
                ];
                $vlData['form_attributes'] = json_encode($formAttributes);
                if (!empty($params['sampleCode']) && !empty($params['sampleCollectionDate'])) {
                    $vlData['unique_id'] = $vlData['unique_id'] ?? $this->commonService->generateUUID();
                    $id = $this->db->insert("form_vl", $vlData);
                    error_log($this->db->getLastError());
                }
            } else {
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSampleCode($params);
            }
        } catch (Exception $e) {
            error_log('Insert VL Sample : ' . $this->db->getLastErrno());
            error_log('Insert VL Sample : ' . $this->db->getLastError());
            error_log('Insert VL Sample : ' . $this->db->getLastQuery());
            error_log('Insert VL Sample : ' . $e->getMessage());
            $id = 0;
        }
        return $id > 0 ? $id : 0;
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
}
