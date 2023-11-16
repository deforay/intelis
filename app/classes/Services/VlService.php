<?php

namespace App\Services;

use Exception;
use SAMPLE_STATUS;
use App\Utilities\DateUtility;
use App\Abstracts\AbstractTestService;
use App\Utilities\MiscUtility;


class VlService extends AbstractTestService
{
    // keep in lowercase to make them easier to compare
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
        '-1.00',
        '< titer min',
        'negative',
        'negat'
    ];
    protected int $suppressionLimit = 1000;
    protected string $table = 'form_vl';
    protected string $shortCode = 'VL';

    public function getSampleCode($params)
    {
        if (empty($params['sampleCollectionDate'])) {
            return json_encode([]);
        } else {
            $globalConfig = $this->commonService->getGlobalConfig();
            $params['sampleCodeFormat'] = $globalConfig['sample_code'] ?? 'MMYY';
            $params['prefix'] = $params['prefix'] ?? $globalConfig['sample_code_prefix'] ?? $this->shortCode;
            return $this->generateSampleCode($this->table, $params);
        }
    }

    public function getVlSampleTypesByName($name = "")
    {
        $where = "";
        if (!empty($name)) {
            $where = " AND sample_name LIKE '$name%'";
        }
        $query = "SELECT * FROM r_vl_sample_type WHERE `status` like 'active' $where";
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
        $find = [
            'c/ml',
            'cp/ml',
            'copies/ml',
            'cop/ml',
            'copies',
            'cpml',
            'cp',
            'HIV-1 DETECTED',
            'HIV1 DETECTED',
            'HIV-1 NOT DETECTED',
            'HIV-1 NOTDETECTED',
            'HIV1 NOTDETECTED',

        ];
        $finalResult = trim(str_ireplace($find, '', $finalResult));

        if (empty($finalResult)) {
            $vlResultCategory = null;
        } elseif (in_array($finalResult, ['fail', 'failed', 'failure', 'error', 'err'])) {
            $vlResultCategory = 'failed';
        } elseif (in_array($resultStatus, [1, 2, 3, 10])) {
            $vlResultCategory = null;
        } elseif ($resultStatus == 4) {
            $vlResultCategory = 'rejected';
        } elseif ($resultStatus == 5) {
            $vlResultCategory = 'invalid';
        } else {

            if (is_numeric($finalResult) || MiscUtility::isScientificNotation($finalResult)) {
                $finalResult = floatval($finalResult);
                if ($finalResult < $this->suppressionLimit) {
                    $vlResultCategory = 'suppressed';
                } elseif ($finalResult >= $this->suppressionLimit) {
                    $vlResultCategory = 'not suppressed';
                }
            } else {
                if (in_array(strtolower($orignalResultValue), $this->suppressedArray)) {
                    $textResult = 10;
                } else {
                    $textResult = (float) filter_var($finalResult, FILTER_SANITIZE_NUMBER_FLOAT);
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

    public function processViralLoadResultFromForm(array $params): array
    {
        $isRejected = 'no';
        $finalResult = $params['vlResult'] ?? null;
        $absDecimalVal = $absVal = $logVal = $txtVal = null;
        $hivDetection = $params['hivDetection'] ?? null;
        $resultStatus = null;

        // if ($params['isSampleRejected'] ?? null === 'yes') {
        if ($params['isSampleRejected'] == 'yes') {
            $isRejected = 'yes';
            $finalResult = $params['vlResult'] = $params['vlLog'] = null;
            $resultStatus = SAMPLE_STATUS\REJECTED;
        } elseif (!empty($params['vlResult'])) {
            $resultStatus = SAMPLE_STATUS\PENDING_APPROVAL; // Awaiting Approval
            //Result is saved as entered
            $finalResult = $params['vlResult'];

            if (in_array(strtolower($params['vlResult']), ['fail', 'failed', 'failure', 'error', 'err', 'invalid'])) {
                $hivDetection = null;
                $resultStatus = SAMPLE_STATUS\TEST_FAILED; // Invalid/Failed
                $finalResult = $params['vlResult'];
            } elseif (in_array(strtolower($params['vlResult']), ['noresult', 'no result', 'no'])) {
                $hivDetection = null;
                $resultStatus = SAMPLE_STATUS\NO_RESULT; // No Result
            } else {

                $interpretedResults = $this->interpretViralLoadResult($params['vlResult']);

                $logVal = $interpretedResults['logVal'] ?? null;
                $absDecimalVal = $interpretedResults['absDecimalVal'] ?? null;
                $absVal = $interpretedResults['absVal'] ?? null;
                $txtVal = $interpretedResults['txtVal'] ?? null;
            }
        } elseif (!empty($params['vlLog']) && is_numeric($params['vlLog'])) {
            $resultStatus = SAMPLE_STATUS\PENDING_APPROVAL; // Awaiting Approval
            $finalResult = pow(10, $params['vlLog']);
        }

        $hivDetection = $hivDetection ?? '';
        $finalResult = trim($hivDetection . ' ' . $finalResult);

        if (
            !empty($params['api']) &&
            $params['api'] == 'yes' &&
            $resultStatus == SAMPLE_STATUS\PENDING_APPROVAL &&
            !empty($finalResult) &&
            $this->commonService->getGlobalConfig('vl_auto_approve_api_results') == 'yes'
        ) {
            $resultStatus = SAMPLE_STATUS\ACCEPTED;
        }


        return [
            'isRejected' => $isRejected,
            'finalResult' => $finalResult,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'logVal' => $logVal,
            'txtVal' => $txtVal,
            'hivDetection' => $hivDetection,
            'resultStatus' => $resultStatus,
        ];
    }

    public function interpretViralLoadResult($result, $unit = null, $defaultLowVlResultText = null): ?array
    {
        return once(function () use ($result, $unit, $defaultLowVlResultText) {

            $vlResultType = $this->checkViralLoadValueType($result);

            if ($vlResultType == 'empty') {
                return null;
            }

            $finalResult = $vlResult = trim(htmlspecialchars_decode($result));
            $vlResult = str_ireplace(['c/ml', 'cp/ml', 'copies/ml', 'cop/ml', 'copies'], '', $vlResult);
            $vlResult = str_ireplace('-', '', $vlResult);
            $vlResult = trim(str_ireplace(['hiv1 detected', 'hiv1 notdetected'], '', $vlResult));

            if ($vlResult == "-1.00") {
                $finalResult = $vlResult = "Not Detected";
            }
            if ($vlResultType == 'numeric') {
                //passing only number
                return $this->interpretViralLoadNumericResult($vlResult, $unit);
            } else {
                //Passing orginal result value for text results
                return $this->interpretViralLoadTextResult($finalResult, $unit, $defaultLowVlResultText);
            }
        });
    }

    public function interpretViralLoadTextResult($result, $unit = null, $defaultLowVlResultText = null): ?array
    {

        // If result is blank, then return null
        if (empty(trim($result))) {
            return null;
        }

        // If result is numeric, then process it as a number
        if ($this->checkViralLoadValueType($result) == 'numeric') {
            return $this->interpretViralLoadNumericResult($result, $unit);
        }

        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results');
        $interpretAndConvertResult = !empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes';

        $resultStatus = null;

        // Some machines and some countries prefer a default text result
        $vlDefaultTextResult = !empty(trim($defaultLowVlResultText)) && trim($defaultLowVlResultText) != "" ? $defaultLowVlResultText : "Target Not Detected";

        $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;

        $originalResultValue = $result;

        if ($result == '-1.00') {
            $result = "Target Not Detected";
        }

        $strToLowerresult = strtolower($result);
        switch ($strToLowerresult) {
            case 'bdl':
            case '< 839':
                $vlResult = $txtVal = 'Below Detection Limit';
                break;
            case 'target not detected':
            case 'not detected':
            case 'tnd':
                $vlResult = $txtVal = $vlDefaultTextResult;
                break;
            case '< 2.00E+1':
            case '< titer min':
                $absDecimalVal = 20;
                $txtVal = $vlResult = $absVal = "< 20";
                break;
            case '> titer max"':
                $absDecimalVal = 10000000;
                $txtVal = $vlResult = $absVal = "> 1000000";
                break;
            case '< inf':
                $absDecimalVal = 839;
                $vlResult = $absVal = 839;
                $logVal = 2.92;
                $txtVal = null;
                break;
            default:
                if (strpos($result, "<") !== false) {
                    $result = $this->extractViralLoadValue(trim(str_replace("<", "", $result)));
                    if (!empty($unit) && strpos($unit, 'Log') !== false) {
                        $logVal = $result;
                        $absVal = $absDecimalVal = round(pow(10, $logVal), 2);
                    } else {
                        $vlResult = $absVal = $absDecimalVal = $result;
                        $logVal = round(log10($absDecimalVal), 2);
                    }

                    $vlResult = $originalResultValue = "< " . $absDecimalVal;
                    $txtVal = null;
                } elseif (strpos($result, ">") !== false) {
                    $result = $this->extractViralLoadValue(trim(str_replace(">", "", $result)));
                    if (!empty($unit) && strpos($unit, 'Log') !== false) {
                        $logVal = $result;
                        $absDecimalVal = round(pow(10, $logVal), 2);
                    } else {
                        $vlResult = $absVal = $absDecimalVal = $result;
                        $logVal = round(log10($absDecimalVal), 2);
                    }
                    $vlResult = $originalResultValue = ">" . $absDecimalVal;
                    $txtVal = null;
                } else {
                    $vlResult = $txtVal = $result;
                }
                break;
        }
        if ($interpretAndConvertResult) {
            $originalResultValue = $vlResult;
        }

        return [
            'logVal' => $logVal,
            'result' => $originalResultValue,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'resultStatus' => $resultStatus,
        ];
    }

    public function interpretViralLoadNumericResult(string $result, ?string $unit = null): ?array
    {
        $result = trim($result);
        // If result is blank, then return null
        if (empty($result)) {
            return null;
        }

        // If result is NOT numeric, then process it as a text result
        if ($this->checkViralLoadValueType($result) == 'text') {
            return $this->interpretViralLoadTextResult($result, $unit);
        }

        $resultStatus = $vlResult = $logVal = $txtVal = $absDecimalVal = $absVal = null;
        $originalResultValue = $result;


        $interpretAndConvertResult = $this->commonService->getGlobalConfig('vl_interpret_and_convert_results');

        $interpretAndConvertResult = !empty($interpretAndConvertResult) && $interpretAndConvertResult === 'yes';

        if (!empty($unit) && strpos($unit, 'Log') !== false && is_numeric($result)) {
            $logVal = (float) $result;
            $originalResultValue =
                $vlResult = $absVal =
                $absDecimalVal = round(pow(10, $logVal), 2);
        } elseif (!empty($unit) && strpos($unit, '10') !== false) {
            $unitArray = explode(".", $unit);
            $exponentArray = explode("*", $unitArray[0]);
            $multiplier = pow((float) $exponentArray[0], (float) $exponentArray[1]);
            $vlResult = $result * $multiplier;
            $unit = $unitArray[1];
        } elseif (strpos($result, 'E+') !== false || strpos($result, 'E-') !== false) {
            if (strpos($result, '< 2.00E+1') !== false) {
                $vlResult = "< 20";
                $absVal = $absDecimalVal = 20;
            } else {
                // incase there are some brackets in the result
                $resultArray = explode("(", $result);

                $absVal = ($resultArray[0]);
                $vlResult = $absDecimalVal = (float) $resultArray[0];
                $logVal = round(log10($absDecimalVal), 2);
            }
        } elseif ($result == '< 839') {
            $vlResult = $txtVal = 'Below Detection Limit';
        } else {
            $vlResult = $absVal = $absDecimalVal = floatval($result);
            $logVal = round(log10($absDecimalVal), 2);
            $txtVal = null;
        }

        if ($interpretAndConvertResult) {
            $originalResultValue = $vlResult;
        }


        return [
            'logVal' => $logVal,
            'result' => $originalResultValue,
            'absDecimalVal' => $absDecimalVal,
            'absVal' => $absVal,
            'txtVal' => $txtVal,
            'resultStatus' => $resultStatus
        ];
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

    public function insertSample($params, $returnSampleData = false)
    {
        try {

            $formId = $this->commonService->getGlobalConfig('vl_form');

            $provinceId = $params['provinceId'] ?? null;
            $sampleCollectionDate = $params['sampleCollectionDate'] ?? null;

            // PNG FORM (formId = 5) CANNOT HAVE PROVINCE EMPTY
            // Sample Collection Date Cannot be Empty
            if (empty($sampleCollectionDate) || ($formId == 5 && empty($provinceId))) {
                return 0;
            }

            $sampleCodeParams = [];
            $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
            $sampleCodeParams['provinceCode'] = $params['provinceCode'] ?? null;
            $sampleCodeParams['provinceId'] = $provinceId;
            $sampleCodeParams['maxCodeKeyVal'] = $params['oldSampleCodeKey'] ?? null;


            $sampleJson = $this->getSampleCode($sampleCodeParams);
            $sampleData = json_decode($sampleJson, true);

            $sQuery = "SELECT vl_sample_id FROM form_vl ";
            if (!empty($sampleData['sampleCode'])) {
                $sQuery .= " WHERE (sample_code like '" . $sampleData['sampleCode'] . "' OR remote_sample_code like '" . $sampleData['sampleCode'] . "')";
            }
            $sQuery .= " LIMIT 1";
            $rowData = $this->db->rawQueryOne($sQuery);

            $id = 0;
            if (empty($rowData) && !empty($sampleData['sampleCode'])) {

                $tesRequestData = [
                    'vlsm_country_id' => $formId,
                    'unique_id' => $params['uniqueId'] ?? $this->commonService->generateUUID(),
                    'facility_id' => $params['facilityId'] ?? null,
                    'lab_id' => $params['labId'] ?? null,
                    'app_sample_code' => $params['appSampleCode'] ?? null,
                    'sample_collection_date' => DateUtility::isoDateFormat($sampleCollectionDate, true),
                    'vlsm_instance_id' => $_SESSION['instanceId'] ?? $this->commonService->getInstanceId() ?? null,
                    'province_id' => $provinceId,
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
                $this->db->insert("form_vl", $tesRequestData);
                $id = $this->db->getInsertId();
                if ($this->db->getLastErrno() > 0) {
                    error_log($this->db->getLastError());
                }
            } else {
                // If this sample id exists, let us regenerate the sample id and insert
                $params['oldSampleCodeKey'] = $sampleData['sampleCodeKey'];
                return $this->insertSample($params);
            }
        } catch (Exception $e) {
            error_log('Insert VL Sample : ' . $this->db->getLastErrno());
            error_log('Insert VL Sample : ' . $this->db->getLastError());
            error_log('Insert VL Sample : ' . $this->db->getLastQuery());
            error_log('Insert VL Sample : ' . $e->getMessage());
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

    public function getVlReasonsForTesting(): array
    {
        return $this->db->rawQuery("SELECT test_reason_id,test_reason_name
                                            FROM r_vl_test_reasons
                                                WHERE `test_reason_status` LIKE 'active'
                                                AND (parent_reason IS NULL OR parent_reason = 0)");
    }

    public function checkViralLoadValueType($input)
    {
        // Check if it's null or empty
        if (is_null($input) || trim($input) == '') {
            return 'empty';
        }

        // Check if it is a numeric value, including scientific notation
        if (is_numeric($input) || MiscUtility::isScientificNotation($input)) {
            return 'numeric';
        }

        // If not null, not empty, and not numeric, it's text
        return 'text';
    }

    public function extractViralLoadValue($input): ?string
    {
        // Trim the input to remove leading/trailing whitespace
        $trimmedInput = trim($input);

        // Proceed with converting the number to float if it is numeric or scientific notation
        if (is_numeric($trimmedInput) || MiscUtility::isScientificNotation($trimmedInput)) {
            return floatval($trimmedInput);
        }

        return null;
    }
}
